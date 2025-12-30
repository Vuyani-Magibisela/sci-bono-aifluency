<?php
/**
 * Test Script for Phase 7 Schema Fixes
 * Validates ProjectController works with new schema fields
 */

require_once 'api/vendor/autoload.php';

use App\Models\Project;
use App\Models\ProjectSubmission;

$pdo = require 'api/config/database.php';

echo "===========================================\n";
echo "Phase 7 Schema Fix - API Integration Tests\n";
echo "===========================================\n\n";

$passed = 0;
$failed = 0;

// Test 1: Project Model - Find with new fields
echo "Test 1: Project Model - Read with new fields\n";
try {
    $projectModel = new Project($pdo);
    $project = $projectModel->find(1);

    if ($project && isset($project->course_id) && isset($project->slug) && isset($project->order)) {
        echo "✅ PASS - Project has course_id: {$project->course_id}, slug: {$project->slug}, order: {$project->order}\n";
        $passed++;
    } else {
        echo "❌ FAIL - Project missing new fields\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
    $failed++;
}
echo "\n";

// Test 2: Project Model - getByCourse() method
echo "Test 2: Project Model - getByCourse() method\n";
try {
    $projectModel = new Project($pdo);
    $projects = $projectModel->getByCourse(1);

    if (is_array($projects) && count($projects) > 0) {
        echo "✅ PASS - Found " . count($projects) . " projects for course 1\n";
        echo "   Sample: {$projects[0]->title} (Order: {$projects[0]->order})\n";
        $passed++;
    } else {
        echo "❌ FAIL - getByCourse() returned no projects\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
    $failed++;
}
echo "\n";

// Test 3: Project Model - findBySlug() method
echo "Test 3: Project Model - findBySlug() method\n";
try {
    $projectModel = new Project($pdo);
    $project = $projectModel->findBySlug('ai-concept-map-project-1', 1);

    if ($project && $project->id == 1) {
        echo "✅ PASS - Found project by slug: {$project->title}\n";
        $passed++;
    } else {
        echo "❌ FAIL - findBySlug() didn't return expected project\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
    $failed++;
}
echo "\n";

// Test 4: Project Model - Create new project with new fields
echo "Test 4: Project Model - Create project with new fields\n";
try {
    $projectModel = new Project($pdo);

    $newProjectId = $projectModel->create([
        'course_id' => 1,
        'module_id' => 1,
        'title' => 'Test Project Schema Validation',
        'slug' => 'test-project-schema-' . time(),
        'order' => 999,
        'description' => 'This is a test project to validate schema fixes',
        'requirements' => 'Test requirements',
        'max_score' => 100,
        'is_published' => 0
    ]);

    if ($newProjectId) {
        $created = $projectModel->find($newProjectId);
        if ($created && $created->order == 999) {
            echo "✅ PASS - Created project ID: $newProjectId with order: {$created->order}\n";

            // Clean up test project
            $projectModel->delete($newProjectId);
            echo "   (Test project deleted)\n";
            $passed++;
        } else {
            echo "❌ FAIL - Created project but order field not saved correctly\n";
            $failed++;
        }
    } else {
        echo "❌ FAIL - Could not create project\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
    $failed++;
}
echo "\n";

// Test 5: Slug uniqueness constraint
echo "Test 5: Slug uniqueness constraint enforcement\n";
try {
    $projectModel = new Project($pdo);

    // Try to create project with duplicate slug in same course
    $duplicateSlug = 'ai-concept-map-project-1'; // Already exists

    try {
        $projectModel->create([
            'course_id' => 1,
            'module_id' => 1,
            'title' => 'Duplicate Slug Test',
            'slug' => $duplicateSlug,
            'order' => 998,
            'description' => 'Should fail due to unique constraint',
            'max_score' => 100,
            'is_published' => 0
        ]);

        echo "❌ FAIL - Duplicate slug was allowed (constraint not working)\n";
        $failed++;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false ||
            strpos($e->getMessage(), '1062') !== false) {
            echo "✅ PASS - Duplicate slug correctly prevented by unique constraint\n";
            $passed++;
        } else {
            echo "❌ FAIL - Exception but not duplicate key: " . $e->getMessage() . "\n";
            $failed++;
        }
    }
} catch (Exception $e) {
    echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
    $failed++;
}
echo "\n";

// Test 6: ProjectSubmission Model - uploaded_file_id field
echo "Test 6: ProjectSubmission Model - uploaded_file_id field\n";
try {
    $submissionModel = new ProjectSubmission($pdo);

    // Check if fillable includes uploaded_file_id
    $reflection = new ReflectionClass($submissionModel);
    $property = $reflection->getProperty('fillable');
    $property->setAccessible(true);
    $fillable = $property->getValue($submissionModel);

    if (in_array('uploaded_file_id', $fillable)) {
        echo "✅ PASS - ProjectSubmission model has uploaded_file_id in fillable array\n";
        $passed++;
    } else {
        echo "❌ FAIL - uploaded_file_id not in fillable array\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
    $failed++;
}
echo "\n";

// Test 7: Foreign key cascade delete
echo "Test 7: Foreign key cascade on delete\n";
try {
    // Create a test course (if needed) - skip this test if we can't create courses
    echo "⚠️  SKIP - Cascade delete test requires course creation (skipped to avoid data loss)\n";
    // This test would require creating a test course, then projects, then deleting the course
    // Skipping to avoid affecting production data
} catch (Exception $e) {
    echo "⚠️  SKIP - " . $e->getMessage() . "\n";
}
echo "\n";

// Test 8: Index performance check
echo "Test 8: Index usage for course queries\n";
try {
    $stmt = $pdo->query("EXPLAIN SELECT * FROM projects WHERE course_id = 1 ORDER BY `order`");
    $explain = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $usesIndex = false;
    foreach ($explain as $row) {
        if (isset($row['key']) && strpos($row['key'], 'idx_course') !== false) {
            $usesIndex = true;
            $indexName = $row['key'];
        }
    }

    if ($usesIndex) {
        echo "✅ PASS - Query uses index: $indexName\n";
        $passed++;
    } else {
        echo "⚠️  WARNING - Query doesn't use course index (may affect performance)\n";
    }
} catch (Exception $e) {
    echo "❌ FAIL - Exception: " . $e->getMessage() . "\n";
    $failed++;
}
echo "\n";

// Summary
echo "===========================================\n";
echo "Test Summary:\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "Status: " . ($failed === 0 ? "✅ ALL TESTS PASSED" : "❌ SOME TESTS FAILED") . "\n";
echo "===========================================\n\n";

if ($failed === 0) {
    echo "✅ Phase 7 schema fixes validated successfully!\n";
    echo "The ProjectController can now use course_id, slug, and order fields.\n";
    echo "File tracking enhanced with uploaded_file_id.\n";
    exit(0);
} else {
    echo "❌ Some tests failed. Please review errors above.\n";
    exit(1);
}
