/* Page script: shop - runs after common.js

   Products, sorting and pagination are all server-side now, so this file no
   longer holds any product data or builds any markup. What is left is the
   behaviour that genuinely belongs in the browser. */
(function(){
  'use strict';
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  var grid = document.getElementById('grid');

  /* ---- reveal on scroll, and fill the stock bars as each card appears ---- */
  if(grid){
    var cards = Array.prototype.slice.call(grid.querySelectorAll('.card'));

    var fill = function(card){
      var f = card.querySelector('.stock__fill');
      if(f) f.style.width = f.dataset.w + '%';
    };

    var io = ('IntersectionObserver' in window) ? new IntersectionObserver(function(en){
      en.forEach(function(e){
        if(!e.isIntersecting) return;
        e.target.classList.add('is-in');
        fill(e.target);
        io.unobserve(e.target);
      });
    }, {threshold:.12, rootMargin:'0px 0px -40px 0px'}) : null;

    cards.forEach(function(c){
      if(io && !reduce){ io.observe(c); }
      else { c.classList.add('is-in'); fill(c); }
    });
  }

  /* ---- sorting ----
     Reloads with ?sort=, so the chosen order applies to the whole catalogue
     rather than only the twelve products on the current page. Page is dropped
     because page 6 of the old order means nothing in the new one. */
  var sort = document.getElementById('sort');
  if(sort){
    sort.addEventListener('change', function(){
      var url = new URL(window.location.href);
      if(sort.value === 'default') url.searchParams.delete('sort');
      else url.searchParams.set('sort', sort.value);
      url.searchParams.delete('page');
      window.location.assign(url.toString());
    });
  }

  /* ---- search filter ----
     Narrows the current page only; the header search is a quick filter, not a
     catalogue search. */
  var q = document.getElementById('q');
  if(q && grid){
    q.addEventListener('input', function(){
      var t = q.value.trim().toLowerCase();

      grid.querySelectorAll('.card').forEach(function(card){
        var title = card.querySelector('.card__title');
        var cat = card.querySelector('.card__eyebrow');
        var hay = ((title ? title.textContent : '') + ' ' +
                   (cat ? cat.textContent : '')).toLowerCase();
        card.style.display = (!t || hay.indexOf(t) > -1) ? '' : 'none';
      });
    });
  }

  /* ---- grid / list toggle ---- */
  var vGrid = document.getElementById('vGrid'), vList = document.getElementById('vList');
  if(grid && vGrid && vList){
    var setView = function(list){
      grid.classList.toggle('is-list', list);
      vList.classList.toggle('is-on', list);
      vGrid.classList.toggle('is-on', !list);
      vList.setAttribute('aria-pressed', list);
      vGrid.setAttribute('aria-pressed', !list);
    };
    vGrid.addEventListener('click', function(){ setView(false); });
    vList.addEventListener('click', function(){ setView(true); });
  }

  /* ---- wishlist (delegated)
     "Select options" is a link to the product page, so it needs no handler. ---- */
  if(grid){
    grid.addEventListener('click', function(e){
      var w = e.target.closest('.wish');
      if(!w) return;
      e.preventDefault();
      var on = w.classList.toggle('is-on');
      w.setAttribute('aria-pressed', on);
      w.querySelector('svg').setAttribute('fill', on ? 'currentColor' : 'none');
    });
  }

  /* ---- countdown ----
     The manager's coupon end time is rendered onto the promotion strip.
     Once it passes, the now-invalid promotion disappears immediately. */
  var promotion = document.getElementById('promotion');
  var clock = document.getElementById('clock');
  var saleEnd = promotion ? Date.parse(promotion.dataset.promotionEndsAt || '') : NaN;
  var timer;
  function tick(){
    if(!promotion || !clock || Number.isNaN(saleEnd)) return;
    var left = saleEnd - Date.now();
    if(left <= 0){
      promotion.hidden = true;
      clearInterval(timer);
      return;
    }
    var s = Math.floor(left/1000);
    var pad = function(n){ return String(n).padStart(2,'0'); };
    clock.querySelector('[data-unit="h"]').textContent = pad(Math.floor(s/3600));
    clock.querySelector('[data-unit="m"]').textContent = pad(Math.floor(s%3600/60));
    clock.querySelector('[data-unit="s"]').textContent = pad(s%60);
  }
  if(clock && !Number.isNaN(saleEnd)){ tick(); timer = setInterval(tick, 1000); }

  /* ---- category sections ----
     One open at a time is wrong here: these are reference, and somebody
     comparing two of them should be able to have both open. */
  document.querySelectorAll('[data-accordion] .catacc__head').forEach(function(head){
    head.addEventListener('click', function(){
      var body = head.nextElementSibling;
      var open = head.getAttribute('aria-expanded') === 'true';

      head.setAttribute('aria-expanded', open ? 'false' : 'true');
      body.hidden = open;
      head.closest('.catacc__item').classList.toggle('is-open', !open);
    });
  });

  /* ---- newsletter ---- */
  var nf = document.getElementById('newsForm'), ok = document.getElementById('newsOk');
  if(nf && ok){
    nf.addEventListener('submit', function(e){
      e.preventDefault();
      ok.textContent = 'Thanks — check your inbox to confirm.';
      nf.reset();
    });
  }
})();
