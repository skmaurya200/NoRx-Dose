/* Page script: blog-details - runs after common.js and cart-store.js */
(function(){
  'use strict';

  /* ---- copy the post's own URL ---- */
  var btn = document.getElementById('copyLink');
  if(!btn) return;

  btn.addEventListener('click', function(){
    var label = btn.querySelector('svg') ? btn.innerHTML : '';
    var done = function(){
      btn.textContent = 'Link copied';
      setTimeout(function(){ btn.innerHTML = label; }, 1600);
    };

    if(navigator.clipboard){
      navigator.clipboard.writeText(window.location.href).then(done).catch(done);
    } else {
      done();
    }
  });
})();
