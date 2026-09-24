/* Page script: product - runs after common.js and cart-store.js

   The product itself is rendered server-side; this file reads the pack list
   the page carries on #packs and keeps the selector, the price, the stock line
   and the cart in step - exactly as it did before, just with real data. */
(function(){
  'use strict';
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  var packsBox = document.getElementById('packs');
  if(!packsBox) return;

  var PACKS = [];
  try { PACKS = JSON.parse(packsBox.dataset.packs || '[]'); }
  catch(e){ PACKS = []; }

  /* No sizes defined: the product is sold as a single item. The selector stays
     hidden, but the stock line, the buy buttons and the cart still need a price
     and a stock figure, so one implicit pack stands in for it. */
  var HAS_PACKS = PACKS.length > 0;

  if(!HAS_PACKS){
    var fallbackStock = packsBox.dataset.fallbackStock;
    PACKS = [{
      label: '',
      price: parseFloat(packsBox.dataset.fallbackPrice) || 0,
      was: null,
      best: false,
      stock: fallbackStock === '' || fallbackStock === undefined ? null : parseInt(fallbackStock, 10)
    }];
  }

  var CUR = packsBox.dataset.currency || '$';
  /* What a size is counted in, appended to every label so "30" reads as
     "30 - Pills". One word for the whole shop, from config/shop.php. */
  var UNIT = (packsBox.dataset.unitLabel || '').trim();
  var LOW = parseInt(packsBox.dataset.lowStock, 10);
  if(isNaN(LOW)) LOW = 5;

  /* Backorders on means the shop keeps selling at zero stock, so a sold-out
     size is still buyable - it just ships later. Without this the buy buttons
     would lock on a product the operator deliberately left orderable. */
  var BACKORDER = packsBox.dataset.allowBackorder === '1';

  var pNow = document.getElementById('pNow');
  var pWas = document.getElementById('pWas');
  var pSave = document.getElementById('pSave');
  var packLabel = document.getElementById('packLabel');
  var stockMsg = document.getElementById('stockMsg');
  var sPrice = document.getElementById('sPrice');
  var at = 0;

  function money(v){ return CUR + Number(v).toFixed(2); }

  /* Every control that puts something in the basket, so a sold-out size can
     disable them all in one go. */
  var buyButtons = ['addCart', 'sCart', 'buyNow', 'sBuy']
    .map(function(id){ return document.getElementById(id); })
    .filter(Boolean);

  var soldOut = false;

  function setBuyable(canBuy){
    soldOut = !canBuy;
    buyButtons.forEach(function(b){ b.disabled = soldOut; });
  }

  packsBox.innerHTML = !HAS_PACKS ? '' : PACKS.map(function(p, i){
    return ''+
    '<button class="pack'+(i === 0 ? ' is-on' : '')+'" role="radio" aria-checked="'+(i===0)+'" data-i="'+i+'">'+
      (p.best ? '<span class="pack__best">Best value</span>' : '')+
      '<b></b>'+
      '<span class="pack__price">'+money(p.price)+'</span>'+
    '</button>';
  }).join('');

  /* Labels are set as text, never as HTML: they come from the catalogue and
     could contain characters that would otherwise be parsed as markup. */
  /* textContent, never HTML: the label comes from the catalogue and could
     contain characters that would otherwise be parsed as markup. */
  packsBox.querySelectorAll('.pack b').forEach(function(el, i){
    el.textContent = UNIT ? PACKS[i].label + ' - ' + UNIT : PACKS[i].label;
  });

  function paint(){
    var p = PACKS[at];

    pNow.textContent = money(p.price);
    sPrice.textContent = money(p.price);
    if(packLabel) packLabel.textContent = p.label + ' selected';

    /* A struck-through price and a saving are only shown when there is a real
       previous price to compare against. */
    if(p.was && p.was > p.price){
      pWas.textContent = money(p.was);
      pSave.textContent = 'Save ' + Math.round((1 - p.price / p.was) * 100) + '%';
      pWas.style.display = '';
      pSave.style.display = '';
    } else {
      pWas.textContent = '';
      pSave.textContent = '';
      pWas.style.display = 'none';
      pSave.style.display = 'none';
    }

    /* stock === null means the product does not track inventory at all. */
    if(p.stock === null || p.stock === undefined){
      stockMsg.textContent = 'In stock — ships within 24 hours';
    } else if(p.stock <= 0){
      stockMsg.textContent = BACKORDER
        ? 'On backorder — order now'
        : 'Out of stock';
    } else if(p.stock <= LOW){
      stockMsg.textContent = 'Low stock — ' + p.stock + ' left';
    } else {
      stockMsg.textContent = 'In stock — ships within 24 hours';
    }

    packsBox.querySelectorAll('.pack').forEach(function(b, i){
      b.classList.toggle('is-on', i === at);
      b.setAttribute('aria-checked', i === at);
    });

    /* A customer must not be able to buy something the shop does not have.
       The mockup never hit this because its stock was fictional. */
    setBuyable(BACKORDER || !(p.stock !== null && p.stock !== undefined && p.stock <= 0));

    if(!reduce){
      pNow.animate([{opacity:.3, transform:'translateY(-5px)'},{opacity:1, transform:'none'}],
                   {duration:320, easing:'cubic-bezier(.22,.61,.36,1)'});
    }
  }
  paint();

  packsBox.addEventListener('click', function(e){
    var b = e.target.closest('.pack');
    if(!b) return;
    at = parseInt(b.dataset.i, 10);
    paint();
  });

  /* ---- quantity ---- */
  var qty = document.getElementById('qty');
  document.getElementById('minus').addEventListener('click', function(){
    qty.value = Math.max(1, (parseInt(qty.value,10) || 1) - 1);
  });
  document.getElementById('plus').addEventListener('click', function(){
    qty.value = Math.min(99, (parseInt(qty.value,10) || 1) + 1);
  });
  qty.addEventListener('change', function(){
    var v = parseInt(qty.value,10);
    qty.value = (!v || v < 1) ? 1 : Math.min(99, v);
  });

  /* ---- add to cart: writes into the shared store, so the header badge and
     the cart page pick it up straight away ---- */
  var PRODUCT_ID = packsBox.dataset.productId;
  var PRODUCT_NAME = packsBox.dataset.productName || '';
  var BADGE = packsBox.dataset.productBadge || '';
  var IMAGE = packsBox.dataset.productImage || '';

  /* The line as the cart stores it. Shared by both buttons so "Buy now" can
     never put something different in the basket from "Add to cart". */
  function currentLine(){
    var pack = PACKS[at];

    return {
      /* Keyed on the real product id plus the pack, so two sizes of the same
         product are two cart lines and two different products never merge. */
      id: HAS_PACKS ? PRODUCT_ID + '-' + String(pack.label).replace(/\s+/g, '-') : PRODUCT_ID,

      /* The two fields the checkout is priced from. The line's own price is
         carried for display only - the server reads it back out of the
         catalogue and ignores whatever the browser thought it was. */
      productId: parseInt(PRODUCT_ID, 10),
      packLabel: HAS_PACKS ? pack.label : null,

      name: HAS_PACKS ? PRODUCT_NAME + ' — ' + pack.label : PRODUCT_NAME,
      badge: BADGE,
      /* Carried so the cart and the checkout can show the product rather than
         a generic icon. Empty falls back to the store's default bottle. */
      image: IMAGE,
      price: pack.price
    };
  }

  function chosenQty(){
    return parseInt(qty.value, 10) || 1;
  }

  function addToCart(btn){
    if(soldOut) return;

    Cart.add(currentLine(), chosenQty());

    var label = btn.innerHTML;
    btn.innerHTML = '✓ Added';
    btn.disabled = true;
    setTimeout(function(){ btn.innerHTML = label; btn.disabled = soldOut; }, 1400);
  }

  /* Buy now: this item only. The basket is emptied first, so the checkout
     that opens is for the thing that was just clicked and nothing else. */
  function buyNow(btn){
    if(soldOut) return;

    Cart.buyNow(currentLine(), chosenQty());

    btn.innerHTML = 'Taking you to checkout…';
    /* Every buy control, not just this one - the page is leaving, and a
       second click would add the item twice. */
    buyButtons.forEach(function(b){ b.disabled = true; });

    var urls = (window.AURUM_SHOP && window.AURUM_SHOP.urls) || {};
    window.location.href = urls.checkout || '/checkout';
  }

  function on(id, handler){
    var btn = document.getElementById(id);
    if(btn) btn.addEventListener('click', function(){ handler(btn); });
  }

  on('addCart', addToCart);
  on('sCart', addToCart);
  on('buyNow', buyNow);
  on('sBuy', buyNow);

  /* ---- countdown ----
     Point SALE_END at the real end of the promotion. When it
     passes the strip says so rather than looping forever. */
  var SALE_END = new Date();
  SALE_END.setHours(SALE_END.getHours() + 2, SALE_END.getMinutes() + 47, SALE_END.getSeconds(), 0);

  var clock = document.getElementById('clock');
  var timer;
  function tick(){
    if(!clock) return;
    var left = SALE_END - Date.now();
    if(left <= 0){
      clock.innerHTML = '<span style="font-size:.76rem;color:var(--muted)">Offer ended</span>';
      clearInterval(timer);
      return;
    }
    var s = Math.floor(left/1000);
    var pad = function(n){ return String(n).padStart(2,'0'); };
    clock.querySelector('[data-u="h"]').textContent = pad(Math.floor(s/3600));
    clock.querySelector('[data-u="m"]').textContent = pad(Math.floor(s%3600/60));
    clock.querySelector('[data-u="s"]').textContent = pad(s%60);
  }
  if(clock){ tick(); timer = setInterval(tick, 1000); }

  /* ---- panels + steps (delegated, both levels) ---- */
  document.addEventListener('click', function(e){
    var ph = e.target.closest('.panel__head');
    if(ph){
      var panel = ph.parentElement;
      var open = panel.classList.toggle('is-open');
      ph.setAttribute('aria-expanded', open);
      return;
    }
    var sb = e.target.closest('.step__btn');
    if(sb){
      var step = sb.parentElement;
      var o = step.classList.toggle('is-open');
      sb.setAttribute('aria-expanded', o);
    }
  });

  /* ---- write a review ----

     Posts to the storefront API and says so. What comes back is a receipt,
     not a review: it is pending, and it does not appear in the list above
     until somebody in the panel has read it - not even for the person who
     just wrote it. */
  var revForm = document.getElementById('revForm');

  if(revForm){
    var revOut = document.getElementById('formOk');
    var revBtn = document.getElementById('rSubmit');
    var sending = false;

    var say = function(text, tone){
      revOut.textContent = text;
      revOut.classList.toggle('is-bad', tone === 'bad');
      revOut.classList.toggle('is-good', tone === 'good');
    };

    var csrf = function(){
      var meta = document.querySelector('meta[name="csrf-token"]');
      return meta ? meta.getAttribute('content') : '';
    };

    revForm.addEventListener('submit', function(e){
      e.preventDefault();
      if(sending) return;

      var endpoint = (window.AURUM_SHOP && window.AURUM_SHOP.endpoints || {}).submit_review;
      if(!endpoint) return;

      sending = true;
      revBtn.disabled = true;
      say('Sending…');

      fetch(endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrf()
        },
        body: JSON.stringify({
          product_id: parseInt(revForm.dataset.product, 10),
          author_name: document.getElementById('rname').value,
          author_email: document.getElementById('remail').value,
          rating: parseInt((revForm.querySelector('input[name="rrating"]:checked') || {}).value, 10),
          body: document.getElementById('rtext').value
        })
      })
        .then(function(response){
          return response.json()
            .catch(function(){ return null; })
            .then(function(body){ return body; });
        })
        .then(function(body){
          if(body && body.success){
            say(body.message, 'good');
            revForm.reset();
            return;
          }

          if(body && body.errors){
            var first = Object.keys(body.errors)[0];
            say(body.errors[first][0], 'bad');
            return;
          }

          say((body && body.message) || 'We could not send your review. Please try again.', 'bad');
        })
        .catch(function(){
          say('We could not reach the store. Check your connection and try again.', 'bad');
        })
        .finally(function(){
          sending = false;
          revBtn.disabled = false;
        });
    });
  }

  /* ---- sticky buy bar: show once the main CTA scrolls past ---- */
  var sticky = document.getElementById('sticky');
  var topSec = document.getElementById('top');
  if(sticky && topSec && 'IntersectionObserver' in window){
    new IntersectionObserver(function(en){
      sticky.classList.toggle('is-on', !en[0].isIntersecting);
    }, {rootMargin:'-120px 0px 0px 0px'}).observe(topSec);
  }
})();
