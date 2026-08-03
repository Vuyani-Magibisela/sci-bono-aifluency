/*
 * AI Fluency Course v2 — UI mode + first-visit popup + persistent switcher.
 *
 * Storage keys (localStorage):
 *   aihub_ui_mode        = 'old' | 'new'  (default: 'old')
 *   aihub_ui_popup_seen  = '1'            (set once the intro popup was shown)
 *
 * Loaded by BOTH old and new UI pages. Exposes window.UIMode.
 */

(function () {
  'use strict';

  var KEY_MODE = 'aihub_ui_mode';
  var KEY_POPUP = 'aihub_ui_popup_seen';
  var MODE_OLD = 'old';
  var MODE_NEW = 'new';

  // Courses that get the v2 experience. Update when new courses receive the redesign.
  var V2_COURSE_IDS = [1]; // 1 = AI Fluency

  function getMode() {
    try {
      var m = localStorage.getItem(KEY_MODE);
      return m === MODE_NEW ? MODE_NEW : MODE_OLD;
    } catch (e) {
      return MODE_OLD;
    }
  }

  function setMode(mode) {
    try {
      localStorage.setItem(KEY_MODE, mode === MODE_NEW ? MODE_NEW : MODE_OLD);
    } catch (e) {}
  }

  function popupSeen() {
    try { return localStorage.getItem(KEY_POPUP) === '1'; } catch (e) { return false; }
  }

  function markPopupSeen() {
    try { localStorage.setItem(KEY_POPUP, '1'); } catch (e) {}
  }

  function isV2Course(courseId) {
    var id = parseInt(courseId, 10);
    return !isNaN(id) && V2_COURSE_IDS.indexOf(id) !== -1;
  }

  /**
   * Resolve a path relative to the site root. Handles both /student/... and root-hosted pages.
   */
  function siteRoot() {
    // Detect based on current path — if we're under /student/ (or deeper), root is above.
    var p = window.location.pathname;
    if (p.indexOf('/student/') !== -1) {
      return p.substring(0, p.indexOf('/student/') + 1); // trailing slash
    }
    // Fall back to origin root.
    return '/';
  }

  function urlForV2Course(courseId) {
    return siteRoot() + 'student/course-v2/course.html?course_id=' + encodeURIComponent(courseId);
  }

  function urlForV2Module(moduleId) {
    return siteRoot() + 'student/course-v2/module.html?module_id=' + encodeURIComponent(moduleId);
  }

  function urlForOldCourse(courseId) {
    return siteRoot() + 'student/course-view.html?course_id=' + encodeURIComponent(courseId);
  }

  /**
   * Show the first-visit popup on the course landing page.
   * Called from the OLD UI's course-view.html. No-op if:
   *   - already in new UI
   *   - popup already seen
   *   - course is not in V2_COURSE_IDS
   */
  function showPopupIfEligible(courseId) {
    if (!isV2Course(courseId)) return;
    if (getMode() === MODE_NEW) return;
    if (popupSeen()) return;
    if (document.querySelector('.v2-modal-backdrop')) return;

    injectStylesheet(siteRoot() + 'css/course-v2/tokens.css');
    injectStylesheet(siteRoot() + 'css/course-v2/animations.css');
    injectStylesheet(siteRoot() + 'css/course-v2/shell.css');
    injectFonts();

    var backdrop = document.createElement('div');
    backdrop.className = 'v2-modal-backdrop';
    backdrop.innerHTML = ''
      + '<div class="v2-modal" role="dialog" aria-modal="true" aria-labelledby="v2-popup-title">'
      + '  <button class="v2-modal__close" type="button" aria-label="Close">&times;</button>'
      + '  <div class="v2-modal__badge">New experience</div>'
      + '  <h2 class="v2-modal__title" id="v2-popup-title">A fresh way to learn AI</h2>'
      + '  <p class="v2-modal__body">We\'ve redesigned this course with a gamified, mission-based experience — XP, badges, mini-games, and your robot guide Bono. Your progress carries over, and you can switch back to the classic view anytime.</p>'
      + '  <div class="v2-modal__actions">'
      + '    <button class="v2-btn v2-btn--secondary" type="button" data-v2-popup-dismiss>Not now</button>'
      + '    <button class="v2-btn v2-btn--primary" type="button" data-v2-popup-accept>Try the new experience</button>'
      + '  </div>'
      + '</div>';

    document.body.appendChild(backdrop);

    var close = function () {
      markPopupSeen();
      if (backdrop.parentNode) backdrop.parentNode.removeChild(backdrop);
    };

    backdrop.querySelector('[data-v2-popup-dismiss]').addEventListener('click', close);
    backdrop.querySelector('.v2-modal__close').addEventListener('click', close);
    backdrop.addEventListener('click', function (e) {
      if (e.target === backdrop) close();
    });
    document.addEventListener('keydown', function esc(e) {
      if (e.key === 'Escape') { close(); document.removeEventListener('keydown', esc); }
    });

    backdrop.querySelector('[data-v2-popup-accept]').addEventListener('click', function () {
      markPopupSeen();
      setMode(MODE_NEW);
      window.location.href = urlForV2Course(courseId);
    });
  }

  /**
   * Render a persistent header pill switching between old and new UI.
   * Used on BOTH sides. `context` = { courseId?, moduleId? } tells us where to send the user.
   */
  function renderSwitcher(container, context) {
    if (!container) return;
    context = context || {};
    var currentMode = getMode();
    var pill = document.createElement('a');
    pill.className = 'v2-mode-pill';
    pill.setAttribute('type', 'button');

    if (currentMode === MODE_NEW) {
      pill.innerHTML = '<span>&#8617;</span> Switch to classic';
      pill.href = context.courseId ? urlForOldCourse(context.courseId) : (siteRoot() + 'student/dashboard.html');
      pill.addEventListener('click', function () { setMode(MODE_OLD); });
    } else {
      // Only show "try new" in old UI when we're on a v2-eligible course.
      if (!context.courseId || !isV2Course(context.courseId)) return;
      pill.innerHTML = '<span>&#10024;</span> Try new experience';
      pill.href = urlForV2Course(context.courseId);
      pill.addEventListener('click', function () {
        markPopupSeen();
        setMode(MODE_NEW);
      });
    }

    container.appendChild(pill);
  }

  function injectStylesheet(href) {
    if (document.querySelector('link[href="' + href + '"]')) return;
    var link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = href;
    document.head.appendChild(link);
  }

  function injectFonts() {
    if (document.querySelector('link[data-v2-fonts]')) return;
    var preconnect1 = document.createElement('link');
    preconnect1.rel = 'preconnect';
    preconnect1.href = 'https://fonts.googleapis.com';
    preconnect1.setAttribute('data-v2-fonts', '1');
    document.head.appendChild(preconnect1);

    var preconnect2 = document.createElement('link');
    preconnect2.rel = 'preconnect';
    preconnect2.href = 'https://fonts.gstatic.com';
    preconnect2.crossOrigin = 'anonymous';
    document.head.appendChild(preconnect2);

    var link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = 'https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Nunito:wght@600;700;800&display=swap';
    document.head.appendChild(link);
  }

  window.UIMode = {
    MODE_OLD: MODE_OLD,
    MODE_NEW: MODE_NEW,
    getMode: getMode,
    setMode: setMode,
    isV2Course: isV2Course,
    v2CourseIds: V2_COURSE_IDS.slice(),
    showPopupIfEligible: showPopupIfEligible,
    renderSwitcher: renderSwitcher,
    urlForV2Course: urlForV2Course,
    urlForV2Module: urlForV2Module,
    urlForOldCourse: urlForOldCourse,
    siteRoot: siteRoot,
    injectFonts: injectFonts
  };
})();
