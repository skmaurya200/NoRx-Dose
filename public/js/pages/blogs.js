/* Page script: blogs - runs after common.js and cart-store.js

   Filtering itself is server-side now: the chips are links and the search box
   is a GET field, so a filtered view is a real URL that can be shared, linked
   and paginated. All this file does is save people pressing Enter. */
(function(){
  'use strict';

  var form = document.getElementById('blogFilter');
  var q = document.getElementById('q');

  if(form && q){
    var timer;

    /* Debounced, because every submit is a page load. 500ms is long enough to
       finish a word and short enough not to feel broken. */
    q.addEventListener('input', function(){
      window.clearTimeout(timer);
      timer = window.setTimeout(function(){ form.submit(); }, 500);
    });

    /* Enter submits immediately - waiting out the debounce after an explicit
       keystroke reads as a page that ignored you. */
    q.addEventListener('keydown', function(e){
      if(e.key !== 'Enter') return;
      e.preventDefault();
      window.clearTimeout(timer);
      form.submit();
    });
  }

  /* ---- newsletter ---- */
  var news = document.getElementById('newsForm');
  var ok = document.getElementById('newsOk');

  if(news){
    news.addEventListener('submit', function(e){
      e.preventDefault();
      ok.textContent = 'Thanks — check your inbox to confirm.';
      news.reset();
    });
  }
})();
