/* NoRx Dose - manager (admin panel) behaviour
   Loaded on every /manager page via resources/views/manager/components/layout.blade.php */
(function () {
  'use strict';

  /* ---- sidebar drawer (mobile) ---- */
  var sidebar = document.getElementById('sidebar');
  var overlay = document.getElementById('sidebarOverlay');
  var burger = document.getElementById('burgerBtn');

  if (sidebar && overlay && burger) {
    var toggleSidebar = function (open) {
      sidebar.classList.toggle('open', open);
      overlay.classList.toggle('show', open);
    };
    burger.addEventListener('click', function () {
      toggleSidebar(!sidebar.classList.contains('open'));
    });
    overlay.addEventListener('click', function () {
      toggleSidebar(false);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') toggleSidebar(false);
    });
  }

  /* ---- charts: only the dashboard ships these canvases, and Chart.js is only
         pulled in on the pages that need it, so both are checked first ---- */
  if (typeof Chart === 'undefined') return;

  var salesCtx = document.getElementById('salesChart');
  if (salesCtx) {
    var salesGradient = salesCtx.getContext('2d').createLinearGradient(0, 0, 0, 220);
    salesGradient.addColorStop(0, 'rgba(201,161,46,0.35)');
    salesGradient.addColorStop(1, 'rgba(201,161,46,0)');

    new Chart(salesCtx, {
      type: 'line',
      data: {
        labels: JSON.parse(salesCtx.dataset.labels || '[]'),
        datasets: [{
          label: 'Revenue',
          data: JSON.parse(salesCtx.dataset.values || '[]'),
          borderColor: '#c9a12e',
          backgroundColor: salesGradient,
          fill: true,
          tension: 0.4,
          pointRadius: 3,
          pointBackgroundColor: '#c9a12e',
          borderWidth: 2.5
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
          y: { grid: { color: '#ece4d2' }, ticks: { callback: function (v) { return '$' + v; } } },
          x: { grid: { display: false } }
        }
      }
    });
  }

  var statusCtx = document.getElementById('statusChart');
  if (statusCtx) {
    new Chart(statusCtx, {
      type: 'doughnut',
      data: {
        labels: JSON.parse(statusCtx.dataset.labels || '[]'),
        datasets: [{
          data: JSON.parse(statusCtx.dataset.values || '[]'),
          backgroundColor: JSON.parse(statusCtx.dataset.colors || '[]'),
          borderWidth: 0
        }]
      },
      options: {
        cutout: '72%',
        plugins: { legend: { display: false } }
      }
    });
  }
})();

/* NoRx Dose - manager logout module
   Any [data-logout] / [data-logout-all] control posts to the auth API. The
   session cookie carries the credential, so nothing is read from storage. */
(function () {
  'use strict';

  function csrf() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  function signOut(endpoint, control) {
    if (control.dataset.busy === '1') return;
    control.dataset.busy = '1';

    fetch(endpoint, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrf()
      },
      credentials: 'same-origin'
    })
      .then(function (response) {
        // 401/419 means the session is already gone - the destination is the
        // same either way, so treat it as a completed sign-out.
        if (response.ok || response.status === 401 || response.status === 419) {
          window.location.assign('/manager/login');
          return;
        }
        control.dataset.busy = '0';
        window.AurumToast.error('Could not sign out. Please try again.');
      })
      .catch(function () {
        control.dataset.busy = '0';
        window.AurumToast.error('Could not reach the server. Please try again.');
      });
  }

  document.addEventListener('click', function (e) {
    var one = e.target.closest('[data-logout]');
    if (one) {
      e.preventDefault();
      signOut('/api/manager/auth/logout', one);
      return;
    }

    var all = e.target.closest('[data-logout-all]');
    if (all) {
      e.preventDefault();
      window.AurumConfirm({
        title: 'Sign out everywhere?',
        message: 'Every employee is signed out of the panel, and every other session and API token of yours ends too. Nobody - employee or admin - can sign in again without a new verification code.',
        confirmText: 'Sign out everywhere'
      }).then(function (confirmed) {
        if (confirmed) signOut('/api/manager/auth/logout-all', all);
      });
    }
  });
})();
