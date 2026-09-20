# Course v2 (Gamified UI) — QA & Deployment Readiness Report

**Date:** 2026-09-19
**Build under test:** `main` @ `a8c141e`, plus the fixes listed below
**Environment:** WSL, PHP 8.5.4 CLI server via `dev-router.php`, MySQL `scibono_aihub_db`
**Test account:** `v2qa.student@test.local` (user id 1173, role `student`, enrolled in course 1)

Course v2 was committed in `e1c1054` (2026-08-03) and had never been run against a live stack.
This is the first end-to-end verification pass.

---

## Verdict

**The 4 authored chapters are ready to ship. The other 40 lessons are not.**

The gamification core — progress, badges, mini-games, module gating — works correctly against real
data. One launch-blocking defect was found **and fixed** (XP computed three different ways,
collapsing after a chapter). A second blocker was found and is **not fixed**, because the fix is a
design decision: on non-pack lessons, finishing a chapter silently spends one of the student's four
Knowledge Check attempts (Defect 7).

Both remaining problems are confined to the 40 auto-summarized lessons. Gating v2 to the 4 authored
lessons resolves the summarizer quality issue *and* the quiz-attempt issue at once, and is the
recommended path to shipping now.

Risk of deploying is low by construction: v2 is opt-in. `js/course-v2/ui-mode.js` defaults
`aihub_ui_mode` to `'old'`, so no existing learner is moved into the new UI without choosing it.

---

## What was verified

| Area | Result |
|---|---|
| First-visit popup + persistent switcher | Pass |
| Course hub (6 modules, lock states, completion %) | Pass |
| Module hub (11 missions, earned badge chips) | Pass |
| Chapter runner (intro → concept → era → challenge → mini-game → reward) | Pass |
| Mini-games: Match (tap-to-pair), Turing | Pass |
| Badge persistence (`user_badges`, idempotent) | Pass |
| `lesson_progress` written, enrollment % recalculated (1/44 → 2.27%) | Pass |
| Module gating on a locked module | Pass — API 403s, reason surfaced |
| Old-UI hook: redirect when opted in, banner when not | Pass |
| Mobile at 400px (no horizontal overflow, single column) | Pass |
| Console errors during the happy path | None |
| Badge API tests (`api/tests/test_badges.php`, 16 assertions) | Pass |
| XP consistency across course / module / chapter | **Failed, now fixed** |
| Knowledge Check submission from a non-pack chapter | **Failed — see Defect 7, not fixed** |

### Not covered

- **PWA / offline.** The `CACHE_NAME` bump and the 22 precached v2 paths were verified by
  inspection, not by loading a v2 page with the network disabled.
- **Old-UI → v2 carry-over.** v2's totals are derived entirely from `lesson_progress` and
  `quiz_attempts`, and were confirmed to read existing rows, but a lesson was not completed in the
  classic UI and then observed appearing in v2.
- **Mini-games Sort-It and Data Explosion slider.** Match and Turing were played end to end; the
  other two were not reached, since they sit on chapters not walked.
- **Drag-and-drop input.** Match was completed via tap-to-pair; the drag path was not exercised.

### Backend tests

`api/tests/test_badges.php` was added — 16 HTTP-level assertions covering auth enforcement,
awarding, idempotency, slug validation, listing and the `module_id` filter. It is wired into
`api/tests/run_all_tests.sh`.

It is written as an HTTP test rather than following the in-process style of the older tests in
that directory, because **those tests are stale and cannot pass** — they `require __DIR__ .
'/vendor/autoload.php'` (there is no `api/tests/vendor/`) and construct controllers with no
argument, while `BaseController::__construct(\PDO $pdo)` has required one since Phase 11. All 9
legacy tests fail for this reason, unrelated to v2. **Repairing them is open follow-up work.**

---

## Defects found and fixed

### 1. XP was computed three different ways (launch blocker)

Finishing the first chapter showed **+120 XP**; the module hub then showed **45 XP**; the chapter
page header showed **27 XP**. A student's XP visibly collapsed after completing a chapter.

Causes, all now corrected:

