<?php
/**
 * Content Extraction Script 1: Extract Chapters to JSON
 *
 * Purpose: Parse all chapter HTML files and extract lesson data
 * Input: Chapter HTML files (chapter*.html)
 * Output: lessons.json
 *
 * Usage: php extract-chapters.php
 */

// Configuration
$baseDir = dirname(dirname(__DIR__)); // Project root
$outputDir = __DIR__ . '/output';
$outputFile = $outputDir . '/lessons.json';

// Ensure output directory exists
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

echo "=== Chapter Content Extraction ===\n";
echo "Base directory: $baseDir\n";
echo "Output file: $outputFile\n\n";

// Find all chapter HTML files
$chapterFiles = glob($baseDir . '/chapter*.html');

if (empty($chapterFiles)) {
    die("ERROR: No chapter files found in $baseDir\n");
}

echo "Found " . count($chapterFiles) . " chapter files\n\n";

$lessons = [];
$errors = [];

foreach ($chapterFiles as $file) {
    $filename = basename($file);
    echo "Processing: $filename... ";

    try {
        $html = file_get_contents($file);

        if ($html === false) {
            throw new Exception("Failed to read file");
        }

        // Create DOMDocument
        $dom = new DOMDocument();

        // Suppress HTML5 warnings
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        // Extract module badge to determine module_id
        $moduleBadgeNodes = $xpath->query("//div[@class='module-badge']");
        $moduleName = '';
        $moduleId = null;

        if ($moduleBadgeNodes->length > 0) {
            $moduleName = trim($moduleBadgeNodes->item(0)->textContent);
            if (preg_match('/Module (\d+)/', $moduleName, $matches)) {
                $moduleId = (int)$matches[1];
            }
        }

        // Extract title
        $titleNodes = $xpath->query("//div[@class='chapter-header']/h1");
        $title = '';
        if ($titleNodes->length > 0) {
            $title = trim($titleNodes->item(0)->textContent);
        }

        // Extract chapter number for order_index
        $orderIndex = 0;
        if (preg_match('/Chapter (\d+)\.(\d+)/', $title, $chapterMatches)) {
            // Convert chapter numbering to sequential index
            // e.g., Chapter 1.00 → 100, Chapter 1.05 → 105, Chapter 2.01 → 201
            $orderIndex = ($chapterMatches[1] * 100) + (int)$chapterMatches[2];
        } elseif (preg_match('/Chapter (\d+)/', $title, $chapterMatches)) {
            // Simple chapter number (no decimal)
            $orderIndex = (int)$chapterMatches[1] * 100;
        }

        // Extract subtitle
        $subtitleNodes = $xpath->query("//div[@class='chapter-header']/p[@class='subtitle']");
        $subtitle = '';
        if ($subtitleNodes->length > 0) {
            $subtitle = trim($subtitleNodes->item(0)->textContent);
        }

        // Extract full content
        $contentNodes = $xpath->query("//div[@class='chapter-content']");
        $content = '';
        if ($contentNodes->length > 0) {
            $content = $dom->saveHTML($contentNodes->item(0));
        }

        // Extract navigation tabs (section structure)
        $navTabs = [];
        $navTabNodes = $xpath->query("//div[@class='chapter-nav']//a[@class='nav-tab' or contains(@class, 'nav-tab')]");
        foreach ($navTabNodes as $tab) {
            $href = $tab->getAttribute('href');
            $tabText = trim($tab->textContent);

            // Extract icon if present
            $iconNodes = $xpath->query(".//i[contains(@class, 'fa')]", $tab);
            $icon = '';
            if ($iconNodes->length > 0) {
                $iconClass = $iconNodes->item(0)->getAttribute('class');
                if (preg_match('/fa-[\w-]+/', $iconClass, $iconMatches)) {
                    $icon = $iconMatches[0];
                }
            }

            $navTabs[] = [
                'id' => ltrim($href, '#'),
                'title' => $tabText,
                'icon' => $icon
            ];
        }

        // Extract previous/next navigation
        $prevNodes = $xpath->query("//a[contains(@class, 'nav-button') and contains(@class, 'previous')]");
        $nextNodes = $xpath->query("//a[contains(@class, 'nav-button') and contains(@class, 'next')]");

        $prevLink = null;
        $nextLink = null;

        if ($prevNodes->length > 0) {
            $prevLink = $prevNodes->item(0)->getAttribute('href');
        }
        if ($nextNodes->length > 0) {
            $nextLink = $nextNodes->item(0)->getAttribute('href');
        }

        // Create slug from filename
        $slug = pathinfo($file, PATHINFO_FILENAME);

        // Add lesson data
        $lessons[] = [
            'module_id' => $moduleId,
            'module_name' => $moduleName,
            'title' => $title,
            'subtitle' => $subtitle,
            'slug' => $slug,
            'content' => $content,
            'sections' => $navTabs,
            'order_index' => $orderIndex,
            'duration_minutes' => 15, // Default estimate
            'is_published' => true,
            'previous_slug' => $prevLink ? pathinfo($prevLink, PATHINFO_FILENAME) : null,
            'next_slug' => $nextLink ? pathinfo($nextLink, PATHINFO_FILENAME) : null,
            'source_file' => $filename
        ];

        echo "✓ OK (Module $moduleId, Order $orderIndex)\n";

    } catch (Exception $e) {
        echo "✗ ERROR: " . $e->getMessage() . "\n";
        $errors[] = [
            'file' => $filename,
            'error' => $e->getMessage()
        ];
    }
}

