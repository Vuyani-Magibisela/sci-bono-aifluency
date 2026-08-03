/*
 * AI Fluency Course v2 — Mini-game: Data Explosion (year slider)
 * Chapter 1.03 / lesson_id = 4.
 *
 * Slider from 1990 to 2024. Growing bar visualises exponential data volume.
 * Reaching 2024 marks the game done + awards XP.
 */

(function () {
  'use strict';

  var DATA = {
    prompt: 'Slide from 1990 to 2024 and watch the world’s data explode.',
    yearMin: 1990,
    yearMax: 2024,
    xpReward: 15
  };

  function volumeZB(year) {
    // 0.001 * 1.42^(year - 1990) — matches the prototype's formula.
    return 0.001 * Math.pow(1.42, year - DATA.yearMin);
  }

  function captionFor(year) {
    if (year <= 1992) return 'Barely a trickle. Only universities and hobbyists are online.';
    if (year <= 2000) return 'The web goes mainstream. Data is starting to pile up.';
    if (year <= 2010) return 'Smartphones flood the world with new data every second.';
    if (year <= 2018) return 'Social media, video streaming, sensors — the flood grows.';
    return 'A data explosion. All of it is fuel for modern AI.';
  }

  function mount(container, opts) {
    opts = opts || {};
    var state = { year: DATA.yearMin, awarded: false };

    container.innerHTML = ''
      + '<div class="v2-minigame-shell">'
      + '  <div class="v2-quiz-eyebrow">Mini-Game · Data Explosion</div>'
      + '  <h2 class="v2-minigame-shell__title">Slide Through the Years</h2>'
      + '  <p class="v2-minigame-shell__hint">' + escapeHtml(DATA.prompt) + '</p>'
      + '  <div class="v2-slider-wrap">'
      + '    <div class="v2-slider-volume" data-vol>&asymp; 0.001 ZB</div>'
      + '    <div class="v2-slider-bar-track"><div class="v2-slider-bar-fill" data-bar style="height:2%"></div></div>'
      + '    <div class="v2-slider-year" data-year>' + DATA.yearMin + '</div>'
      + '    <input class="v2-slider-input" type="range" min="' + DATA.yearMin + '" max="' + DATA.yearMax + '" step="1" value="' + DATA.yearMin + '" data-slider />'
      + '    <p class="v2-slider-caption" data-cap>' + escapeHtml(captionFor(DATA.yearMin)) + '</p>'
      + '  </div>'
      + '  <div class="v2-step-footer" data-footer></div>'
      + '</div>';

    var slider = container.querySelector('[data-slider]');
    var yearEl = container.querySelector('[data-year]');
    var volEl = container.querySelector('[data-vol]');
    var barEl = container.querySelector('[data-bar]');
    var capEl = container.querySelector('[data-cap]');
    var footer = container.querySelector('[data-footer]');

    slider.addEventListener('input', function () {
      state.year = parseInt(slider.value, 10);
      var vol = volumeZB(state.year);
      var pct = Math.min(100, 2 + Math.log(1 + vol * 12) * 20);
      yearEl.textContent = state.year;
      volEl.innerHTML = '&asymp; ' + formatZB(vol) + ' ZB';
      barEl.style.height = pct + '%';
      capEl.textContent = captionFor(state.year);

      if (state.year >= DATA.yearMax && !state.awarded) {
        state.awarded = true;
        if (opts.xpBurst) opts.xpBurst(DATA.xpReward);
        if (opts.mascot) opts.mascot.say('That’s the data explosion in a nutshell. Fuel for every modern AI.', { autoHideMs: 6000 });
        renderContinue();
      }
    });

    function renderContinue() {
      footer.innerHTML = '';
      var next = document.createElement('button');
      next.className = 'v2-btn v2-btn--primary';
      next.textContent = 'Continue';
      next.addEventListener('click', function () {
        if (opts.onComplete) opts.onComplete({ xpAwarded: DATA.xpReward });
      });
      footer.appendChild(next);
    }
  }

  function formatZB(v) {
    if (v < 0.01) return v.toFixed(3);
    if (v < 1) return v.toFixed(2);
    if (v < 100) return v.toFixed(1);
    return Math.round(v).toString();
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  window.V2Minigame_Slider = { mount: mount };
})();
