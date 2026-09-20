/*
 * AI Fluency Course v2 — Derived XP.
 *
 * Pure functions. No API calls, no state, no side effects.
 * Given the same raw progress payloads (from data-adapter), always returns the same XP totals.
 * This is the promise that lets old-UI users switch to new UI and see their earned XP immediately.
 */

(function () {
  'use strict';

  // Per-source XP values — must match the awards the runner shows on-screen so display and totals agree.
  var XP_LESSON_STARTED = 10;
  var XP_LESSON_COMPLETED = 30;
  var XP_QUIZ_MAX = 60;                   // scaled by score (0-100)
  var XP_MINIGAME_COMPLETED = 15;
  var XP_CONCEPT = 10;                    // per concept step continue (used only inside a single chapter run)
  var XP_QUIZ_FIRST_CORRECT = 20;         // per quiz-question first-correct (used only in-run)

  function lessonXP(lessonProgress) {
    if (!lessonProgress) return 0;
    var s = lessonProgress.status;
    if (s === 'completed') return XP_LESSON_COMPLETED;
    if (s === 'in_progress') return XP_LESSON_STARTED;
    return 0;
  }

  function quizXP(bestAttempt) {
    if (!bestAttempt) return 0;
    var score = Number(bestAttempt.score);
    if (isNaN(score) || score <= 0) return 0;
    return Math.round((score / 100) * XP_QUIZ_MAX);
  }

  function minigameXP(lessonProgress) {
    if (!lessonProgress) return 0;
    return lessonProgress.status === 'completed' ? XP_MINIGAME_COMPLETED : 0;
  }

  /**
   * Sum XP across all lessons in a module.
   * @param {Array} lessons  API-shape lesson objects (with .id)
   * @param {Object} progressByLesson  { [lesson_id]: lessonProgressRow }
   * @param {Object} bestAttemptByQuiz { [quiz_id]: bestAttemptRow }
   * @param {Object} quizByLesson      { [lesson_id]: quiz_id }  optional map
   * @param {Function} hasMinigame     (lesson) => boolean       optional predicate
   */
  function moduleXP(lessons, progressByLesson, bestAttemptByQuiz, quizByLesson, hasMinigame) {
    if (!lessons || !lessons.length) return 0;
    var total = 0;
    for (var i = 0; i < lessons.length; i++) {
      var l = lessons[i];
      var p = progressByLesson ? progressByLesson[l.id] : null;
      total += lessonXP(p);

      var quizId = quizByLesson ? quizByLesson[l.id] : (l.quiz_id || null);
      if (quizId && bestAttemptByQuiz && bestAttemptByQuiz[quizId]) {
        total += quizXP(bestAttemptByQuiz[quizId]);
      }

      if (hasMinigame && hasMinigame(l) && p) {
        total += minigameXP(p);
      }
    }
    return total;
  }

  /**
   * Course-wide XP derived from the module rows that GET /courses/:id already returns.
   *
   * The course payload carries lessons_count / completion_percentage / quiz_passed per module
   * but no per-lesson progress, so this is the one derivation every screen can afford to run on
   * load. It reuses the constants above, so the header pill reads the same on the course page,
   * the module page and inside a chapter.
   *
   * Minigame XP is deliberately excluded: the course payload cannot say which lessons carry a
   * minigame. moduleXP() below stays the finer-grained, module-scoped figure.
   */
  function courseXPFromModules(moduleRows) {
    if (!moduleRows || !moduleRows.length) return 0;
    var total = 0;
    for (var i = 0; i < moduleRows.length; i++) {
      var m = moduleRows[i] || {};
      var lessons = Number(m.lessons_count != null ? m.lessons_count : m.lessonsCount) || 0;
      var pct = Number(m.completion_percentage != null ? m.completion_percentage : m.completionPercentage) || 0;

      if (lessons > 0 && pct > 0) {
        total += Math.round((pct / 100) * lessons) * XP_LESSON_COMPLETED;
      }
      if (m.quiz_passed || m.quizPassed) {
        total += XP_QUIZ_MAX;
      }
    }
    return total;
  }

  /**
   * Sum XP across all modules in a course.
   * @param {Array} modules  each with .lessons[]
   */
  function courseXP(modules, progressByLesson, bestAttemptByQuiz, quizByLesson, hasMinigame) {
    if (!modules || !modules.length) return 0;
    var total = 0;
    for (var i = 0; i < modules.length; i++) {
      total += moduleXP(
        modules[i].lessons || [],
        progressByLesson,
        bestAttemptByQuiz,
        quizByLesson,
        hasMinigame
      );
    }
    return total;
  }

  window.XP = {
    // Per-source constants — used both by display and by chapter-runner burst animations.
    XP_LESSON_STARTED: XP_LESSON_STARTED,
    XP_LESSON_COMPLETED: XP_LESSON_COMPLETED,
    XP_QUIZ_MAX: XP_QUIZ_MAX,
    XP_MINIGAME_COMPLETED: XP_MINIGAME_COMPLETED,
    XP_CONCEPT: XP_CONCEPT,
    XP_QUIZ_FIRST_CORRECT: XP_QUIZ_FIRST_CORRECT,
    lessonXP: lessonXP,
    quizXP: quizXP,
    minigameXP: minigameXP,
    moduleXP: moduleXP,
    courseXPFromModules: courseXPFromModules,
    courseXP: courseXP
  };
})();
