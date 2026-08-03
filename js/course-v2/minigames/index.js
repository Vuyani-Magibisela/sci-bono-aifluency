/*
 * AI Fluency Course v2 — Mini-game registry.
 *
 * Two resolution paths:
 *   1. bySlug(slug)      — used when the content-pack says which mini-game to play
 *                          (e.g. 'match' for the History Journey, 'turing' for Ch 1.01).
 *   2. forLesson(lessonId) — legacy fallback: hardcoded lesson_id → slug map for
 *                            lessons without a content-pack entry.
 *
 * A missing entry means the chapter runner will skip the mini-game step entirely.
 */

(function () {
  'use strict';

  // Legacy lesson-id → slug map. Used only for lessons the content pack hasn't covered yet.
  var BY_LESSON_ID = {
    // Note: with the content pack in place, these are also covered there — kept as a safety net.
    2: 'turing',
    3: 'sort',
    4: 'slider'
  };

  var MODULES = {
    turing: function () { return window.V2Minigame_Turing; },
    sort:   function () { return window.V2Minigame_Sort; },
    slider: function () { return window.V2Minigame_Slider; },
    match:  function () { return window.V2Minigame_Match; }
  };

  function bySlug(slug) {
    if (!slug) return null;
    var factory = MODULES[slug];
    if (!factory) return null;
    var mod = factory();
    if (!mod || typeof mod.mount !== 'function') return null;
    return { slug: slug, mount: mod.mount };
  }

  function forLesson(lessonId) {
    var slug = BY_LESSON_ID[lessonId];
    return slug ? bySlug(slug) : null;
  }

  function hasMinigame(lessonId) {
    if (window.V2ContentPack && window.V2ContentPack.has(lessonId)) {
      var pack = window.V2ContentPack.forLesson(lessonId);
      return !!(pack && pack.minigame && MODULES[pack.minigame]);
    }
    return !!BY_LESSON_ID[lessonId];
  }

  window.V2Minigames = {
    bySlug: bySlug,
    forLesson: forLesson,
    hasMinigame: hasMinigame,
    byLessonId: BY_LESSON_ID
  };
})();