- `student/course-v2/course.html` and `chapter.html` derived XP as
  `Math.round(completion_percentage * 3)` — an ad-hoc heuristic unrelated to the real model. The
  `js/course-v2/xp.js` constants were only ever used by `module.html`, despite that file
  documenting itself as the shared source of truth.
- `chapter-runner.js` accumulated per-step XP (`XP_CONCEPT`, `XP_QUIZ_FIRST_CORRECT`) into the
  header pill and the reward screen, but those awards are in-run only and are never re-derived.

Fix:

- Added `XP.courseXPFromModules()` to `js/course-v2/xp.js` — one course-wide derivation built
  from the existing constants, using data `GET /courses/:id` already returns (no extra requests
  on the course page). All three screens now call it, so the header pill reads identically
  everywhere.
- `awardXP()` gained a `persisted` flag. Burst animations still fire for every step award, but
  only awards the derived model can read back (lesson completion, quiz score) move the pill.
- The reward screen reports the persisted delta instead of the in-run total.
- Replaying a completed chapter awards nothing further (guarded on `chapter.alreadyCompleted`),
  so the pill cannot drift above the derived value.

Verified: completing a fresh chapter moves the pill 30 → 60, the reward reads **+30**, and 60 XP
survives a reload on all three screens.

*Note:* the module hub's "N XP earned" line remains module-scoped and includes mini-game XP, so it
is legitimately larger than the course-wide pill. `courseXPFromModules()` excludes mini-game XP
because the course payload cannot say which lessons carry one — documented in `xp.js`.

### 2. Course description rendered as `AI&#039;s`

`courses.description` was stored pre-escaped at migration time, and v2 escaped it again.
`Shell.escapeHtml()` now decodes entities once before escaping, which is still safe against real
markup. One row is affected today; the fix covers any content re-introduced the same way.

### 3. Chapter label read "CHAPTER 1.104"

`formatChapterNumber()` used `order_index` directly, but module 1's lessons are indexed 100–110.
It now prefers the canonical number in the lesson title and folds the hundreds offset out of the
fallback.

### 4. Intro text duplicated on non-pack lessons

Where `description` is empty, the intro body fell back to `subtitle` — the same string already
shown as the tagline, printed twice in a row. The body is now dropped instead.

### 5. Locked module presented as a crash

A gated lesson rendered "Something went wrong" with the header stuck on "Loading…". It now reads
"Locked for now" with the API's explanation, and no error is logged for an expected state.
Added `Shell.setContextLabel()`.

### 6. Stray `-->` rendered on all 6 old-UI module pages (pre-existing)

The disabled static-header block is opened with `<!--`, but a nested `<!-- ... -->` around the
download button closed it early, leaving the block's own `-->` visible as page text. Present since
`da42913` (2025-11-11) — not a v2 regression, but visible on the default UI, so fixed in all six
files by removing the nested comment.

---

## Defect 7 — NOT fixed: non-pack chapters silently spend Knowledge Check attempts

**Severity: launch blocker for the 40 auto-summarized lessons. Needs a design decision.**

Every quiz in the database is module-level (`quizzes.lesson_id` is NULL for all 6). So
`pickQuizForLesson()` (`js/course-v2/data-adapter.js:560`) falls back to `quizzes[0]` — the module's
graded Knowledge Check — for **every** lesson in that module.

For pack-authored chapters this is harmless: `submitDBQuiz: false` (data-adapter.js:312), with an
explicit comment that pack quizzes are local practice. For non-pack chapters `submitDBQuiz: !!quiz`
(data-adapter.js:368), so finishing the chapter POSTs a real attempt to `/quizzes/:id/submit`.

Module 1 has 7 non-pack lessons (ids 5–11). `quizzes.max_attempts` is **4**, enforced server-side by
`QuizController::submitAttempt` via `canUserAttempt()`. Verified against the running API:

```
auto-submit 1 -> HTTP 201 Quiz submitted successfully
auto-submit 2 -> HTTP 201 Quiz submitted successfully
auto-submit 3 -> HTTP 201 Quiz submitted successfully
auto-submit 4 -> HTTP 201 Quiz submitted successfully
auto-submit 5 -> HTTP 403 You have reached the maximum number of attempts for this quiz
auto-submit 6 -> HTTP 403 You have reached the maximum number of attempts for this quiz
```

