/*
 * AI Fluency Course v2 — Shell runtime.
 *
 * Builds the shared page shell (header, stage, mascot, toast, loader, error),
 * exposes a Mascot helper, and renders the XP pill / progress dots.
 */

(function () {
  'use strict';

  var LOGO_SRC = null; // Set via Shell.init({ logoSrc }) or defaults to a placeholder
  var siteRoot = function () { return (window.UIMode && window.UIMode.siteRoot) ? window.UIMode.siteRoot() : '/'; };

  // ============================================================
  // Root layout
  // ============================================================

  function mountRoot(container) {
    if (!container) throw new Error('Shell.mountRoot needs a container');
    container.classList.add('v2-root');
    container.innerHTML = ''
      + '<div class="v2-bg-orb v2-bg-orb--top-left"></div>'
      + '<div class="v2-bg-orb v2-bg-orb--bottom-right"></div>';
    return container;
  }

  // ============================================================
  // Header
  // ============================================================

  /**
   * Render the shared header.
   * @param {HTMLElement} container
   * @param {Object} opts
   *   contextLabel:  string     — eyebrow text (e.g. "Module 1 · AI Foundations")
   *   showXP:        boolean    — show the XP pill
   *   xp:            number     — current XP total
   *   showBack:      { href, label }  — optional back link
   *   showSwitcher:  { courseId?, moduleId? } — mount the ui-mode switcher pill
   *   dots:          { count, current } — chapter progress dots
   */
  function renderHeader(container, opts) {
    opts = opts || {};
    var header = document.createElement('header');
    header.className = 'v2-header';

    // Brand cluster: logo + eyebrow on top, Dashboard link below
    var brand = document.createElement('div');
    brand.className = 'v2-header__brand';
    var dashHref = siteRoot() + 'student/dashboard.html';
    brand.innerHTML = ''
      + '<div class="v2-header__brand-top">'
      + '  <a class="v2-header__logo-chip" href="' + escapeAttr(dashHref) + '" aria-label="Sci-Bono AI Hub home"><img src="' + escapeAttr(LOGO_SRC || (siteRoot() + 'images/favicon.ico')) + '" alt="Sci-Bono Discovery Centre" /></a>'
      + '  <div class="v2-header__eyebrow">Sci-Bono AI Hub</div>'
      + '</div>'
      + '<a class="v2-header__dashboard" href="' + escapeAttr(dashHref) + '" title="Back to dashboard" aria-label="Back to dashboard">'
      + '  <span aria-hidden="true">&larr;</span> Dashboard'
      + '</a>';
    header.appendChild(brand);

    // Center: context or progress dots
    var center = document.createElement('div');
    center.className = 'v2-header__center';
    if (opts.dots && opts.dots.count) {
      var dotWrap = document.createElement('div');
      dotWrap.className = 'v2-progress-dots';
      for (var i = 0; i < opts.dots.count; i++) {
        var dot = document.createElement('div');
        var isCurrent = i === opts.dots.current;
        var isDone = i < opts.dots.current;
        dot.className = 'v2-progress-dot' + (isCurrent ? ' v2-progress-dot--current' : '') + (isDone ? ' v2-progress-dot--done' : '');
        if (isCurrent) dot.textContent = (i + 1);
        dotWrap.appendChild(dot);
      }
      if (opts.contextLabel) {
        var label = document.createElement('div');
        label.className = 'v2-header__context';
        label.textContent = opts.contextLabel;
        center.appendChild(label);
      }
      center.appendChild(dotWrap);
    } else if (opts.contextLabel) {
      var label2 = document.createElement('div');
      label2.className = 'v2-header__context';
      label2.textContent = opts.contextLabel;
      center.appendChild(label2);
    }
    header.appendChild(center);

    // Right cluster: switcher + XP pill
    var right = document.createElement('div');
    right.style.display = 'flex';
    right.style.alignItems = 'center';
    right.style.gap = '12px';

    if (opts.showSwitcher && window.UIMode) {
      window.UIMode.renderSwitcher(right, opts.showSwitcher);
    }

    if (opts.showXP !== false) {
      var xpWrap = document.createElement('div');
      xpWrap.className = 'v2-xp-wrap';
      xpWrap.setAttribute('data-v2-xp', '1');
      xpWrap.innerHTML = ''
        + '<div class="v2-xp-pill">'
        + '  <svg viewBox="0 0 24 24" width="17" height="17" style="flex:none"><path d="M13 2 L4 14 h6 l-1 8 9-12 h-6 z" fill="#3a2606"/></svg>'
        + '  <span data-v2-xp-value>' + (parseInt(opts.xp || 0, 10)) + ' XP</span>'
        + '</div>';
      right.appendChild(xpWrap);
    }

    header.appendChild(right);
    container.appendChild(header);
    return header;
  }

  function updateXP(container, xp) {
    var el = container.querySelector('[data-v2-xp-value]');
    if (el) el.textContent = (parseInt(xp || 0, 10)) + ' XP';
  }

  function xpWrap(container) {
    return container.querySelector('[data-v2-xp]');
  }

  // ============================================================
  // Stage card
  // ============================================================

  function renderStage(container) {
    var stage = document.createElement('section');
    stage.className = 'v2-stage';
    stage.innerHTML = ''
      + '<div class="v2-stage__grid"></div>'
      + '<div class="v2-stage__glow"></div>'
      + '<div class="v2-stage__dot v2-stage__dot--1"></div>'
      + '<div class="v2-stage__dot v2-stage__dot--2"></div>'
      + '<div class="v2-stage__dot v2-stage__dot--3"></div>'
      + '<div class="v2-stage__dot v2-stage__dot--4"></div>'
      + '<div class="v2-stage__content" data-v2-stage-content></div>';
    container.appendChild(stage);
    return stage.querySelector('[data-v2-stage-content]');
  }

  // ============================================================
  // Mascot
  // ============================================================

  function attachMascot(stage) {
    if (!stage) return null;

    var wrap = document.createElement('div');
    wrap.className = 'v2-mascot';
    wrap.style.display = 'none';
    wrap.innerHTML = ''
      + '<div class="v2-mascot__bubble" data-mascot-bubble></div>'
      + '<img class="v2-mascot__img" src="' + escapeAttr(siteRoot() + 'images/course-v2/bono-mascot.svg') + '" alt="Bono the mascot" />';
    stage.appendChild(wrap); // sit at the bottom-right corner INSIDE the stage card

    var hideTimer = null;
    return {
      say: function (text, opts) {
        opts = opts || {};
        var bubble = wrap.querySelector('[data-mascot-bubble]');
        if (!bubble) return;
        bubble.textContent = text;
        wrap.style.display = 'flex';
        wrap.classList.remove('v2-anim-pop');
        void wrap.offsetWidth;
        wrap.classList.add('v2-anim-pop');
        if (hideTimer) clearTimeout(hideTimer);
        if (opts.autoHideMs) {
          hideTimer = setTimeout(function () { wrap.style.display = 'none'; }, opts.autoHideMs);
        }
      },
      hide: function () {
        if (hideTimer) clearTimeout(hideTimer);
        wrap.style.display = 'none';
      },
      element: wrap
    };
  }

  // ============================================================
  // Toast
  // ============================================================

  function toast(text, opts) {
    opts = opts || {};
    var el = document.createElement('div');
    el.className = 'v2-toast';
    el.textContent = text;
    document.body.appendChild(el);
    var lifespan = opts.durationMs || 3200;
    setTimeout(function () {
      el.classList.add('v2-toast--leaving');
      setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 260);
    }, lifespan);
  }

  // ============================================================
  // Loader / Error states
  // ============================================================

  function showLoader(container, text) {
    container.innerHTML = ''
      + '<div class="v2-loader">'
      + '  <div class="v2-loader__spinner"></div>'
      + '  <span>' + escapeHtml(text || 'Loading…') + '</span>'
      + '</div>';
  }

  function showError(container, message) {
    container.innerHTML = ''
      + '<div class="v2-error">'
      + '  <h2>Something went wrong</h2>'
      + '  <p>' + escapeHtml(message || 'Please try again.') + '</p>'
      + '  <a href="' + escapeAttr(siteRoot() + 'student/dashboard.html') + '" class="v2-btn v2-btn--secondary" style="margin-top:20px">Back to dashboard</a>'
      + '</div>';
  }

  // ============================================================
  // Auth gate
  // ============================================================

  function requireAuth() {
    if (typeof Auth !== 'undefined' && Auth.isAuthenticated && Auth.isAuthenticated()) {
      return Auth.getUser();
    }
    window.location.href = siteRoot() + 'public/login.html?redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
    return null;
  }

  // ============================================================
  // Utils
  // ============================================================

  function escapeHtml(s) {
    if (s == null) return '';
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function escapeAttr(s) {
    return escapeHtml(s);
  }

  function setDocTitle(title) {
    if (title) document.title = title;
  }

  window.Shell = {
    init: function (opts) {
      opts = opts || {};
      if (opts.logoSrc) LOGO_SRC = opts.logoSrc;
    },
    mountRoot: mountRoot,
    renderHeader: renderHeader,
    updateXP: updateXP,
    xpWrap: xpWrap,
    renderStage: renderStage,
    attachMascot: attachMascot,
    toast: toast,
    showLoader: showLoader,
    showError: showError,
    requireAuth: requireAuth,
    escapeHtml: escapeHtml,
    escapeAttr: escapeAttr,
    setDocTitle: setDocTitle
  };
})();
