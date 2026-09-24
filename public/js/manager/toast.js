/* NoRx Dose - manager toasts
   Bottom-right notifications. Exposed as window.AurumToast so every other panel
   script reports through one place instead of calling window.alert(). */
(function () {
  'use strict';

  var STACK_ID = 'toastStack';
  var DEFAULT_MS = 4500;

  var ICONS = {
    success: 'bi-check-circle-fill',
    error: 'bi-exclamation-triangle-fill',
    info: 'bi-info-circle-fill'
  };

  var TITLES = {
    success: 'Done',
    error: 'Something went wrong',
    info: 'Heads up'
  };

  function stack() {
    var el = document.getElementById(STACK_ID);

    // Created on demand so no page has to remember to include the container.
    if (!el) {
      el = document.createElement('div');
      el.id = STACK_ID;
      el.className = 'toast-stack';
      // Polite, not assertive: a save confirmation should not interrupt a
      // screen reader mid-sentence.
      el.setAttribute('aria-live', 'polite');
      el.setAttribute('aria-atomic', 'false');
      document.body.appendChild(el);
    }

    return el;
  }

  function dismiss(toast) {
    if (toast.dataset.leaving === '1') return;
    toast.dataset.leaving = '1';
    toast.classList.add('is-leaving');

    // Falls back to a plain remove if the animation never fires (reduced
    // motion, or a background tab where animationend does not run).
    var done = function () { if (toast.parentNode) toast.parentNode.removeChild(toast); };
    toast.addEventListener('animationend', done, { once: true });
    window.setTimeout(done, 400);
  }

  /**
   * show(message, type, options)
   *   type: 'success' | 'error' | 'info'
   *   options: { title, duration }  duration 0 keeps it until dismissed
   */
  function show(message, type, options) {
    type = ICONS[type] ? type : 'info';
    options = options || {};

    var toast = document.createElement('div');
    toast.className = 'toast-msg toast-msg--' + type;
    toast.setAttribute('role', type === 'error' ? 'alert' : 'status');

    var icon = document.createElement('i');
    icon.className = 'bi ' + ICONS[type] + ' t-icon';

    var body = document.createElement('div');
    body.className = 't-body';

    var title = document.createElement('div');
    title.className = 't-title';
    title.textContent = options.title || TITLES[type];

    var text = document.createElement('div');
    text.className = 't-text';
    // textContent, never innerHTML: these strings come back from the API and
    // can echo operator input, so treating them as markup would be an XSS.
    text.textContent = message == null ? '' : String(message);

    var close = document.createElement('button');
    close.type = 'button';
    close.className = 't-close';
    close.setAttribute('aria-label', 'Dismiss notification');
    close.innerHTML = '<i class="bi bi-x-lg"></i>';
    close.addEventListener('click', function () { dismiss(toast); });

    body.appendChild(title);
    body.appendChild(text);
    toast.appendChild(icon);
    toast.appendChild(body);
    toast.appendChild(close);

    var host = stack();
    host.appendChild(toast);

    // Old toasts are trimmed so a burst cannot fill the viewport.
    while (host.children.length > 4) dismiss(host.firstElementChild);

    var ms = options.duration === undefined ? DEFAULT_MS : options.duration;
    if (ms > 0) {
      var timer = window.setTimeout(function () { dismiss(toast); }, ms);
      // Reading a long message should not race the timer.
      toast.addEventListener('mouseenter', function () { window.clearTimeout(timer); });
      toast.addEventListener('mouseleave', function () {
        timer = window.setTimeout(function () { dismiss(toast); }, 1800);
      });
    }

    return toast;
  }

  window.AurumToast = {
    show: show,
    success: function (m, o) { return show(m, 'success', o); },
    error: function (m, o) { return show(m, 'error', o); },
    info: function (m, o) { return show(m, 'info', o); }
  };

  // Anything the server flashed into the page shows on load.
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-toast]').forEach(function (el) {
      show(el.getAttribute('data-toast'), el.getAttribute('data-toast-type') || 'info');
      el.remove();
    });
  });
})();
