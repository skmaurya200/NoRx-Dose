/* NoRx Dose - activity log bulk selection
   Single-row delete is catalogue.js's shared [data-delete] handler; this adds
   the select-all box and "Delete selected", through the same AurumApi client
   and confirmation dialog. */
(function () {
  'use strict';

  var api = window.AurumApi;
  var toast = window.AurumToast;

  document.addEventListener('DOMContentLoaded', function () {
    var bulk = document.querySelector('[data-bulk-delete]');
    var all = document.querySelector('[data-select-all]');
    if (!bulk) return;

    function boxes() {
      return Array.prototype.slice.call(document.querySelectorAll('[data-select-row]'));
    }

    function selected() {
      return boxes().filter(function (box) { return box.checked; });
    }

    function sync() {
      var count = selected().length;
      bulk.disabled = count === 0;
      bulk.innerHTML = '<i class="bi bi-trash"></i> Delete selected' + (count ? ' (' + count + ')' : '');
      if (all) all.checked = count > 0 && count === boxes().length;
    }

    if (all) {
      all.addEventListener('change', function () {
        boxes().forEach(function (box) { box.checked = all.checked; });
        sync();
      });
    }

    document.addEventListener('change', function (event) {
      if (event.target.matches('[data-select-row]')) sync();
    });

    bulk.addEventListener('click', function () {
      var chosen = selected();
      if (!chosen.length) return;

      window.AurumConfirm({
        title: 'Delete ' + chosen.length + ' log ' + (chosen.length === 1 ? 'entry' : 'entries') + '?',
        message: 'The selected entries will be removed permanently. The deletion itself is recorded.',
        confirmText: 'Delete'
      }).then(function (confirmed) {
        if (!confirmed) return;

        bulk.disabled = true;

        api.request(bulk.getAttribute('data-endpoint'), {
          method: 'POST',
          body: { ids: chosen.map(function (box) { return parseInt(box.value, 10); }) }
        })
          .then(function (result) {
            var problem = api.transportProblem(result);

            if (problem || !result.body.success) {
              toast.error(problem || result.body.message);
              return;
            }

            // Reloaded rather than pruned: the page should refill from the
            // next page, and the new ADMIN_DELETED_ACTIVITY_LOG row belongs
            // at the top.
            try {
              window.sessionStorage.setItem('aurum.flash', JSON.stringify({ message: result.body.message, type: 'success' }));
            } catch (e) { /* private mode - the reload still happens */ }

            window.location.reload();
          })
          .catch(function () { toast.error('Could not reach the server. Please try again.'); })
          .finally(sync);
      });
    });

    sync();
  });
})();
