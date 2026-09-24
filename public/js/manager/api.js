/* NoRx Dose - manager API client + form binder
   One place that talks to /api/manager/*, so the CSRF header, the response
   envelope and the error-to-field mapping are implemented once.

   The API always answers with { success, message, data, errors }, and this
   file is what turns that into a red line under the right input and a toast in
   the bottom-right corner. */
(function () {
  'use strict';

  /* ============================================================== transport */

  function csrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  /**
   * request(url, options) -> Promise<{ status, ok, body }>
   *
   * Never rejects on an HTTP error status - a 422 is an ordinary answer here,
   * not an exception. It only rejects when the network itself fails.
   */
  function request(url, options) {
    options = options || {};

    var headers = {
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': csrfToken()
    };

    var body = options.body;

    // FormData sets its own multipart boundary; setting Content-Type by hand
    // would corrupt the body. JSON has to declare its type explicitly.
    if (body && !(body instanceof FormData)) {
      headers['Content-Type'] = 'application/json';
      body = JSON.stringify(body);
    }

    return fetch(url, {
      method: options.method || 'GET',
      headers: headers,
      credentials: 'same-origin',
      body: body
    }).then(function (response) {
      // A 419 or a redirect to the login page comes back as HTML, so the JSON
      // parse is guarded rather than assumed.
      return response.json()
        .catch(function () { return null; })
        .then(function (parsed) {
          return { status: response.status, ok: response.ok, body: parsed };
        });
    });
  }

  /**
   * A human message for the cases the caller should not have to special-case.
   * Returns null when the response is fine and the caller should carry on.
   */
  function transportProblem(result) {
    if (result.status === 419) {
      return 'Your session expired. Reload the page and sign in again.';
    }

    if (result.status === 401) {
      return 'You are signed out. Reload the page to sign in again.';
    }

    if (result.status === 403) {
      return (result.body && result.body.message) || 'You are not allowed to do that.';
    }

    if (result.status === 413) {
      return 'That file is too large for the server to accept.';
    }

    if (!result.body) {
      return 'The server sent an unexpected response. Please try again.';
    }

    return null;
  }

  /* ========================================================== field errors */

  /**
   * Find the small red slot that belongs to a field. Prefers an explicit
   * [data-error-for], then falls back to a slot placed right after the input,
   * so a field added later still reports without extra wiring.
   */
  function errorSlot(scope, field) {
    var slot = scope.querySelector('[data-error-for="' + cssEscape(field) + '"]');
    if (slot) return slot;

    var input = fieldInput(scope, field);
    if (!input) return null;

    var wrap = input.closest('.field') || input.parentNode;
    return wrap ? wrap.querySelector('.field-error') : null;
  }

  function fieldInput(scope, field) {
    return scope.querySelector('[name="' + cssEscape(field) + '"]')
      || scope.querySelector('[name="' + cssEscape(field) + '[]"]')
      || scope.querySelector('[name="' + cssEscape(bracketName(field)) + '"]')
      || scope.querySelector('#' + cssEscape(field));
  }

  /**
   * Laravel reports nested failures with dots ("specifications.0.label") while
   * the inputs that produced them are named with brackets
   * ("specifications[0][label]"). Without this translation a repeater row
   * would fail validation with no red line against the row at fault.
   */
  function bracketName(field) {
    var parts = String(field).split('.');
    if (parts.length === 1) return field;

    return parts[0] + parts.slice(1).map(function (part) {
      return '[' + part + ']';
    }).join('');
  }

  // CSS.escape is missing in older Safari; names here are attribute values, so
  // quoting the few characters that would break the selector is enough.
  function cssEscape(value) {
    if (window.CSS && window.CSS.escape) return window.CSS.escape(value);
    return String(value).replace(/([ !"#$%&'()*+,.\/:;<=>?@\[\\\]^`{|}~])/g, '\\$1');
  }

  function clearErrors(scope) {
    scope.querySelectorAll('.field-error.is-shown').forEach(function (slot) {
      slot.classList.remove('is-shown');
      slot.textContent = '';
    });
    scope.querySelectorAll('.is-invalid').forEach(function (input) {
      input.classList.remove('is-invalid');
      input.removeAttribute('aria-invalid');
    });
    scope.querySelectorAll('.form-tab.has-errors').forEach(function (tab) {
      tab.classList.remove('has-errors');
    });
  }

  /**
   * Paint { field: [messages] } onto the form and return the first input that
   * failed, so the caller can focus it.
   */
  function showErrors(scope, errors) {
    var first = null;

    Object.keys(errors || {}).forEach(function (field) {
      var messages = errors[field];
      var message = Array.isArray(messages) ? messages[0] : String(messages);

      var input = fieldInput(scope, field);
      var slot = errorSlot(scope, field);

      if (input) {
        input.classList.add('is-invalid');
        input.setAttribute('aria-invalid', 'true');
        if (!first) first = input;
      }

      if (slot) {
        slot.textContent = message;
        slot.classList.add('is-shown');
        if (!first) first = slot;
      }

      // A field the form has no control for (a rule on the whole array, say)
      // would otherwise fail silently, so it is reported as a toast instead.
      if (!input && !slot) {
        window.AurumToast.error(message);
      }
    });

    markTabsWithErrors(scope);

    return first;
  }

  /**
   * Flag every tab holding an invalid field, and switch to the first one - an
   * error on a hidden pane must never leave the operator staring at a form
   * that "just will not save".
   */
  function markTabsWithErrors(scope) {
    var panes = scope.querySelectorAll('.tab-pane-custom');
    if (!panes.length) return;

    var firstBroken = null;

    panes.forEach(function (pane) {
      if (!pane.querySelector('.is-invalid, .field-error.is-shown')) return;

      var tab = scope.querySelector('.form-tab[data-tab-target="' + cssEscape(pane.id) + '"]')
        || document.querySelector('.form-tab[data-tab-target="' + cssEscape(pane.id) + '"]');

      if (tab) tab.classList.add('has-errors');
      if (!firstBroken) firstBroken = tab;
    });

    if (firstBroken) firstBroken.click();
  }

  function focusField(target) {
    if (!target) return;

    if (typeof target.focus === 'function') {
      target.focus({ preventScroll: true });
    }

    if (typeof target.scrollIntoView === 'function') {
      target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }

  /* ================================================================= busy */

  function setBusy(form, busy) {
    var buttons = form.querySelectorAll('[type="submit"], [data-submit]');

    buttons.forEach(function (button) {
      button.disabled = busy;
      button.classList.toggle('is-busy', busy);
    });
  }

  /* ==================================================== confirmation dialog */

  /**
   * A real dialog instead of window.confirm(): the native one cannot say which
   * product is about to be deleted, and on mobile it reads as a browser
   * warning rather than part of the panel.
   */
  function confirmAction(options) {
    return new Promise(function (resolve) {
      var backdrop = document.createElement('div');
      backdrop.className = 'confirm-backdrop';
      backdrop.innerHTML =
        '<div class="confirm-box" role="alertdialog" aria-modal="true" aria-labelledby="confirmTitle">' +
          '<div class="confirm-icon ' + (options.danger === false ? '' : 'is-danger') + '">' +
            '<i class="bi ' + (options.icon || 'bi-exclamation-triangle') + '"></i>' +
          '</div>' +
          '<h3 id="confirmTitle"></h3>' +
          '<p></p>' +
          '<div class="confirm-actions">' +
            '<button type="button" class="btn-ghost" data-confirm-cancel></button>' +
            '<button type="button" class="' +
              (options.danger === false ? 'btn-gold' : 'btn-danger-soft') +
              '" data-confirm-ok></button>' +
          '</div>' +
        '</div>';

      // textContent for the copy: a product name goes in here and must never
      // be parsed as markup.
      backdrop.querySelector('h3').textContent = options.title || 'Are you sure?';
      backdrop.querySelector('p').textContent = options.message || '';
      backdrop.querySelector('[data-confirm-cancel]').textContent = options.cancelText || 'Cancel';
      backdrop.querySelector('[data-confirm-ok]').textContent = options.confirmText || 'Delete';

      var previouslyFocused = document.activeElement;

      function close(answer) {
        document.removeEventListener('keydown', onKey);
        backdrop.remove();
        document.body.style.removeProperty('overflow');
        if (previouslyFocused && previouslyFocused.focus) previouslyFocused.focus();
        resolve(answer);
      }

      function onKey(event) {
        if (event.key === 'Escape') close(false);
        if (event.key === 'Enter') close(true);
      }

      backdrop.querySelector('[data-confirm-cancel]').addEventListener('click', function () { close(false); });
      backdrop.querySelector('[data-confirm-ok]').addEventListener('click', function () { close(true); });
      backdrop.addEventListener('click', function (event) {
        if (event.target === backdrop) close(false);
      });
      document.addEventListener('keydown', onKey);

      // Stops the page behind the dialog from scrolling under it on touch.
      document.body.style.overflow = 'hidden';
      document.body.appendChild(backdrop);
      backdrop.querySelector('[data-confirm-ok]').focus();
    });
  }

  /* ========================================================== form binding */

  /**
   * Binds every <form data-api-form>. The form declares where to post and what
   * to do afterwards:
   *
   *   data-api-form            the endpoint
   *   data-method              defaults to POST
   *   data-redirect            where to go on success
   *   data-success             message to flash before redirecting
   */
  function bindForm(form) {
    if (form.dataset.bound === '1') return;
    form.dataset.bound = '1';

    form.addEventListener('submit', function (event) {
      event.preventDefault();

      if (form.dataset.busy === '1') return;
      form.dataset.busy = '1';

      clearErrors(form);
      setBusy(form, true);

      var redirecting = false;

      // FormData rather than a JSON body: these forms carry file inputs, and
      // it also means an unchecked box is simply absent, which the server-side
      // request classes already normalise.
      request(form.getAttribute('data-api-form'), {
        method: form.getAttribute('data-method') || 'POST',
        body: new FormData(form)
      })
        .then(function (result) {
          var problem = transportProblem(result);

          if (problem) {
            window.AurumToast.error(problem);
            return;
          }

          var body = result.body;

          if (body.success) {
            var redirect = form.getAttribute('data-redirect');

            if (redirect) {
              redirecting = true;
              // Handed to the next page through sessionStorage so the toast
              // survives the navigation and appears where the operator lands.
              try {
                window.sessionStorage.setItem(
                  'aurum.flash',
                  JSON.stringify({
                    message: form.getAttribute('data-success') || body.message,
                    type: 'success'
                  })
                );
              } catch (e) { /* private mode - the redirect still happens */ }

              window.location.assign(redirect);
              return;
            }

            window.AurumToast.success(body.message);
            form.dispatchEvent(new CustomEvent('aurum:saved', { detail: body.data }));
            return;
          }

          if (body.errors) {
            focusField(showErrors(form, body.errors));
          }

          window.AurumToast.error(body.message || 'Please check the form and try again.');
        })
        .catch(function () {
          window.AurumToast.error('Could not reach the server. Check your connection and try again.');
        })
        .finally(function () {
          form.dataset.busy = '0';
          // Stays busy while navigating, so the form cannot be submitted twice.
          if (!redirecting) setBusy(form, false);
        });
    });
  }

  /* Show whatever the previous page flashed before redirecting here. */
  function drainFlash() {
    var raw;

    try {
      raw = window.sessionStorage.getItem('aurum.flash');
      if (raw) window.sessionStorage.removeItem('aurum.flash');
    } catch (e) {
      return;
    }

    if (!raw) return;

    try {
      var flash = JSON.parse(raw);
      if (flash && flash.message) window.AurumToast.show(flash.message, flash.type);
    } catch (e) { /* malformed - nothing worth showing */ }
  }

  document.addEventListener('DOMContentLoaded', function () {
    drainFlash();
    document.querySelectorAll('form[data-api-form]').forEach(bindForm);
  });

  window.AurumConfirm = confirmAction;

  window.AurumApi = {
    request: request,
    transportProblem: transportProblem,
    clearErrors: clearErrors,
    showErrors: showErrors,
    focusField: focusField,
    setBusy: setBusy,
    bindForm: bindForm
  };
})();
