/* NoRx Dose - shared behaviour (marquee, sticky shell, mobile menu)
   Loaded on every page via resources/views/components/baselayout.blade.php */
(function () {
  'use strict';

  /* ---- marquee loop: clone the group so the scroll never shows a gap ---- */
  var ticker = document.getElementById('ticker');
  if (ticker && ticker.firstElementChild) {
    ticker.appendChild(ticker.firstElementChild.cloneNode(true));
  }

  /* ---- sticky shell: publish its height so in-page sticky sidebars can sit
         clear of it without hard-coding the ticker + nav heights ---- */
  var shellTop = document.getElementById('shellTop');
  if (shellTop) {
    var syncHeight = function () {
      document.documentElement.style.setProperty(
        '--shell-top-h', shellTop.offsetHeight + 'px'
      );
    };
    if (window.ResizeObserver) {
      new ResizeObserver(syncHeight).observe(shellTop);
    } else {
      window.addEventListener('resize', syncHeight, { passive: true });
    }
    syncHeight();
  }

  /* ---- sticky nav ---- */
  var nav = document.getElementById('nav');
  if (nav) {
    var onScroll = function () {
      nav.classList.toggle('is-stuck', window.scrollY > 12);
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  /* ---- mobile search ----
     The desktop form stays the only search implementation. On narrow screens
     this button simply reveals that same form as a small dropdown. */
  var mobileSearchToggle = document.getElementById('mobileSearchToggle');
  var searchBox = document.getElementById('searchBox');
  var searchInput = document.getElementById('q');
  var setSearchOpen = null;

  if (nav && mobileSearchToggle && searchBox) {
    setSearchOpen = function (open) {
      nav.classList.toggle('is-search-open', open);
      mobileSearchToggle.setAttribute('aria-expanded', open);
      mobileSearchToggle.setAttribute('aria-label', open ? 'Close product search' : 'Open product search');

      if (open && searchInput) {
        window.requestAnimationFrame(function () { searchInput.focus(); });
      }
    };

    mobileSearchToggle.addEventListener('click', function () {
      setSearchOpen(!nav.classList.contains('is-search-open'));
    });

    document.addEventListener('click', function (event) {
      if (!nav.classList.contains('is-search-open')) return;
      if (searchBox.contains(event.target) || mobileSearchToggle.contains(event.target)) return;

      setSearchOpen(false);
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && nav.classList.contains('is-search-open')) {
        setSearchOpen(false);
        mobileSearchToggle.focus();
      }
    });
  }

  /* ---- mobile menu ---- */
  var burger = document.getElementById('burger');
  var menu = document.getElementById('menu');
  if (burger && menu) {
    /* The nav carries the state as well as the menu itself: the search box
       lives outside the menu in the markup but is shown alongside it at
       this width, and a class on their shared parent is what lets one rule
       reach both. */
    var setOpen = function (open) {
      if (open && setSearchOpen) setSearchOpen(false);
      menu.classList.toggle('is-open', open);
      if (nav) nav.classList.toggle('is-menu-open', open);
      burger.setAttribute('aria-expanded', open);
    };

    burger.addEventListener('click', function () {
      setOpen(!menu.classList.contains('is-open'));
    });

    menu.addEventListener('click', function (e) {
      if (e.target.tagName === 'A') setOpen(false);
    });
  }
})();
