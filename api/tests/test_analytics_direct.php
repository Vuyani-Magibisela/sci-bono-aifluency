<?php
/**
 * Direct Analytics Methods Test
 * Phase 6 - Task 9: Test analytics methods directly
 */

require_once 'api/vendor/autoload.php';

use App\Models\QuizQuestion;
use App\Models\QuizAttempt;

// Get global PDO connection
$pdo = require 'api/config/database.php';

echo "=== Phase 6 Analytics Direct Method Tests ===\n\n";

$tests = [];
$passed = 0;
$failed = 0;

// Test 1: Question Difficulty Stats
echo "Test 1: Question Difficulty Stats (Question ID 1)\n";
try {
    $stats = QuizQuestion::getDifficultyStats(1);

    if (isset($stats['total_attempts']) && isset($stats['success_rate']) && isset($stats['difficulty_score'])) {
        echo "✅ PASSED\n";
        echo "Total Attempts: " . $stats['total_attempts'] . "\n";
        echo "Success Rate: " . $stats['success_rate'] . "%\n";
        echo "Difficulty Score: " . $stats['difficulty_score'] . "\n";
        $passed++;
    } else {
        echo "❌ FAILED - Missing required fields\n";
        print_r($stats);
        $failed++;
    }
} catch (Exception $e) {
    echo "❌ FAILED - Exception: " . $e->getMessage() . "\n";
    $failed++;
}
echo str_repeat("-", 80) . "\n\n";

// Test 2: Question Difficulty Ranking
echo "Test 2: Question Difficulty Ranking (Quiz ID 1)\n";
try {
    $ranking = QuizQuestion::getQuestionDifficultyRanking(1);

    if (is_array($ranking)) {
        echo "✅ PASSED\n";
        echo "Found " . count($ranking) . " questions\n";
        if (count($ranking) > 0) {
            echo "Sample: ID {$ranking[0]['id']}, Success Rate: {$ranking[0]['success_rate']}%, Difficulty: {$ranking[0]['difficulty_score']}\n";
        }
        $passed++;
    } else {
        echo "❌ FAILED - Expected array\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "❌ FAILED - Exception: " . $e->getMessage() . "\n";
    $failed++;
}
echo str_repeat("-", 80) . "\n\n";

// Test 3: Performance Trends
echo "Test 3: Performance Trends (User 5, Quiz 1)\n";
try {
    $trends = QuizAttempt::getPerformanceTrends(5, 1);

    if (isset($trends['attempts']) && isset($trends['trend']) && isset($trends['best_score'])) {
        echo "✅ PASSED\n";
        echo "Number of Attempts: " . count($trends['attempts']) . "\n";
        echo "Trend: " . $trends['trend'] . "\n";
        echo "Best Score: " . $trends['best_score'] . "\n";
        echo "Latest Score: " . $trends['latest_score'] . "\n";
        echo "Avg Improvement: " . $trends['avg_improvement'] . "\n";
        $passed++;
    } else {
        echo "❌ FAILED - Missing required fields\n";
        print_r($trends);
        $failed++;
    }
} catch (Exception $e) {
    echo "❌ FAILED - Exception: " . $e->getMessage() . "\n";
    $failed++;
}
echo str_repeat("-", 80) . "\n\n";

// Test 4: User Learning Curve
echo "Test 4: User Learning Curve (User 3)\n";
try {
    $curve = QuizAttempt::getUserLearningCurve(3);

    if (is_array($curve)) {
        echo "✅ PASSED\n";
        echo "Found " . count($curve) . " quiz attempts\n";
        if (count($curve) > 0) {
            echo "Sample: Quiz '{$curve[0]['quiz_title']}', Score: {$curve[0]['score']}\n";
        }
        $passed++;
    } else {
        echo "❌ FAILED - Expected array\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "❌ FAILED - Exception: " . $e->getMessage() . "\n";
    $failed++;
}
echo str_repeat("-", 80) . "\n\n";

// Test 5: Class Comparison
echo "Test 5: Class Comparison (User 3, Quiz 1)\n";
try {
    $comparison = QuizAttempt::getClassComparison(3, 1);

    if (isset($comparison['user_best_score']) && isset($comparison['class_average']) && isset($comparison['percentile'])) {
        echo "✅ PASSED\n";
        echo "User Best Score: " . $comparison['user_best_score'] . "\n";
        echo "Class Average: " . $comparison['class_average'] . "\n";
        echo "Class Median: " . $comparison['class_median'] . "\n";
        echo "User Rank: " . $comparison['rank'] . " of " . $comparison['total_students'] . "\n";
        echo "Percentile: " . $comparison['percentile'] . "%\n";
        $passed++;
    } else {
        echo "❌ FAILED - Missing required fields\n";
        print_r($comparison);
        $failed++;
    }
} catch (Exception $e) {
    echo "❌ FAILED - Exception: " . $e->getMessage() . "\n";
    $failed++;
}
echo str_repeat("-", 80) . "\n\n";

// Test 6: Quiz Leaderboard
echo "Test 6: Quiz Leaderboard (Quiz 1, Top 10)\n";
try {
    $leaderboard = QuizAttempt::getQuizLeaderboard(1, 10);

    if (is_array($leaderboard)) {
        echo "✅ PASSED\n";
        echo "Found " . count($leaderboard) . " students on leaderboard\n";
        foreach ($leaderboard as $i => $entry) {
            echo ($i + 1) . ". {$entry['name']} - Score: {$entry['score']}\n";
        }
        $passed++;
    } else {
        echo "❌ FAILED - Expected array\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "❌ FAILED - Exception: " . $e->getMessage() . "\n";
    $failed++;
}
echo str_repeat("-", 80) . "\n\n";

// Summary
echo "\n=== Test Summary ===\n";
echo "Passed: $passed / 6\n";
echo "Failed: $failed / 6\n";

if ($failed === 0) {
    echo "\n✅ All analytics methods working correctly!\n";
    exit(0);
} else {
    echo "\n❌ Some tests failed. Please review the errors above.\n";
    exit(1);
}
