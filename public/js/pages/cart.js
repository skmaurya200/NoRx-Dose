/* Page script: cart - runs after common.js and cart-store.js */
(function(){
  'use strict';
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* Contents, shipping options and coupons all live in cart-store.js so this
     page, the checkout page and the header badge can never disagree. The
     offers were rendered into the page by HomeController::cart(). */
  var SHIPPING = Cart.SHIPPING;
  Cart.setOffers(window.AURUM_OFFERS || []);

  var itemsBox = document.getElementById('items');
  var shipBox = document.getElementById('ship');
  var offersBox = document.getElementById('offers');
  var offersCount = document.getElementById('offersCount');
  var checkoutBtn = document.getElementById('checkout');
  var msg = document.getElementById('couponMsg');

  function money(n){ return Cart.money(n); }
  function plural(n){ return n + (n === 1 ? ' item' : ' items'); }

  /* Labels come from the catalogue, so they are set as text, never parsed as
     markup - a code called "<b>" is a naming mistake, not a script tag. */
  function text(el, value){ el.textContent = value; return el; }

  /* ---- render items ---- */
  function drawItems(){
    var items = Cart.items();

    if(!items.length){
      itemsBox.innerHTML = ''+
        '<div class="empty">'+
          '<span class="empty__ic"><svg><use href="#i-bag"/></svg></span>'+
          '<h3>Your cart is empty</h3>'+
          '<p>Nothing in here yet — have a look at what we make.</p>'+
          '<a href="'+(window.AURUM_SHOP && window.AURUM_SHOP.urls ? window.AURUM_SHOP.urls.shop : '/shop')+'" class="back">Browse the shop</a>'+
        '</div>';
      document.getElementById('back').style.display = 'none';
      return;
    }
    document.getElementById('back').style.display = '';

    itemsBox.innerHTML = items.map(function(it){
      return ''+
      '<article class="item" data-id="'+it.id+'">'+
        '<div class="item__media"><img data-thumb alt=""></div>'+
        '<div class="item__info">'+
          '<h3 data-name></h3>'+
          (it.badge ? '<span class="item__badge">✓ <span data-badge></span></span>' : '')+
          '<p class="item__each">'+money(it.price)+' each</p>'+
        '</div>'+
        '<div class="qty">'+
          '<button data-act="minus" aria-label="Decrease quantity">−</button>'+
          '<span>'+it.qty+'</span>'+
          '<button data-act="plus" aria-label="Increase quantity">+</button>'+
        '</div>'+
        '<div class="item__total">'+money(it.price * it.qty)+'</div>'+
        '<button class="item__x" data-act="remove" aria-label="Remove item">✕</button>'+
      '</article>';
    }).join('');

    itemsBox.querySelectorAll('.item').forEach(function(card, i){
      text(card.querySelector('[data-name]'), items[i].name);

      var badge = card.querySelector('[data-badge]');
      if(badge) text(badge, items[i].badge);

      var thumb = card.querySelector('[data-thumb]');
      thumb.src = Cart.imageFor(items[i]);
      thumb.alt = items[i].name;
    });
  }

  /* ---- render shipping ---- */
  function drawShip(){
    var shipAt = Cart.shipAt();
    shipBox.innerHTML = SHIPPING.map(function(s, i){
      return ''+
      '<button class="shipOpt'+(i === shipAt ? ' is-on' : '')+'" role="radio" aria-checked="'+(i===shipAt)+'" data-i="'+i+'">'+
        '<span class="shipOpt__dot"></span>'+
        '<span class="shipOpt__t">'+
          '<b>'+s.name+' <span class="tag">'+s.tag+'</span></b>'+
          '<span>'+s.note+'</span>'+
        '</span>'+
        '<span class="shipOpt__p" data-cost>'+Cart.shipPriceLabel(i)+'</span>'+
      '</button>';
    }).join('');
  }

  /* ---- render offers ----
     One card per live public code. The card says what the code does, whether
     this basket qualifies, and - when it does not - exactly how much more is
     needed. Applying is one tap; there is no code to type anywhere. */
  function drawOffers(){
    var offers = Cart.offers();
    var sub = Cart.subtotal();
    var applied = Cart.coupon();

    if(offersCount) offersCount.textContent = offers.length ? offers.length + ' available' : '';

    if(!offers.length){
      offersBox.innerHTML = '<p class="offers__none">No offers running right now.</p>';
      return;
    }

    offersBox.innerHTML = offers.map(function(o){
      var qualifies = sub >= o.min_order_amount;
      var isOn = applied === o.code;

      var action = isOn ? 'Applied'
                 : qualifies ? 'Apply'
                 : 'Add ' + money(o.min_order_amount - sub) + ' more';

      return ''+
      '<button type="button" class="offer'+(isOn ? ' is-on' : '')+(!qualifies && !isOn ? ' is-locked' : '')+'"'+
              ' data-code="'+encodeURIComponent(o.code)+'" aria-pressed="'+isOn+'"'+
              (!qualifies && !isOn ? ' disabled' : '')+'>'+
        '<span class="offer__ic"><svg><use href="#i-tag"/></svg></span>'+
        '<span class="offer__t">'+
          '<b data-label></b>'+
          '<span data-desc></span>'+
        '</span>'+
        '<span class="offer__act" data-act></span>'+
      '</button>';
    }).join('');

    offersBox.querySelectorAll('.offer').forEach(function(card, i){
      var o = offers[i];
      var qualifies = sub >= o.min_order_amount;
      var isOn = applied === o.code;

      var label = card.querySelector('[data-label]');
      text(label, o.value_label + ' ');

      var code = document.createElement('span');
      code.className = 'offer__code';
      code.textContent = o.code;
      label.appendChild(code);

      text(card.querySelector('[data-desc]'), o.description);
      text(card.querySelector('[data-act]'),
        isOn ? 'Applied' : qualifies ? 'Apply' : 'Add ' + money(o.min_order_amount - sub) + ' more');
    });
  }

  /* ---- totals ---- */
  function draw(){
    var t = Cart.totals();

    document.getElementById('headCount').textContent = plural(t.units);
    document.getElementById('subLabel').textContent = 'Subtotal (' + plural(t.units) + ')';
    document.getElementById('subVal').textContent = money(t.sub);

    var offRow = document.getElementById('offRow');
    offRow.style.display = t.off > 0 ? '' : 'none';
    document.getElementById('offLabel').textContent = t.coupon
      ? t.coupon.code + ' — ' + t.coupon.value_label
      : 'Discount';
    document.getElementById('offVal').textContent = '−' + money(t.off);

    document.getElementById('shipVal').textContent = t.freeShip ? 'Free' : money(t.shipCost);
    document.getElementById('totalVal').textContent = money(t.total);

    /* A code that stopped qualifying because the cart shrank says so, rather
       than the discount quietly vanishing from the total. */
    if(t.coupon && t.sub < t.coupon.min_order_amount){
      msg.className = 'couponMsg bad';
      msg.textContent = 'Add ' + money(t.coupon.min_order_amount - t.sub) +
                        ' more to use ' + t.coupon.code + '.';
    }

    checkoutBtn.disabled = !Cart.items().length;

    /* keep each option's listed price in step with any free-delivery promo */
    shipBox.querySelectorAll('[data-cost]').forEach(function(el, i){
      el.textContent = Cart.shipPriceLabel(i);
    });

    drawOffers();
  }

  drawShip();
  drawItems();
  draw();

  /* every write to the store repaints the page and the header badge */
  Cart.onChange(function(){ drawItems(); draw(); });

  /* ---- item actions ---- */
  itemsBox.addEventListener('click', function(e){
    var btn = e.target.closest('[data-act]');
    if(!btn) return;
    var card = btn.closest('.item');
    var id = card.dataset.id;
    var it = Cart.items().find(function(x){ return String(x.id) === id; });
    if(!it) return;

    var act = btn.dataset.act;

    if(act === 'plus')  Cart.setQty(it.id, it.qty + 1);
    if(act === 'minus') Cart.setQty(it.id, it.qty - 1);

    if(act === 'remove'){
      card.classList.add('is-going');
      setTimeout(function(){ Cart.remove(it.id); }, reduce ? 0 : 300);
    }
  });

  /* ---- clear cart ---- */
  document.getElementById('clear').addEventListener('click', function(){
    if(!Cart.items().length) return;
    msg.textContent = '';
    Cart.clear();
  });

  /* ---- shipping select ---- */
  shipBox.addEventListener('click', function(e){
    var b = e.target.closest('.shipOpt');
    if(!b) return;
    var i = parseInt(b.dataset.i, 10);
    Cart.setShip(i);
    shipBox.querySelectorAll('.shipOpt').forEach(function(o, n){
      o.classList.toggle('is-on', n === i);
      o.setAttribute('aria-checked', n === i);
    });
  });

  /* ---- apply / remove an offer ----
     Tapping an applied code takes it off again; tapping any other one swaps
     to it, because only one discount applies to an order. */
  offersBox.addEventListener('click', function(e){
    var card = e.target.closest('.offer');
    if(!card || card.disabled) return;

    var code = decodeURIComponent(card.dataset.code);

    if(Cart.coupon() === code){
      Cart.setCoupon(null);
      msg.className = 'couponMsg';
      msg.textContent = '';
      draw();
      return;
    }

    Cart.setCoupon(code);

    var offer = Cart.findOffer(code);
    msg.className = 'couponMsg ok';
    msg.textContent = code + ' applied — ' + offer.value_label + ' your order.';
    draw();
  });

  /* ---- checkout: the cart is already saved, so just go there ---- */
  checkoutBtn.addEventListener('click', function(){
    if(!Cart.items().length) return;
    this.innerHTML = 'Taking you to checkout…';
    this.disabled = true;
    window.location.href = this.dataset.checkoutUrl || '/checkout';
  });
})();
