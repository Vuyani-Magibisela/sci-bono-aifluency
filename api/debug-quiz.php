<!DOCTYPE html>
<html>
<head>
<title>Quiz Debug - Frontend Test</title>
<style>
body { font-family: monospace; background: #1a1a2e; color: #e0e0e0; padding: 20px; max-width: 900px; margin: 0 auto; }
.pass { color: #00ff88; } .fail { color: #ff4444; } .warn { color: #ffaa00; }
.section { background: #16213e; padding: 15px; margin: 10px 0; border-radius: 8px; }
h2 { color: #00d4ff; margin-top: 0; }
pre { background: #0a0a1a; padding: 10px; overflow-x: auto; border-radius: 4px; white-space: pre-wrap; }
button { background: #0f3460; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 14px; margin: 5px; }
button:hover { background: #1a5276; }
#results { margin-top: 15px; }
</style>
</head>
<body>
<h1>Quiz Debug — Frontend + Backend</h1>
<p>Time: <?= date('Y-m-d H:i:s T') ?></p>

<!-- SECTION A: Server-side checks (PHP) -->
<div class="section">
<h2>A. Server-Side Checks</h2>
<pre><?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    require_once __DIR__ . '/vendor/autoload.php';
    require_once __DIR__ . '/config/config.php';

    $host = defined('DB_HOST') ? DB_HOST : 'localhost';
    $dbname = defined('DB_NAME') ? DB_NAME : 'ai_fluency_lms';
    $username = defined('DB_USER') ? DB_USER : 'root';
    $password = defined('DB_PASSWORD') ? DB_PASSWORD : '';
    $port = defined('DB_PORT') ? DB_PORT : 3306;

    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    echo "DB connected: $dbname\n\n";

    // Count attempts
    $cnt = $pdo->query("SELECT COUNT(*) as c FROM quiz_attempts")->fetch(PDO::FETCH_ASSOC);
    echo "Total quiz_attempts: {$cnt['c']}\n\n";

    echo "Recent attempts:\n";
    $stmt = $pdo->query("SELECT id, user_id, quiz_id, score, passed, status, time_started FROM quiz_attempts ORDER BY id DESC LIMIT 10");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        echo sprintf("  ID=%-4s user=%-4s quiz=%-4s score=%-8s passed=%-2s status=%-10s started=%s\n",
            $r['id'], $r['user_id'], $r['quiz_id'], $r['score'], $r['passed'], $r['status'], $r['time_started'] ?? 'NULL');
    }
    if (empty($rows)) echo "  (none)\n";

    // Last 5 error lines
    echo "\nLast 5 php_errors.log:\n";
    $logFile = __DIR__ . '/logs/php_errors.log';
    if (file_exists($logFile)) {
        $lines = array_slice(file($logFile), -5);
        foreach ($lines as $l) echo "  " . rtrim($l) . "\n";
    } else {
        echo "  (not found)\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?></pre>
</div>

<!-- SECTION B: Frontend JavaScript Tests -->
<div class="section">
<h2>B. Frontend Auth & API Tests</h2>
<p>These tests run in your browser using the same JS libraries the quiz page uses.</p>
<button onclick="runAllTests()">Run All Frontend Tests</button>
<div id="results"><pre>Click the button above to run tests...</pre></div>
</div>

<!-- Load the same JS files the quiz page uses -->
<script src="/js/storage.js"></script>
<script src="/js/api.js"></script>
<script src="/js/auth.js"></script>
<script src="/js/content-loader.js"></script>

<script>
function log(msg, type = '') {
    const el = document.getElementById('results');
    const span = type ? `<span class="${type}">${msg}</span>` : msg;
    el.innerHTML += span + '\n';
}

async function runAllTests() {
    const el = document.getElementById('results');
    el.innerHTML = '<pre>';

    log('=== FRONTEND DIAGNOSTIC ===');
    log('Time: ' + new Date().toISOString());
    log('Page URL: ' + window.location.href);
    log('');

    // Test 1: Storage / Auth
    log('--- Test 1: Authentication ---');
    const token = Storage.get('access_token');
    const refreshToken = Storage.get('refresh_token');
    const userData = Storage.get('user');

    if (token) {
        log('[OK] access_token exists: ' + token.substring(0, 20) + '...', 'pass');
    } else {
        log('[FAIL] access_token is NULL — user is not logged in!', 'fail');
        log('       The quiz page will skip API call and use local scoring.', 'fail');
        log('       FIX: Log in first, then take the quiz.', 'warn');
    }

    if (refreshToken) {
        log('[OK] refresh_token exists', 'pass');
    } else {
        log('[WARN] refresh_token is NULL', 'warn');
    }

    if (userData) {
        let user = userData;
        if (typeof user === 'string') {
            try { user = JSON.parse(user); } catch(e) {}
        }
        log('[OK] User data: id=' + (user.id || '?') + ', name=' + (user.name || '?') + ', role=' + (user.role || '?'), 'pass');
    } else {
        log('[FAIL] No user data in storage', 'fail');
    }

    // Test 1b: Auth.getUser() — this is what ContentLoader checks
    log('');
    log('--- Test 1b: Auth.getUser() ---');
    try {
        const authUser = Auth.getUser();
        if (authUser) {
            log('[OK] Auth.getUser() returns: id=' + authUser.id + ', name=' + authUser.name, 'pass');
        } else {
            log('[FAIL] Auth.getUser() returns NULL — THIS IS WHY QUIZ DOESNT SAVE!', 'fail');
            log('       ContentLoader.submitQuiz() checks Auth.getUser() first.', 'fail');
            log('       If null, it skips the API and calculates score locally.', 'fail');
        }
    } catch(e) {
        log('[FAIL] Auth.getUser() threw error: ' + e.message, 'fail');
    }

    // Test 2: API base URL
    log('');
    log('--- Test 2: API Configuration ---');
    log('API.baseURL = ' + API.baseURL);
    if (API.baseURL === '/api' || API.baseURL === '/sci-bono-aifluency/api') {
        log('[OK] API base URL looks correct', 'pass');
    } else {
        log('[WARN] Unexpected API base URL', 'warn');
    }

    // Test 3: API health check
    log('');
    log('--- Test 3: API Health Check ---');
    try {
        const resp = await fetch(API.baseURL + '/health', {
            headers: { 'Content-Type': 'application/json' }
        });
        const data = await resp.json();
        log('[OK] API /health responded: ' + resp.status + ' — ' + JSON.stringify(data).substring(0, 100), 'pass');
    } catch(e) {
        log('[FAIL] API /health failed: ' + e.message, 'fail');
    }

    // Test 4: Authenticated API call
    log('');
    log('--- Test 4: Authenticated API Call (GET /quizzes) ---');
    if (token) {
        try {
            const resp = await API.get('/quizzes?published=true');
            if (resp && resp.data) {
                const quizzes = resp.data.items || resp.data.quizzes || [];
                log('[OK] GET /quizzes returned ' + quizzes.length + ' quizzes', 'pass');
                if (quizzes.length > 0) {
                    log('     First quiz: id=' + quizzes[0].id + ', title=' + quizzes[0].title, '');
                }
            } else {
                log('[WARN] GET /quizzes returned: ' + JSON.stringify(resp).substring(0, 200), 'warn');
            }
        } catch(e) {
            log('[FAIL] GET /quizzes failed: ' + e.message, 'fail');
            log('       Auth may be expired. Try logging out and back in.', 'warn');
        }
    } else {
        log('[SKIP] No token, skipping authenticated test', 'warn');
    }

    // Test 5: Get quiz attempts
    log('');
    log('--- Test 5: GET /quizzes/1/attempts ---');
    if (token) {
        try {
            const resp = await API.get('/quizzes/1/attempts');
            if (resp && resp.data) {
                const attempts = resp.data.attempts || [];
                log('[OK] GET /quizzes/1/attempts returned ' + attempts.length + ' attempts', 'pass');
                attempts.forEach(a => {
                    log('     Attempt ID=' + a.id + ' score=' + a.score + ' passed=' + a.passed + ' status=' + a.status, '');
                });
            }
        } catch(e) {
            log('[FAIL] GET /quizzes/1/attempts failed: ' + e.message, 'fail');
        }
    } else {
        log('[SKIP] No token', 'warn');
    }

    // Test 6: Test quiz submission (quiz_id=1, dummy answers)
    log('');
    log('--- Test 6: POST /quizzes/1/submit (test submission) ---');
    if (token) {
        try {
            const testAnswers = [
                {question_id: 1, selected_answer: 1, time_spent: 5},
                {question_id: 75, selected_answer: 1, time_spent: 5},
                {question_id: 2, selected_answer: 2, time_spent: 5},
                {question_id: 76, selected_answer: 2, time_spent: 5},
                {question_id: 3, selected_answer: 1, time_spent: 5},
                {question_id: 77, selected_answer: 1, time_spent: 5},
                {question_id: 4, selected_answer: 2, time_spent: 5},
                {question_id: 78, selected_answer: 2, time_spent: 5},
                {question_id: 79, selected_answer: 1, time_spent: 5},
                {question_id: 80, selected_answer: 2, time_spent: 5},
                {question_id: 81, selected_answer: 1, time_spent: 5},
                {question_id: 82, selected_answer: 2, time_spent: 5},
                {question_id: 83, selected_answer: 1, time_spent: 5},
                {question_id: 84, selected_answer: 1, time_spent: 5}
            ];

            log('  Submitting all 14 correct answers...');
            const resp = await API.post('/quizzes/1/submit', { answers: testAnswers });
            log('[OK] Quiz submission response:', 'pass');
            log('  ' + JSON.stringify(resp).substring(0, 500));
            if (resp.data) {
                log('  Score: ' + resp.data.score + ', Passed: ' + resp.data.passed, resp.data.passed ? 'pass' : 'fail');
            }
        } catch(e) {
            log('[FAIL] Quiz submission FAILED: ' + e.message, 'fail');
            log('  This is the error the quiz page silently catches!', 'warn');
        }
    } else {
        log('[SKIP] No token — cannot test submission', 'warn');
    }

    // Test 7: ContentLoader.submitQuiz (the actual function the quiz page uses)
    log('');
    log('--- Test 7: ContentLoader.submitQuiz() (actual function) ---');
    if (typeof ContentLoader !== 'undefined') {
        log('[OK] ContentLoader is loaded', 'pass');
        if (typeof ContentLoader.submitQuiz === 'function') {
            log('[OK] ContentLoader.submitQuiz exists', 'pass');
        } else {
            log('[FAIL] ContentLoader.submitQuiz is not a function', 'fail');
        }
    } else {
        log('[FAIL] ContentLoader is NOT defined — JS file not loaded?', 'fail');
    }

    log('');
    log('=== DONE ===');
    el.innerHTML += '</pre>';
}
</script>

</body>
</html>
