/* NoRx Dose - manager catalogue behaviour
   Categories, products and the product details screen. Every mutation goes
   through AurumApi so the envelope, the CSRF header and the error handling
   stay in one place. */
(function () {
  'use strict';

  var api = window.AurumApi;
  var toast = window.AurumToast;

  var confirmAction = window.AurumConfirm;

  /* ================================================================== tabs */

  function initTabs() {
    document.querySelectorAll('.form-tab[data-tab-target]').forEach(function (tab) {
      tab.addEventListener('click', function () {
        var group = tab.closest('.form-tabs');
        var target = document.getElementById(tab.getAttribute('data-tab-target'));
        if (!group || !target) return;

        group.querySelectorAll('.form-tab').forEach(function (other) {
          other.classList.remove('active');
          other.setAttribute('aria-selected', 'false');
        });

        document.querySelectorAll('.tab-pane-custom').forEach(function (pane) {
          pane.classList.remove('active');
        });

        tab.classList.add('active');
        tab.setAttribute('aria-selected', 'true');
        target.classList.add('active');
      });
    });
  }

  /* ====================================================== single image field */

  function initImagePickers() {
    document.querySelectorAll('[data-image-picker]').forEach(function (picker) {
      var input = picker.querySelector('input[type="file"]');
      var preview = picker.querySelector('[data-image-preview]');
      var dropzone = picker.querySelector('[data-image-drop]');
      var removeFlag = picker.querySelector('[data-image-remove-flag]');
      if (!input) return;

      function render(src) {
        if (!preview) return;

        if (src) {
          preview.innerHTML =
            '<img alt="Selected image">' +
            '<button type="button" class="ip-remove" data-image-clear aria-label="Remove image">' +
              '<i class="bi bi-x-lg"></i>' +
            '</button>';
          preview.querySelector('img').src = src;
          preview.hidden = false;
        } else {
          preview.innerHTML = '';
          preview.hidden = true;
        }
      }

      function accept(files) {
        if (!files || !files.length) return;

        var file = files[0];

        // A friendly, immediate check. The server re-validates the mime and
        // the bytes regardless - this only saves a pointless round trip.
        if (!/^image\//.test(file.type)) {
          toast.error('Choose an image file (JPG, PNG or WebP).');
          return;
        }

        var reader = new FileReader();
        reader.onload = function (event) { render(event.target.result); };
        reader.readAsDataURL(file);

        // Back to "keep whatever is stored". A flag that names a field - the
        // Pages form posts remove_images[] rather than a single boolean -
        // clears to empty instead of to "0".
        if (removeFlag) removeFlag.value = removeFlag.hasAttribute('data-remove-value') ? '' : '0';
      }

      input.addEventListener('change', function () { accept(input.files); });

      if (dropzone) {
        dropzone.addEventListener('click', function () { input.click(); });
        dropzone.addEventListener('keydown', function (event) {
          if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            input.click();
          }
        });

        ['dragenter', 'dragover'].forEach(function (name) {
          dropzone.addEventListener(name, function (event) {
            event.preventDefault();
            dropzone.classList.add('is-dragging');
          });
        });

        ['dragleave', 'drop'].forEach(function (name) {
          dropzone.addEventListener(name, function (event) {
            event.preventDefault();
            dropzone.classList.remove('is-dragging');
          });
        });

        dropzone.addEventListener('drop', function (event) {
          if (!event.dataTransfer || !event.dataTransfer.files.length) return;
          input.files = event.dataTransfer.files;
          accept(input.files);
        });
      }

      picker.addEventListener('click', function (event) {
        if (!event.target.closest('[data-image-clear]')) return;

        input.value = '';
        render(null);
        // Tells the server to drop the stored image, as opposed to simply not
        // sending a new one - those are two different intentions. A form with
        // several pickers says which one by name.
        if (removeFlag) {
          removeFlag.value = removeFlag.getAttribute('data-remove-value') || '1';
        }
      });
    });
  }

  /* ================================================== specification repeater */

  function initSpecRepeater() {
    var host = document.querySelector('[data-spec-rows]');
    if (!host) return;

    var addBtn = document.querySelector('[data-spec-add]');
    var max = parseInt(host.getAttribute('data-spec-max') || '30', 10);

    function rowCount() {
      return host.querySelectorAll('.spec-row').length;
    }

    function addRow(label, value) {
      if (rowCount() >= max) {
        toast.info('You can add up to ' + max + ' specification rows.');
        return;
      }

      // The index only has to be unique within the post; Laravel re-keys the
      // array, and the service drops blank rows before saving.
      var index = Date.now() + '-' + rowCount();

      var row = document.createElement('div');
      row.className = 'spec-row';
      row.innerHTML =
        '<input type="text" class="field-input spec-label" placeholder="Label, e.g. Serving size">' +
        '<input type="text" class="field-input" placeholder="Value, e.g. 2 capsules daily">' +
        '<button type="button" class="spec-remove" data-spec-remove aria-label="Remove this row">' +
          '<i class="bi bi-trash"></i>' +
        '</button>';

      var inputs = row.querySelectorAll('input');
      inputs[0].name = 'specifications[' + index + '][label]';
      inputs[1].name = 'specifications[' + index + '][value]';
      inputs[0].value = label || '';
      inputs[1].value = value || '';

      host.appendChild(row);
      if (!label) inputs[0].focus();
    }

    if (addBtn) addBtn.addEventListener('click', function () { addRow(); });

    host.addEventListener('click', function (event) {
      var remove = event.target.closest('[data-spec-remove]');
      if (!remove) return;
      remove.closest('.spec-row').remove();
    });

    // An empty repeater looks broken, so start with one row to type into.
    if (rowCount() === 0) addRow();
  }

  /* ====================================================== detail sections */

  /**
   * The sections an operator adds for one product.
   *
   * Separate from the specification repeater above because the two are not the
   * same shape: a specification is a label and a short value in a table, this
   * is a heading and a paragraph under it. Sharing one repeater would mean one
   * of them rendering in the wrong control.
   *
   * Unlike specifications there is no starter row - a product that needs none
   * of these is the normal case, and an empty box invites filling in.
   */
  function initSectionRepeater() {
    var host = document.querySelector('[data-section-rows]');
    if (!host) return;

    var template = document.querySelector('[data-section-template]');
    var addBtn = document.querySelector('[data-section-add]');
    var max = parseInt(host.getAttribute('data-section-max') || '12', 10);

    function rowCount() {
      return host.querySelectorAll('[data-section-row]').length;
    }

    function addRow() {
      if (!template) return;

      if (rowCount() >= max) {
        toast.info('You can add up to ' + max + ' sections.');
        return;
      }

      /* Unique within this post is all that is needed - Laravel re-keys the
         array, and the service drops any row that is only half filled in. */
      var index = 'n' + Date.now() + '-' + rowCount();

      var markup = template.innerHTML.split('__INDEX__').join(index);
      var holder = document.createElement('div');
      holder.innerHTML = markup;

      var row = holder.querySelector('[data-section-row]');
      if (!row) return;

      host.appendChild(row);

      // The editor in this row arrived after page load, so it has to be
      // started by hand.
      if (window.AurumEditor) window.AurumEditor.initAll(row);

      var label = row.querySelector('.section-row__label');
      if (label) label.focus();
    }

    if (addBtn) addBtn.addEventListener('click', addRow);

    host.addEventListener('click', function (event) {
      var remove = event.target.closest('[data-section-remove]');
      if (!remove) return;

      /* The row carries a hidden input and an editor; removing the element
         removes both, and the extra_sections_present flag on the form is what
         tells the server an empty repeater means "clear them". */
      remove.closest('[data-section-row]').remove();
    });
  }

  /* ============================================================ pack sizes */

  function initPackRepeater() {
    var host = document.querySelector('[data-pack-rows]');
    if (!host) return;

    var addBtn = document.querySelector('[data-pack-add]');
    var max = parseInt(host.getAttribute('data-pack-max') || '12', 10);

    function rowCount() {
      return host.querySelectorAll('.pack-row').length;
    }

    function addRow(focus) {
      if (rowCount() >= max) {
        toast.info('A product can have up to ' + max + ' sizes.');
        return;
      }

      var index = Date.now() + '-' + rowCount();

      var row = document.createElement('div');
      row.className = 'pack-row';
      row.innerHTML =
        '<input type="text" class="field-input pack-label" maxlength="60" placeholder="e.g. 30 count" aria-label="Size label">' +
        '<input type="number" class="field-input" step="0.01" min="0" inputmode="decimal" placeholder="Price" aria-label="Price">' +
        '<input type="number" class="field-input" step="0.01" min="0" inputmode="decimal" placeholder="Compare at" aria-label="Compare-at price">' +
        '<input type="number" class="field-input" step="1" min="0" inputmode="numeric" placeholder="Stock" aria-label="Stock">' +
        '<span class="pack-best"><input type="checkbox" class="switch" value="1" aria-label="Mark as best value"></span>' +
        '<button type="button" class="spec-remove" data-pack-remove aria-label="Remove this size">' +
          '<i class="bi bi-trash"></i>' +
        '</button>';

      var fields = row.querySelectorAll('input');
      fields[0].name = 'packs[' + index + '][label]';
      fields[1].name = 'packs[' + index + '][price]';
      fields[2].name = 'packs[' + index + '][compare_at_price]';
      fields[3].name = 'packs[' + index + '][stock_quantity]';
      fields[4].name = 'packs[' + index + '][is_best_value]';

      host.appendChild(row);

      // Not on the row seeded at load: that would scroll the form down to the
      // size chooser before the operator has typed the product's name.
      if (focus !== false) fields[0].focus();
    }

    if (addBtn) addBtn.addEventListener('click', function () { addRow(true); });

    host.addEventListener('click', function (event) {
      var remove = event.target.closest('[data-pack-remove]');
      if (!remove) return;
      remove.closest('.pack-row').remove();
    });

    /* Only one size can be the best value, so ticking one clears the rest. */
    host.addEventListener('change', function (event) {
      var box = event.target;
      if (!box.matches('.pack-best input[type="checkbox"]') || !box.checked) return;

      host.querySelectorAll('.pack-best input[type="checkbox"]').forEach(function (other) {
        if (other !== box) other.checked = false;
      });
    });

    /* A new product opens with one empty row, so the chooser reads as
       something to fill in rather than a heading with a button under it. An
       untouched row is discarded server-side, so this costs nothing. */
    if (rowCount() === 0) addRow(false);
  }

  /* ======================================================== row-level actions */

  /**
   * Delete anything that exposes data-delete="<endpoint>". The row is removed
   * optimistically only after the API confirms.
   */
  function initDeleteButtons() {
    document.addEventListener('click', function (event) {
      var button = event.target.closest('[data-delete]');
      if (!button) return;

      event.preventDefault();

      confirmAction({
        title: button.getAttribute('data-confirm-title') || 'Delete this item?',
        message: button.getAttribute('data-confirm-message') || 'This cannot be undone from here.',
        confirmText: 'Delete'
      }).then(function (confirmed) {
        if (!confirmed) return;

        button.classList.add('is-busy');
        button.disabled = true;

        api.request(button.getAttribute('data-delete'), { method: 'DELETE' })
          .then(function (result) {
            var problem = api.transportProblem(result);

            if (problem) {
              toast.error(problem);
              return;
            }

            if (!result.body.success) {
              // A 409 lands here: the category still has products, and the
              // message says exactly which obstacle to clear first.
              toast.error(result.body.message);
              return;
            }

            toast.success(result.body.message);

            var row = button.closest('[data-row]');
            var redirect = button.getAttribute('data-redirect');

            if (redirect) {
              window.location.assign(redirect);
            } else if (row) {
              row.remove();
              refreshEmptyState();
            } else {
              window.location.reload();
            }
          })
          .catch(function () {
            toast.error('Could not reach the server. Please try again.');
          })
          .finally(function () {
            button.classList.remove('is-busy');
            button.disabled = false;
          });
      });
    });
  }

  /**
   * The way back from a soft delete, wherever a "Deleted" list offers one.
   *
   * The row is removed rather than repainted: the list being looked at is the
   * deleted one, and a restored record no longer belongs in it.
   */
  function initRestoreButtons() {
    document.addEventListener('click', function (event) {
      var button = event.target.closest('[data-restore]');
      if (!button) return;

      event.preventDefault();

      button.classList.add('is-busy');
      button.disabled = true;

      api.request(button.getAttribute('data-restore'), { method: 'PATCH' })
        .then(function (result) {
          var problem = api.transportProblem(result);

          if (problem) {
            toast.error(problem);
            return;
          }

          if (!result.body.success) {
            toast.error(result.body.message);
            return;
          }

          toast.success(result.body.message);

          var row = button.closest('[data-row]');

          if (row) {
            row.remove();
            refreshEmptyState();
          } else {
            window.location.reload();
          }
        })
        .catch(function () {
          toast.error('Could not reach the server. Please try again.');
        })
        .finally(function () {
          button.classList.remove('is-busy');
          button.disabled = false;
        });
    });
  }

  /** Category active/hidden switch in the list. */
  /**
   * The row-level on/off switch, shared by every module that has one.
   *
   * The wording and the icon differ per module - a category is shown or
   * hidden, a discount code is enabled or disabled - so the button declares
   * them rather than this function assuming the category's.
   *
   * The badge prefers whatever status_label the API returns, because being
   * switched on is not the same as being usable: a re-enabled coupon that
   * expired last week is still expired.
   */
  function initToggleButtons() {
    document.addEventListener('click', function (event) {
      var button = event.target.closest('[data-toggle-active]');
      if (!button) return;

      event.preventDefault();
      button.disabled = true;

      function attr(name, fallback) {
        return button.getAttribute(name) || fallback;
      }

      api.request(button.getAttribute('data-toggle-active'), { method: 'PATCH' })
        .then(function (result) {
          var problem = api.transportProblem(result);

          if (problem || !result.body.success) {
            toast.error(problem || result.body.message);
            return;
          }

          var data = result.body.data;
          var isActive = data.is_active;
          var isGood = data.is_redeemable === undefined ? isActive : data.is_redeemable;

          var row = button.closest('[data-row]');
          var badge = row ? row.querySelector('[data-status-badge]') : null;

          if (badge) {
            badge.textContent = data.status_label
              || (isActive ? attr('data-on-label', 'Active') : attr('data-off-label', 'Hidden'));
            badge.className = 'badge-status ' + (isGood ? 'badge-active' : 'badge-muted');
          }

          button.innerHTML = '<i class="bi ' + (isActive
            ? attr('data-on-icon', 'bi-eye')
            : attr('data-off-icon', 'bi-eye-slash')) + '"></i>';

          button.setAttribute('title', isActive
            ? attr('data-on-title', 'Hide from storefront')
            : attr('data-off-title', 'Show on storefront'));

          toast.success(result.body.message);
        })
        .catch(function () { toast.error('Could not reach the server. Please try again.'); })
        .finally(function () { button.disabled = false; });
    });
  }

  /** Product status dropdown in the list. */
  function initStatusSelects() {
    document.querySelectorAll('[data-status-select]').forEach(function (select) {
      var previous = select.value;

      // Which field the endpoint expects, and which class prefix paints the
      // control. Products post "status"; an order's payment column posts
      // "payment_status" to a different endpoint on the same row.
      var field = select.getAttribute('data-status-field') || 'status';
      var prefix = select.getAttribute('data-status-prefix') || 'status';

      select.addEventListener('change', function () {
        select.disabled = true;

        var body = {};
        body[field] = select.value;

        api.request(select.getAttribute('data-status-select'), {
          method: 'PATCH',
          body: body
        })
          .then(function (result) {
            var problem = api.transportProblem(result);

            if (problem || !result.body.success) {
              // Put the control back where it was: leaving it showing a value
              // the server rejected would misreport the catalogue.
              select.value = previous;
              toast.error(problem || result.body.message);
              return;
            }

            previous = select.value;
            select.className = 'field-select status-select ' + prefix + '-' + select.value;

            // Marking a payment in also starts the order moving, so any
            // sibling control on the row is repainted from the response
            // rather than left showing what it said a moment ago.
            syncSiblings(select, result.body.data);

            toast.success(result.body.message);
          })
          .catch(function () {
            select.value = previous;
            toast.error('Could not reach the server. Please try again.');
          })
          .finally(function () { select.disabled = false; });
      });
    });
  }

  /**
   * Repaints the other status controls on the same row from whatever the API
   * returned, so two fields that move together on the server also move
   * together on screen.
   */
  function syncSiblings(changed, data) {
    if (!data) return;

    var scope = changed.closest('[data-row]') || changed.closest('[data-status-group]');
    if (!scope) return;

    scope.querySelectorAll('[data-status-select]').forEach(function (other) {
      if (other === changed) return;

      var field = other.getAttribute('data-status-field') || 'status';
      var value = data[field];

      if (!value || other.value === value) return;

      var prefix = other.getAttribute('data-status-prefix') || 'status';
      other.value = value;
      other.className = 'field-select status-select ' + prefix + '-' + value;
    });

    scope.querySelectorAll('[data-status-badge-for]').forEach(function (badge) {
      var value = data[badge.getAttribute('data-status-badge-for')];
      if (value) badge.textContent = value.charAt(0).toUpperCase() + value.slice(1);
    });
  }

  function refreshEmptyState() {
    var table = document.querySelector('[data-list-table]');
    var empty = document.querySelector('[data-list-empty]');
    if (!table || !empty) return;

    if (table.querySelectorAll('tbody tr[data-row]').length === 0) {
      table.hidden = true;
      empty.hidden = false;
    }
  }

  /* ================================================================ gallery */

  function initGallery() {
    var gallery = document.querySelector('[data-gallery]');
    if (!gallery) return;

    var uploadInput = document.querySelector('[data-gallery-input]');
    var uploadBtn = document.querySelector('[data-gallery-trigger]');
    var endpoint = gallery.getAttribute('data-gallery');

    if (uploadBtn && uploadInput) {
      uploadBtn.addEventListener('click', function () { uploadInput.click(); });

      uploadInput.addEventListener('change', function () {
        if (!uploadInput.files.length) return;

        var form = new FormData();
        Array.prototype.forEach.call(uploadInput.files, function (file) {
          form.append('images[]', file);
        });

        uploadBtn.classList.add('is-busy');
        uploadBtn.disabled = true;

        api.request(endpoint, { method: 'POST', body: form })
          .then(function (result) {
            var problem = api.transportProblem(result);

            if (problem) {
              toast.error(problem);
              return;
            }

            if (!result.body.success) {
              // Covers both the per-file validation errors and the gallery
              // ceiling, which comes back as a 422 with a plain message.
              if (result.body.errors) {
                Object.keys(result.body.errors).forEach(function (key) {
                  toast.error(result.body.errors[key][0]);
                });
              } else {
                toast.error(result.body.message);
              }
              return;
            }

            toast.success(result.body.message);
            window.location.reload();
          })
          .catch(function () { toast.error('Could not upload. Please try again.'); })
          .finally(function () {
            uploadBtn.classList.remove('is-busy');
            uploadBtn.disabled = false;
            uploadInput.value = '';
          });
      });
    }

    gallery.addEventListener('click', function (event) {
      var primary = event.target.closest('[data-image-primary]');
      var remove = event.target.closest('[data-image-delete]');

      if (primary) {
        event.preventDefault();
        api.request(primary.getAttribute('data-image-primary'), { method: 'PATCH' })
          .then(function (result) {
            var problem = api.transportProblem(result);

            if (problem || !result.body.success) {
              toast.error(problem || result.body.message);
              return;
            }

            toast.success(result.body.message);
            window.location.reload();
          })
          .catch(function () { toast.error('Could not reach the server. Please try again.'); });
        return;
      }

      if (remove) {
        event.preventDefault();

        confirmAction({
          title: 'Remove this image?',
          message: 'It will be deleted from the server and from the product page.',
          confirmText: 'Remove'
        }).then(function (confirmed) {
          if (!confirmed) return;

          api.request(remove.getAttribute('data-image-delete'), { method: 'DELETE' })
            .then(function (result) {
              var problem = api.transportProblem(result);

              if (problem || !result.body.success) {
                toast.error(problem || result.body.message);
                return;
              }

              toast.success(result.body.message);
              var item = remove.closest('.gallery-item');
              if (item) item.remove();
            })
            .catch(function () { toast.error('Could not reach the server. Please try again.'); });
        });
      }
    });
  }

  /* ================================================================ filters */

  function initFilters() {
    var form = document.querySelector('[data-filter-form]');
    if (!form) return;

    // Selects apply immediately; the search box waits for Enter or the button,
    // so the page does not reload on every keystroke.
    form.querySelectorAll('select').forEach(function (select) {
      select.addEventListener('change', function () { form.submit(); });
    });

    var reset = form.querySelector('[data-filter-reset]');
    if (reset) {
      reset.addEventListener('click', function (event) {
        event.preventDefault();
        window.location.assign(form.getAttribute('data-filter-reset-url') || window.location.pathname);
      });
    }
  }

  /* ========================================================= page repeaters */

  /**
   * The row lists on the Pages form - the home page's FAQ, for one.
   *
   * Generic on purpose: a new row is cloned from a <template> the server
   * rendered, so this never needs to know what sub-fields a repeater has. All
   * it does is name the inputs and keep the numbering straight.
   */
  function initContentRepeaters() {
    document.querySelectorAll('[data-content-repeater]').forEach(function (host) {
      var rows = host.querySelector('[data-repeat-rows]');
      var template = host.querySelector('[data-repeat-template]');
      var addBtn = host.querySelector('[data-repeat-add]');
      if (!rows || !template) return;

      var base = host.getAttribute('data-repeat-name');
      var max = parseInt(host.getAttribute('data-repeat-max') || '30', 10);
      var label = host.getAttribute('data-repeat-label') || 'Row';
      var counter = host.closest('.field').querySelector('[data-repeat-count]');

      /**
       * Renames every input to match its position.
       *
       * Done after any change rather than tracked as rows are added, because
       * moving and removing rows would otherwise leave gaps in the indexes -
       * and the order in the payload is the order on the page.
       */
      function reindex() {
        var all = rows.querySelectorAll('.repeat-row');

        all.forEach(function (row, index) {
          var number = row.querySelector('.repeat-row__n');
          if (number) number.textContent = index + 1;

          row.querySelectorAll('[data-repeat-field], input, textarea').forEach(function (field) {
            var key = field.getAttribute('data-repeat-field');

            if (!key && field.name) {
              // An existing row: recover the sub-field from its current name.
              var match = field.name.match(/\[([^\[\]]+)\]$/);
              key = match ? match[1] : null;
            }

            if (key) field.name = base + '[' + index + '][' + key + ']';
          });
        });

        if (counter) counter.textContent = all.length;
        if (addBtn) addBtn.disabled = all.length >= max;
      }

      if (addBtn) {
        addBtn.addEventListener('click', function () {
          if (rows.querySelectorAll('.repeat-row').length >= max) {
            toast.info('Up to ' + max + ' ' + label.toLowerCase() + ' rows.');
            return;
          }

          rows.appendChild(template.content.cloneNode(true));
          reindex();

          var added = rows.lastElementChild;
          var first = added && added.querySelector('input, textarea');
          if (first) first.focus();
        });
      }

      host.addEventListener('click', function (event) {
        var row = event.target.closest('.repeat-row');
        if (!row || !rows.contains(row)) return;

        if (event.target.closest('[data-repeat-remove]')) {
          row.remove();
          reindex();
          return;
        }

        if (event.target.closest('[data-repeat-up]') && row.previousElementSibling) {
          rows.insertBefore(row, row.previousElementSibling);
          reindex();
          return;
        }

        if (event.target.closest('[data-repeat-down]') && row.nextElementSibling) {
          rows.insertBefore(row.nextElementSibling, row);
          reindex();
        }
      });

      reindex();
    });
  }

  /* The same rule the server uses when it derives a slug, so nothing here
     promises a URL the save will not produce. */
  function slugify(value) {
    return String(value || '')
      .toLowerCase()
      .normalize('NFD').replace(/[̀-ͯ]/g, '')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '');
  }

  /* ============================================================= slug field */

  /**
   * A URL field that follows another field as it is typed.
   *
   * The category form wants the slug visible while the name is being written,
   * not only after the save - so this fills the input rather than only the
   * placeholder. It stops the moment the operator edits the slug themselves,
   * and it never touches a slug that already exists, because an edit screen's
   * URL is already linked to and indexed.
   *
   * The rule matches App\Services\Catalogue\SlugGenerator, so what is shown
   * here is what the save will produce - apart from the "-2" the server adds
   * when the slug is already taken, which the browser cannot know about.
   */
  function initSlugFields() {
    document.querySelectorAll('[data-slug-from]').forEach(function (field) {
      var source = document.getElementById(field.getAttribute('data-slug-from'));
      if (!source) return;

      // A slug that arrived with a value is one the storefront already uses.
      var locked = field.value.trim() !== '';

      field.addEventListener('input', function () { locked = true; });

      source.addEventListener('input', function () {
        if (locked) return;
        field.value = slugify(source.value);
      });
    });
  }

  /* ============================================================ seo preview */

  /**
   * The search-result preview on the journal form.
   *
   * It is an approximation, not a promise - a search engine rewrites titles
   * and descriptions whenever it thinks it can do better. What it is good for
   * is showing the operator how much room they actually have, which is the
   * part character counters alone never make obvious.
   */
  function initSeoPreview() {
    var card = document.querySelector('[data-seo]');
    if (!card) return;

    var base = card.getAttribute('data-seo-base') || '/blogs/';
    var site = card.getAttribute('data-seo-site') || '';

    var title = document.getElementById('meta_title');
    var desc = document.getElementById('meta_description');
    var slug = document.getElementById('slug');
    var postTitle = document.getElementById('title');
    var excerpt = document.getElementById('excerpt');

    var outUrl = card.querySelector('[data-seo-url]');
    var outTitle = card.querySelector('[data-seo-title]');
    var outDesc = card.querySelector('[data-seo-desc]');

    function clip(value, limit) {
      value = String(value || '').replace(/\s+/g, ' ').trim();
      return value.length > limit ? value.slice(0, limit - 1).trimEnd() + '…' : value;
    }

    function paint() {
      var shownTitle = (title.value.trim() || (postTitle ? postTitle.value.trim() : '')) || 'Untitled post';

      /* The site name is appended by the storefront, so the preview appends it
         too - it is part of the length the operator is writing against. */
      if (site && shownTitle.indexOf(site) === -1) shownTitle += ' - ' + site;

      var shownDesc = desc.value.trim()
        || (excerpt ? excerpt.value.trim() : '')
        || 'No description yet — search engines will pick a sentence from the post.';

      var shownSlug = slugify(slug && slug.value.trim() ? slug.value : (postTitle ? postTitle.value : ''))
        || 'your-post';

      outUrl.textContent = base + shownSlug;
      outTitle.textContent = clip(shownTitle, 60);
      outDesc.textContent = clip(shownDesc, 160);

      count(title);
      count(desc);
    }

    /* "42 / 60" beside the label, turning amber once the field is over its
       useful length rather than blocking the operator. */
    function count(field) {
      if (!field) return;

      var readout = card.querySelector('[data-count-for="' + field.id + '"]');
      if (!readout) return;

      var max = parseInt(field.getAttribute('data-count-max') || '0', 10);
      var length = field.value.trim().length;

      readout.textContent = length ? length + ' / ' + max : '';
      readout.classList.toggle('is-over', max > 0 && length > max);
    }

    [title, desc, slug, postTitle, excerpt].forEach(function (field) {
      if (field) field.addEventListener('input', paint);
    });

    /* A blank slug on a new post follows the title, which is what the server
       will do - but an operator who has typed one keeps it. */
    if (slug && postTitle && !slug.value) {
      postTitle.addEventListener('input', function () {
        if (slug.dataset.touched === '1') return;
        slug.placeholder = slugify(postTitle.value) || 'worked-out-from-the-title';
      });

      slug.addEventListener('input', function () { slug.dataset.touched = '1'; });
    }

    paint();
  }

  /* ====================================================== journal takeaways */

  /**
   * The "short version" list under a post. Simpler than the spec repeater -
   * one field per row, and no starter row, because a post without a summary
   * is a perfectly normal post.
   */
  function initTakeawayRepeater() {
    var host = document.querySelector('[data-take-rows]');
    if (!host) return;

    var addBtn = document.querySelector('[data-take-add]');
    var max = parseInt(host.getAttribute('data-take-max') || '8', 10);

    function rowCount() {
      return host.querySelectorAll('.take-row').length;
    }

    addBtn.addEventListener('click', function () {
      if (rowCount() >= max) {
        toast.info('A post can have up to ' + max + ' lines in its summary.');
        return;
      }

      // Unique within this post only; Laravel re-keys the array and the
      // service drops blank lines before saving.
      var index = Date.now() + '-' + rowCount();

      var row = document.createElement('div');
      row.className = 'take-row';
      row.innerHTML =
        '<input type="text" class="field-input" maxlength="200" ' +
               'placeholder="One thing worth remembering" aria-label="Short-version line">' +
        '<button type="button" class="spec-remove" data-take-remove aria-label="Remove this line">' +
          '<i class="bi bi-trash"></i>' +
        '</button>';

      var input = row.querySelector('input');
      input.name = 'takeaways[' + index + ']';

      host.appendChild(row);
      input.focus();
    });

    host.addEventListener('click', function (event) {
      var remove = event.target.closest('[data-take-remove]');
      if (!remove) return;
      remove.closest('.take-row').remove();
    });
  }

  /* ========================================================== coupon form */

  /**
   * A fixed-amount code has no percentage to cap, so the maximum-discount
   * field is put away when that type is chosen - and cleared, because a
   * hidden input still posts, and the request rejects a maximum on a fixed
   * code rather than silently ignoring it.
   */
  function initCouponForm() {
    var type = document.querySelector('[data-coupon-type]');
    if (!type) return;

    var percentOnly = document.querySelector('[data-percent-only]');
    var unit = document.querySelector('[data-value-unit]');
    var max = document.getElementById('max_discount_amount');

    function sync() {
      var isPercent = type.value === 'percent';

      if (unit) unit.textContent = isPercent ? '%' : (unit.getAttribute('data-symbol') || '$');
      if (percentOnly) percentOnly.hidden = !isPercent;
      if (max && !isPercent) max.value = '';
    }

    type.addEventListener('change', sync);
    sync();
  }

  /* =================================================================== boot */

  document.addEventListener('DOMContentLoaded', function () {
    initTabs();
    initImagePickers();
    initSpecRepeater();
    initSectionRepeater();
    initPackRepeater();
    initDeleteButtons();
    initRestoreButtons();
    initToggleButtons();
    initStatusSelects();
    initGallery();
    initFilters();
    initCouponForm();
    initTakeawayRepeater();
    initSlugFields();
    initSeoPreview();
    initContentRepeaters();
  });

})();
