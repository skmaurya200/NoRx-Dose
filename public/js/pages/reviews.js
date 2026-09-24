/* Page script: reviews - runs after common.js */
(function(){
  'use strict';
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* Approved reviews, rendered server side into window.AURUM_REVIEWS by
     resources/views/reviews.blade.php. Nothing is invented here and nothing
     is hidden: the list is exactly what the shop has published. */
  var REVIEWS = Array.isArray(window.AURUM_REVIEWS) ? window.AURUM_REVIEWS : [];
  var SHOP = window.AURUM_SHOP || {};

  var PER = 6;
  var box = document.getElementById('reviews');
  var loadBtn = document.getElementById('load');
  var state = {cat:'all', shown:PER};

  function initials(n){
    return String(n || '?').split(' ').filter(Boolean)
      .map(function(w){ return w[0]; }).join('').slice(0,2).toUpperCase();
  }
  function stars(n){ return '★★★★★'.slice(0,n) + '☆☆☆☆☆'.slice(0, 5-n); }

  /* Every review is somebody else's writing, so it is escaped rather than
     dropped into innerHTML as-is. */
  function esc(v){
    return String(v == null ? '' : v)
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }

  function revHTML(r, i){
    /* Every third card is set dark, which is what the design does with a
       long list - it is a rhythm, not a property of the review. */
    var dark = i % 4 === 1;

    return ''+
    '<article class="rev'+(dark ? ' rev--dark' : '')+'" data-id="'+esc(r.id)+'" style="--d:'+(i % 3) * 70+'ms">'+
      '<div class="rev__top">'+
        '<span class="rev__badge">'+(r.verified ? '✓ Verified purchase' : '✓ Published review')+'</span>'+
        '<span class="rev__stars">'+stars(r.stars)+'</span>'+
      '</div>'+
      (r.title ? '<p class="rev__text"><b>'+esc(r.title)+'</b></p>' : '')+
      '<p class="rev__text is-clamped">'+esc(r.text)+'</p>'+
      '<button class="more">Read more</button>'+
      '<button class="helpful"><span>👍</span> Helpful <b>'+(r.helpful || 0)+'</b></button>'+
      '<div class="rev__foot">'+
        '<span class="rev__av">'+esc(initials(r.who))+'</span>'+
        '<span class="rev__who"><b>'+esc(r.who)+'</b><span>'+esc(r.meta)+'</span></span>'+
        (r.verified ? '<span class="rev__check">✓ Verified</span>' : '')+
      '</div>'+
      '<p class="rev__cat">'+esc(r.product || r.cat_label)+'</p>'+
    '</article>';
  }

  var io = ('IntersectionObserver' in window) ? new IntersectionObserver(function(en){
    en.forEach(function(e){
      if(!e.isIntersecting) return;
      e.target.classList.add('is-in');
      io.unobserve(e.target);
    });
  }, {threshold:.1, rootMargin:'0px 0px -30px 0px'}) : null;

  function render(){
    var pool = REVIEWS.filter(function(r){ return state.cat === 'all' || r.cat === state.cat; });
    var list = pool.slice(0, state.shown);

    box.innerHTML = list.length
      ? list.map(revHTML).join('')
      : '<p style="grid-column:1/-1;text-align:center;padding:50px;color:var(--muted)">'+
        (REVIEWS.length ? 'No reviews in that category yet.' : 'No reviews published yet — yours could be the first.')+
        '</p>';

    box.querySelectorAll('.rev').forEach(function(c){
      if(io && !reduce) io.observe(c);
      else c.classList.add('is-in');
    });

    loadBtn.style.display = pool.length > state.shown ? '' : 'none';
  }
  render();

  loadBtn.addEventListener('click', function(){
    state.shown += PER;
    render();
  });

  /* ---- category chips ---- */
  document.getElementById('chips').addEventListener('click', function(e){
    var chip = e.target.closest('.chip');
    if(!chip) return;
    document.querySelectorAll('.chip').forEach(function(c){ c.classList.remove('is-on'); });
    chip.classList.add('is-on');
    state.cat = chip.dataset.cat;
    state.shown = PER;
    render();
  });

  /* ---- read more + helpful (delegated) ---- */
  box.addEventListener('click', function(e){
    var m = e.target.closest('.more');
    if(m){
      var txt = m.previousElementSibling;
      var open = txt.classList.toggle('is-clamped');
      m.classList.toggle('is-open', !open);
      m.firstChild.textContent = open ? 'Read more' : 'Show less';
      return;
    }
    var h = e.target.closest('.helpful');
    if(h && !h.classList.contains('is-on')){
      var card = h.closest('.rev');
      var n = h.querySelector('b');

      /* Counted straight away and left counted. The vote is recorded on the
         server; taking it back would need an account to key it to, and there
         are none. */
      h.classList.add('is-on');
      n.textContent = parseInt(n.textContent, 10) + 1;

      post(SHOP.endpoints && SHOP.endpoints.reviews_helpful
        ? SHOP.endpoints.reviews_helpful.replace('__ID__', card.dataset.id)
        : null, null);
    }
  });

  /* ---- tabs ---- */
  var tabs = Array.prototype.slice.call(document.querySelectorAll('.tab'));
  var panels = Array.prototype.slice.call(document.querySelectorAll('.panel'));

  function show(i){
    tabs.forEach(function(t, n){
      t.classList.toggle('is-on', n === i);
      t.setAttribute('aria-selected', n === i);
    });
    panels.forEach(function(p, n){ p.classList.toggle('is-on', n === i); });
    history.replaceState(null, '', '#' + tabs[i].getAttribute('aria-controls'));
    if(i === 1) fillBars();
  }

  tabs.forEach(function(t, i){
    t.addEventListener('click', function(){ show(i); });
    t.addEventListener('keydown', function(e){
      var next = e.key === 'ArrowRight' ? i + 1 : e.key === 'ArrowLeft' ? i - 1 : null;
      if(next === null) return;
      e.preventDefault();
      next = (next + tabs.length) % tabs.length;
      tabs[next].focus();
      show(next);
    });
  });

  var hash = location.hash.replace('#','');
  var start = panels.findIndex(function(p){ return p.id === hash; });
  if(start > -1) show(start);

  /* ---- rating bars ---- */
  function fillBars(){
    document.querySelectorAll('.bars .fill').forEach(function(f){
      f.style.width = f.dataset.w + '%';
    });
  }

  /* ---- star picker ---- */
  var picker = document.getElementById('picker');
  var pickerOut = document.getElementById('pickerOut');
  var chosen = 0;
  var LABEL = ['Not rated yet','Poor','Fair','Good','Very good','Excellent'];

  function paint(v){
    picker.querySelectorAll('button').forEach(function(b){
      b.classList.toggle('on', parseInt(b.dataset.v,10) <= v);
    });
    pickerOut.textContent = LABEL[v];
  }
  picker.addEventListener('click', function(e){
    var b = e.target.closest('button');
    if(!b) return;
    chosen = parseInt(b.dataset.v,10);
    paint(chosen);
  });
  picker.addEventListener('mouseover', function(e){
    var b = e.target.closest('button');
    if(b) paint(parseInt(b.dataset.v,10));
  });
  picker.addEventListener('mouseleave', function(){ paint(chosen); });

  /* ---- posting ---- */
  function csrf(){
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  /* Returns a promise of {status, body}, or of null when there is no endpoint
     to call - the helpful counter uses that and does not care. */
  function post(url, payload){
    if(!url) return Promise.resolve(null);

    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrf()
      },
      body: JSON.stringify(payload || {})
    }).then(function(response){
      return response.json()
        .catch(function(){ return null; })
        .then(function(body){ return { status: response.status, body: body }; });
    });
  }

  /* ---- review form ---- */
  var form = document.getElementById('revForm'), formOk = document.getElementById('formOk');
  var sending = false;

  form.addEventListener('submit', function(e){
    e.preventDefault();
    if(sending) return;

    if(!chosen){ formOk.textContent = 'Pick a star rating first.'; return; }

    var submit = form.querySelector('button[type="submit"]');

    sending = true;
    submit.disabled = true;
    formOk.textContent = 'Sending…';

    post(SHOP.endpoints && SHOP.endpoints.submit_review, {
      author_name: document.getElementById('rname').value,
      author_email: document.getElementById('remail').value,
      category: document.getElementById('rcat').value,
      rating: chosen,
      body: document.getElementById('rtext').value
    })
      .then(function(result){
        var body = result && result.body;

        if(body && body.success){
          /* Deliberately not added to the list above: it is pending, and it
             is not published until somebody here has read it. */
          formOk.textContent = body.message;
          form.reset();
          chosen = 0;
          paint(0);
          return;
        }

        if(body && body.errors){
          var first = Object.keys(body.errors)[0];
          formOk.textContent = body.errors[first][0];
          return;
        }

        formOk.textContent = (body && body.message) ||
          'We could not send your review. Please try again.';
      })
      .catch(function(){
        formOk.textContent = 'We could not reach the store. Check your connection and try again.';
      })
      .finally(function(){
        sending = false;
        submit.disabled = false;
      });
  });

  /* ---- FAQ accordion ---- */
  document.querySelectorAll('.q').forEach(function(q){
    var btn = q.querySelector('.q__btn'), panel = q.querySelector('.q__panel');
    btn.setAttribute('aria-expanded','false');
    btn.addEventListener('click', function(){
      var open = q.classList.contains('is-open');
      document.querySelectorAll('.q.is-open').forEach(function(o){
        o.classList.remove('is-open');
        o.querySelector('.q__panel').style.maxHeight = null;
        o.querySelector('.q__btn').setAttribute('aria-expanded','false');
      });
      if(!open){
        q.classList.add('is-open');
        panel.style.maxHeight = panel.scrollHeight + 'px';
        btn.setAttribute('aria-expanded','true');
      }
    });
  });

  /* ---- counters ---- */
  function runCount(el){
    var target = parseFloat(el.dataset.count), suffix = el.dataset.suffix || '';
    var comma = el.hasAttribute('data-comma'), dur = 1500, start = null;
    if(reduce){ el.textContent = (comma ? target.toLocaleString('en-US') : target) + suffix; return; }
    function step(ts){
      if(!start) start = ts;
      var p = Math.min((ts - start) / dur, 1);
      var v = Math.round(target * (1 - Math.pow(1 - p, 3)));
      el.textContent = (comma ? v.toLocaleString('en-US') : v) + suffix;
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
