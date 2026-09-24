/* NoRx Dose - manager sign-in
   Posts to the auth API and reflects whatever it returns. No credential or
   token is ever written to localStorage: a browser sign-in lands in the admin
   session cookie, which is HttpOnly and out of reach of this script. */
(function () {
  'use strict';

  var form = document.getElementById('loginForm');
  if (!form) return;

  var btn = document.getElementById('submitBtn');
  var btnLabel = document.getElementById('submitLabel');
  var alertBox = document.getElementById('formAlert');
  var alertText = document.getElementById('formAlertText');

  var FIELDS = ['login', 'password'];

  /* ---- password visibility ---- */
  var toggleEye = document.getElementById('toggleEye');
  var passwordInput = document.getElementById('password');

  if (toggleEye && passwordInput) {
    toggleEye.addEventListener('click', function () {
      var reveal = passwordInput.type === 'password';
      passwordInput.type = reveal ? 'text' : 'password';
      toggleEye.setAttribute('aria-pressed', String(reveal));
      toggleEye.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
      toggleEye.innerHTML = reveal
        ? '<i class="bi bi-eye-slash"></i>'
        : '<i class="bi bi-eye"></i>';
    });
  }

  /* ---- feedback helpers ---- */
  function clearErrors() {
    alertBox.classList.remove('is-shown');
    alertText.textContent = '';

    FIELDS.forEach(function (name) {
      var input = document.getElementById(name);
      var slot = document.getElementById(name + 'Error');
      if (input) input.classList.remove('is-invalid');
      if (slot) {
        slot.classList.remove('is-shown');
        slot.textContent = '';
      }
    });
  }

  function showAlert(message) {
    alertText.textContent = message;
    alertBox.classList.add('is-shown');
  }

  // The API returns { errors: { field: [messages] } } on a 422.
  function showFieldErrors(errors) {
    Object.keys(errors || {}).forEach(function (field) {
      var input = document.getElementById(field);
      var slot = document.getElementById(field + 'Error');
      var message = Array.isArray(errors[field]) ? errors[field][0] : String(errors[field]);

      if (input) input.classList.add('is-invalid');
      if (slot) {
        slot.textContent = message;
        slot.classList.add('is-shown');
      }
    });
  }

  function setBusy(busy) {
    btn.disabled = busy;
    btn.classList.toggle('is-busy', busy);
    btnLabel.textContent = busy ? 'Signing in…' : 'Sign in';
  }

  /* ---- submit ---- */
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (btn.disabled) return;

    clearErrors();
    setBusy(true);
    var navigating = false;

    var tokenMeta = document.querySelector('meta[name="csrf-token"]');

    fetch('/api/manager/auth/login', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': tokenMeta ? tokenMeta.getAttribute('content') : ''
      },
      // Needed for the session cookie to be set on the response.
      credentials: 'same-origin',
      body: JSON.stringify({
        login: document.getElementById('login').value,
        password: passwordInput.value,
        remember: document.getElementById('remember').checked
      })
    })
      .then(function (response) {
        // A 419 comes back as HTML, so guard the JSON parse.
        return response.json()
          .catch(function () { return null; })
          .then(function (body) { return { status: response.status, body: body }; });
      })
      .then(function (result) {
        var body = result.body;

        if (result.status === 419) {
          showAlert('Your session expired. Reload the page and try again.');
          return;
        }

        if (!body) {
          showAlert('Something went wrong. Please try again.');
          return;
        }

        if (body.success) {
          navigating = true;
          // Full navigation, not history.pushState: the panel is server
          // rendered and must be fetched with the new session.
          window.location.assign(
            (body.data && body.data.redirect) || '/manager'
          );
          return;
        }

        if (body.errors) showFieldErrors(body.errors);
        showAlert(body.message || 'Sign in failed.');
      })
      .catch(function () {
        showAlert('Could not reach the server. Check your connection and try again.');
      })
      .finally(function () {
        // Stays busy on success, so the button cannot be pressed a second time
        // while the browser is already navigating away.
        if (!navigating) setBusy(false);
      });
  });
})();
