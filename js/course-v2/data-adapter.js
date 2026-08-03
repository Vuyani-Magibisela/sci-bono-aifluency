/*
 * AI Fluency Course v2 — Data adapter.
 *
 * Thin translation layer between the existing API and the v2 view model.
 * All endpoints already exist — this file NEVER introduces a new backend contract.
 *
 * Endpoints used:
 *   GET  /api/courses/:id                 → { course, modules[…] } (with per-module completion_percentage, is_unlocked, quiz_passed)
 *   GET  /api/modules/:id                 → module (with .course, .statistics)
 *   GET  /api/lessons?module_id=:id       → paginated items with .progress attached per lesson
 *   GET  /api/lessons/:id                 → single lesson with .progress, .module, .next_lesson, .previous_lesson
 *   GET  /api/quizzes?module_id=:id       → paginated quizzes
 *   GET  /api/quizzes/:id                 → quiz with .questions, .user_attempts, .user_best_score, .can_attempt
 *   POST /api/lessons/:id/start           → creates progress row
 *   POST /api/lessons/:id/complete        → marks complete + recalcs enrollment
 *   POST /api/quizzes/:id/submit          → records attempt
 */

(function () {
  'use strict';

  // ============================================================
  // Loaders — thin wrappers around API.get() with error handling
  // ============================================================

  async function loadCourse(courseId) {
    var res = await API.get('/courses/' + encodeURIComponent(courseId));
    if (!res || !res.success) throw new Error(res && res.message ? res.message : 'Failed to load course');
    return res.data && res.data.course ? res.data.course : null;
  }

  async function loadModule(moduleId) {
    var res = await API.get('/modules/' + encodeURIComponent(moduleId));
    if (!res || !res.success) throw new Error(res && res.message ? res.message : 'Failed to load module');
    // Module controller returns the module directly at `data` (not `.module`).
    return res.data || null;
  }

  async function loadLessonsForModule(moduleId) {
    var res = await API.get('/lessons?module_id=' + encodeURIComponent(moduleId) + '&pageSize=100');
    if (!res || !res.success) throw new Error(res && res.message ? res.message : 'Failed to load lessons');
    var items = (res.data && res.data.items) || [];
    items.sort(function (a, b) { return (a.order_index || 0) - (b.order_index || 0); });
    return items;
  }

  async function loadLesson(lessonId) {
    var res = await API.get('/lessons/' + encodeURIComponent(lessonId));
    if (!res || !res.success) throw new Error(res && res.message ? res.message : 'Failed to load lesson');
    return (res.data && res.data.lesson) || res.data || null;
  }

  async function loadModuleQuizzes(moduleId) {
    var res = await API.get('/quizzes?module_id=' + encodeURIComponent(moduleId) + '&pageSize=50');
    if (!res || !res.success) return [];
    return (res.data && res.data.items) || [];
  }

  async function loadQuiz(quizId) {
    var res = await API.get('/quizzes/' + encodeURIComponent(quizId));
    if (!res || !res.success) throw new Error(res && res.message ? res.message : 'Failed to load quiz');
    return (res.data && res.data.quiz) || res.data || null;
  }

  // Mutation endpoints
  async function markLessonStarted(lessonId) {
    return API.post('/lessons/' + encodeURIComponent(lessonId) + '/start', {});
  }

  async function markLessonComplete(lessonId, timeSpentMinutes) {
    return API.post('/lessons/' + encodeURIComponent(lessonId) + '/complete', {
      time_spent_minutes: timeSpentMinutes || 0
    });
  }

  async function submitQuiz(quizId, answers, timeTakenMinutes) {
    return API.post('/quizzes/' + encodeURIComponent(quizId) + '/submit', {
      answers: answers,
      time_taken_minutes: timeTakenMinutes || 0
    });
  }

  async function awardBadge(slug, extras) {
    var payload = Object.assign({ badge_slug: slug }, extras || {});
    return API.post('/badges', payload);
  }

  async function loadMyBadges(moduleId) {
    var url = '/badges/my-badges' + (moduleId ? '?module_id=' + encodeURIComponent(moduleId) : '');
    var res = await API.get(url);
    if (!res || !res.success) return [];
    return (res.data && res.data.items) || [];
  }

  // ============================================================
  // View-model transforms
  // ============================================================

  var MODULE_ACCENTS = [
    'var(--v2-accent-cyan)',    // module 1
    'var(--v2-accent-purple)',  // module 2
    'var(--v2-accent-orange)',  // module 3
    'var(--v2-accent-green)',   // module 4
    'var(--v2-accent-pink)',    // module 5
    'var(--v2-accent-gold)'     // module 6
  ];

  var LESSON_ACCENTS = [
    'var(--v2-accent-blue)',
    'var(--v2-accent-cyan)',
    'var(--v2-accent-orange)',
    'var(--v2-accent-green)',
    'var(--v2-accent-pink)',
    'var(--v2-accent-gold)',
    'var(--v2-accent-purple)'
  ];

  function moduleAccent(orderIndex) {
    var idx = Math.max(0, (parseInt(orderIndex, 10) || 1) - 1);
    return MODULE_ACCENTS[idx % MODULE_ACCENTS.length];
  }

  function lessonAccent(orderIndex) {
    var idx = Math.max(0, (parseInt(orderIndex, 10) || 1) - 1);
    return LESSON_ACCENTS[idx % LESSON_ACCENTS.length];
  }

  /**
   * Course-landing view model: list of modules with accent, XP, and progress.
   */
  function toCourseLandingModel(course) {
    if (!course) return null;
    var modules = (course.modules || []).slice().sort(function (a, b) {
      return (a.order_index || 0) - (b.order_index || 0);
    });
    return {
      id: course.id,
      title: course.title,
      description: course.description,
      isEnrolled: !!course.is_enrolled,
      completionPercentage: parseInt(course.completion_percentage || 0, 10),
      modules: modules.map(function (m) {
        return {
          id: m.id,
          number: parseInt(m.order_index, 10) || 0,
          title: m.title,
          description: m.description,
          accent: moduleAccent(m.order_index),
          completionPercentage: parseInt(m.completion_percentage || 0, 10),
          isUnlocked: m.is_unlocked !== false,
          lockedReason: m.locked_reason || null,
          quizPassed: !!m.quiz_passed,
          projectSubmitted: !!m.project_submitted
        };
      })
    };
  }

  /**
   * Module-hub view model: mission-card grid built from lessons + progress.
   */
  function toModuleHubModel(module, lessons) {
    if (!module) return null;

    var totalXP = 0;
    var completedCount = 0;

    var missions = (lessons || []).map(function (lesson, i) {
      var status = (lesson.progress && lesson.progress.status) || 'not_started';
      var xp = window.XP ? window.XP.lessonXP(lesson.progress) : 0;
      totalXP += xp;
      if (status === 'completed') completedCount += 1;

      var statusLabel;
      if (status === 'completed') statusLabel = 'Completed';
      else if (status === 'in_progress') statusLabel = 'Continue';
      else statusLabel = 'Start';

      return {
        lessonId: lesson.id,
        number: i + 1,
        title: lesson.title,
        blurb: lesson.subtitle || lesson.description || '',
        accent: lessonAccent(lesson.order_index),
        status: status,
        statusLabel: statusLabel,
        xp: xp,
        isCompleted: status === 'completed'
      };
    });

    return {
      id: module.id,
      moduleNumber: parseInt(module.order_index, 10) || 0,
      title: module.title,
      description: module.description,
      subtitle: module.subtitle || 'Missions to complete this module',
      totalMissions: missions.length,
      completedMissions: completedCount,
      totalXP: totalXP,
      accent: moduleAccent(module.order_index),
      missions: missions,
      quizPassed: !!module.quiz_passed,
      projectSubmitted: !!module.project_submitted,
      courseId: module.course_id
    };
  }

  /**
   * Chapter view model: choose the gamified script from the content pack when present,
   * otherwise auto-summarize the DB lesson content into kid-friendly bites.
   */
  function toChapterModel(lesson, quiz) {
    if (!lesson) return null;

    var pack = window.V2ContentPack && window.V2ContentPack.forLesson(lesson.id);
    if (pack) return buildFromPack(lesson, quiz, pack);
    return buildFromLesson(lesson, quiz);
  }

  /**
   * Build a chapter model from a hand-authored content-pack entry.
   */
  function buildFromPack(lesson, quiz, pack) {
    var accent = pack.accent || lessonAccent(lesson.order_index);
    var isJourney = pack.type === 'journey';
    var order = pack.stepOrder || defaultStepOrder(pack);

    var steps = [];
    order.forEach(function (token) {
      if (token === 'intro') {
        steps.push({
          type: 'intro',
          kicker: pack.intro.kicker || 'Chapter',
          title: pack.intro.title || lesson.title,
          tagline: pack.intro.tagline || '',
          body: pack.intro.body || '',
          cta: pack.intro.cta || 'Begin'
        });
      } else if (token.indexOf('concept:') === 0) {
        var ci = parseInt(token.split(':')[1], 10);
        var c = pack.concepts && pack.concepts[ci];
        if (c) {
          steps.push({
            type: 'concept',
            index: ci + 1,
            total: (pack.concepts || []).length,
            title: c.title,
            tag: c.tag || 'Concept',
            body: c.body,
            definition: c.definition || null,
            icon: c.icon || 'fa-lightbulb'
          });
        }
      } else if (token.indexOf('era:') === 0) {
        var ei = parseInt(token.split(':')[1], 10);
        var e = pack.eras && pack.eras[ei];
        if (e) {
          steps.push({
            type: 'era',
            index: ei + 1,
            total: (pack.eras || []).length,
            year: e.year,
            tag: e.tag,
            title: e.title,
            line: e.line,
            narrate: e.narrate || '',
            eraAccent: e.color || accent
          });
        }
      } else if (token.indexOf('quiz:') === 0) {
        var qi = parseInt(token.split(':')[1], 10);
        var q = pack.quizzes && pack.quizzes[qi];
        if (q) {
          steps.push({
            type: 'quiz',
            source: 'pack',
            questions: [{
              id: 'pack-' + lesson.id + '-' + qi,
              question: q.q,
              options: q.options,
              correctIndex: q.correct,
              hint: q.hint || '',
              explanation: q.hint || ''
            }]
          });
        }
      } else if (token === 'minigame' && pack.minigame) {
        steps.push({
          type: 'minigame',
          slug: pack.minigame,
          data: pack.minigameData || null
        });
      } else if (token === 'reward') {
        steps.push({
          type: 'reward',
          title: (isJourney ? 'Journey Complete!' : 'Chapter Complete!'),
          badge: pack.badge || null
        });
      }
    });

    return {
      lessonId: lesson.id,
      moduleId: lesson.module_id,
      title: lesson.title,
      accent: accent,
      isJourney: isJourney,
      totalSteps: steps.length,
      steps: steps,
      quizId: quiz ? quiz.id : null,   // module quiz (still submitted for gating)
      submitDBQuiz: false,             // pack has its own quizzes; don't submit the DB module quiz mid-run
      badge: pack.badge || null,
      alreadyCompleted: !!(lesson.progress && lesson.progress.status === 'completed'),
      usedPack: true
    };
  }

  /**
   * Auto-gamified chapter model built from raw DB content when no pack entry exists.
   * Aggressively summarizes: ≤5 concept cards, bold first sentence, body ≤ ~200 chars.
   */
  function buildFromLesson(lesson, quiz) {
    var accent = lessonAccent(lesson.order_index);
    var concepts = summarizeIntoConcepts(lesson.content || lesson.body || lesson.description || '');
    var quizStep = quiz ? buildQuizStep(quiz) : null;

    var steps = [];
    steps.push({
      type: 'intro',
      kicker: 'Chapter ' + formatChapterNumber(lesson),
      title: lesson.title,
      tagline: lesson.subtitle || '',
      body: kidFriendlyIntro(lesson),
      cta: 'Begin'
    });

    concepts.forEach(function (c, i) {
      steps.push({
        type: 'concept',
        index: i + 1,
        total: concepts.length,
        title: c.title,
        tag: 'Concept',
        body: c.body,
        definition: c.definition || null,
        icon: c.icon || 'fa-lightbulb'
      });
    });

    if (quizStep) steps.push(quizStep);

    steps.push({
      type: 'reward',
      title: 'Chapter Complete!',
      badge: autoBadgeFor(lesson)
    });

    return {
      lessonId: lesson.id,
      moduleId: lesson.module_id,
      title: lesson.title,
      accent: accent,
      isJourney: false,
      totalSteps: steps.length,
      steps: steps,
      quizId: quiz ? quiz.id : null,
      submitDBQuiz: !!quiz,
      badge: autoBadgeFor(lesson),
      alreadyCompleted: !!(lesson.progress && lesson.progress.status === 'completed'),
      usedPack: false
    };
  }

  function defaultStepOrder(pack) {
    var order = ['intro'];
    (pack.concepts || []).forEach(function (_, i) { order.push('concept:' + i); });
    (pack.eras || []).forEach(function (_, i) { order.push('era:' + i); });
    (pack.quizzes || []).forEach(function (_, i) { order.push('quiz:' + i); });
    if (pack.minigame) order.push('minigame');
    order.push('reward');
    return order;
  }

  function kidFriendlyIntro(lesson) {
    var raw = lesson.description || lesson.subtitle || '';
    if (!raw) return 'Ready to dig in? Let’s go.';
    var s = String(raw).replace(/<[^>]+>/g, '').replace(/\s+/g, ' ').trim();
    if (s.length > 220) s = s.slice(0, 217) + '…';
    return s;
  }

  function autoBadgeFor(lesson) {
    var t = (lesson.title || 'Chapter').replace(/^Chapter\s*\d+(\.\d+)?[:\s]*/i, '').trim() || lesson.title;
    return {
      slug: 'lesson_' + lesson.id,
      name: t + ' Badge'
    };
  }

  function formatChapterNumber(lesson) {
    var mod = lesson.module_id || 0;
    var ord = parseInt(lesson.order_index, 10) || 0;
    return mod + '.' + (ord < 10 ? '0' + ord : ord);
  }

  /**
   * Split HTML content into concept cards.
   * Concept titles come from <h1>..<h4>; body from the following siblings until the next heading.
   */
  /**
   * Aggressive auto-summarize: turn raw lesson HTML into short kid-friendly concept cards.
   * Rules:
   *  - Strip inline styles, scripts, iframes, images (icons are decorative in v2).
   *  - Prefer heading-anchored sections. If a section body > ~200 chars, split into further cards.
   *  - Fallback: split by paragraphs, coalesce until each card body is ≤ ~200 chars, cap at 5 cards.
   *  - Detect definition blocks (blockquote / .definition / <dt>+<dd>) and attach to nearest concept.
   */
  var MAX_CARDS = 5;
  var MAX_BODY_CHARS = 220;

  function summarizeIntoConcepts(html) {
    if (!html || typeof html !== 'string') {
      return [{ title: 'Overview', body: 'Content coming soon.', definition: null, icon: 'fa-book-open' }];
    }

    var container = document.createElement('div');
    container.innerHTML = String(html)
      .replace(/<script[\s\S]*?<\/script>/gi, '')
      .replace(/<iframe[\s\S]*?<\/iframe>/gi, '')
      .replace(/style="[^"]*"/gi, '')
      .replace(/style='[^']*'/gi, '');

    // Extract raw text sections keyed by their preceding heading (if any).
    var sections = [];
    var current = { title: null, text: '' };
    Array.prototype.forEach.call(container.childNodes, function (node) {
      if (node.nodeType === 1 && /^H[1-4]$/.test(node.tagName)) {
        if (current.text.trim()) sections.push(current);
        current = { title: (node.textContent || '').trim(), text: '' };
      } else if (node.nodeType === 1) {
        current.text += ' ' + (node.textContent || '');
      } else if (node.nodeType === 3) {
        current.text += ' ' + node.textContent;
      }
    });
    if (current.text.trim() || current.title) sections.push(current);

    // If no sections at all, drop back to plain text of the whole thing.
    if (!sections.length) {
      sections = [{ title: null, text: container.textContent || '' }];
    }

    // Turn sections into cards. Each section may produce >1 card if long.
    var cards = [];
    for (var i = 0; i < sections.length && cards.length < MAX_CARDS; i++) {
      var s = sections[i];
      var normText = (s.text || '').replace(/\s+/g, ' ').trim();
      if (!normText && !s.title) continue;

      var sentences = splitSentences(normText);
      var head = s.title || (sentences[0] ? firstNWords(sentences[0], 6) : ('Concept ' + (cards.length + 1)));
      var bodyBuf = '';
      var used = [];
      for (var j = 0; j < sentences.length; j++) {
        var next = (bodyBuf ? bodyBuf + ' ' : '') + sentences[j];
        if (next.length > MAX_BODY_CHARS && bodyBuf) {
          cards.push({ title: cards.length && !s.title ? 'More on ' + head : head, body: emphasiseFirst(bodyBuf), definition: null, icon: iconForConcept(cards.length) });
          bodyBuf = sentences[j];
          if (cards.length >= MAX_CARDS) break;
        } else {
          bodyBuf = next;
        }
        used.push(sentences[j]);
      }
      if (bodyBuf && cards.length < MAX_CARDS) {
        cards.push({ title: head, body: emphasiseFirst(bodyBuf), definition: null, icon: iconForConcept(cards.length) });
      }
    }

    if (!cards.length) {
      cards.push({ title: 'Overview', body: 'This lesson’s content will show here.', definition: null, icon: 'fa-book-open' });
    }
    return cards;
  }

  function splitSentences(text) {
    if (!text) return [];
    // Simple sentence splitter — good enough for lesson prose.
    return text
      .split(/(?<=[.!?])\s+(?=[A-Z"“(])/)
      .map(function (s) { return s.trim(); })
      .filter(Boolean);
  }

  function firstNWords(s, n) {
    var words = String(s).split(/\s+/).slice(0, n).join(' ');
    return words + (String(s).split(/\s+/).length > n ? '…' : '');
  }

  function emphasiseFirst(bodyText) {
    // Bold the first sentence so cards read as one-punch headlines.
    var m = /^([^.!?]+[.!?])(\s+)([\s\S]*)$/.exec(bodyText);
    if (!m) return '<p>' + escapeText(bodyText) + '</p>';
    return '<p><strong>' + escapeText(m[1]) + '</strong>' + m[2] + escapeText(m[3]) + '</p>';
  }

  function escapeText(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function iconForConcept(i) {
    var icons = ['fa-lightbulb', 'fa-brain', 'fa-cogs', 'fa-network-wired', 'fa-microchip', 'fa-atom', 'fa-project-diagram', 'fa-robot'];
    return icons[i % icons.length];
  }

  function buildQuizStep(quiz) {
    if (!quiz || !quiz.questions || !quiz.questions.length) return null;
    return {
      type: 'quiz',
      quizId: quiz.id,
      title: quiz.title,
      description: quiz.description,
      passingScore: quiz.passing_score || 70,
      questions: quiz.questions.map(function (q) {
        var options = q.options;
        if (typeof options === 'string') {
          try { options = JSON.parse(options); } catch (e) { options = []; }
        }
        return {
          id: q.id,
          question: q.question || q.question_text,
          options: options || [],
          correctIndex: (q.correct_answer != null ? parseInt(q.correct_answer, 10) : null),
          explanation: q.explanation || '',
          points: q.points || 10
        };
      })
    };
  }

  /**
   * Given a module, resolve its "main" quiz — the one to render at the end of a chapter.
   * Falls back to the first module-scoped quiz if none is per-lesson.
   */
  function pickQuizForLesson(lessonId, quizzes) {
    if (!quizzes || !quizzes.length) return null;
    // Prefer per-lesson quiz
    var lessonQuiz = quizzes.find(function (q) { return q.lesson_id === lessonId; });
    if (lessonQuiz) return lessonQuiz;
    // Fall back to first module quiz
    return quizzes[0];
  }

  window.DataAdapter = {
    loadCourse: loadCourse,
    loadModule: loadModule,
    loadLessonsForModule: loadLessonsForModule,
    loadLesson: loadLesson,
    loadModuleQuizzes: loadModuleQuizzes,
    loadQuiz: loadQuiz,
    markLessonStarted: markLessonStarted,
    markLessonComplete: markLessonComplete,
    submitQuiz: submitQuiz,
    awardBadge: awardBadge,
    loadMyBadges: loadMyBadges,
    toCourseLandingModel: toCourseLandingModel,
    toModuleHubModel: toModuleHubModel,
    toChapterModel: toChapterModel,
    pickQuizForLesson: pickQuizForLesson,
    moduleAccent: moduleAccent,
    lessonAccent: lessonAccent
  };
})();
