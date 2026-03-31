(function () {
  'use strict';

  /* ── Navigation scroll ─────────────────────────────────── */
  var nav = document.getElementById('nav');
  if (nav) {
    window.addEventListener('scroll', function () {
      nav.classList.toggle('scrolled', window.scrollY > 40);
    }, { passive: true });
  }

  /* ── Menu burger ───────────────────────────────────────── */
  var burger = document.getElementById('nav-burger');
  var list   = document.getElementById('nav-list');

  window.toggleMenu = function () {
    var open = list.classList.toggle('open');
    burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    document.body.style.overflow = open ? 'hidden' : '';
  };
  window.closeMenu = function () {
    if (!list) return;
    list.classList.remove('open');
    if (burger) burger.setAttribute('aria-expanded', 'false');
    document.body.style.overflow = '';
  };

  /* ── Animations révélation au scroll ───────────────────── */
  if ('IntersectionObserver' in window) {
    var els = document.querySelectorAll('.axe,.bio,.act,.val,.inf,.diff-i,.photo,.evt-card');
    els.forEach(function (el) { el.classList.add('rv'); });
    var ob = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) {
          e.target.classList.add('vis');
          ob.unobserve(e.target);
        }
      });
    }, { threshold: 0.1 });
    els.forEach(function (el) { ob.observe(el); });
  }
}());
