/* NoRx Dose - the conversation screen in the panel.

   Three jobs: draw messages that arrive while the screen is open, send a
   reply, and flip the conversation between the assistant and a person.

   Polling rather than a socket. A support screen that is open for ten minutes
   makes about seventy-five requests in that time, which is nothing next to
   running a websocket server for it - and it degrades to "reload the page"
   instead of to silence. It stops while the tab is hidden, because nobody is
   reading it then. */
(function () {
  'use strict';

  var thread = document.querySelector('[data-chat-thread]');
  if (!thread) return;

  var log = thread.querySelector('[data-chat-log]');
  var form = thread.querySelector('[data-chat-form]');
  var input = thread.querySelector('[data-chat-input]');
  var send = thread.querySelector('[data-chat-send]');
  var status = thread.querySelector('[data-chat-status]');
  var error = thread.querySelector('[data-chat-error]');
  var toggle = document.querySelector('[data-mode-toggle]');
  var modeTitle = document.querySelector('[data-mode-title]');
  var counter = document.querySelector('[data-chat-count]');

  var lastUuid = thread.dataset.lastUuid || '';
  var interval = Math.max(3, parseInt(thread.dataset.pollSeconds || '8', 10)) * 1000;
  var busy = false;
  var timer = null;

  var LABELS = { guest: 'Visitor', manager: 'Support team', ai: 'Assistant' };

  function node(tag, className, text) {
    var el = document.createElement(tag);
    if (className) el.className = className;
    if (text !== undefined && text !== null) el.textContent = String(text);
    return el;
  }

  function say(problem) {
    error.textContent = problem || '';
    error.hidden = !problem;
  }

  function working(value, label) {
    busy = value;
    if (send) send.disabled = value;
    if (input) input.disabled = value;
    status.textContent = label || '';
    status.hidden = !label;
  }

  /* ---------------------------------------------------------------- render */

  function draw(message) {
    if (log.querySelector('[data-message-id="' + window.CSS.escape(message.id) + '"]')) return;

    var empty = log.querySelector('.chat-empty');
    if (empty) empty.remove();

    var author = message.author || 'ai';
    var article = node('article', 'chat-msg chat-msg--' + author);
    article.dataset.messageId = message.id;

    var head = node('header', 'chat-msg__head');
    head.appendChild(node('span', 'chat-msg__who', message.author_name || LABELS[author] || 'Assistant'));

    var when = node('time', '', formatTime(message.created_at));
    when.dateTime = message.created_at || '';
    head.appendChild(when);
    article.appendChild(head);

    // textContent throughout: a visitor's message is untrusted text and this
    // screen is the one place a member of staff reads it.
    article.appendChild(node('div', 'chat-msg__body', message.text));

    if (message.products && message.products.length) {
      var list = node('ul', 'chat-msg__products');
      message.products.forEach(function (product) {
        var item = node('li', '', product.name || 'Product');
        item.appendChild(node('span', '', product.price_formatted || ''));
        list.appendChild(item);
      });
      article.appendChild(list);
    }

    var facts = [message.intent ? String(message.intent).replace(/_/g, ' ') : null, message.ai_model].filter(Boolean);

    if (facts.length) {
      var meta = node('footer', 'chat-msg__meta');
      facts.forEach(function (fact) { meta.appendChild(node('span', '', fact)); });
      article.appendChild(meta);
    }

    log.appendChild(article);
    log.scrollTop = log.scrollHeight;

    if (counter) counter.textContent = String(log.querySelectorAll('.chat-msg').length);
  }

  function formatTime(iso) {
    var date = iso ? new Date(iso) : new Date();
    if (isNaN(date.getTime())) return '';
    return date.toLocaleString([], { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
  }

  function drawAll(messages) {
    (messages || []).forEach(function (message) {
      draw(message);
      lastUuid = message.id;
    });
  }

  function setMode(mode) {
    var manual = mode === 'manual';
    if (toggle) toggle.checked = manual;
    if (modeTitle) modeTitle.textContent = manual ? 'You are answering' : 'The assistant is answering';
  }

  /* ----------------------------------------------------------------- fetch */

  function poll() {
    if (busy || document.hidden) return Promise.resolve();

    var url = thread.dataset.messagesUrl + (lastUuid ? '?after=' + encodeURIComponent(lastUuid) : '');

    return window.AurumApi.request(url).then(function (result) {
      var problem = window.AurumApi.transportProblem(result);
      if (problem) { say(problem); return; }
      if (!result.ok || !result.body || !result.body.success) return;

      say('');
      drawAll(result.body.data.messages);
      setMode(result.body.data.reply_mode);
    }).catch(function () {
      // A dropped poll is not worth a message; the next one will say so if it
      // is a real outage.
    });
  }

  function start() {
    if (timer) return;
    timer = window.setInterval(poll, interval);
  }

  document.addEventListener('visibilitychange', function () {
    if (document.hidden) { window.clearInterval(timer); timer = null; return; }
    poll();
    start();
  });

  /* ----------------------------------------------------------------- write */

  if (form) {
    form.addEventListener('submit', function (event) {
      event.preventDefault();

      var text = (input.value || '').trim();
      if (busy || !text) return;

      working(true, 'Sending…');
      say('');

      window.AurumApi.request(thread.dataset.replyUrl, {
        method: 'POST',
        body: { message: text }
      }).then(function (result) {
        var problem = window.AurumApi.transportProblem(result);
        if (problem) { say(problem); return; }

        if (!result.ok || !result.body || !result.body.success) {
          var errors = (result.body && result.body.errors) || {};
          say((errors.message && errors.message.join(' '))
            || (result.body && result.body.message)
            || 'Could not send that reply.');
          return;
        }

        input.value = '';
        draw(result.body.data.message);
        lastUuid = result.body.data.message.id;
        setMode(result.body.data.reply_mode);
        window.AurumToast.success('Reply sent.');
      }).catch(function () {
        say('Could not reach the server. Check your connection and try again.');
      }).finally(function () {
        working(false, '');
        input.focus();
      });
    });
  }

  if (toggle) {
    toggle.addEventListener('change', function () {
      var wanted = toggle.checked ? 'manual' : 'ai';

      working(true, 'Switching…');
      say('');

      window.AurumApi.request(thread.dataset.modeUrl, {
        method: 'PATCH',
        body: { reply_mode: wanted, after: lastUuid || null }
      }).then(function (result) {
        var problem = window.AurumApi.transportProblem(result);

        if (problem || !result.ok || !result.body || !result.body.success) {
          // Put the switch back where it was: it must always show what the
          // server actually thinks.
          toggle.checked = !toggle.checked;
          say(problem || (result.body && result.body.message) || 'Could not switch this conversation.');
          return;
        }

        drawAll(result.body.data.messages);
        setMode(result.body.data.reply_mode);
        window.AurumToast.success(result.body.message);
      }).catch(function () {
        toggle.checked = !toggle.checked;
        say('Could not reach the server. Check your connection and try again.');
      }).finally(function () {
        working(false, '');
      });
    });
  }

  log.scrollTop = log.scrollHeight;
  start();
}());
