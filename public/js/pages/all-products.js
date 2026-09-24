/* Page script: all-products - runs after common.js

   The catalogue itself is rendered server-side by Blade, so this file no longer
   builds any markup. It filters the cards that are already on the page, which
   keeps the chips instant and means the grid is present for search engines and
   for anyone with JavaScript switched off. */
(function(){
  'use strict';
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  var grid  = document.getElementById('grid');
  if(!grid) return;

  var cards = Array.prototype.slice.call(grid.querySelectorAll('.card'));
  var empty = document.getElementById('empty');
  var count = document.getElementById('catCount');
  var state = {cat:'all', q:''};

  /* Searchable text is read once per card rather than on every keystroke. */
  cards.forEach(function(card){
    var title = card.querySelector('.card__title');
    card.dataset.search = ((title ? title.textContent : '') + ' ' +
                           (card.dataset.cat || '')).toLowerCase();
  });

  var io = ('IntersectionObserver' in window) ? new IntersectionObserver(function(en){
    en.forEach(function(e){
      if(!e.isIntersecting) return;
      e.target.classList.add('is-in');
      io.unobserve(e.target);
    });
  }, {threshold:.1, rootMargin:'0px 0px -30px 0px'}) : null;

  function reveal(card){
    if(io && !reduce) io.observe(card);
    else card.classList.add('is-in');
  }
  cards.forEach(reveal);

  function render(){
    var t = state.q.toLowerCase();
    var shown = 0;

    cards.forEach(function(card){
      var okCat = state.cat === 'all' || card.dataset.cat === state.cat;
      var okQ   = !t || card.dataset.search.indexOf(t) > -1;
      var match = okCat && okQ;

      /* display rather than the hidden attribute: .card is a flex container,
         and its display rule would win over [hidden]. */
      card.style.display = match ? '' : 'none';

      if(match){
        shown++;
        /* A card revealed while hidden never fired its observer, so anything
           filtered back into view is revealed now. */
        if(!card.classList.contains('is-in')) reveal(card);
      }
    });

    if(count) count.textContent = shown + (shown === 1 ? ' product' : ' products');
    if(empty) empty.style.display = shown ? 'none' : '';
  }
  render();

  /* ---- category chips ---- */
  var filters = document.getElementById('filters');
  var notes = Array.prototype.slice.call(document.querySelectorAll('[data-cat-note]'));

  /* The chosen category's own copy, under the grid. "All" shows none: a page
     of every product is not about any one category. */
  function paintNote(){
    notes.forEach(function(note){
      note.hidden = state.cat === 'all' || note.dataset.catNote !== state.cat;
    });
  }
  paintNote();

  if(filters){
    filters.addEventListener('click', function(e){
      var chip = e.target.closest('.chip');
      if(!chip) return;
      filters.querySelectorAll('.chip').forEach(function(c){ c.classList.remove('is-on'); });
      chip.classList.add('is-on');
      state.cat = chip.dataset.cat;
      render();
      paintNote();
    });
  }

  /* ---- category sections ----
     Several open at once on purpose: these are reference, and somebody
     comparing two of them should be able to have both open. --*/
  document.addEventListener('click', function(e){
    var head = e.target.closest('[data-accordion] .catacc__head');
    if(!head) return;

    var body = head.nextElementSibling;
    var open = head.getAttribute('aria-expanded') === 'true';

    head.setAttribute('aria-expanded', open ? 'false' : 'true');
    body.hidden = open;
    head.closest('.catacc__item').classList.toggle('is-open', !open);
  });

  /* ---- search ---- */
  var q = document.getElementById('q');
  if(q) q.addEventListener('input', function(){ state.q = q.value.trim(); render(); });

  /* ---- deep link from the home page's category tiles (/all-products#slug) ---- */
  if(window.location.hash){
    var wanted = decodeURIComponent(window.location.hash.slice(1)).toLowerCase();
    var target = filters && Array.prototype.slice.call(filters.querySelectorAll('.chip'))
      .filter(function(c){
        return (c.dataset.cat || '').toLowerCase().replace(/[^a-z0-9]+/g,'-') === wanted;
      })[0];
    if(target) target.click();
  }

  /* ---- wishlist (delegated)
     "Select options" is a link to the product page, so it needs no handler. ---- */
  grid.addEventListener('click', function(e){
    var w = e.target.closest('.wish');
    if(!w) return;
    e.preventDefault();
    var on = w.classList.toggle('is-on');
    w.setAttribute('aria-pressed', on);
    w.querySelector('svg').setAttribute('fill', on ? 'currentColor' : 'none');
  });

  /* ---- back to top (sticky nav itself lives in common.js) ---- */
  var top = document.getElementById('top');
  if(top){
    var onScroll = function(){ top.classList.toggle('is-on', window.scrollY > 700); };
    window.addEventListener('scroll', onScroll, {passive:true});
    onScroll();
    top.addEventListener('click', function(){
      window.scrollTo({top:0, behavior: reduce ? 'auto' : 'smooth'});
    });
  }
})();