// Sort by order_index
usort($lessons, function($a, $b) {
    return $a['order_index'] <=> $b['order_index'];
});

// Save to JSON
$jsonData = json_encode($lessons, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if (file_put_contents($outputFile, $jsonData) === false) {
    die("\nERROR: Failed to write output file\n");
}

// Print summary
echo "\n=== Extraction Summary ===\n";
echo "Total lessons extracted: " . count($lessons) . "\n";
echo "Output saved to: $outputFile\n";

if (!empty($errors)) {
    echo "\nERRORS ENCOUNTERED (" . count($errors) . "):\n";
    foreach ($errors as $error) {
        echo "  - {$error['file']}: {$error['error']}\n";
    }
}

// Print breakdown by module
$moduleBreakdown = [];
foreach ($lessons as $lesson) {
    $moduleId = $lesson['module_id'] ?? 'unknown';
    if (!isset($moduleBreakdown[$moduleId])) {
        $moduleBreakdown[$moduleId] = 0;
    }
    $moduleBreakdown[$moduleId]++;
}

echo "\nLessons by Module:\n";
ksort($moduleBreakdown);
foreach ($moduleBreakdown as $moduleId => $count) {
    echo "  Module $moduleId: $count lessons\n";
}

// Validation warnings
echo "\nValidation Checks:\n";

$noModuleId = array_filter($lessons, fn($l) => empty($l['module_id']));
if (!empty($noModuleId)) {
    echo "  ⚠ WARNING: " . count($noModuleId) . " lessons missing module_id\n";
    foreach ($noModuleId as $lesson) {
        echo "    - {$lesson['source_file']}\n";
    }
}

$noTitle = array_filter($lessons, fn($l) => empty($l['title']));
if (!empty($noTitle)) {
    echo "  ⚠ WARNING: " . count($noTitle) . " lessons missing title\n";
}

$noContent = array_filter($lessons, fn($l) => empty($l['content']));
if (!empty($noContent)) {
    echo "  ⚠ WARNING: " . count($noContent) . " lessons missing content\n";
}

$duplicateSlugs = [];
$slugs = array_column($lessons, 'slug');
$uniqueSlugs = array_unique($slugs);
if (count($slugs) !== count($uniqueSlugs)) {
    echo "  ⚠ WARNING: Duplicate slugs detected!\n";
}

if (empty($noModuleId) && empty($noTitle) && empty($noContent) && count($slugs) === count($uniqueSlugs)) {
    echo "  ✓ All validation checks passed\n";
}

echo "\n✓ Extraction complete!\n";
echo "Next step: Run validate-content.php to verify data integrity\n";
