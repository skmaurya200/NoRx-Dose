/* Page script: shipping-policy - runs after common.js */
(function(){
  'use strict';
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---- tabs ---- */
  var tabs = Array.prototype.slice.call(document.querySelectorAll('.tab'));
  var panels = Array.prototype.slice.call(document.querySelectorAll('.panel'));

  function show(i){
    tabs.forEach(function(t, n){
      t.classList.toggle('is-on', n === i);
      t.setAttribute('aria-selected', n === i);
    });
    panels.forEach(function(p, n){ p.classList.toggle('is-on', n === i); });
    history.replaceState(null, '', '#' + tabs[i].getAttribute('aria-controls'));
  }

  tabs.forEach(function(t, i){
    t.addEventListener('click', function(){ show(i); });
    /* arrow-key navigation between tabs */
    t.addEventListener('keydown', function(e){
      var next = e.key === 'ArrowRight' ? i + 1 : e.key === 'ArrowLeft' ? i - 1 : null;
      if(next === null) return;
      e.preventDefault();
      next = (next + tabs.length) % tabs.length;
      tabs[next].focus();
      show(next);
    });
  });

  /* open the tab named in the URL hash, if any */
  var hash = location.hash.replace('#','');
  var start = panels.findIndex(function(p){ return p.id === hash; });
  if(start > -1) show(start);

  /* ---- FAQ accordion ---- */
  document.querySelectorAll('.q').forEach(function(q){
    var btn = q.querySelector('.q__btn');
    var panel = q.querySelector('.q__panel');
    btn.setAttribute('aria-expanded','false');
    btn.addEventListener('click', function(){
      var open = q.classList.contains('is-open');
      document.querySelectorAll('.q.is-open').forEach(function(o){
        o.classList.remove('is-open');
        o.querySelector('.q__panel').style.maxHeight = null;
        o.querySelector('.q__btn').setAttribute('aria-expanded','false');
      });
      if(!open){
        q.classList.add('is-open');
        panel.style.maxHeight = panel.scrollHeight + 'px';
        btn.setAttribute('aria-expanded','true');
      }
    });
  });
})();
