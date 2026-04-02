(function () {
  'use strict';

  /* ── Thème automatique : écoute les changements système sombre/clair ─── */
  if (document.documentElement.getAttribute('data-auto-theme') === '1') {
    var mql = window.matchMedia && window.matchMedia('(prefers-color-scheme:dark)');
    if (mql && mql.addEventListener) {
      mql.addEventListener('change', function () {
        var h    = new Date().getHours();
        var dark = mql.matches;
        var t    = (h >= 20 || h < 7) ? 'aurore' : (dark ? 'foret' : 'lumiere');
        document.documentElement.setAttribute('data-theme', t);
      });
    }
  }

  /* ── Fond décoratif du hero (injecté en JS pour garder
        le contenu Gutenberg sans balise HTML brute)  ──────── */
  var hero = document.querySelector('.hero');
  if (hero) {
    if (!hero.querySelector('.hero__bg')) {
      var bg = document.createElement('div');
      bg.className = 'hero__bg';
      bg.setAttribute('aria-hidden', 'true');
      bg.innerHTML =
        '<svg viewBox="0 0 1440 900" preserveAspectRatio="xMidYMid slice" fill="none">' +
        '<path d="M-80,450 C120,180 320,720 560,380 C780,60 980,620 1200,400 C1330,290 1400,340 1520,310" stroke="rgba(194,139,54,0.07)" stroke-width="1.5"/>' +
        '<path d="M200,900 C300,600 480,820 640,500 C800,180 960,680 1100,350 C1200,140 1350,240 1520,180" stroke="rgba(158,55,16,0.05)" stroke-width="1"/>' +
        '<path d="M-120,200 C80,420 260,120 480,360 C700,600 820,200 1060,480 C1240,700 1380,500 1540,600" stroke="rgba(194,139,54,0.05)" stroke-width="2"/>' +
        '<ellipse cx="720" cy="450" rx="380" ry="240" stroke="rgba(194,139,54,0.04)" stroke-width="1"/>' +
        '</svg>';
      hero.prepend(bg);
    }
    if (!hero.querySelector('.hero__scrl')) {
      var scrl = document.createElement('div');
      scrl.className = 'hero__scrl';
      scrl.setAttribute('aria-hidden', 'true');
      scrl.textContent = 'Défiler';
      hero.appendChild(scrl);
    }
  }

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
