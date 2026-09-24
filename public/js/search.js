/* NoRx Dose - the header search box

   Loaded on every page, right after common.js. The box is a real <form> that
   posts to /search, so it works with this file absent or blocked; what follows
   is the suggestion panel on top of that.

   The term is sealed into the results URL with the application key, which this
   file does not have. Every link to a search therefore arrives from the server
   ready-made - see the note by draw() below.

   Nothing here decides what matches. The endpoint returns live products,
   active categories and published posts, and this file draws them. */
(function () {
  'use strict';

  var form = document.getElementById('searchBox');
  var input = document.getElementById('q');
  var panel = document.getElementById('searchPanel');

  if (!form || !input || !panel) return;

  var SHOP = window.AURUM_SHOP || {};
  var ENDPOINT = (SHOP.endpoints || {}).search;
  var MIN = 2;

  if (!ENDPOINT) return;

  var timer = null;
  var at = -1;
  var items = [];
  var lastTerm = null;
  var cache = {};

  /* Everything the panel prints came from a database row somebody typed, so
     it is escaped rather than dropped into innerHTML as markup. */
  function esc(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  /* The matched part of a name, marked. Escaped first and the term escaped
     for the regex separately, so a search for "c++" cannot break the pattern
     or the markup. */
  function mark(text, term) {
    var safe = esc(text);

    if (!term) return safe;

    var pattern = esc(term).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

    return safe.replace(new RegExp('(' + pattern + ')', 'ig'), '<mark>$1</mark>');
  }

  /* There is deliberately no resultsUrl() here.

     The term is sealed into the URL with the application key, which the page
     does not have and must not. So every link to a search comes back from the
     endpoint already built - data.results_url for the current term, and a url
     on each popular entry - and pressing enter posts the form instead. */

  /* ------------------------------------------------------------- drawing */

  function draw(data) {
    var term = data.term || '';
    var html = '';

    if (data.products.length) {
      html += '<p class="sugg__label">Products</p>';

      html += data.products.map(function (product) {
        return '<a class="sugg__item" role="option" href="' + esc(product.url) + '">' +
          '<img class="sugg__thumb" src="' + esc(product.image) + '" alt="" loading="lazy">' +
          '<span class="sugg__txt">' +
            '<b>' + mark(product.name, term) + '</b>' +
            (product.category ? '<span>' + esc(product.category) + '</span>' : '') +
          '</span>' +
          '<span class="sugg__price">' + esc(product.price) + '</span>' +
        '</a>';
      }).join('');
    }

    if (data.categories.length) {
      html += '<p class="sugg__label">Categories</p>';
      html += data.categories.map(function (category) {
        return '<a class="sugg__item" role="option" href="' + esc(category.url) + '">' +
          '<span class="sugg__txt"><b>' + mark(category.name, term) + '</b></span>' +
        '</a>';
      }).join('');
    }

    if (data.posts.length) {
      html += '<p class="sugg__label">Journal</p>';
      html += data.posts.map(function (post) {
        return '<a class="sugg__item" role="option" href="' + esc(post.url) + '">' +
          '<span class="sugg__txt"><b>' + mark(post.title, term) + '</b></span>' +
        '</a>';
      }).join('');
    }

    /* Nothing typed yet: what other people search for. Only terms that found
       something, so a suggestion never leads to an empty page. */
    if (!html && data.popular && data.popular.length) {
      html = '<p class="sugg__label">Popular searches</p><div class="sugg__chips">' +
        data.popular.map(function (popular) {
          return '<a href="' + esc(popular.url) + '">' + esc(popular.term) + '</a>';
        }).join('') +
      '</div>';
    }

    if (!html) {
      html = '<p class="sugg__empty">Nothing matched &ldquo;' + esc(term) + '&rdquo;.<br>' +
             'Try a shorter word, or a brand name.</p>';
    }

    // Only worth offering when there is more behind it than is on screen.
    if (term && data.results_url && data.total > data.products.length) {
      html += '<a class="sugg__all" href="' + esc(data.results_url) + '">' +
              'See all ' + data.total + ' results</a>';
    }

    panel.innerHTML = html;
    open();
  }

  function open() {
    panel.hidden = false;
    input.setAttribute('aria-expanded', 'true');

    items = Array.prototype.slice.call(panel.querySelectorAll('a'));
    at = -1;
  }

  function close() {
    panel.hidden = true;
    input.setAttribute('aria-expanded', 'false');
    items = [];
    at = -1;
  }

  function highlight(next) {
    if (!items.length) return;

    // Wraps in both directions, so holding Down never dead-ends.
    at = (next + items.length) % items.length;

    items.forEach(function (item, i) {
      item.classList.toggle('is-on', i === at);
    });

    items[at].scrollIntoView({ block: 'nearest' });
  }

  /* ------------------------------------------------------------ fetching */

  function load(term) {
    if (term === lastTerm) {
      if (cache[term]) draw(cache[term]);

      return;
    }

    lastTerm = term;

    if (cache[term]) {
      draw(cache[term]);

      return;
    }

    fetch(ENDPOINT + '?q=' + encodeURIComponent(term), {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (response) { return response.json(); })
      .then(function (body) {
        if (!body || !body.success) return;

        cache[term] = body.data;

        // The box may have moved on while this was in flight - a slow reply
        // must not overwrite the answer to a newer keystroke.
        if (currentTerm() === term) draw(body.data);
      })
      .catch(function () {
        /* Offline or blocked. The panel stays as it was; pressing enter still
           submits the form and the results page answers properly. */
      });
  }

  function currentTerm() {
    return input.value.trim();
  }

  function schedule() {
    clearTimeout(timer);

    var term = currentTerm();

    // Under two characters matches most of the catalogue. The empty box is
    // still asked, because that is what returns the popular searches.
    if (term.length && term.length < MIN) {
      close();

      return;
    }

    timer = setTimeout(function () { load(term); }, 180);
  }

  /* -------------------------------------------------------------- events */

  input.addEventListener('input', schedule);

  input.addEventListener('focus', function () {
    if (panel.innerHTML.trim() && currentTerm() === lastTerm) {
      open();

      return;
    }

    schedule();
  });

  input.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      close();

      return;
    }

    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      if (panel.hidden || !items.length) return;

      event.preventDefault();
      highlight(event.key === 'ArrowDown' ? at + 1 : at - 1);

      return;
    }

    if (event.key === 'Enter' && at > -1 && items[at]) {
      // A highlighted suggestion goes straight there rather than to the
      // results page, which is what the arrow keys were for.
      event.preventDefault();
      window.location.assign(items[at].getAttribute('href'));
    }
  });

  form.addEventListener('submit', function (event) {
    if (currentTerm() === '') {
      // An empty search would land on a results page with nothing to say.
      event.preventDefault();
      input.focus();
    }
  });

  // A click inside the panel is a navigation; anywhere else closes it.
  document.addEventListener('click', function (event) {
    if (!form.contains(event.target)) close();
  });
})();