So a student working through module 1 in v2 burns all four Knowledge Check attempts by their fifth
non-pack chapter — on answers they never knowingly submitted as the graded assessment — and is then
permanently locked out of the quiz that gates module 2. Passing score is 70.

This did not surface in the walkthrough because the chapters played were pack-authored; the QA
account had **0** quiz attempts after two completed chapters, which is correct for those.

Options:

1. **Gate v2 to authored lessons** — the issue cannot arise, since packed chapters never submit.
   Same fix as the summarizer decision below.
2. **Only submit on the module's final lesson** — one attempt per pass through the module.
3. **Make the Knowledge Check a deliberate action** — a distinct "Take the Knowledge Check" mission
   on the module hub rather than a side effect of finishing any chapter. Cleanest long-term, and it
   restores the visible Knowledge Check that the v2 module hub currently lacks.

Left unfixed deliberately: each option changes assessment behaviour, which is yours to choose.

---

## Open decision: auto-summarized lesson content

**Only 4 of 44 lessons have hand-authored content packs** (`js/course-v2/content-pack.js` covers
lesson ids 1–4, all in module 1). The other 40 fall through to the auto-summarizer.

The four authored chapters are genuinely good. The summarizer output is not. Observed on
Chapter 1.04:

- Heading: *"Introduction to Patterns in Data Data…"* — duplicated word, truncated mid-phrase.
- Body: *"Introduction to Patterns in Data Data is constantly being created and refined…"* — the
  heading is repeated into the start of the body, producing a run-on sentence.
- Card 2 pulled decorative markup into prose: *"…looking for in the data? 101010 010101 110011
  001100 111000 000111 AI systems look for patterns…"*

This is student-facing and reads as broken. Three options:

1. **Gate v2 to authored lessons** (recommended) — extend the allowlist pattern already in
   `js/course-v2/ui-mode.js:20` to lesson level, so unpacked lessons stay on the classic UI.
   Ships the strong experience now without exposing the weak one, **and resolves Defect 7 at the
   same time**.
2. **Author the remaining 40 packs** — best result, much larger piece of work.
3. **Ship as-is** — fastest, but the majority of the course reads poorly.

Improving the summarizer itself (stripping headings already used as titles, dropping
decorative nodes) is possible but would still be guesswork over arbitrary lesson HTML.

---

## Deployment checklist

1. Import `database/production/05_migration_032_user_badges.sql` via phpMyAdmin **after**
   `01_schema.sql`. Verified idempotent (`CREATE TABLE IF NOT EXISTS`, re-import is a no-op).
   Without it, `POST /api/badges` 500s and badges never appear.
2. Upload `student/course-v2/`, `css/course-v2/`, `js/course-v2/`, `images/course-v2/` — none are
   stripped by `.deployignore`.
3. Service worker `CACHE_NAME` bumped **v62 → v63**. Clients need one reload to pick it up.
4. `dev-router.php` is development-only and is listed in `.deployignore` — do not upload it.
5. **Resolve Defect 7 and the summarizer decision before enabling v2 beyond module 1's first four
   chapters.** Both are safe if v2 is gated to the authored lessons.

### Rollback

v2 is opt-in and additive. To disable: do not upload `student/course-v2/`, or remove the
`old-module-hook.js` include from `student/modules/moduleN.html`. Learners on `aihub_ui_mode=old`
(the default) are unaffected either way. The `user_badges` table can be left in place.

---

## Known gaps / follow-up

- The 9 legacy tests in `api/tests/` cannot pass (stale bootstrap) — worth repairing or removing.
- `js/api.js:284` calls `GET /health`, which has no route in `api/routes/api.php`. Pre-existing;
  `healthCheck()` is not on any critical path.
- Match mini-game tiles are `div`s, not buttons — no keyboard access or ARIA roles. Tap and mouse
  both work.
- The old module pages now stack two banners ("Enhanced Version Available!" and "Try the new
  gamified experience"). Cluttered; consider consolidating.
- `test_badges.php` awards a real `qa_test_badge` to the test account, which then shows on that
  account's module hub. Harmless on a QA user; do not run it against a real learner.
