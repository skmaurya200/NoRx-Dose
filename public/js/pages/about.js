/* Page script: about - runs after common.js */
(function(){
  'use strict';
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* The chapters, written into window.AURUM_CHAPTERS by
     resources/views/about.blade.php from the Pages module.

     The icons stay here rather than being editable: they belong to the
     position in the stepper, not to the words, and the sprite only holds the
     handful the page declares. A sixth chapter reuses the first icon rather
     than drawing nothing. */
  var CHIP_ICONS = ['i-pin', 'i-leaf', 'i-flask', 'i-recycle', 'i-globe'];
  var SEAL_ICONS = ['i-leaf', 'i-users', 'i-flask', 'i-recycle', 'i-globe'];

  var CHAPTERS = (Array.isArray(window.AURUM_CHAPTERS) ? window.AURUM_CHAPTERS : [])
    .map(function (c, i) {
      return {
        label: c.label || '',
        eyebrow: c.eyebrow || '',
        title: c.title || '',
        body: c.body || '',
        chip: c.chip || '',
        chipIcon: CHIP_ICONS[i % CHIP_ICONS.length],
        sealText: c.seal || '',
        sealIcon: SEAL_ICONS[i % SEAL_ICONS.length]
      };
    });

  var bars = document.getElementById('bars');
  var card = document.getElementById('card');

  // Every chapter cleared in the panel: the stepper has nothing to step
  // through, so it is left alone rather than drawn empty.
  if (!CHAPTERS.length) return;

  var chapN = document.getElementById('chapN');
  var chapLabel = document.getElementById('chapLabel');
  var prev = document.getElementById('prev');
  var next = document.getElementById('next');
  var at = 0;

  bars.innerHTML = CHAPTERS.map(function(c, i){
    return '<button role="tab" data-i="'+i+'" aria-label="Chapter '+(i+1)+': '+esc(c.label)+'"></button>';
  }).join('');

  /* The chapters are copy typed into the panel, so they are escaped rather
     than dropped into innerHTML as markup. */
  function esc(v){
    return String(v == null ? '' : v)
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }

  function paint(){
    var c = CHAPTERS[at];

    chapN.textContent = String(at + 1).padStart(2,'0');
    chapLabel.textContent = c.label;

    bars.querySelectorAll('button').forEach(function(b, i){
      b.classList.toggle('is-on', i === at);
      b.setAttribute('aria-selected', i === at);
    });

    prev.disabled = at === 0;
    next.disabled = at === CHAPTERS.length - 1;

    card.innerHTML = ''+
      '<div class="card__l">'+
        '<p class="card__eyebrow">'+esc(c.eyebrow)+'</p>'+
        '<h2>'+esc(c.title)+'</h2>'+
        '<p>'+esc(c.body)+'</p>'+
        '<span class="chip"><svg><use href="#'+c.chipIcon+'"/></svg>'+esc(c.chip)+'</span>'+
      '</div>'+
      '<div class="card__r">'+
        '<div class="seal"><svg><use href="#'+c.sealIcon+'"/></svg><b>'+esc(c.sealText)+'</b></div>'+
      '</div>';

    if(!reduce){
      card.animate(
        [{opacity:0, transform:'translateY(14px)'},{opacity:1, transform:'none'}],
        {duration:420, easing:'cubic-bezier(.22,.61,.36,1)'}
      );
    }
  }
  paint();

  prev.addEventListener('click', function(){ if(at > 0){ at--; paint(); } });
  next.addEventListener('click', function(){ if(at < CHAPTERS.length - 1){ at++; paint(); } });
  bars.addEventListener('click', function(e){
    var b = e.target.closest('button');
    if(!b) return;
    at = parseInt(b.dataset.i, 10);
    paint();
  });

  /* keyboard: left/right anywhere on the stepper */
  bars.addEventListener('keydown', function(e){
    if(e.key === 'ArrowRight' && at < CHAPTERS.length - 1){ at++; paint(); }
    if(e.key === 'ArrowLeft'  && at > 0){ at--; paint(); }
  });

  /* ---- quote carousel ---- */
  var slides = Array.prototype.slice.call(document.querySelectorAll('.slide'));
  var dots = document.getElementById('dots');
  var qAt = 0, timer;

  dots.innerHTML = slides.map(function(_, i){
    return '<button data-i="'+i+'" aria-label="Quote '+(i+1)+'"></button>';
  }).join('');

  function showQ(i){
    qAt = (i + slides.length) % slides.length;
    slides.forEach(function(s, n){ s.classList.toggle('is-on', n === qAt); });
    dots.querySelectorAll('button').forEach(function(d, n){ d.classList.toggle('is-on', n === qAt); });
  }
  showQ(0);

  document.getElementById('qPrev').addEventListener('click', function(){ showQ(qAt - 1); restart(); });
  document.getElementById('qNext').addEventListener('click', function(){ showQ(qAt + 1); restart(); });
  dots.addEventListener('click', function(e){
    var d = e.target.closest('button');
    if(d){ showQ(parseInt(d.dataset.i,10)); restart(); }
  });

  function restart(){
    clearInterval(timer);
    if(!reduce) timer = setInterval(function(){ showQ(qAt + 1); }, 7000);
  }
  restart();

  var qBox = document.getElementById('quotes');
  qBox.addEventListener('mouseenter', function(){ clearInterval(timer); });
  qBox.addEventListener('mouseleave', restart);

  /* ---- FAQ accordion ---- */
  document.querySelectorAll('.q').forEach(function(q){
    var btn = q.querySelector('.q__btn');
    btn.setAttribute('aria-expanded','false');
    btn.addEventListener('click', function(){
      var open = q.classList.contains('is-open');
      document.querySelectorAll('.q.is-open').forEach(function(o){
        o.classList.remove('is-open');
        o.querySelector('.q__btn').setAttribute('aria-expanded','false');
      });
      if(!open){
        q.classList.add('is-open');
        btn.setAttribute('aria-expanded','true');
      }
    });
  });
})();
