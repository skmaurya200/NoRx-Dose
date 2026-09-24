/* Page script: faq - runs after common.js */
(function(){
  'use strict';
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* The questions, written into window.AURUM_FAQ by
     resources/views/faq.blade.php from the Pages module.

     They arrive flat - topic, question, answer - and are grouped here, in the
     order the topics first appear, so reordering the rows in the panel
     reorders the accordions on the page.

     The icons stay here: they belong to the position, not to the words, and
     the sprite only holds the handful this page declares. */
  var TOPIC_ICONS = ['i-leaf', 'i-bag', 'i-truck', 'i-lock'];

  var TOPIC_SUBS = {
    'Products and ingredients': 'Formulas, testing, and what is inside',
    'Ordering and payment': 'Placing, changing and paying for orders',
    'Shipping and delivery': 'Carriers, speed, and delivery issues',
    'Privacy, returns and refunds': 'Data protection, returns and money back'
  };

  var DATA = (function(){
    var rows = Array.isArray(window.AURUM_FAQ) ? window.AURUM_FAQ : [];
    var order = [];
    var byTopic = {};

    rows.forEach(function(row){
      var topic = (row.topic || '').trim();
      if(!topic) return;

      if(!byTopic[topic]){
        byTopic[topic] = {
          icon: TOPIC_ICONS[order.length % TOPIC_ICONS.length],
          title: topic,
          sub: TOPIC_SUBS[topic] || '',
          qs: []
        };
        order.push(topic);
      }

      byTopic[topic].qs.push({q: row.question || '', a: row.answer || ''});
    });

    return order.map(function(topic){ return byTopic[topic]; });
  })();

  var wrap = document.getElementById('accs');

  function esc(s){
    return String(s == null ? '' : s)
      .replace(/[&<>]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;'}[c]; });
  }

  function hl(text, term){
    text = String(text == null ? '' : text);
    if(!term) return esc(text);
    var re = new RegExp('(' + term.replace(/[.*+?^${}()|[\]\\]/g,'\\$&') + ')','ig');
    return esc(text).replace(re, '<mark>$1</mark>');
  }

  function build(term){
    var t = (term || '').trim().toLowerCase();

    // No questions at all: say so rather than leaving a blank band where the
    // accordions should be.
    if(!DATA.length){
      wrap.innerHTML = '<div class="noHits"><h3>No questions yet</h3>'+
                       '<p>Contact support below and we will answer directly.</p></div>';
      return;
    }

    var cats = DATA.map(function(c){
      var qs = t
        ? c.qs.filter(function(x){ return String(x.q + ' ' + x.a).toLowerCase().indexOf(t) > -1; })
        : c.qs;
      return {cat:c, qs:qs};
    }).filter(function(c){ return c.qs.length; });

    if(!cats.length){
      wrap.innerHTML = '<div class="noHits"><h3>Nothing matched “' + esc(term) + '”</h3>'+
                       '<p>Try a shorter word, or contact support below and we will answer directly.</p></div>';
      return;
    }

    wrap.innerHTML = cats.map(function(c, ci){
      var open = t ? true : ci === 0;
      return ''+
      '<section class="acc'+(open ? ' is-open' : '')+'">'+
        '<button class="acc__head" aria-expanded="'+open+'">'+
          '<span class="acc__ic"><svg><use href="#'+c.cat.icon+'"/></svg></span>'+
          '<span class="acc__txt"><h3>'+esc(c.cat.title)+'</h3>'+
            (c.cat.sub ? '<p>'+esc(c.cat.sub)+'</p>' : '')+'</span>'+
          '<span class="acc__n">'+c.qs.length+' question'+(c.qs.length === 1 ? '' : 's')+'</span>'+
          '<span class="acc__chev"><svg><use href="#i-chev"/></svg></span>'+
        '</button>'+
        '<div class="acc__body"><div class="acc__inner"><div class="acc__pad">'+
          c.qs.map(function(x, qi){
            return ''+
            '<div class="qa">'+
              '<button class="qa__btn" aria-expanded="false">'+
                '<span class="qa__n">'+String(qi+1).padStart(2,'0')+'</span>'+
                '<span class="qa__q">'+hl(x.q, t)+'</span>'+
                '<span class="qa__sign"></span>'+
              '</button>'+
              '<div class="qa__body"><div class="qa__inner"><p>'+hl(x.a, t)+'</p></div></div>'+
            '</div>';
          }).join('')+
        '</div></div></div>'+
      '</section>';
    }).join('');
  }
  build('');

  /* ---- accordion toggles (delegated, handles both levels) ---- */
  wrap.addEventListener('click', function(e){
    var head = e.target.closest('.acc__head');
    if(head){
      var acc = head.parentElement;
      var open = acc.classList.toggle('is-open');
      head.setAttribute('aria-expanded', open);
      return;
    }
    var qb = e.target.closest('.qa__btn');
    if(qb){
      var qa = qb.parentElement;
      var o = qa.classList.toggle('is-open');
      qb.setAttribute('aria-expanded', o);
    }
  });

  /* ---- search ---- */
  var input = document.getElementById('faqQ');
  var form = document.getElementById('searchForm');
  var timer;

  function run(){ build(input.value); }

  input.addEventListener('input', function(){
    clearTimeout(timer);
    timer = setTimeout(run, 180);
  });
  form.addEventListener('submit', function(e){
    e.preventDefault();
    run();
    document.querySelector('.accs').scrollIntoView({behavior: reduce ? 'auto' : 'smooth', block:'start'});
  });

  /* ---- quick question chips ---- */
  document.querySelectorAll('.quick button').forEach(function(b){
    b.addEventListener('click', function(){
      input.value = b.dataset.ask;
      run();
      document.querySelector('.accs').scrollIntoView({behavior: reduce ? 'auto' : 'smooth', block:'start'});
    });
  });

  /* ---- counters ---- */
  function runCount(el){
    var target = parseFloat(el.dataset.count), suffix = el.dataset.suffix || '';
    var dur = 1400, start = null;
    if(reduce){ el.textContent = target + suffix; return; }
    function step(ts){
      if(!start) start = ts;
      var p = Math.min((ts - start) / dur, 1);
      el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3))) + suffix;
      if(p < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }
  var counters = document.querySelectorAll('[data-count]');
  if('IntersectionObserver' in window){
    var cio = new IntersectionObserver(function(en){
      en.forEach(function(e){ if(e.isIntersecting){ runCount(e.target); cio.unobserve(e.target); } });
    }, {threshold:.5});
    counters.forEach(function(el){ cio.observe(el); });
  } else counters.forEach(runCount);
})();
