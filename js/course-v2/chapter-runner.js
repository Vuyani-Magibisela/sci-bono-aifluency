/*
 * AI Fluency Course v2 — Chapter runner.
 *
 * Drives the chapter step machine: intro → concept × N → quiz → (minigame?) → reward.
 * Handles per-run XP accumulation, quiz submission, lesson-complete API call.
 */

(function () {
  'use strict';

  /**
   * Construct a chapter runner instance.
   * @param {Object} opts
   *   stageContent:  HTMLElement (the .v2-stage__content node)
   *   header:        HTMLElement (the .v2-header)
   *   headerContainer: HTMLElement (the .v2-root, for re-rendering header dots on step advance)
   *   headerOpts:    Object (initial header options; runner will update `.dots`)
   *   chapterModel:  Object (from DataAdapter.toChapterModel)
   *   startingXP:    number (XP the user already has course-wide, so we show a live-summed total)
   *   mascot:        { say(text, opts), hide() }
   *   xpAnchor:      HTMLElement (the .v2-xp-wrap for burst animation)
   *   onExit:        Function() — user clicked "back to module hub"
   */
  function create(opts) {
    var chapter = opts.chapterModel;
    var steps = chapter.steps;
    var state = {
      stepIndex: 0,
      runXP: 0,           // XP earned in this run only
      totalXP: opts.startingXP || 0,  // course-wide XP (updates as we award)
      earned: {},         // idempotency keys — { c0: true, q1: true, g: true }
      quizAnswers: {},    // { question_id: selected_index } for final quiz submit
      startTime: Date.now(),
      lessonMarkedComplete: chapter.alreadyCompleted
    };

    var stage = opts.stageContent;
    var header = opts.header;

    function render() {
      updateHeaderDots();
      var step = steps[state.stepIndex];
      stage.innerHTML = '';
      var stepEl = document.createElement('div');
      stepEl.className = 'v2-step v2-step-' + step.type;
      stepEl.setAttribute('data-step-type', step.type);
      stepEl.style.setProperty('--v2-step-accent', chapter.accent);
      stage.appendChild(stepEl);

      if (step.type === 'intro') renderIntro(stepEl, step);
      else if (step.type === 'concept') renderConcept(stepEl, step);
      else if (step.type === 'era') renderEra(stepEl, step);
      else if (step.type === 'quiz') renderQuiz(stepEl, step);
      else if (step.type === 'minigame') renderMinigame(stepEl, step);
      else if (step.type === 'reward') renderReward(stepEl, step);

      if (window.V2Anim) window.V2Anim.stepEnter(stepEl);
    }

    function updateHeaderDots() {
      // Re-render header center (progress dots)
      var center = header.querySelector('.v2-header__center');
      if (!center) return;
      center.innerHTML = '';
      var label = document.createElement('div');
      label.className = 'v2-header__context';
      label.textContent = chapter.title;
      center.appendChild(label);
      var wrap = document.createElement('div');
      wrap.className = 'v2-progress-dots';
      wrap.style.setProperty('--v2-current-accent', chapter.accent);
      for (var i = 0; i < steps.length; i++) {
        var d = document.createElement('div');
        var isCurrent = i === state.stepIndex;
        var isDone = i < state.stepIndex;
        d.className = 'v2-progress-dot' + (isCurrent ? ' v2-progress-dot--current' : '') + (isDone ? ' v2-progress-dot--done' : '');
        if (isCurrent) d.textContent = (i + 1);
        wrap.appendChild(d);
      }
      center.appendChild(wrap);
    }

    // ============================================================
    // Step renderers
    // ============================================================

    function renderIntro(el, step) {
      el.innerHTML = ''
        + '<div class="v2-step-intro__kicker" style="color:' + chapter.accent + ';border-color:' + chapter.accent + '">' + esc(step.kicker) + '</div>'
        + '<h1 class="v2-step-intro__title">' + esc(step.title) + '</h1>'
        + (step.tagline ? '<p class="v2-step-intro__tagline">' + esc(step.tagline) + '</p>' : '')
        + (step.body ? '<p class="v2-step-intro__body">' + esc(step.body) + '</p>' : '')
        + '<div class="v2-step-footer"><button class="v2-btn v2-btn--primary" data-cta>' + esc(step.cta || 'Begin') + '</button></div>';
      el.querySelector('[data-cta]').addEventListener('click', advance);
      if (opts.mascot) opts.mascot.say('I’m Bono. Let’s go!', { autoHideMs: 4000 });
    }

    function renderEra(el, step) {
      var accent = step.eraAccent || chapter.accent;
      el.innerHTML = ''
        + '<div class="v2-step-concept__label">Memory ' + step.index + ' of ' + step.total + '</div>'
        + '<div class="v2-era-body">'
        + '  <div class="v2-era-medallion" style="border-color:' + accent + '; color:' + accent + '">'
        + '    <div class="v2-era-medallion__year">' + esc(step.year) + '</div>'
        + '  </div>'
        + '  <div class="v2-era-text">'
        + '    <div class="v2-concept-pill" style="color:' + accent + ';border-color:' + accent + '">' + esc(step.tag) + '</div>'
        + '    <h2 class="v2-concept-title">' + esc(step.title) + '</h2>'
        + '    <p class="v2-concept-body-text">' + esc(step.line) + '</p>'
        + '  </div>'
        + '</div>'
        + '<div class="v2-step-footer">'
        + '  <button class="v2-btn v2-btn--primary" data-continue>Continue</button>'
        + '</div>';
      if (opts.mascot && step.narrate) opts.mascot.say(step.narrate, { autoHideMs: 5000 });
      el.querySelector('[data-continue]').addEventListener('click', function () {
        awardXP('e' + state.stepIndex, window.XP.XP_CONCEPT);
        advance();
      });
    }

    function renderConcept(el, step) {
      var iconHtml = '<i class="fas ' + esc(step.icon || 'fa-lightbulb') + '"></i>';
      var defHtml = '';
      if (step.definition) {
        defHtml = ''
          + '<div class="v2-concept-def">'
          + '  <p class="v2-concept-def__term">' + esc(step.definition.term) + '</p>'
          + '  <p class="v2-concept-def__meaning">' + esc(step.definition.meaning) + '</p>'
          + '</div>';
      }
      el.innerHTML = ''
        + '<div class="v2-step-concept__label">Concept ' + step.index + ' of ' + step.total + '</div>'
        + '<div class="v2-concept-body">'
        + '  <div class="v2-concept-medallion">' + iconHtml + '</div>'
        + '  <div class="v2-concept-text">'
        + '    <div class="v2-concept-pill">Concept</div>'
        + '    <h2 class="v2-concept-title">' + esc(step.title) + '</h2>'
        + '    <div class="v2-concept-body-text">' + safeHTML(step.body) + '</div>'
        + '    ' + defHtml
        + '  </div>'
        + '</div>'
        + '<div class="v2-step-footer">'
        + '  <button class="v2-btn v2-btn--primary" data-continue>Continue</button>'
        + '</div>';
      el.querySelector('[data-continue]').addEventListener('click', function () {
        awardXP('c' + state.stepIndex, window.XP.XP_CONCEPT);
        advance();
      });
    }

    function renderQuiz(el, step) {
      var qState = { currentQ: 0, picked: null, correctThisQ: false };

      var header = document.createElement('div');
      header.innerHTML = ''
        + '<div class="v2-quiz-eyebrow">Challenge</div>'
        + '<h2 class="v2-quiz-question" data-question></h2>'
        + '<div class="v2-quiz-options" data-options></div>'
        + '<div class="v2-step-footer" data-footer></div>';
      el.appendChild(header);

      renderQuestion();

      function renderQuestion() {
        var origQ = step.questions[qState.currentQ];
        if (!origQ) {
          submitAndAdvance();
          return;
        }
        // Shuffle options once per render so the correct answer isn't always A.
        // `indexMap[shuffledIdx] = originalIdx` — used when reporting `selected_answer` to the DB.
        var q = shuffleQuestion(origQ);

        var qEl = el.querySelector('[data-question]');
        var oEl = el.querySelector('[data-options]');
        var fEl = el.querySelector('[data-footer]');
        qEl.textContent = q.question;
        oEl.innerHTML = '';
        fEl.innerHTML = '';
        qState.picked = null;
        qState.correctThisQ = false;

        (q.options || []).forEach(function (opt, i) {
          var btn = document.createElement('button');
          btn.className = 'v2-quiz-option';
          btn.innerHTML = '<span class="v2-quiz-option__letter">' + String.fromCharCode(65 + i) + '</span><span>' + esc(opt) + '</span>';
          btn.addEventListener('click', function () {
            if (qState.picked !== null) return;
            qState.picked = i;
            // Persist the ORIGINAL (unshuffled) index so DB submissions still line up
            // with question.correct_answer.
            state.quizAnswers[origQ.id] = q.indexMap[i];
            var buttons = oEl.querySelectorAll('.v2-quiz-option');
            if (i === q.correctIndex) {
              btn.classList.add('v2-quiz-option--correct');
              qState.correctThisQ = true;
              if (!state.earned['q' + qState.currentQ]) {
                awardXP('q' + qState.currentQ, window.XP.XP_QUIZ_FIRST_CORRECT);
              }
              buttons.forEach(function (b) { if (b !== btn) b.disabled = true; });
              showContinue();
            } else {
              btn.classList.add('v2-quiz-option--wrong');
              setTimeout(function () {
                buttons[q.correctIndex].classList.add('v2-quiz-option--correct');
                buttons.forEach(function (b) { b.disabled = true; });
                showContinue();
              }, 350);
              if (opts.mascot) opts.mascot.say('Not quite — ' + (origQ.explanation || origQ.hint || 'try to reason through it.'), { autoHideMs: 5000 });
            }
          });
          oEl.appendChild(btn);
        });
      }

      function shuffleQuestion(q) {
        var opts = (q.options || []).slice();
        var indices = opts.map(function (_, i) { return i; });
        for (var i = indices.length - 1; i > 0; i--) {
          var j = Math.floor(Math.random() * (i + 1));
          var t = indices[i]; indices[i] = indices[j]; indices[j] = t;
        }
        var shuffled = indices.map(function (i) { return opts[i]; });
        var newCorrect = indices.indexOf(q.correctIndex);
        return {
          id: q.id,
          question: q.question,
          options: shuffled,
          correctIndex: newCorrect,
          indexMap: indices,   // shuffled position → original position
          explanation: q.explanation,
          hint: q.hint
        };
      }

      function showContinue() {
        var fEl = el.querySelector('[data-footer]');
        var btn = document.createElement('button');
        btn.className = 'v2-btn v2-btn--primary';
        btn.textContent = (qState.currentQ < step.questions.length - 1) ? 'Next question' : 'Submit challenge';
        btn.addEventListener('click', function () {
          if (qState.currentQ < step.questions.length - 1) {
            qState.currentQ += 1;
            renderQuestion();
          } else {
            submitAndAdvance();
          }
        });
        fEl.appendChild(btn);
      }

      function submitAndAdvance() {
        // Only submit against the DB module quiz when the runner owns those questions.
        // Pack-authored quizzes are local practice — they shouldn't consume the user's
        // limited "Knowledge Check" attempts.
        var shouldSubmit = chapter.submitDBQuiz && chapter.quizId && step.source !== 'pack' && step.questions.length;
        if (shouldSubmit) {
          var answers = step.questions.map(function (q) {
            return { question_id: q.id, selected_answer: state.quizAnswers[q.id] };
          });
          var minutes = Math.max(1, Math.round((Date.now() - state.startTime) / 60000));
          window.DataAdapter.submitQuiz(chapter.quizId, answers, minutes).catch(function (err) {
            console.error('Quiz submit failed:', err);
            window.Shell && window.Shell.toast('Couldn\'t save your quiz — please try again on the next attempt.');
          });
        }
        advance();
      }
    }

    function renderMinigame(el, step) {
      // Resolve mini-game: pack tells us the slug + data; otherwise try the legacy lesson map.
      var mg = null;
      if (step.slug && window.V2Minigames) mg = window.V2Minigames.bySlug(step.slug);
      if (!mg && window.V2Minigames) mg = window.V2Minigames.forLesson(chapter.lessonId);
      if (!mg) { advance(); return; }

      mg.mount(el, {
        data: step.data || null,
        onComplete: function (result) {
          if (!state.earned['g']) {
            awardXP('g', (result && result.xpAwarded) || window.XP.XP_MINIGAME_COMPLETED);
          }
          advance();
        },
        xpBurst: function (n) {
          if (window.V2Anim) window.V2Anim.xpBurst(n, opts.xpAnchor);
        },
        mascot: opts.mascot
      });
    }

    function renderReward(el, step) {
      // On entering reward: mark lesson complete + award badge (both idempotent server-side).
      if (!state.lessonMarkedComplete && chapter.lessonId) {
        var minutes = Math.max(1, Math.round((Date.now() - state.startTime) / 60000));
        window.DataAdapter.markLessonComplete(chapter.lessonId, minutes).then(function () {
          state.lessonMarkedComplete = true;
        }).catch(function (err) {
          console.error('markLessonComplete failed:', err);
          window.Shell && window.Shell.toast('Couldn\'t save your completion — sign in and try again.');
        });
      }

      var badge = step.badge || chapter.badge;
      if (badge && badge.slug && window.DataAdapter.awardBadge) {
        window.DataAdapter.awardBadge(badge.slug, {
          lesson_id: chapter.lessonId,
          module_id: chapter.moduleId,
          badge_name: badge.name
        }).catch(function (err) { console.warn('awardBadge failed (non-blocking):', err); });
      }

      if (window.V2Anim) window.V2Anim.confetti(60);
      if (opts.mascot) opts.mascot.hide();

      var bodyCount = steps.filter(function (s) { return s.type === 'concept' || s.type === 'era'; }).length;
      var bodyLabel = chapter.isJourney ? 'Memories' : 'Concepts';
      var badgeName = badge && badge.name ? badge.name : 'Badge';
      el.innerHTML = ''
        + '<svg class="v2-reward-trophy" viewBox="0 0 64 64" fill="currentColor"><path d="M32 4c-9 0-16 6-16 15v2H8v4c0 8 6 14 14 16v3h-4v6h28v-6h-4v-3c8-2 14-8 14-16v-4h-8v-2c0-9-7-15-16-15zm-12 15c0-6 5-11 12-11s12 5 12 11v10c0 6-5 11-12 11s-12-5-12-11V19z"/></svg>'
        + '<h1 class="v2-reward-title">' + esc(step.title || 'Chapter Complete!') + '</h1>'
        + '<div class="v2-reward-stats">'
        + '  <div class="v2-reward-stat"><div class="v2-reward-stat__value">+' + state.runXP + '</div><div class="v2-reward-stat__label">XP earned</div></div>'
        + '  <div class="v2-reward-stat"><div class="v2-reward-stat__value">' + bodyCount + '</div><div class="v2-reward-stat__label">' + bodyLabel + '</div></div>'
        + '  <div class="v2-reward-stat"><div class="v2-reward-stat__value">🏅</div><div class="v2-reward-stat__label">' + esc(badgeName) + '</div></div>'
        + '</div>'
        + '<div class="v2-reward-actions">'
        + '  <button class="v2-btn v2-btn--secondary" data-back>Back to Module</button>'
        + '  <button class="v2-btn v2-btn--primary" data-next>Next Chapter</button>'
        + '</div>';
      el.querySelector('[data-back]').addEventListener('click', function () { if (opts.onExit) opts.onExit('module'); });
      el.querySelector('[data-next]').addEventListener('click', function () { if (opts.onExit) opts.onExit('next'); });
    }

    // ============================================================
    // Advancement + XP
    // ============================================================

    function advance() {
      if (state.stepIndex >= steps.length - 1) return;
      state.stepIndex += 1;
      render();
    }

    function awardXP(key, amount) {
      if (state.earned[key]) return;
      state.earned[key] = true;
      state.runXP += amount;
      state.totalXP += amount;
      if (opts.xpAnchor && window.V2Anim) window.V2Anim.xpBurst(amount, opts.xpAnchor);
      if (window.Shell && opts.headerContainer) window.Shell.updateXP(opts.headerContainer, state.totalXP);
    }

    // ============================================================
    // Helpers
    // ============================================================

    function esc(s) {
      return String(s == null ? '' : s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    function safeHTML(s) {
      // Content from admin-controlled lesson body — allow HTML through, but sanitise scripts/frames.
      if (s == null) return '';
      return String(s).replace(/<script[\s\S]*?<\/script>/gi, '').replace(/<iframe[\s\S]*?<\/iframe>/gi, '');
    }

    return {
      start: function () { render(); },
      state: state
    };
  }

  window.ChapterRunner = { create: create };
})();
