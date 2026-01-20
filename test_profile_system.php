<?php
/**
 * Phase 8: Profile System Backend Tests
 *
 * Tests all profile-related API endpoints and database operations
 * Run: php test_profile_system.php
 *
 * Tests:
 * 1. Database schema verification (4 tests)
 * 2. User model methods (8 tests)
 * 3. Profile view tracking (3 tests)
 * 4. Search public profiles (3 tests)
 * 5. URL validation (2 tests)
 * 6. Privacy enforcement (2 tests)
 *
 * Total: 22 tests
 */

require_once __DIR__ . '/api/config/database.php';
require_once __DIR__ . '/api/models/BaseModel.php';
require_once __DIR__ . '/api/models/User.php';

use App\Models\User;

// Test results
$tests = [];
$passed = 0;
$failed = 0;

// Helper function to record test results
function test($name, $condition, $details = '') {
    global $tests, $passed, $failed;

    $result = [
        'name' => $name,
        'passed' => $condition,
        'details' => $details
    ];

    $tests[] = $result;

    if ($condition) {
        $passed++;
        echo "✓ PASS: $name\n";
    } else {
        $failed++;
        echo "✗ FAIL: $name\n";
        if ($details) {
            echo "  Details: $details\n";
        }
    }
}

echo "=== Phase 8: Profile System Backend Tests ===\n\n";

// Test 1: Database Schema Verification
echo "Test Group 1: Database Schema Verification\n";
echo "-------------------------------------------\n";
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'bio'");
    $bioColumn = $stmt->fetch();
    test('Bio column exists in users table', $bioColumn !== false);

    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'headline'");
    $headlineColumn = $stmt->fetch();
    test('Headline column exists in users table', $headlineColumn !== false);

    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_public_profile'");
    $privacyColumn = $stmt->fetch();
    test('Privacy columns exist in users table', $privacyColumn !== false);

    $stmt = $pdo->query("SHOW TABLES LIKE 'profile_views'");
    $profileViewsTable = $stmt->fetch();
    test('Profile_views table exists', $profileViewsTable !== false);
} catch (PDOException $e) {
    test('Database schema verification', false, $e->getMessage());
}

// Test 2: User Model Methods
echo "\nTest Group 2: User Model Methods\n";
echo "-------------------------------------------\n";
try {
    $userModel = new User($pdo);

    // Get or create a test user
    $stmt = $pdo->query("SELECT id FROM users LIMIT 1");
    $testUser = $stmt->fetch();

    if (!$testUser) {
        echo "⚠ Warning: No users in database. Creating test user...\n";
        $testUserId = $userModel->createUser([
            'email' => 'test_phase8@example.com',
            'password' => 'testpassword123',
            'name' => 'Test User Phase 8',
            'role' => 'student'
        ]);
    } else {
        $testUserId = $testUser['id'];
    }

    test('Test user available', $testUserId > 0, "User ID: $testUserId");

    // Test updateProfileFields
    $profileData = [
        'bio' => 'Test bio for Phase 8 implementation',
        'headline' => 'AI Enthusiast & Learner',
        'location' => 'Johannesburg, South Africa'
    ];

    $updated = $userModel->updateProfileFields($testUserId, $profileData);
    test('updateProfileFields() returns true', $updated === true);

    // Verify update
    $user = $userModel->find($testUserId);
    test('Bio was saved correctly', $user->bio === 'Test bio for Phase 8 implementation');
    test('Headline was saved correctly', $user->headline === 'AI Enthusiast & Learner');
    test('last_profile_updated timestamp was set', !empty($user->last_profile_updated));

    // Test getPublicProfileData
    $publicProfile = $userModel->getPublicProfileData($testUserId);
    test('getPublicProfileData() returns array', is_array($publicProfile));
    test('Public profile contains name field', isset($publicProfile['name']));
    test('Public profile contains statistics', isset($publicProfile['statistics']));

    // Test profile completion
    $completion = $userModel->getProfileCompletionPercentage($testUserId);
    test('getProfileCompletionPercentage() returns integer', is_int($completion));
    test('Completion is between 0-100', $completion >= 0 && $completion <= 100);

    // Test privacy settings
    $privacyData = [
        'is_public_profile' => false,
        'show_email' => false
    ];
    $privacyUpdated = $userModel->updatePrivacySettings($testUserId, $privacyData);
    test('updatePrivacySettings() returns true', $privacyUpdated === true);

    // Verify privacy update
    $user = $userModel->find($testUserId);
    test('Privacy setting (is_public_profile) was saved', $user->is_public_profile == false);

    // Reset to public for other tests
    $userModel->updatePrivacySettings($testUserId, ['is_public_profile' => true]);

} catch (Exception $e) {
    test('User model methods', false, $e->getMessage());
}

