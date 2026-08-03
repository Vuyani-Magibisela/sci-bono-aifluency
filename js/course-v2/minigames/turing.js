/*
 * AI Fluency Course v2 — Mini-game: Turing Test ("Human or Machine?")
 * Chapter 1.01 / lesson_id = 2. Content from Documentation/UI/AI Fluency Course Redesign.zip.
 *
 * Contract: mount(container, opts) → renders game inside container; calls opts.onComplete()
 *           when all rounds answered + revealed. Triggers opts.xpBurst(15) on completion.
 */

(function () {
  'use strict';

  var DATA = {
    prompt: 'Read each reply, then guess who wrote it.',
    lesson: "Tricky, right? That's exactly Turing's point: if you can't reliably tell the machine from the human, the machine passes the famous Turing Test.",
    rounds: [
      { msg: "Honestly? Mondays are rough. I need at least two coffees before I feel like a real person.", truth: 'machine', why: 'Written by AI — modern models mimic casual, human banter with ease.' },
      { msg: "The mitochondria is the powerhouse of the cell — we literally just learned that in Bio class.", truth: 'human', why: 'A real student wrote this, slang and all.' },
      { msg: "I'd be happy to help! Could you tell me a little more about what you're looking for?", truth: 'machine', why: 'That polished, ever-helpful tone is a classic AI assistant.' }
    ],
    xpReward: 15
  };

  function mount(container, opts) {
    opts = opts || {};
    var state = { picks: {}, revealed: false };

    container.innerHTML = ''
      + '<div class="v2-minigame-shell">'
      + '  <div class="v2-quiz-eyebrow">Mini-Game · Turing Test</div>'
      + '  <h2 class="v2-minigame-shell__title">Human or Machine?</h2>'
      + '  <p class="v2-minigame-shell__hint">' + escapeHtml(DATA.prompt) + '</p>'
      + '  <div class="v2-turing-list" data-list></div>'
      + '  <div class="v2-step-footer" data-footer></div>'
      + '</div>';

    var list = container.querySelector('[data-list]');
    var footer = container.querySelector('[data-footer]');

    DATA.rounds.forEach(function (r, i) {
      var row = document.createElement('div');
      row.className = 'v2-turing-row';
      row.innerHTML = ''
        + '<div class="v2-turing-row__msg">' + escapeHtml(r.msg) + '</div>'
        + '<div class="v2-turing-row__btns">'
        + '  <button class="v2-turing-btn" data-pick="human">Human</button>'
        + '  <button class="v2-turing-btn" data-pick="machine">Machine</button>'
        + '</div>';
      list.appendChild(row);

      var btns = row.querySelectorAll('[data-pick]');
      btns.forEach(function (btn) {
        btn.addEventListener('click', function () {
          if (state.revealed) return;
          state.picks[i] = btn.getAttribute('data-pick');
          btns.forEach(function (b) { b.classList.remove('is-active'); });
          btn.classList.add('is-active');
          refreshFooter();
        });
      });
    });

    function refreshFooter() {
      footer.innerHTML = '';
      var allPicked = DATA.rounds.every(function (_, i) { return !!state.picks[i]; });
      if (!allPicked) return;
      if (state.revealed) {
        var next = document.createElement('button');
        next.className = 'v2-btn v2-btn--primary';
        next.textContent = 'Continue';
        next.addEventListener('click', function () {
          if (opts.onComplete) opts.onComplete({ xpAwarded: DATA.xpReward });
        });
        footer.appendChild(next);
      } else {
        var reveal = document.createElement('button');
        reveal.className = 'v2-btn v2-btn--primary';
        reveal.textContent = 'Reveal the Answers';
        reveal.addEventListener('click', function () {
          state.revealed = true;
          revealAnswers();
          if (opts.xpBurst) opts.xpBurst(DATA.xpReward);
          if (opts.mascot) opts.mascot.say(DATA.lesson, { autoHideMs: 7000 });
          refreshFooter();
        });
        footer.appendChild(reveal);
      }
    }

    function revealAnswers() {
      var rows = list.querySelectorAll('.v2-turing-row');
      rows.forEach(function (row, i) {
        var r = DATA.rounds[i];
        var pick = state.picks[i];
        var btns = row.querySelectorAll('[data-pick]');
        btns.forEach(function (btn) {
          btn.classList.remove('is-active');
          var v = btn.getAttribute('data-pick');
          if (v === r.truth) btn.classList.add('is-correct');
          else if (v === pick) btn.classList.add('is-wrong');
          btn.disabled = true;
        });
        var explain = document.createElement('div');
        explain.className = 'v2-turing-row__reveal';
        var correct = pick === r.truth;
        explain.innerHTML = '<strong style="color:' + (correct ? 'var(--v2-correct-soft)' : 'var(--v2-wrong)') + '">' + (correct ? 'Correct.' : 'Missed.') + '</strong> ' + escapeHtml(r.why);
        row.appendChild(explain);
      });
    }
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  window.V2Minigame_Turing = { mount: mount };
})();
