/* Page script: home - runs after common.js */
(function(){
  'use strict';

  /* ---- duplicate the badge marquee (the header ticker is in common.js) ---- */
  var badges = document.getElementById('badges');
  if(badges) badges.appendChild(badges.firstElementChild.cloneNode(true));
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---- hero entrance ---- */
  window.addEventListener('load', function(){
    document.getElementById('hero').classList.add('is-ready');
  });
  setTimeout(function(){
    document.getElementById('hero').classList.add('is-ready');
  }, 400);

  /* ---- scroll reveal ---- */
  var revealables = document.querySelectorAll('[data-reveal]');
  if(reduce || !('IntersectionObserver' in window)){
    revealables.forEach(function(el){ el.classList.add('is-in'); });
  } else {
    var io = new IntersectionObserver(function(entries){
      entries.forEach(function(en){
        if(en.isIntersecting){
          en.target.classList.add('is-in');
          io.unobserve(en.target);
        }
      });
    }, {threshold:.14, rootMargin:'0px 0px -40px 0px'});
    revealables.forEach(function(el){ io.observe(el); });
  }

  /* ---- animated counters ---- */
  function runCount(el){
    var target = parseFloat(el.dataset.count);
    var suffix = el.dataset.suffix || '';
    var comma  = el.hasAttribute('data-comma');
    var dur = 1500, start = null;

    if(reduce){
      el.textContent = (comma ? target.toLocaleString('en-US') : target) + suffix;
      return;
    }
    function step(ts){
      if(!start) start = ts;
      var p = Math.min((ts - start) / dur, 1);
      var eased = 1 - Math.pow(1 - p, 3);
      var val = Math.round(target * eased);
      el.textContent = (comma ? val.toLocaleString('en-US') : val) + suffix;
      if(p < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }

  var counters = document.querySelectorAll('[data-count]');
  if(!('IntersectionObserver' in window)){
    counters.forEach(runCount);
  } else {
    var cio = new IntersectionObserver(function(entries){
      entries.forEach(function(en){
        if(en.isIntersecting){
          runCount(en.target);
          cio.unobserve(en.target);
        }
      });
    }, {threshold:.5});
    counters.forEach(function(el){ cio.observe(el); });
  }

  /* ---- category row ----
     Drifts on its own so the far end is visible without being looked for, and
     steps on the arrows. One scrollLeft rather than a CSS transform: a
     transform cannot be scrolled, so the two would fight over the same row.

     The groups are identical, so passing one group's width is the same view as
     being back at zero - subtracting it there makes the wrap invisible. */
  (function(){
    var row = document.querySelector('[data-catmq]');
    if(!row) return;

    var view = row.querySelector('[data-catmq-view]');
    var track = row.querySelector('[data-catmq-track]');
    if(!view || !track) return;

    var groups = Math.max(1, parseInt(track.dataset.catmqGroups || '2', 10));

    function groupWidth(){
      return track.scrollWidth / groups;
    }

    function wrap(){
      var width = groupWidth();
      if(width <= 0) return;

      if(view.scrollLeft >= width) view.scrollLeft -= width;
      if(view.scrollLeft <= 0) view.scrollLeft += width;
    }

    /* Start just inside the first group, so stepping backwards on the first
       click has somewhere to go rather than stopping dead at zero. */
    view.scrollLeft = 1;

    row.querySelectorAll('[data-catmq-dir]').forEach(function(btn){
      btn.addEventListener('click', function(){
        var card = track.querySelector('.cat');
        var step = card ? card.getBoundingClientRect().width + 14 : 180;

        view.scrollBy({
          left: step * parseInt(btn.dataset.catmqDir, 10),
          behavior: reduce ? 'auto' : 'smooth'
        });
      });
    });

    view.addEventListener('scroll', wrap, {passive:true});

    if(reduce) return;

    /* Slow enough to read, and paused whenever somebody is using the row -
       a card that slides away under the pointer cannot be clicked. */
    var paused = false;
    ['pointerenter','focusin'].forEach(function(e){
      row.addEventListener(e, function(){ paused = true; });
    });
    ['pointerleave','focusout'].forEach(function(e){
      row.addEventListener(e, function(){ paused = false; });
    });

    var last = null;
    (function drift(now){
      window.requestAnimationFrame(drift);

      if(last === null){ last = now; return; }

      var elapsed = now - last;
      last = now;

      if(paused || document.hidden) return;

      view.scrollLeft += (elapsed / 1000) * 34;
      wrap();
    })();
  })();

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

  /* ---- active nav link on scroll ---- */
  var links = Array.prototype.slice.call(document.querySelectorAll('.nav__links a[href^="#"]'));
  var targets = links.map(function(a){ return document.querySelector(a.getAttribute('href')); }).filter(Boolean);
  if('IntersectionObserver' in window && targets.length){
    var nio = new IntersectionObserver(function(entries){
      entries.forEach(function(en){
        if(!en.isIntersecting) return;
        links.forEach(function(a){
          a.classList.toggle('is-active', a.getAttribute('href') === '#' + en.target.id);
        });
      });
    }, {rootMargin:'-45% 0px -50% 0px'});
    targets.forEach(function(t){ nio.observe(t); });
  }
})();