// Test 3: Profile View Tracking
echo "\nTest Group 3: Profile View Tracking\n";
echo "-------------------------------------------\n";
try {
    $userModel = new User($pdo);

    // Get or create a second test user for viewing
    $stmt = $pdo->query("SELECT id FROM users WHERE id != $testUserId LIMIT 1");
    $viewer = $stmt->fetch();

    if (!$viewer) {
        echo "⚠ Warning: Need second user for view tracking. Creating...\n";
        $viewerId = $userModel->createUser([
            'email' => 'viewer_phase8@example.com',
            'password' => 'testpassword123',
            'name' => 'Viewer User',
            'role' => 'student'
        ]);
    } else {
        $viewerId = $viewer['id'];
    }

    // Get initial view count
    $user = $userModel->find($testUserId);
    $initialViewCount = $user->profile_views_count ?? 0;

    // Track a view (viewer views test user's profile)
    $tracked = $userModel->trackProfileView($testUserId, $viewerId, '127.0.0.1', 'Test User Agent');
    test('trackProfileView() returns true', $tracked === true);

    // Verify view was recorded in profile_views table
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM profile_views WHERE viewed_user_id = ? AND viewer_user_id = ?");
    $stmt->execute([$testUserId, $viewerId]);
    $result = $stmt->fetch();
    test('Profile view recorded in profile_views table', $result['count'] > 0);

    // Verify view counter incremented
    $user = $userModel->find($testUserId);
    test('Profile views counter incremented', $user->profile_views_count > $initialViewCount,
        "Before: $initialViewCount, After: {$user->profile_views_count}");

    // Test that self-views are NOT tracked
    $selfView = $userModel->trackProfileView($testUserId, $testUserId, '127.0.0.1', 'Self View');
    test('Self-views return false (not tracked)', $selfView === false);

} catch (Exception $e) {
    test('Profile view tracking', false, $e->getMessage());
}

// Test 4: Search Public Profiles
echo "\nTest Group 4: Search Public Profiles\n";
echo "-------------------------------------------\n";
try {
    $userModel = new User($pdo);

    // Search for profiles
    $results = $userModel->searchPublicProfiles('Test', true, 10, 0);
    test('searchPublicProfiles() returns array', is_array($results));
    test('Search returns at least one result', count($results) > 0, "Found: " . count($results) . " profiles");

    // Verify results contain expected fields
    if (count($results) > 0) {
        $firstResult = $results[0];
        $hasRequiredFields = isset($firstResult['id']) &&
                            isset($firstResult['name']) &&
                            isset($firstResult['email']);
        test('Search results contain required fields', $hasRequiredFields);
    } else {
        test('Search results contain required fields', false, 'No results to verify');
    }

} catch (Exception $e) {
    test('Search public profiles', false, $e->getMessage());
}

// Test 5: URL Validation
echo "\nTest Group 5: URL Validation\n";
echo "-------------------------------------------\n";
try {
    $userModel = new User($pdo);

    $validUrls = [
        'website_url' => 'https://example.com',
        'github_url' => 'https://github.com/username',
        'linkedin_url' => 'https://linkedin.com/in/username',
        'twitter_url' => 'https://twitter.com/username'
    ];

    $updated = $userModel->updateProfileFields($testUserId, $validUrls);
    test('Valid URLs are accepted', $updated === true);

    // Verify URLs were saved
    $user = $userModel->find($testUserId);
    test('Website URL was saved', $user->website_url === 'https://example.com');
    test('GitHub URL was saved', $user->github_url === 'https://github.com/username');

} catch (Exception $e) {
    test('URL validation', false, $e->getMessage());
}

// Test 6: Privacy Enforcement
echo "\nTest Group 6: Privacy Enforcement\n";
echo "-------------------------------------------\n";
try {
    $userModel = new User($pdo);

    // Make profile private
    $userModel->updatePrivacySettings($testUserId, ['is_public_profile' => false]);

    // Try to get public profile
    $publicProfile = $userModel->getPublicProfileData($testUserId);
    test('Private profiles return null', $publicProfile === null);

    // Make profile public again
    $userModel->updatePrivacySettings($testUserId, ['is_public_profile' => true]);
    $publicProfile = $userModel->getPublicProfileData($testUserId);
    test('Public profiles return data', $publicProfile !== null);

    // Test selective visibility (show_achievements)
    $userModel->updatePrivacySettings($testUserId, ['show_achievements' => false]);
    $user = $userModel->find($testUserId);
    test('Selective visibility setting saved', $user->show_achievements == false);

    // Reset for future tests
    $userModel->updatePrivacySettings($testUserId, ['show_achievements' => true]);

} catch (Exception $e) {
    test('Privacy enforcement', false, $e->getMessage());
}

// Summary
echo "\n=== Test Summary ===\n";
echo "Total Tests: " . ($passed + $failed) . "\n";
echo "Passed: $passed (" . round(($passed / ($passed + $failed)) * 100, 1) . "%)\n";
echo "Failed: $failed\n";

if ($failed === 0) {
    echo "\n✓✓✓ All tests passed! Phase 8A backend is ready. ✓✓✓\n";
    echo "\nNext Steps:\n";
    echo "1. Proceed to Phase 8B: Create profile-edit.html\n";
    echo "2. Create js/profile-edit.js\n";
    echo "3. Update profile.html to display new fields\n";
    exit(0);
} else {
    echo "\n✗✗✗ Some tests failed. Review errors above. ✗✗✗\n";
    exit(1);
}
