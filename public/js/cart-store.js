/* NoRx Dose - shared cart store
   One cart for the whole site, kept in localStorage so it survives navigation.
   Loaded on every page (before the page scripts) via baselayout.blade.php.

   What lives here and what does not:

   - The contents are the browser's: which product, which size, how many.
   - Shipping prices and discount rules are NOT. They arrive on
     window.AURUM_SHOP, rendered by the server from config/shop.php and the
     coupons table, so the figures the page shows are the ones the order will
     be charged. Every total drawn here is a preview; App\Services\Commerce\
     CheckoutService recomputes all of it from the catalogue when the order is
     placed, and its answer is the one that counts. */
(function (window, document) {
  'use strict';

  /* v2: v1 carried demo rows with no product id, which the server cannot
     price. Bumping the key retires those carts instead of failing at
     checkout with an item nobody can explain. */
  var KEY = 'aurum.cart.v2';

  var SHOP = window.AURUM_SHOP || {};
  var SHIPPING = SHOP.shipping || [];
  var FREE_SHIP_OVER = SHOP.free_shipping_over === undefined ? null : SHOP.free_shipping_over;
  var FREE_SHIP_ID = SHOP.free_shipping_method || null;
  var CUR = SHOP.currency_symbol || '$';
  var MAX_QTY = SHOP.max_line_quantity || 99;
  var FALLBACK_IMAGE = SHOP.default_image || '/images/defaults/product.svg';

  /* Public coupons, as the page was rendered with them. Cart and checkout
     each hand their own list over on load; until then there are none. */
  var OFFERS = [];

  var listeners = [];
  var state = read();

  function read() {
    try {
      var raw = window.localStorage.getItem(KEY);
      if (raw) {
        var saved = JSON.parse(raw);
        if (saved && Array.isArray(saved.items)) {
          return {
            items: saved.items.filter(function (it) { return it && it.productId; }),
            shipAt: saved.shipAt || 0,
            coupon: saved.coupon || null
          };
        }
      }
    } catch (e) { /* private mode, blocked storage - start empty */ }
    return { items: [], shipAt: 0, coupon: null };
  }

  function write() {
    try {
      window.localStorage.setItem(KEY, JSON.stringify(state));
    } catch (e) { /* nothing we can do - the cart still works for this page view */ }
    paintBadge();
    listeners.forEach(function (fn) { fn(); });
  }

  function paintBadge() {
    var badge = document.getElementById('cartN');
    if (badge) badge.textContent = units();
  }

  function units() {
    return state.items.reduce(function (a, b) { return a + b.qty; }, 0);
  }

  function subtotal() {
    return state.items.reduce(function (a, b) { return a + b.price * b.qty; }, 0);
  }

  function money(n) {
    return CUR + Number(n || 0).toFixed(2);
  }

  /* ----------------------------------------------------------- coupons ---- */

  function findOffer(code) {
    if (!code) return null;
    var wanted = String(code).toUpperCase();
    for (var i = 0; i < OFFERS.length; i++) {
      if (String(OFFERS[i].code).toUpperCase() === wanted) return OFFERS[i];
    }
    return null;
  }

  /* The same arithmetic as Coupon::discountFor(), so the preview and the
     receipt agree. Kept deliberately small: any rule more involved than this
     belongs on the server only, with the page asking for a quote. */
  function discountOf(offer, sub) {
    if (!offer || sub <= 0 || sub < offer.min_order_amount) return 0;

    var off = offer.type === 'percent' ? sub * (offer.value / 100) : offer.value;

    if (offer.max_discount_amount !== null && offer.max_discount_amount !== undefined) {
      off = Math.min(off, offer.max_discount_amount);
    }

    return Math.round(Math.min(off, sub) * 100) / 100;
  }

  function activeOffer() {
    var offer = findOffer(state.coupon);

    /* A code that no longer clears its minimum - the customer removed an item
       after applying it - simply stops discounting. It stays selected so the
       card still reads as chosen and the cart can say what is missing. */
    return offer;
  }

  /* ---------------------------------------------------------- shipping ---- */

  function isFree(option, netSub) {
    return FREE_SHIP_OVER !== null && netSub >= FREE_SHIP_OVER && option.id === FREE_SHIP_ID;
  }

  function shipPriceLabel(i) {
    var option = SHIPPING[i];
    if (!option) return '';
    var sub = subtotal();
    return isFree(option, sub - discountOf(activeOffer(), sub)) ? 'Free' : money(option.cost);
  }

  /* One place that decides discount, shipping and total, so the cart page and
     the checkout page can never disagree about what the order costs. */
  function totals() {
    var sub = subtotal();
    var offer = activeOffer();
    var off = discountOf(offer, sub);

    var option = SHIPPING[state.shipAt] || SHIPPING[0] || { id: '', name: '', cost: 0 };
    var freeShip = isFree(option, sub - off);
    /* an empty cart is never charged for delivery */
    var shipCost = (!state.items.length || freeShip) ? 0 : option.cost;

    return {
      units: units(),
      sub: sub,
      off: off,
      coupon: offer,
      option: option,
      freeShip: freeShip,
      shipCost: shipCost,
      total: Math.max(0, sub - off + shipCost)
    };
  }

  /* What the server needs to price this cart: no names, no prices, just the
     choices. Anything else in here would be ignored on the way in. */
  function payloadItems() {
    return state.items.map(function (it) {
      return {
        product_id: it.productId,
        pack_label: it.packLabel || null,
        quantity: it.qty
      };
    });
  }

  var Cart = {
    SHIPPING: SHIPPING,
    FREE_SHIP_OVER: FREE_SHIP_OVER,
    CURRENCY: CUR,
    shipPriceLabel: shipPriceLabel,
    money: money,

    items: function () { return state.items; },
    units: units,
    subtotal: subtotal,
    totals: totals,
    payloadItems: payloadItems,

    /* ---- offers ---- */

    /** Called once per page with the coupons the server rendered. */
    setOffers: function (offers) {
      OFFERS = Array.isArray(offers) ? offers : [];

      /* A code selected on an earlier visit that has since been withdrawn is
         dropped rather than left showing a discount that will not be honoured. */
      if (state.coupon && !findOffer(state.coupon)) {
        state.coupon = null;
        write();
      }
    },

    offers: function () { return OFFERS; },
    findOffer: findOffer,
    discountOf: discountOf,

    /* ---- contents ---- */

    add: function (item, qty) {
      qty = Math.max(1, parseInt(qty, 10) || 1);

      var found = state.items.find(function (x) { return x.id === item.id; });

      if (found) {
        found.qty = Math.min(MAX_QTY, found.qty + qty);
      } else {
        state.items.push({
          id: item.id,
          /* The two fields the server prices from. Everything else on the row
             is for drawing the cart. */
          productId: item.productId,
          packLabel: item.packLabel || null,
          name: item.name,
          badge: item.badge || '',
          image: item.image || FALLBACK_IMAGE,
          price: item.price,
          qty: Math.min(MAX_QTY, qty)
        });
      }

      write();
    },

    /* Buy now: this item and nothing else. Everything already in the basket
       is dropped, which is the difference between "buy this" and "add this". */
    buyNow: function (item, qty) {
      state.items = [];
      state.coupon = null;
      Cart.add(item, qty);
    },

    /* A line's picture, with the default for anything saved before images
       were carried on the line. */
    imageFor: function (item) {
      return (item && item.image) || FALLBACK_IMAGE;
    },

    setQty: function (id, qty) {
      var it = state.items.find(function (x) { return x.id === id; });
      if (!it) return;
      it.qty = Math.min(MAX_QTY, Math.max(1, qty));
      write();
    },

    remove: function (id) {
      state.items = state.items.filter(function (x) { return x.id !== id; });
      write();
    },

    clear: function () {
      state.items = [];
      state.coupon = null;
      write();
    },

    /* ---- shipping ---- */

    shipAt: function () { return state.shipAt; },
    setShip: function (i) {
      state.shipAt = Math.max(0, Math.min(SHIPPING.length - 1, i));
      write();
    },
    shipMethodId: function () {
      var option = SHIPPING[state.shipAt] || SHIPPING[0];
      return option ? option.id : null;
    },

    /* ---- coupon ---- */

    coupon: function () { return state.coupon; },

    setCoupon: function (code) {
      state.coupon = code ? String(code).toUpperCase() : null;
      write();
    },

    onChange: function (fn) { listeners.push(fn); }
  };

  window.Cart = Cart;
  paintBadge();
})(window, document);
