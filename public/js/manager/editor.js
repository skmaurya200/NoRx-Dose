/* NoRx Dose - the post editor

   A small WYSIWYG built on a contenteditable, rather than a library.

   The reason is the output. App\Support\HtmlSanitizer stores an allow-list of
   plain semantic tags - p, h2, h3, ul, ol, li, a, blockquote and so on - and
   most editors ship their own conventions on top of that (class-based
   alignment, bullet lists rendered as <ol> with a data attribute, wrapper
   divs). Every one of those is stripped on save, so what the author sees and
   what the storefront prints would drift apart. Writing the toolbar here keeps
   the two identical.

   The commands are execCommand, which is formally deprecated and universally
   implemented; there is no replacement for rich-text editing that does not
   mean writing a selection model from scratch. */
(function (window, document) {
  'use strict';

  function init(host) {
    if (host.dataset.ready === '1') return;
    host.dataset.ready = '1';

    var input = document.getElementById(host.getAttribute('data-editor-input'));
    if (!input) return;

    var area = host.querySelector('[data-editor-area]');
    var toolbar = host.querySelector('[data-editor-toolbar]');
    var blockPicker = host.querySelector('[data-editor-block]');
    var source = host.querySelector('[data-editor-source]');
    var counter = host.querySelector('[data-editor-count]');

    /* The hidden input holds whatever was loaded from the database, so the
       editor starts from the stored markup rather than from the markup a
       previous session happened to leave in the DOM. */
    area.innerHTML = input.value || '';
    normalise();

    /* ------------------------------------------------------------ commands */

    /* Block-level commands go through formatBlock; the rest are direct. A
       second click on the active heading returns the line to a paragraph,
       which is what every editor does and what people expect. */
    function run(command, value) {
      area.focus();

      if (command === 'block') {
        // A toolbar button toggles: clicking the active one goes back to a
        // paragraph, which is what every editor does.
        setBlock(currentBlock() === value ? 'p' : value);
      } else if (command === 'blockSet') {
        /* From the paragraph/heading picker, which is not a toggle: choosing
           "Heading 3" means the line becomes an h3, even if it already was
           one. Only the buttons above toggle back to a paragraph. */
        setBlock(value);
      } else if (command === 'link') {
        makeLink();
      } else if (command === 'image') {
        pickImage();
      } else if (command === 'imageUrl') {
        insertImageByUrl();
      } else if (command === 'hr') {
        document.execCommand('insertHorizontalRule');
      } else if (command === 'clear') {
        document.execCommand('removeFormat');
        document.execCommand('unlink');
      } else {
        document.execCommand(command);
      }

      normalise();
      sync();
      paintState();
    }

    var BLOCKS = ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote'];

    /**
     * Turns the line the caret is on into <tag>.
     *
     * execCommand('formatBlock') is tried first because it handles a selection
     * spanning several paragraphs, but it cannot be relied on: browsers
     * disagree about whether the tag needs angle brackets, and it silently
     * does nothing when the caret is directly inside the contenteditable
     * rather than inside a block. So the result is checked, and the block is
     * rewritten by hand when the command did not take.
     */
    function setBlock(tag) {
      var blocks = selectedBlocks();

      if (!blocks.length) return;

      try {
        document.execCommand('formatBlock', false, '<' + tag + '>');
      } catch (e) {
        /* Ignored - the manual path below is the real implementation. */
      }

      // Took, and every line the selection covers is now the right tag.
      if (blocks.every(function (block) {
        return !block.parentNode || block.nodeName.toLowerCase() === tag;
      })) {
        return;
      }

      // It did not, so the lines are rewritten by hand. Re-read them: the
      // command may have replaced some of the nodes on its way to doing
      // nothing useful.
      var last = null;

      selectedBlocks().forEach(function (block) {
        var name = block.nodeName.toLowerCase();

        if (name === tag) {
          last = block;

          return;
        }

        /* A list is left to execCommand. Rewriting it here would fold every
           <li> into one heading, which is not what "make this a heading"
           means when the caret is in a bulleted line. */
        if (name === 'ul' || name === 'ol') {
          return;
        }

        var replacement = document.createElement(tag);

        while (block.firstChild) {
          replacement.appendChild(block.firstChild);
        }

        // An empty line still needs something to put the caret in, or the
        // heading collapses to nothing and cannot be typed into.
        if (!replacement.firstChild) {
          replacement.appendChild(document.createElement('br'));
        }

        block.parentNode.replaceChild(replacement, block);
        last = replacement;
      });

      if (last) selectInside(last);
    }

    /**
     * Every top-level block the selection touches.
     *
     * Top-level, so a caret inside <li> or <strong> resolves to the line that
     * contains it rather than to the tag immediately around it. A collapsed
     * caret gives one block; a selection dragged across three paragraphs
     * gives three, which is what makes "select it all and pick Heading 2"
     * behave the way it looks like it should.
     */
    function selectedBlocks() {
      var selection = window.getSelection();

      if (!selection || !selection.rangeCount) return [];

      var range = selection.getRangeAt(0);
      var first = topLevel(range.startContainer);
      var last = topLevel(range.endContainer);

      if (!first) return [];
      if (!last || first === last) return [first];

      var blocks = [];
      var node = first;

      while (node) {
        blocks.push(node);

        if (node === last) break;

        node = node.nextElementSibling;
      }

      // The end came before the start in document order, which means the two
      // are not siblings in the way assumed above. One line is the safe read.
      return blocks[blocks.length - 1] === last ? blocks : [first];
    }

    /**
     * The direct child of the editor that contains a node, or null when the
     * node is not inside one - the caret sitting straight in the
     * contenteditable, which normalise() turns into a <p> anyway.
     */
    function topLevel(node) {
      if (!node || !area.contains(node) || node === area) return null;

      while (node && node.parentNode !== area) {
        node = node.parentNode;
      }

      return node && node.nodeType === 1 ? node : null;
    }

    /** Puts the caret at the end of an element, so typing carries on there. */
    function selectInside(element) {
      var range = document.createRange();
      range.selectNodeContents(element);
      range.collapse(false);

      var selection = window.getSelection();
      selection.removeAllRanges();
      selection.addRange(range);
    }

    /* What the caret is sitting in, as a tag name.

       queryCommandValue is inconsistent across browsers - some answer "div",
       some answer "" inside a bare contenteditable - so the DOM is walked as
       a fallback. Anything that is not a block this editor produces reads as
       a paragraph, which is what the markup is normalised to anyway. */
    function currentBlock() {
      var reported = String(document.queryCommandValue('formatBlock') || '')
        .toLowerCase().replace(/[<>]/g, '');

      if (BLOCKS.indexOf(reported) > -1) return reported;

      var selection = window.getSelection();

      if (!selection || !selection.rangeCount) return 'p';

      var node = selection.anchorNode;

      while (node && node !== area) {
        if (node.nodeType === 1) {
          var tag = node.nodeName.toLowerCase();
          if (BLOCKS.indexOf(tag) > -1) return tag;
        }

        node = node.parentNode;
      }

      return 'p';
    }

    /**
     * The <a> the caret is inside, if any.
     *
     * Walks up rather than checking the node directly: a selection inside a
     * linked <strong> is still a selection inside the link, and that is the
     * case where "I cannot edit this link" actually bites.
     */
    function linkAtCaret() {
      var selection = window.getSelection();

      if (!selection || selection.rangeCount === 0) return null;

      var node = selection.getRangeAt(0).startContainer;

      while (node && node !== area) {
        if (node.nodeType === 1 && node.nodeName === 'A') return node;
        node = node.parentNode;
      }

      return null;
    }

    /**
     * A small panel inside the editor, rather than window.prompt.
     *
     * The native prompt is modal to the whole browser, unstyled, impossible to
     * validate as you type, and on some platforms it steals the selection the
     * link is supposed to wrap. This keeps the selection alive in a Range and
     * puts it back before the command runs.
     *
     * With the caret already in a link it edits that link instead - the address
     * comes back in the field and a Remove button appears - because a link you
     * can only ever add once is a link you are stuck with.
     */
    function makeLink() {
      var existing = linkAtCaret();
      var selection = window.getSelection();

      if (!existing && (!selection || selection.isCollapsed)) {
        window.AurumToast.info('Select the words you want to link, or put the cursor in a link to edit it.');
        return;
      }

      // Editing: work on the whole link, not on whatever part of it happens to
      // be selected.
      var range = document.createRange();

      if (existing) {
        range.selectNode(existing);
      } else {
        range = selection.getRangeAt(0).cloneRange();
      }

      openLinkModal(range, existing);
    }

    var linkModal = null;

    function openLinkModal(range, existing) {
      if (linkModal) closeLinkModal();

      linkModal = document.createElement('div');
      linkModal.className = 'ed-modal';
      linkModal.innerHTML =
        '<div class="ed-modal__box" role="dialog" aria-modal="true" aria-label="Link">' +
          '<label class="ed-modal__label" for="edLinkUrl">Link address</label>' +
          '<input type="url" id="edLinkUrl" class="ed-modal__input" ' +
            'placeholder="https://example.com" autocomplete="off" spellcheck="false">' +
          '<p class="ed-modal__hint">https://, mailto:, tel:, or a path beginning with /</p>' +
          '<p class="ed-modal__error" role="alert" hidden></p>' +
          '<div class="ed-modal__actions">' +
            (existing
              ? '<button type="button" class="ed-modal__btn ed-modal__btn--off" data-link-remove>Remove link</button>'
              : '') +
            '<button type="button" class="ed-modal__btn" data-link-cancel>Cancel</button>' +
            '<button type="button" class="ed-modal__btn ed-modal__btn--go" data-link-apply>' +
              (existing ? 'Save link' : 'Add link') +
            '</button>' +
          '</div>' +
        '</div>';

      host.appendChild(linkModal);

      var field = linkModal.querySelector('#edLinkUrl');
      var error = linkModal.querySelector('.ed-modal__error');

      if (existing) field.value = existing.getAttribute('href') || '';

      var fail = function (message) {
        error.textContent = message;
        error.hidden = false;
        field.focus();
      };

      /* The selection was lost to the modal's own field, so it is put back
         before any command that needs it. */
      var restore = function () {
        var selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
      };

      var settle = function () {
        normalise();
        sync();
        paintState();
      };

      var apply = function () {
        var url = field.value.trim();

        if (url === '') {
          fail('Type an address first.');
          return;
        }

        /* The sanitiser drops javascript: and data: hrefs on save. Saying so
           here means the author finds out now rather than wondering later why
           their link vanished. */
        if (!/^(https?:|mailto:|tel:|\/|#)/i.test(url)) {
          fail('Links must start with https://, mailto:, tel: or /.');
          return;
        }

        closeLinkModal();

        if (existing) {
          // Editing in place keeps the surrounding markup intact; unlinking and
          // relinking would flatten anything inside the anchor.
          existing.setAttribute('href', url);
          area.focus();
        } else {
          restore();
          document.execCommand('createLink', false, url);
        }

        settle();
      };

      var remove = function () {
        closeLinkModal();
        restore();
        document.execCommand('unlink');
        area.focus();
        settle();
      };

      linkModal.querySelector('[data-link-apply]').addEventListener('click', apply);
      linkModal.querySelector('[data-link-cancel]').addEventListener('click', closeLinkModal);

      var removeBtn = linkModal.querySelector('[data-link-remove]');
      if (removeBtn) removeBtn.addEventListener('click', remove);

      linkModal.addEventListener('click', function (event) {
        if (event.target === linkModal) closeLinkModal();
      });

      field.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') { event.preventDefault(); apply(); }
        if (event.key === 'Escape') { event.preventDefault(); closeLinkModal(); }
      });

      field.focus();
      field.select();
    }

    function closeLinkModal() {
      if (!linkModal) return;
      linkModal.remove();
      linkModal = null;
    }

    /* ---- images ---- */

    /**
     * Upload from the author's machine. The file goes to the panel API, which
     * stores it through PublicUpload and hands back a root-relative path -
     * relative, because a post outlives the domain it was written on.
     */
    function pickImage() {
      var endpoint = host.getAttribute('data-editor-upload');

      if (!endpoint) {
        insertImageByUrl();
        return;
      }

      var picker = document.createElement('input');
      picker.type = 'file';
      picker.accept = 'image/jpeg,image/png,image/webp';

      picker.addEventListener('change', function () {
        var file = picker.files && picker.files[0];
        if (!file) return;

        // Asked for before the upload: it is the one thing only the author
        // knows, and a picture with no alternative text is invisible to a
        // screen reader and to image search.
        var alt = window.prompt('Describe the image for screen readers and image search', '') || '';

        upload(file, alt, endpoint);
      });

      picker.click();
    }

    function upload(file, alt, endpoint) {
      var payload = new FormData();
      payload.append('image', file);
      payload.append('alt', alt);

      // Remembered before the request: focus is lost the moment the file
      // dialog opens, and the caret is where the picture has to land.
      var at = savedRange;

      window.AurumToast.info('Uploading…');

      window.AurumApi.request(endpoint, { method: 'POST', body: payload })
        .then(function (result) {
          var problem = window.AurumApi.transportProblem(result);

          if (problem || !result.body.success) {
            window.AurumToast.error(problem || result.body.message);
            return;
          }

          restore(at);
          placeImage(result.body.data.url, alt);
          window.AurumToast.success('Image added.');
        })
        .catch(function () {
          window.AurumToast.error('Could not upload that image. Please try again.');
        });
    }

    /** For a picture that already lives somewhere else. */
    function insertImageByUrl() {
      var url = window.prompt('Image address (https://…)', 'https://');
      if (!url) return;

      url = url.trim();

      if (!/^(https?:|\/)/i.test(url)) {
        window.AurumToast.error('Image addresses must start with https:// or /.');
        return;
      }

      placeImage(url, window.prompt('Describe the image for screen readers', '') || '');
    }

    function placeImage(url, alt) {
      var img = document.createElement('img');
      img.src = url;
      img.alt = alt;

      insertNode(img);
      normalise();
      sync();
    }

    /* The caret, kept across anything that steals focus - a file dialog, a
       prompt - so an inserted picture lands where the author was typing
       rather than at the end of the post. */
    var savedRange = null;

    function remember() {
      var selection = window.getSelection();

      if (selection && selection.rangeCount && area.contains(selection.anchorNode)) {
        savedRange = selection.getRangeAt(0).cloneRange();
      }
    }

    function restore(range) {
      if (!range) return;

      var selection = window.getSelection();

      try {
        selection.removeAllRanges();
        selection.addRange(range);
      } catch (e) {
        /* The range pointed at nodes that have since been replaced. The caret
           stays wherever focus put it, which is better than throwing and
           taking the rest of the command with it. */
      }
    }

    function insertNode(node) {
      var selection = window.getSelection();

      if (!selection || !selection.rangeCount || !area.contains(selection.anchorNode)) {
        area.appendChild(node);
        return;
      }

      var range = selection.getRangeAt(0);
      range.deleteContents();
      range.insertNode(node);
      range.setStartAfter(node);
      range.collapse(true);
      selection.removeAllRanges();
      selection.addRange(range);
    }

    /* ---------------------------------------------------------- normalising */

    /* Loose text typed straight into the editor is wrapped in a paragraph, so
       the stored body is never a bare text node the storefront cannot style.
       <div> is what contenteditable produces on Enter in some browsers; it is
       not on the allow-list, so it is converted rather than stripped later. */
    function normalise() {
      Array.prototype.slice.call(area.childNodes).forEach(function (node) {
        if (node.nodeType === 3 && node.textContent.trim() !== '') {
          var p = document.createElement('p');
          area.replaceChild(p, node);
          p.appendChild(node);
          return;
        }

        if (node.nodeType === 1 && node.nodeName === 'DIV') {
          var block = document.createElement('p');
          block.innerHTML = node.innerHTML;
          area.replaceChild(block, node);
        }
      });

      if (!area.innerHTML.trim()) area.innerHTML = '<p><br></p>';
    }

    /* ------------------------------------------------------------- plumbing */

    function sync() {
      input.value = area.innerHTML.trim() === '<p><br></p>' ? '' : area.innerHTML;
      paintCount();
    }

    function paintCount() {
      if (!counter) return;

      var words = area.textContent.trim().split(/\s+/).filter(Boolean).length;
      var minutes = Math.max(1, Math.ceil(words / 200));

      counter.textContent = words + (words === 1 ? ' word' : ' words') +
                            ' · about ' + minutes + ' min read';
    }

    /* Lights the buttons, and points the picker, at whatever the cursor is
       sitting in. */
    function paintState() {
      var block = currentBlock();

      if (blockPicker) {
        // A quote is a block too, and it has its own button; the picker falls
        // back to Paragraph rather than showing nothing selected.
        blockPicker.value = blockPicker.querySelector('[value="' + block + '"]') ? block : 'p';
      }

      toolbar.querySelectorAll('[data-cmd]').forEach(function (button) {
        var command = button.getAttribute('data-cmd');
        var on = false;

        if (command === 'block') {
          on = block === button.getAttribute('data-value');
        } else {
          try { on = document.queryCommandState(command); } catch (e) { on = false; }
        }

        button.classList.toggle('is-on', on);
        button.setAttribute('aria-pressed', on);
      });
    }

    toolbar.addEventListener('mousedown', function (event) {
      /* Kept before the default: clicking a button would otherwise blur the
         editor and throw away the selection the command needs. A select is
         left alone - preventing its mousedown would stop it opening. */
      var button = event.target.closest('[data-cmd]');
      if (button) event.preventDefault();
    });

    if (blockPicker) {
      /* Opening the picker blurs the editor, so the caret is remembered on the
         way in and put back before the command runs. */
      blockPicker.addEventListener('mousedown', remember);
      blockPicker.addEventListener('focus', remember);

      blockPicker.addEventListener('change', function () {
        /* Read the choice BEFORE anything else touches the picker.

           area.focus() below fires the editor's own focus handler, which runs
           paintState(), which points the picker back at whatever block the
           caret is in. Reading blockPicker.value after that returned the old
           block every time, so the command applied was "leave it as it is"
           and nothing appeared to happen. */
        var chosen = blockPicker.value;

        /* Focus first, then put the caret back: focusing a contenteditable
           moves the selection on its own, so restoring before the focus would
           be undone by it. */
        area.focus();
        restore(savedRange);

        run('blockSet', chosen);

        /* Hand the keyboard back to the editor. Left focused, the next arrow
           key would change the picker instead of moving the caret. */
        blockPicker.blur();
        area.focus();
      });
    }

    toolbar.addEventListener('click', function (event) {
      var button = event.target.closest('[data-cmd]');
      if (!button) return;

      event.preventDefault();
      run(button.getAttribute('data-cmd'), button.getAttribute('data-value'));
    });

    area.addEventListener('input', function () { normalise(); sync(); });
    area.addEventListener('keyup', function () { remember(); paintState(); });
    area.addEventListener('mouseup', function () { remember(); paintState(); });
    area.addEventListener('blur', remember);
    area.addEventListener('focus', paintState);

    /* Paste as plain text. Pasting from a web page is exactly how a tracking
       script or a wall of foreign markup gets into a post; the sanitiser would
       catch it on save, but the author would have spent the meantime editing
       something that was about to change under them. */
    area.addEventListener('paste', function (event) {
      event.preventDefault();

      var text = (event.clipboardData || window.clipboardData).getData('text/plain');
      document.execCommand('insertText', false, text);
    });

    /* The usual shortcuts, so the toolbar is not the only way in. */
    area.addEventListener('keydown', function (event) {
      if (!(event.metaKey || event.ctrlKey)) return;

      var key = event.key.toLowerCase();
      var map = { b: 'bold', i: 'italic', u: 'underline', k: 'link' };

      if (map[key]) {
        event.preventDefault();
        run(map[key]);
      }
    });

    /* ---------------------------------------------------------- source view */

    if (source) {
      var toggle = host.querySelector('[data-editor-source-toggle]');

      toggle.addEventListener('click', function () {
        // Whether the source view is open right now - this click closes it.
        var wasOpen = !source.hidden;

        if (wasOpen) {
          // Coming back: take whatever was typed as HTML and render it.
          area.innerHTML = source.value;
          normalise();
          sync();
        } else {
          source.value = area.innerHTML;
        }

        source.hidden = wasOpen;
        area.hidden = !wasOpen;
        toolbar.classList.toggle('is-off', !wasOpen);
        toggle.classList.toggle('is-on', !wasOpen);
        toggle.setAttribute('aria-pressed', String(!wasOpen));

        if (wasOpen) {
          // The caret is back in the editor, so the toolbar should say what it
          // is sitting in rather than whatever it last showed.
          area.focus();
          paintState();
        } else {
          source.focus();
        }
      });

      source.addEventListener('input', function () {
        input.value = source.value;
      });
    }

    /* A last sync on submit, in case the caret was still in the editor and no
       input event had fired for the final keystroke. */
    var form = host.closest('form');
    if (form) {
      form.addEventListener('submit', function () {
        if (source && !source.hidden) {
          area.innerHTML = source.value;
          normalise();
        }
        sync();
      }, true);
    }

    sync();
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-editor]').forEach(init);
  });

  /* Editors that arrive after load - a repeater row added by hand - have to be
     started by whoever created them. init() is idempotent: it marks its host
     and returns early on a second call. */
  window.AurumEditor = {
    init: init,
    initAll: function (scope) {
      (scope || document).querySelectorAll('[data-editor]').forEach(init);
    }
  };
})(window, document);
