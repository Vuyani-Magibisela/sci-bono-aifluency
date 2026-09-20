<?php
/**
 * Badge endpoint tests (v2 course UI gamification).
 *
 * HTTP-level tests against a running server, rather than the in-process style used by the
 * older tests in this directory (those predate the PDO-injected controller constructors
 * and no longer bootstrap correctly).
 *
 * Requires a server serving the API, e.g. from the repo root:
 *     php -S localhost:8000 dev-router.php
 *
 * Override defaults with environment variables:
 *     API_BASE_URL   (default http://localhost:8000/api)
 *     TEST_EMAIL     (default v2qa.student@test.local)
 *     TEST_PASSWORD  (default SecurePass123!)
 *
 * Exits 0 when every assertion passes, 1 otherwise.
 */

$baseUrl  = getenv('API_BASE_URL') ?: 'http://localhost:8000/api';
$email    = getenv('TEST_EMAIL') ?: 'v2qa.student@test.local';
$password = getenv('TEST_PASSWORD') ?: 'SecurePass123!';

// Fixed slug so repeated runs stay idempotent - the UNIQUE(user_id, badge_slug)
// constraint in migration 032 means a rerun never accumulates rows.
$slug = 'qa_test_badge';

$passed = 0;
$failed = 0;

function request(string $method, string $url, ?array $body = null, ?string $token = null): array
{
    $ch = curl_init($url);
    $headers = ['Accept: application/json'];

    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    if ($token !== null) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 15,
    ]);

    $raw    = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error  = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['status' => 0, 'body' => null, 'error' => $error];
    }

    return ['status' => $status, 'body' => json_decode($raw, true), 'raw' => $raw];
}

function check(string $name, bool $condition, string $detail = ''): void
{
    global $passed, $failed;

    if ($condition) {
        $passed++;
        echo "  PASS  {$name}\n";
    } else {
        $failed++;
        echo "  FAIL  {$name}" . ($detail !== '' ? " -- {$detail}" : '') . "\n";
    }
}

echo "Badge endpoint tests against {$baseUrl}\n\n";

// ---------------------------------------------------------------- authentication
$login = request('POST', $baseUrl . '/auth/login', ['email' => $email, 'password' => $password]);

if ($login['status'] !== 200 || empty($login['body']['data']['tokens']['accessToken'])) {
    echo "  FAIL  login as {$email} -- HTTP {$login['status']}: " . substr($login['raw'] ?? '', 0, 200) . "\n";
    echo "\nCannot continue without a token.\n";
    exit(1);
}

$token = $login['body']['data']['tokens']['accessToken'];
check('login returns an access token', true);

// ---------------------------------------------------------------- auth is enforced
$r = request('GET', $baseUrl . '/badges/my-badges');
check('GET /badges/my-badges without a token is rejected', $r['status'] === 401, "got HTTP {$r['status']}");

$r = request('POST', $baseUrl . '/badges', ['badge_slug' => $slug]);
check('POST /badges without a token is rejected', $r['status'] === 401, "got HTTP {$r['status']}");

// ---------------------------------------------------------------- awarding
$r = request('POST', $baseUrl . '/badges', [
    'badge_slug' => $slug,
    'badge_name' => 'QA Test Badge',
    'lesson_id'  => 1,
    'module_id'  => 1,
], $token);
check('POST /badges with a valid slug succeeds', $r['status'] === 200, "got HTTP {$r['status']}");
check(
    'awarded badge is returned in the response',
    ($r['body']['data']['badge']['badge_slug'] ?? null) === $slug,
    'response: ' . substr($r['raw'] ?? '', 0, 200)
);

// ---------------------------------------------------------------- idempotency
$before = request('GET', $baseUrl . '/badges/my-badges', null, $token);
$countBefore = $before['body']['data']['total'] ?? -1;

$r = request('POST', $baseUrl . '/badges', ['badge_slug' => $slug, 'badge_name' => 'QA Test Badge'], $token);
check('re-awarding the same badge still succeeds', $r['status'] === 200, "got HTTP {$r['status']}");

$after = request('GET', $baseUrl . '/badges/my-badges', null, $token);
$countAfter = $after['body']['data']['total'] ?? -2;
check(
    're-awarding does not create a duplicate row',
    $countBefore === $countAfter && $countBefore >= 1,
    "before={$countBefore} after={$countAfter}"
);

// ---------------------------------------------------------------- slug validation
$invalid = [
    'empty slug'            => '',
    'slug containing space' => 'bad slug',
    'slug with punctuation' => 'bad!slug',
    'slug over 64 chars'    => str_repeat('a', 65),
];
foreach ($invalid as $label => $bad) {
    $r = request('POST', $baseUrl . '/badges', ['badge_slug' => $bad], $token);
    check("POST /badges rejects {$label}", $r['status'] === 400, "got HTTP {$r['status']}");
}

// ---------------------------------------------------------------- listing
$r = request('GET', $baseUrl . '/badges/my-badges', null, $token);
check('GET /badges/my-badges succeeds', $r['status'] === 200, "got HTTP {$r['status']}");

$items = $r['body']['data']['items'] ?? null;
check('listing returns an items array', is_array($items));

$slugs = is_array($items) ? array_column($items, 'badge_slug') : [];
check('listing includes the awarded badge', in_array($slug, $slugs, true), 'slugs: ' . implode(',', $slugs));

// module_id filter: the badge was awarded against module 1, so a different module must not match
$r = request('GET', $baseUrl . '/badges/my-badges?module_id=1', null, $token);
$filtered = array_column($r['body']['data']['items'] ?? [], 'badge_slug');
check('module_id=1 filter includes the badge', in_array($slug, $filtered, true), 'slugs: ' . implode(',', $filtered));

$r = request('GET', $baseUrl . '/badges/my-badges?module_id=9999', null, $token);
$filtered = array_column($r['body']['data']['items'] ?? [], 'badge_slug');
check('module_id=9999 filter excludes the badge', !in_array($slug, $filtered, true), 'slugs: ' . implode(',', $filtered));

// ---------------------------------------------------------------- summary
echo "\n" . str_repeat('-', 60) . "\n";
echo "Passed: {$passed}   Failed: {$failed}\n";

exit($failed === 0 ? 0 : 1);
