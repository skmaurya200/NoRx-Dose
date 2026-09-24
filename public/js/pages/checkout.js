/* Page script: checkout - runs after common.js and cart-store.js

   The browser checks here are for speed of feedback only. Every one of them
   is restated in App\Http\Requests\APIs\Checkout\PlaceOrderRequest, and the
   totals are recomputed from the catalogue by CheckoutService, so nothing on
   this page decides what an order costs or whether it is allowed. */
(function(){
  'use strict';
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  var SHOP = window.AURUM_SHOP || {};
  var SHIPPING = Cart.SHIPPING;
  Cart.setOffers(window.AURUM_OFFERS || []);

  var linesBox = document.getElementById('lines');
  var shipBox = document.getElementById('ship');
  var offersBox = document.getElementById('offers');
  var offersCount = document.getElementById('offersCount');
  var formErr = document.getElementById('formErr');
  var cMsg = document.getElementById('couponMsg');

  function money(n){ return Cart.money(n); }
  function text(el, value){ if(el) el.textContent = value; return el; }

  /* ---- order lines ----
     Names and image addresses come from the catalogue, so they are set as
     properties rather than interpolated into the HTML string. */
  function drawLines(){
    var items = Cart.items();

    linesBox.innerHTML = items.map(function(it){
      return ''+
      '<div class="line">'+
        '<span class="line__media"><img data-thumb alt=""></span>'+
        '<span class="line__t"><b data-name></b><span>Qty: '+it.qty+'</span></span>'+
        '<span class="line__p">'+money(it.price * it.qty)+'</span>'+
      '</div>';
    }).join('');

    linesBox.querySelectorAll('[data-name]').forEach(function(el, i){
      text(el, items[i].name);
    });

    linesBox.querySelectorAll('[data-thumb]').forEach(function(img, i){
      img.src = Cart.imageFor(items[i]);
      img.alt = items[i].name;
    });
  }

  /* ---- shipping options ---- */
  function drawShip(){
    shipBox.innerHTML = SHIPPING.map(function(s, i){
      var on = i === Cart.shipAt();
      return ''+
      '<button type="button" class="shipOpt'+(on ? ' is-on' : '')+'" role="radio" aria-checked="'+on+'" data-i="'+i+'">'+
        '<span class="shipOpt__dot"></span>'+
        '<span class="shipOpt__t"><b>'+s.name+' <span class="tag">'+s.tag+'</span></b><span>'+s.note+'</span></span>'+
        '<span class="shipOpt__p" data-cost>'+Cart.shipPriceLabel(i)+'</span>'+
      '</button>';
    }).join('');
  }

  /* ---- offers: the same one-tap list as the cart ---- */
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

      return ''+
      '<button type="button" class="offer'+(isOn ? ' is-on' : '')+(!qualifies && !isOn ? ' is-locked' : '')+'"'+
              ' data-code="'+encodeURIComponent(o.code)+'" aria-pressed="'+isOn+'"'+
              (!qualifies && !isOn ? ' disabled' : '')+'>'+
        '<span class="offer__ic"><svg><use href="#i-tag"/></svg></span>'+
        '<span class="offer__t"><b data-label></b><span data-desc></span></span>'+
        '<span class="offer__act" data-act></span>'+
      '</button>';
    }).join('');

    offersBox.querySelectorAll('.offer').forEach(function(card, i){
      var o = offers[i];
      var qualifies = sub >= o.min_order_amount;
      var isOn = applied === o.code;

      var label = text(card.querySelector('[data-label]'), o.value_label + ' ');
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
    var units = t.units;

    document.getElementById('subLabel').textContent = 'Subtotal (' + units + (units === 1 ? ' item' : ' items') + ')';
    document.getElementById('subVal').textContent = money(t.sub);

    var offRow = document.getElementById('offRow');
    offRow.style.display = t.off > 0 ? '' : 'none';
    document.getElementById('offLabel').textContent = t.coupon
      ? t.coupon.code + ' — ' + t.coupon.value_label
      : 'Discount';
    document.getElementById('offVal').textContent = '−' + money(t.off);

    document.getElementById('shipVal').textContent = t.freeShip ? 'Free' : money(t.shipCost);
    document.getElementById('totalVal').textContent = money(t.total);

    document.getElementById('sTotal').textContent = money(t.total);
    document.getElementById('sShip').textContent = t.option.name;
    document.getElementById('sItems').textContent = units + (units === 1 ? ' item' : ' items');

    shipBox.querySelectorAll('[data-cost]').forEach(function(el, i){
      el.textContent = Cart.shipPriceLabel(i);
    });

    drawOffers();
  }

  drawLines();
  drawShip();
  draw();

  /* An empty cart cannot be checked out. Sent back rather than shown a form
     that could only ever fail. */
  if(!Cart.items().length){
    window.location.replace((SHOP.urls && SHOP.urls.cart) || '/cart');
    return;
  }

  shipBox.addEventListener('click', function(e){
    var b = e.target.closest('.shipOpt');
    if(!b) return;
    var at = parseInt(b.dataset.i, 10);
    Cart.setShip(at);
    shipBox.querySelectorAll('.shipOpt').forEach(function(o, i){
      o.classList.toggle('is-on', i === at);
      o.setAttribute('aria-checked', i === at);
    });
    draw();
  });

  /* ---- apply / remove an offer ---- */
  offersBox.addEventListener('click', function(e){
    var card = e.target.closest('.offer');
    if(!card || card.disabled) return;

    var code = decodeURIComponent(card.dataset.code);

    if(Cart.coupon() === code){
      Cart.setCoupon(null);
      cMsg.className = 'couponMsg';
      cMsg.textContent = '';
      draw();
      return;
    }

    Cart.setCoupon(code);
    cMsg.className = 'couponMsg ok';
    cMsg.textContent = code + ' applied — ' + Cart.findOffer(code).value_label + ' your order.';
    draw();
  });

  /* ============================================================
     INPUT FORMATTING
     ============================================================ */
  var cardno = document.getElementById('cardno');
  cardno.addEventListener('input', function(){
    var v = cardno.value.replace(/\D/g,'').slice(0,19);
    cardno.value = v.replace(/(.{4})/g,'$1 ').trim();
  });

  var exp = document.getElementById('exp');
  exp.addEventListener('input', function(){
    var v = exp.value.replace(/\D/g,'').slice(0,4);
    exp.value = v.length > 2 ? v.slice(0,2) + ' / ' + v.slice(2) : v;
  });

  var cvc = document.getElementById('cvc');
  cvc.addEventListener('input', function(){
    cvc.value = cvc.value.replace(/\D/g,'').slice(0,4);
  });

  /* ============================================================
     VALIDATION  (a fast first pass; the server decides)
     ============================================================ */
  function luhn(num){
    var s = 0, alt = false;
    for(var i = num.length - 1; i >= 0; i--){
      var d = parseInt(num[i], 10);
      if(alt){ d *= 2; if(d > 9) d -= 9; }
      s += d;
      alt = !alt;
    }
    return s % 10 === 0;
  }

  function checkOne(el){
    var v = el.value.trim();
    var ok = !!v;

    if(ok && el.hasAttribute('data-email')) ok = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v);

    /* Same shape the server enforces: five digits, or ZIP+4. */
    if(ok && el.hasAttribute('data-zip')) ok = /^\d{5}(-\d{4})?$/.test(v);

    if(ok && el.hasAttribute('data-phone')) ok = /^[0-9+()\-.\s]{7,32}$/.test(v);

    if(ok && el.hasAttribute('data-card')){
      var digits = v.replace(/\D/g,'');
      ok = digits.length >= 13 && digits.length <= 19 && luhn(digits);
    }

    if(ok && el.hasAttribute('data-exp')){
      var m = v.replace(/\D/g,'');
      if(m.length !== 4){ ok = false; }
      else {
        var mm = parseInt(m.slice(0,2),10), yy = parseInt(m.slice(2),10);
        var now = new Date();
        var thisYY = now.getFullYear() % 100, thisMM = now.getMonth() + 1;
        ok = mm >= 1 && mm <= 12 && (yy > thisYY || (yy === thisYY && mm >= thisMM));
      }
    }

    /* Amex prints four digits, everyone else three. */
    if(ok && el.hasAttribute('data-cvc')){
      var pan = cardno.value.replace(/\D/g,'');
      ok = v.length === (/^3[47]/.test(pan) ? 4 : 3);
    }

    setFieldError(el, null, !ok);
    return ok;
  }

  /* Marks a field, and replaces its message when the server sent a specific
     one - "That card has expired" beats the generic line in the markup. */
  function setFieldError(el, message, bad){
    var field = el.closest('.field');
    el.classList.toggle('bad', !!bad);
    if(field) field.classList.toggle('is-bad', !!bad);

    if(message && field){
      var err = field.querySelector('.err');
      if(err){
        if(!err.dataset.original) err.dataset.original = err.textContent;
        err.textContent = message;
      }
    } else if(!bad && field){
      var reset = field.querySelector('.err');
      if(reset && reset.dataset.original) reset.textContent = reset.dataset.original;
    }
  }

  function clearErrors(){
    document.querySelectorAll('.field.is-bad').forEach(function(field){
      field.classList.remove('is-bad');
      var input = field.querySelector('input, select, textarea');
      if(input) input.classList.remove('bad');
      var err = field.querySelector('.err');
      if(err && err.dataset.original) err.textContent = err.dataset.original;
    });
    formErr.className = 'formErr';
    formErr.textContent = '';
  }

  /* clear the error as soon as they start fixing it */
  document.querySelectorAll('[data-req]').forEach(function(el){
    el.addEventListener('input', function(){
      if(el.classList.contains('bad')) checkOne(el);
    });
    el.addEventListener('change', function(){
      if(el.classList.contains('bad')) checkOne(el);
    });
    el.addEventListener('blur', function(){
      if(el.value.trim()) checkOne(el);
    });
  });

  /* ============================================================
     SUBMIT
     ============================================================ */
  var placeBtn = document.getElementById('place');
  var stickyBtn = document.getElementById('sPlace');
  var busy = false;

  function field(name){ return document.querySelector('[name="' + name + '"]'); }

  function payload(){
    return {
      items: Cart.payloadItems(),
      shipping_method: Cart.shipMethodId(),
      coupon_code: Cart.coupon(),

      first_name: field('first_name').value.trim(),
      last_name: field('last_name').value.trim(),
      email: field('email').value.trim(),
      phone: field('phone').value.trim(),
      street: field('street').value.trim(),
      city: field('city').value.trim(),
      state: field('state').value,
      postal_code: field('postal_code').value.trim(),
      country: field('country').value,
      notes: field('notes').value.trim() || null,

      card_holder: field('card_holder').value.trim(),
      card_number: field('card_number').value.replace(/\s/g, ''),
      card_expiry: field('card_expiry').value,
      card_cvc: field('card_cvc').value
    };
  }

  function setBusy(on, label){
    busy = on;
    [placeBtn, stickyBtn].forEach(function(b){ if(b) b.disabled = on; });
    placeBtn.innerHTML = on
      ? (label || 'Placing your order…')
      : '<svg><use href="#i-lock"/></svg>Place order securely';
  }

  function showFormError(message){
    formErr.className = 'formErr is-on';
    formErr.textContent = message;
    formErr.scrollIntoView({behavior: reduce ? 'auto' : 'smooth', block: 'center'});
  }

  /* Paints whatever the server refused. Keys are the request's own field
     names, which is why every input carries a matching name attribute. */
  function paintServerErrors(errors){
    var first = null;

    Object.keys(errors).forEach(function(key){
      /* items.0.quantity and friends belong to a cart line, not to an input
         on this form, so they are reported at form level. */
      var el = field(key);
      if(!el) return;

      setFieldError(el, [].concat(errors[key])[0], true);
      if(!first) first = el;
    });

    if(first){
      first.scrollIntoView({behavior: reduce ? 'auto' : 'smooth', block: 'center'});
      first.focus({preventScroll: true});
      return true;
    }

    return false;
  }

  function csrf(){
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  function submit(){
    if(busy) return;

    clearErrors();

    var fields = Array.prototype.slice.call(document.querySelectorAll('[data-req]'));
    var bad = fields.filter(function(el){ return !checkOne(el); });

    if(bad.length){
      bad[0].scrollIntoView({behavior: reduce ? 'auto' : 'smooth', block:'center'});
      bad[0].focus({preventScroll:true});
      return;
    }

    setBusy(true);

    fetch(SHOP.endpoints.place_order, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrf()
      },
      body: JSON.stringify(payload())
    })
      .then(function(response){
        return response.json()
          .catch(function(){ return null; })
          .then(function(body){ return { status: response.status, body: body }; });
      })
      .then(function(result){
        var body = result.body;

        if(body && body.success){
          placeBtn.innerHTML = '✓ Order placed';
          /* Cleared before the redirect: the order is written, and a cart
             left behind would let the same basket be ordered twice on a
             back button. */
          Cart.clear();
          window.location.href = body.data.redirect;
          return;
        }

        setBusy(false);

        if(!body){
          showFormError('We could not reach the store. Check your connection and try again.');
          return;
        }

        if(body.errors && paintServerErrors(body.errors)) return;

        showFormError(body.message || 'We could not place your order. Please try again.');
      })
      .catch(function(){
        setBusy(false);
        showFormError('We could not reach the store. Check your connection and try again.');
      });
  }

  document.getElementById('checkoutForm').addEventListener('submit', function(e){
    e.preventDefault();
    submit();
  });
  stickyBtn.addEventListener('click', submit);

  /* ---- sticky bar: show once the summary button scrolls past ---- */
  var sticky = document.getElementById('sticky');
  if('IntersectionObserver' in window){
    new IntersectionObserver(function(en){
      sticky.classList.toggle('is-on', !en[0].isIntersecting);
    }, {rootMargin:'0px 0px -80px 0px'}).observe(placeBtn);
  }
})();
