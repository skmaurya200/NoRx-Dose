/* Guest support widget.

   Every URL and every piece of copy comes from Laravel. Nothing here builds
   markup from a server string: messages, product rows and error text are all
   written with textContent, so a visitor's own words - or anything a model
   echoed back - can never be parsed as HTML.

   Two things are new next to a plain request/response chat. Product results
   are drawn as a table, because once there are five of them the useful
   question is "which is cheapest", and a column answers that at a glance. And
   when a member of staff takes the conversation over there is no reply to the
   request at all, so the widget polls the history endpoint for one. */
(function () {
  'use strict';

  var root = document.getElementById('talkToUs');
  if (!root) return;

  var panel = document.getElementById('talkPanel');
  var opener = document.getElementById('talkOpen');
  var dot = document.getElementById('talkDot');
  var presence = document.getElementById('talkPresence');
  var input = document.getElementById('talkInput');
  var send = document.getElementById('talkSend');
  var messages = document.getElementById('talkMessages');
  var scroller = document.getElementById('talkScroll');
  var error = document.getElementById('talkError');
  var typing = document.getElementById('talkTyping');
  var older = document.getElementById('talkOlder');
  var retry = document.getElementById('talkRetry');
  var chips = document.getElementById('talkChips');

  var session = null;
  var busy = false;
  var before = null;
  var lastId = null;
  var rendered = new Map();
  var retryMessage = null;
  var pollTimer = null;
  var pollInterval = Math.max(3, parseInt(root.dataset.pollSeconds || '8', 10)) * 1000;

  var SPEAKERS = { user: 'You', manager: 'Support team', ai: 'Talk to Us' };

  function node(tag, className, text) {
    var el = document.createElement(tag);
    if (className) el.className = className;
    if (text !== undefined && text !== null) el.textContent = String(text);
    return el;
  }

  function url(template, messageId) {
    return template.replace('__SESSION__', encodeURIComponent(session))
      .replace('__MESSAGE__', encodeURIComponent(messageId || ''));
  }

  /**
   * Anything that ends up in an href or a src is parsed first and rejected
   * unless it is http(s) - and, for a link into this shop, unless it is this
   * origin. A javascript: url in a product snapshot would otherwise be one
   * click from running.
   */
  function safeUrl(value, sameOrigin) {
    try {
      var parsed = new URL(value, location.origin);
      if (!['http:', 'https:'].includes(parsed.protocol)) return null;
      if (sameOrigin && parsed.origin !== location.origin) return null;
      return parsed.href;
    } catch (_) { return null; }
  }

  function setBusy(value) {
    busy = value;
    typing.hidden = !value;
    input.disabled = value || !session;

    root.querySelectorAll('button:not(#talkOpen):not(#talkClose)').forEach(function (button) {
      button.disabled = value;
    });

    send.disabled = value || !session || !input.value.trim();
    messages.setAttribute('aria-busy', value ? 'true' : 'false');

    if (value) bottom();
  }

  function showError(problem) {
    error.textContent = problem.message || 'Sorry, something went wrong. Please try again.';
    error.hidden = false;

    if (problem.status === 419) {
      error.textContent = 'Your browser session expired. Refresh this page to reconnect.';
    }

    if (problem.status === 404) {
      session = null;
      retry.hidden = false;
      error.textContent = 'This chat session is no longer available. Reconnect to start a new conversation.';
    }
  }

  function bottom() {
    scroller.scrollTop = scroller.scrollHeight;
  }

  async function request(endpoint, method, data) {
    var controller = new AbortController();
    var timeout = setTimeout(function () { controller.abort(); }, 85000);

    try {
      var response = await fetch(endpoint, {
        method: method || 'GET', credentials: 'same-origin', signal: controller.signal,
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: data ? JSON.stringify(data) : undefined
      });

      var json;
      try { json = await response.json(); } catch (_) { json = {}; }

      if (!response.ok || !json.success) {
        var problem = new Error(json.message || 'Sorry, something went wrong. Please try again.');
        problem.status = response.status;
        problem.fields = json.errors || {};

        if (response.status === 429) {
          problem.message = 'Please wait ' + (response.headers.get('Retry-After') || 'a few') + ' seconds before trying again.';
        }

        throw problem;
      }

      return json.data;
    } catch (problem) {
      if (problem.name === 'AbortError') {
        throw new Error('The reply is taking longer than expected. Retry your message; it will not be saved twice.');
      }
      throw problem;
    } finally { clearTimeout(timeout); }
  }

  /* -------------------------------------------------------------- results */

  function resultsTable() {
    var box = node('div', 'talk__results');
    var wrap = node('div', 'talk__table-wrap');
    var table = node('table', 'talk__table');

    var head = document.createElement('thead');
    var headRow = document.createElement('tr');
    ['Product', 'Price', 'Availability', ''].forEach(function (label) {
      headRow.appendChild(node('th', '', label));
    });
    head.appendChild(headRow);

    table.append(head, document.createElement('tbody'));
    wrap.appendChild(table);
    box.appendChild(wrap);

    return box;
  }

  function resultRows(box, products) {
    var body = box.querySelector('tbody');

    products.forEach(function (product) {
      var link = safeUrl(product.url, true);

      // No link means nothing the visitor could act on, and a duplicate is a
      // page boundary landing on a product already shown.
      if (!link || body.querySelector('tr[data-product-url="' + window.CSS.escape(link) + '"]')) return;

      var row = document.createElement('tr');
      row.dataset.productUrl = link;

      var first = document.createElement('td');
      var cell = node('div', 'talk__cell-product');

      var imageUrl = safeUrl(product.image, false);
      if (imageUrl) {
        var image = node('img');
        image.src = imageUrl;
        image.alt = '';
        image.loading = 'lazy';
        cell.appendChild(image);
      }

      var name = node('div', 'talk__cell-name', product.name);
      if (product.category) name.appendChild(node('span', 'talk__cell-sub', product.category));
      cell.appendChild(name);
      first.appendChild(cell);

      var price = node('td', 'talk__cell-price', product.price_formatted);
      if (product.compare_at_formatted) {
        price.appendChild(node('del', '', product.compare_at_formatted));
      }

      var view = node('a', 'talk__view', 'View');
      view.href = link;

      var action = document.createElement('td');
      action.appendChild(view);

      row.append(first, price, node('td', 'talk__cell-stock', product.availability), action);
      body.appendChild(row);
    });
  }

  /**
   * A numbered procedure, printed as the shop wrote it.
   *
   * These arrive already written - no model reworded them - so they are drawn
   * verbatim rather than being parsed out of a paragraph.
   */
  function stepList(article, message) {
    if (!message.steps || !message.steps.length) return;

    var list = node('ol', 'talk__steps');

    message.steps.forEach(function (step) {
      list.appendChild(node('li', '', step));
    });

    article.appendChild(list);

    var link = safeUrl(message.link, true);

    if (link) {
      var go = node('a', 'talk__go', 'Open the page');
      go.href = link;
      article.appendChild(go);
    }
  }

  /**
   * One-tap follow-ups under an answer. Each one is sent as an ordinary
   * message, so nothing here is a second way into the API.
   */
  function followUps(article, message) {
    if (!message.suggestions || !message.suggestions.length) return;

    var row = node('div', 'talk__followups');

    message.suggestions.forEach(function (text) {
      var button = node('button', '', text);
      button.type = 'button';
      button.dataset.chip = text;
      row.appendChild(button);
    });

    row.addEventListener('click', function (event) {
      var button = event.target.closest('[data-chip]');
      if (!button || busy || !session) return;

      input.value = button.dataset.chip;
      send.disabled = false;
      document.getElementById('talkForm').requestSubmit();
    });

    article.appendChild(row);
  }

  function attachMore(article, box, message) {
    var existing = article.querySelector('[data-more]');
    if (existing) existing.remove();

    if (!message.pagination || !message.pagination.has_more) return;

    var more = node('button', 'talk__secondary', 'Show more products');
    more.type = 'button';
    more.dataset.more = 'true';
    article.appendChild(more);

    more.addEventListener('click', async function () {
      if (busy) return;
      setBusy(true);
      error.hidden = true;

      try {
        var data = await request(url(root.dataset.productsUrl, message.id) + '?page=' + (message.pagination.current_page + 1));
        resultRows(box, data.products);
        attachMore(article, box, data.message);
      } catch (problem) { showError(problem); }
      finally { setBusy(false); }
    });
  }

  /* -------------------------------------------------------------- render */

  function render(message, fragment) {
    if (!message || rendered.has(message.id)) return;

    var author = message.sender === 'user' ? 'user' : (message.author === 'manager' ? 'manager' : 'ai');
    var variant = message.type === 'system' ? 'system' : author;

    var article = node('article', 'talk__message talk__message--' + variant);
    article.dataset.messageId = message.id;

    /* No form: the assistant asks for an address or a number in words, and
       whatever is typed next is read for one. A four-field form to collect one
       answer is what most people closed. */
    if (message.requires_contact) article.classList.add('talk__message--asking');

    if (message.type !== 'system') {
      article.appendChild(node('span', 'talk__speaker', SPEAKERS[author] || SPEAKERS.ai));
    }

    article.appendChild(node('p', 'talk__bubble', message.text));

    if (message.products && message.products.length) {
      var box = resultsTable();
      resultRows(box, message.products);
      article.appendChild(box);
      attachMore(article, box, message);
    }

    stepList(article, message);

    followUps(article, message);

    rendered.set(message.id, article);
    lastId = message.id;
    (fragment || messages).appendChild(article);
  }

  function setPresence(mode) {
    if (!presence) return;

    var human = mode === 'manual';
    presence.classList.toggle('is-human', human);
    presence.lastChild.textContent = human
      ? ' Our support team is answering'
      : ' Store assistant · answers from our catalogue';
  }

  /* --------------------------------------------------------------- poll */

  /* Only runs while the panel is open and the tab is visible, and only asks
     for what has arrived since the last message the widget drew. */
  async function pollOnce() {
    if (busy || !session || document.hidden || !lastId) return;

    try {
      var data = await request(url(root.dataset.historyUrl) + '?after=' + encodeURIComponent(lastId));
      var fresh = 0;

      (data.messages || []).forEach(function (message) {
        if (rendered.has(message.id)) return;
        render(message);
        fresh++;
      });

      setPresence(data.reply_mode);

      if (fresh > 0) {
        bottom();
        if (panel.hidden && dot) dot.hidden = false;
      }
    } catch (_) {
      // A dropped poll is not worth telling the visitor about.
    }
  }

  function startPolling() {
    if (pollTimer) return;
    pollTimer = window.setInterval(pollOnce, pollInterval);
  }

  function stopPolling() {
    window.clearInterval(pollTimer);
    pollTimer = null;
  }

  document.addEventListener('visibilitychange', function () {
    if (document.hidden || panel.hidden) { stopPolling(); return; }
    pollOnce();
    startPolling();
  });

  /* ------------------------------------------------------------ lifecycle */

  async function connect() {
    if (busy) return;

    setBusy(true);
    error.hidden = true;
    retry.hidden = true;

    try {
      var data = await request(root.dataset.sessionUrl, 'POST', {});

      if (session !== data.session_uuid) { retryMessage = null; }
      session = data.session_uuid;

      var history = await request(url(root.dataset.historyUrl));

      // Redrawn from scratch on every open, so loaded pages reconcile with
      // what the server actually has.
      rendered.clear();
      messages.replaceChildren();
      lastId = null;

      history.messages.forEach(function (message) { render(message); });

      before = history.pagination.before;
      older.hidden = !history.pagination.has_more;
      if (chips) chips.hidden = history.messages.length > 0;

      setPresence(history.reply_mode);
      if (dot) dot.hidden = true;

      bottom();
      startPolling();
    } catch (problem) { showError(problem); retry.hidden = false; }
    finally { setBusy(false); if (!panel.hidden) input.focus(); }
  }

  function close() {
    panel.hidden = true;
    opener.setAttribute('aria-expanded', 'false');
    stopPolling();
    opener.focus();
  }

  opener.addEventListener('click', function () {
    if (!panel.hidden) { close(); return; }
    panel.hidden = false;
    opener.setAttribute('aria-expanded', 'true');
    connect();
  });

  document.getElementById('talkClose').addEventListener('click', close);
  panel.addEventListener('keydown', function (event) { if (event.key === 'Escape') close(); });
  retry.addEventListener('click', connect);

  input.addEventListener('input', function () {
    send.disabled = busy || !session || !input.value.trim();

    // Grows with the message and stops at the height the stylesheet caps.
    input.style.height = 'auto';
    input.style.height = Math.min(input.scrollHeight, 120) + 'px';
  });

  input.addEventListener('keydown', function (event) {
    if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
      event.preventDefault();
      if (!send.disabled) document.getElementById('talkForm').requestSubmit();
    }
  });

  if (chips) {
    chips.addEventListener('click', function (event) {
      var button = event.target.closest('[data-chip]');
      if (!button || busy || !session) return;

      input.value = button.dataset.chip;
      send.disabled = false;
      document.getElementById('talkForm').requestSubmit();
    });
  }

  document.getElementById('talkForm').addEventListener('submit', async function (event) {
    event.preventDefault();

    var text = input.value.trim();
    if (busy || !session || !text) return;

    // The same request_uuid on a retry, so a timeout that actually succeeded
    // replays its answer instead of asking twice.
    if (!retryMessage || retryMessage.message !== text) {
      retryMessage = { session_uuid: session, request_uuid: crypto.randomUUID(), message: text };
    }

    setBusy(true);
    error.hidden = true;
    if (chips) chips.hidden = true;

    try {
      var data = await request(root.dataset.messageUrl, 'POST', retryMessage);

      render(data.user_message);

      if (data.message) {
        render(data.message);
      } else if (data.awaiting_human) {
        // A person is handling this conversation; the answer arrives through
        // the poll rather than in this response.
        setPresence('manual');
      }

      input.value = '';
      input.style.height = 'auto';
      retryMessage = null;
      bottom();
      startPolling();
    } catch (problem) { showError(problem); }
    finally { setBusy(false); input.focus(); }
  });

  older.addEventListener('click', async function () {
    if (busy || !before) return;

    setBusy(true);
    error.hidden = true;
    var previousHeight = scroller.scrollHeight;

    try {
      var data = await request(url(root.dataset.historyUrl) + '?before=' + encodeURIComponent(before));
      var fragment = document.createDocumentFragment();
      var newest = lastId;

      data.messages.forEach(function (message) { render(message, fragment); });

      // render() tracks the newest message for polling; prepending older ones
      // must not move that cursor backwards.
      lastId = newest;

      messages.prepend(fragment);
      before = data.pagination.before;
      older.hidden = !data.pagination.has_more;
      scroller.scrollTop += scroller.scrollHeight - previousHeight;
    } catch (problem) { showError(problem); }
    finally { setBusy(false); }
  });
}());
