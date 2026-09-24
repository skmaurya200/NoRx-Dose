/* NoRx Dose - sign-in verification code
   Posts the code to the auth API. The challenge it answers lives in the
   server-side session, so this script never sees it, and it never sees the
   code except as the digits the operator types. Nothing is written to
   localStorage or sessionStorage. The countdowns start from seconds the server
   worked out - the browser clock decides nothing. */
(function () {
  'use strict';

  var form = document.getElementById('otpForm');
  if (!form) return;

  var input = document.getElementById('code');
  var btn = document.getElementById('submitBtn');
  var btnLabel = document.getElementById('submitLabel');
  var resendBtn = document.getElementById('resendBtn');
  var cancelBtn = document.getElementById('cancelBtn');
  var countdown = document.getElementById('otpCountdown');
  var alertBox = document.getElementById('formAlert');
  var alertText = document.getElementById('formAlertText');
  var noticeBox = document.getElementById('formNotice');
  var noticeText = document.getElementById('formNoticeText');
  var codeError = document.getElementById('codeError');

  var expiresIn = parseInt(form.getAttribute('data-expires-in') || '0', 10);
  var resendIn = parseInt(form.getAttribute('data-resend-in') || '0', 10);
  var resendsLeft = parseInt(form.getAttribute('data-resends-left') || '0', 10);

  /* ---- transport ---- */
  function post(url, body) {
    var tokenMeta = document.querySelector('meta[name="csrf-token"]');

    return fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': tokenMeta ? tokenMeta.getAttribute('content') : ''
      },
      credentials: 'same-origin',
      body: JSON.stringify(body || {})
    }).then(function (response) {
      return response.json()
        .catch(function () { return null; })
        .then(function (parsed) { return { status: response.status, body: parsed }; });
    });
  }

  /* ---- feedback ---- */
  function clearMessages() {
    alertBox.classList.remove('is-shown');
    noticeBox.classList.remove('is-shown');
    input.classList.remove('is-invalid');
    codeError.classList.remove('is-shown');
    codeError.textContent = '';
  }

  function showAlert(message) {
    alertText.textContent = message;
    alertBox.classList.add('is-shown');
  }

  function showNotice(message) {
    noticeText.textContent = message;
    noticeBox.classList.add('is-shown');
  }

  function setBusy(busy) {
    btn.disabled = busy;
    btn.classList.toggle('is-busy', busy);
    btnLabel.textContent = busy ? 'Verifying…' : 'Verify and sign in';
  }

  function backToLogin(delay) {
    window.setTimeout(function () { window.location.assign('/manager/login'); }, delay || 0);
  }

  /* ---- countdowns ---- */
  function format(seconds) {
    var m = Math.floor(seconds / 60);
    var s = seconds % 60;
    return m + ':' + (s < 10 ? '0' : '') + s;
  }

  function paintResend() {
    if (resendsLeft <= 0) {
      resendBtn.disabled = true;
      resendBtn.textContent = 'No more resends';
      return;
    }

    resendBtn.disabled = resendIn > 0;
    resendBtn.textContent = resendIn > 0 ? 'Resend in ' + resendIn + 's' : 'Resend code';
  }

  function tick() {
    if (expiresIn > 0) expiresIn -= 1;
    if (resendIn > 0) resendIn -= 1;

    countdown.textContent = expiresIn > 0
      ? 'Code expires in ' + format(expiresIn)
      : 'Code expired - resend or go back';

    paintResend();
  }

  countdown.textContent = expiresIn > 0 ? 'Code expires in ' + format(expiresIn) : 'Code expired - resend or go back';
  paintResend();
  window.setInterval(tick, 1000);

  /* Digits only, so a pasted "123 456" still fits. */
  input.addEventListener('input', function () {
    input.value = input.value.replace(/\D+/g, '').slice(0, 6);
  });

  /* ---- verify ---- */
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (btn.disabled) return;

    clearMessages();

    if (!/^\d{6}$/.test(input.value)) {
      input.classList.add('is-invalid');
      codeError.textContent = 'Enter the 6-digit verification code.';
      codeError.classList.add('is-shown');
      return;
    }

    setBusy(true);
    var navigating = false;

    post('/api/manager/auth/otp/verify', { code: input.value })
      .then(function (result) {
        var body = result.body;

        if (result.status === 419) {
          showAlert('Your session expired. Sign in again.');
          backToLogin(1500);
          return;
        }

        if (body && body.success) {
          navigating = true;
          window.location.assign((body.data && body.data.redirect) || '/manager');
          return;
        }

        input.value = '';

        // 429 (attempt limit) and 403 (account switched off) end the
        // challenge on the server, so there is nothing left to retry here.
        if (result.status === 429 || result.status === 403) {
          showAlert((body && body.message) || 'Too many attempts. Please try again later.');
          backToLogin(2500);
          return;
        }

        showAlert((body && body.message) || 'Invalid or expired OTP.');
        input.focus();
      })
      .catch(function () {
        showAlert('Could not reach the server. Check your connection and try again.');
      })
      .finally(function () {
        if (!navigating) setBusy(false);
      });
  });

  /* ---- resend ---- */
  resendBtn.addEventListener('click', function () {
    if (resendBtn.disabled) return;

    clearMessages();
    resendBtn.disabled = true;

    post('/api/manager/auth/otp/resend')
      .then(function (result) {
        var body = result.body;

        if (body && body.success) {
          resendsLeft -= 1;
          expiresIn = (body.data && body.data.expires_in) || expiresIn;
          resendIn = (body.data && body.data.resend_available_in) || 60;
          input.value = '';
          input.focus();
          showNotice(body.message);
          return;
        }

        if (result.status === 422) {
          showAlert('This sign-in has expired. Sign in again.');
          backToLogin(2000);
          return;
        }

        showAlert((body && body.message) || 'Could not resend the code.');
      })
      .catch(function () {
        showAlert('Could not reach the server. Check your connection and try again.');
      })
      .finally(paintResend);
  });

  /* ---- back ---- */
  cancelBtn.addEventListener('click', function () {
    cancelBtn.disabled = true;

    // The pending code is invalidated server-side, then back to the start
    // whatever the answer - going back must always work.
    post('/api/manager/auth/otp/cancel')
      .catch(function () { return null; })
      .then(function () { backToLogin(); });
  });
})();
