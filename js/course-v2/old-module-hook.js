/*
 * AI Fluency Course v2 — Old-UI module hook.
 *
 * Loaded into each student/modules/moduleN.html at the bottom of <body>.
 * Responsibilities:
 *   1. Redirect to v2 module page if the user opted into new UI.
 *   2. Otherwise, inject a "Try the new gamified experience" banner right after the
 *      existing "Enhanced Version Available" info banner.
 *
 * Module ID detection: extract it from the `module-dynamic.html?module_id=N` link that
 * every module page already contains as the "Switch Now" CTA.
 */

(function () {
  'use strict';

  function detectModuleId() {
    var link = document.querySelector('a[href*="module-dynamic.html"][href*="module_id="]');
    if (link) {
      var m = /module_id=(\d+)/.exec(link.getAttribute('href') || '');
      if (m) return parseInt(m[1], 10);
    }
    // Fall back to URL param on the current page (some pages might use it)
    var params = new URLSearchParams(window.location.search);
    var id = parseInt(params.get('module_id'), 10);
    return id || null;
  }

  function run() {
    if (!window.UIMode) return;
    var moduleId = detectModuleId();
    if (!moduleId) return;

    // If user chose new UI, redirect.
    if (UIMode.getMode() === UIMode.MODE_NEW) {
      window.location.href = UIMode.urlForV2Module(moduleId);
      return;
    }

    // Otherwise inject the v2 promo banner after the existing banner.
    var existing = document.querySelector('.info-banner');
    if (!existing) return;

    var banner = document.createElement('div');
    banner.style.cssText = 'background: linear-gradient(135deg, #0b2c52, #06203f); color: #fff; padding: 1.25rem; margin-bottom: 1.5rem; border-radius: 12px; box-shadow: 0 4px 16px rgba(0,0,0,0.25); border: 1px solid rgba(47,212,221,0.3);';
    banner.innerHTML = ''
      + '<div style="display: flex; align-items: center; gap: 1rem;">'
      + '  <div style="font-size: 2rem;">&#10024;</div>'
      + '  <div style="flex: 1;">'
      + '    <p style="margin: 0; font-weight: 700; font-size: 1.05rem; font-family: \'Baloo 2\', Nunito, sans-serif;">Try the new gamified experience</p>'
      + '    <p style="margin: 0.4rem 0 0 0; opacity: 0.85; font-size: 0.95rem;">XP, badges, mini-games and a robot guide called Bono. Your progress carries over.</p>'
      + '  </div>'
      + '  <button type="button" data-v2-launch style="background: #2fd4dd; color: #06203f; padding: 0.75rem 1.4rem; border-radius: 999px; border: none; font-weight: 800; white-space: nowrap; cursor: pointer; font-size: 0.95rem;">Try new UI &rarr;</button>'
      + '</div>';

    existing.parentNode.insertBefore(banner, existing.nextSibling);

    banner.querySelector('[data-v2-launch]').addEventListener('click', function () {
      UIMode.setMode(UIMode.MODE_NEW);
      window.location.href = UIMode.urlForV2Module(moduleId);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }
})();
