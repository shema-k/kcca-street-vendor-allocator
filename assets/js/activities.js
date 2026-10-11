/**
 * KCCA VRSP — activity slideshow and theme toggle.
 *
 * The slideshow advances on its own, stops the moment a reader shows any
 * interest in it, and can be stopped and started again by hand. All three
 * are requirements of the same idea: an auto slideshow is only appropriate
 * while nobody is reading it.
 */

(function () {
  'use strict';

  /* ================================================================= */
  /* Slideshow                                                         */
  /* ================================================================= */
  function initSlideshows() {
    document.querySelectorAll('[data-slideshow]').forEach(function (root) {
      var slides = [].slice.call(root.querySelectorAll('.act'));
      if (slides.length < 2) return;

      var dots = [].slice.call(root.querySelectorAll('[data-slide-dot]'));
      var playBtn = root.querySelector('[data-slide-play]');
      var prev = root.querySelector('[data-slide-nav="prev"]');
      var next = root.querySelector('[data-slide-nav="next"]');

      var interval = parseInt(root.getAttribute('data-interval') || '5200', 10);
      var userControls = root.getAttribute('data-controls') !== '0';
      var timer = null;
      var index = 0;
      var stopped = false;

      /* A reader who asked for less motion should not have content moving
         without them asking. Show the first card and leave it. */
      var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

      function show(i) {
        index = (i + slides.length) % slides.length;
        slides.forEach(function (s, n) {
          var on = n === index;
          s.classList.toggle('on', on);
          if (on) { s.removeAttribute('aria-hidden'); } else { s.setAttribute('aria-hidden', 'true'); }
        });
        dots.forEach(function (d, n) {
          var on = n === index;
          d.classList.toggle('on', on);
          d.setAttribute('aria-selected', on ? 'true' : 'false');
        });
      }

      function stop() {
        stopped = true;
        if (timer) { clearInterval(timer); timer = null; }
        if (playBtn) {
          playBtn.setAttribute('aria-pressed', 'true');
          playBtn.setAttribute('aria-label', 'Play the slideshow');
          playBtn.title = 'Play';
          playBtn.innerHTML = '\u25b6';
        }
      }

      function start() {
        if (reduce) return;            // never auto-advance for reduced motion
        stopped = false;
        if (timer) { clearInterval(timer); }
        timer = setInterval(function () { show(index + 1); }, Math.max(1500, interval));
        if (playBtn) {
          playBtn.setAttribute('aria-pressed', 'false');
          playBtn.setAttribute('aria-label', 'Pause the slideshow');
          playBtn.title = 'Pause';
          playBtn.innerHTML = '\u2759\u2759';
        }
      }

      /* Controls. */
      dots.forEach(function (d) {
        d.addEventListener('click', function () {
          show(parseInt(d.getAttribute('data-slide-dot'), 10));
          if (!reduce) start();
        });
      });
      if (prev) { prev.addEventListener('click', function () { show(index - 1); }); }
      if (next) { next.addEventListener('click', function () { show(index + 1); }); }
      if (playBtn) {
        playBtn.addEventListener('click', function () {
          if (stopped) { start(); } else { stop(); }
        });
      }

      /* Any interest from the reader stops the rotation: hover, focus, or
         a touch. It is restarted by the play button only. */
      ['mouseenter', 'focusin', 'touchstart'].forEach(function (ev) {
        root.addEventListener(ev, stop, { passive: true });
      });

      /* Keyboard: left and right, and Home and End. */
      root.addEventListener('keydown', function (e) {
        var k = e.key;
        if (k === 'ArrowLeft')  { e.preventDefault(); show(index - 1); stop(); }
        if (k === 'ArrowRight') { e.preventDefault(); show(index + 1); stop(); }
        if (k === 'Home')       { e.preventDefault(); show(0); stop(); }
        if (k === 'End')        { e.preventDefault(); show(slides.length - 1); stop(); }
      });

      show(0);
      if (userControls && !reduce) { start(); }
    });
  }

  /* ================================================================= */
  /* Theme                                                             */
  /* ================================================================= */
  var KEY = 'kcca-theme';

  /* The choice is written to both stores. localStorage survives a reload;
     the cookie carries it to the server first, so the sign-in page — which
     has no localStorage-written stylesheet in time — can honour it too. */
  function store(theme) {
    try { localStorage.setItem(KEY, theme); } catch (e) {}
    document.cookie = KEY + '=' + theme + ';path=/;max-age=31536000;samesite=lax';
  }

  function read() {
    var c = document.cookie.match(new RegExp('(^| )' + KEY + '=([^;]+)'));
    if (c) return c[2];
    try { return localStorage.getItem(KEY); } catch (e) { return null; }
  }

  function apply(theme) {
    var root = document.documentElement;
    if (theme === 'dark' || theme === 'light') {
      root.setAttribute('data-theme', theme);
    } else {
      root.removeAttribute('data-theme');
    }
    document.querySelectorAll('[data-theme-toggle]').forEach(function (b) {
      b.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
      b.setAttribute('aria-label', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
    });
  }

  /* Applied from markup, before first paint, so there is no flash. */
  apply(read());

  function toggle() {
    var current = read();

    /* No stored choice: fall back to whatever the operating system says,
       then take the opposite. */
    var effective = current;
    if (!effective && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
      effective = 'dark';
    }

    var next = effective === 'dark' ? 'light' : 'dark';
    store(next);
    apply(next);
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-theme-toggle]');
    if (!btn) return;
    e.preventDefault();
    toggle();
  });

  function initThemeToggles() {
    document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
      var current = read();
      var effective = current;
      if (!effective && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
        effective = 'dark';
      }
      btn.setAttribute('aria-pressed', effective === 'dark' ? 'true' : 'false');
    });
  }

  function boot() {
    initSlideshows();
    initThemeToggles();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  window.KCCATheme = { toggle: toggle, apply: apply, read: read };
})();