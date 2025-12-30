<?php
/**
 * Quiz Question Extraction Script
 * Extracts quiz questions from static HTML files and generates SQL migration
 *
 * Usage: php extract_quiz_questions.php > ../migrations/018_populate_quiz_questions.sql
 */

// Answer keys extracted from HTML files
$answerKeys = [
    2 => ['b', 'c', 'c', 'b', 'a', 'b', 'c', 'b', 'a', 'c'],
    3 => ['b', 'b', 'b', 'b', 'c', 'c', 'b', 'c', 'c', 'c'],
    4 => ['b', 'c', 'c', 'c', 'd', 'b', 'c', 'c', 'b', 'c'],
    5 => ['b', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a'],
    6 => ['c', 'b', 'b', 'c', 'b', 'b', 'a', 'b', 'c', 'c']
];

// Convert letter to index
function letterToIndex($letter) {
    return ord(strtolower($letter)) - ord('a');
}

// Escape for SQL
function sqlEscape($str) {
    return addslashes($str);
}

// Extract questions from HTML file
function extractQuestions($moduleId, $filePath, $answers) {
    if (!file_exists($filePath)) {
        echo "-- ERROR: File not found: $filePath\n";
        return [];
    }

    $html = file_get_contents($filePath);

    // Use DOMDocument to parse HTML
    $dom = new DOMDocument();
    @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($dom);

    $questions = [];

    // Find all question containers
    $questionContainers = $xpath->query("//div[@class='question-container']");

    $questionNumber = 1;
    foreach ($questionContainers as $container) {
        $questionData = [];

        // Get question text
        $questionNodes = $xpath->query(".//div[@class='question']", $container);
        if ($questionNodes->length > 0) {
            $questionText = trim($questionNodes->item(0)->textContent);
            // Remove question number from text (e.g., "1. What is..." -> "What is...")
            $questionText = preg_replace('/^\d+\.\s*/', '', $questionText);
            $questionData['question'] = $questionText;
        }

        // Get options
        $options = [];
        $optionNodes = $xpath->query(".//div[@class='option']", $container);

        foreach ($optionNodes as $optionNode) {
            $labelNodes = $xpath->query(".//label", $optionNode);
            if ($labelNodes->length > 0) {
                $options[] = trim($labelNodes->item(0)->textContent);
            }
        }

        $questionData['options'] = $options;

        // Get explanation
        $explanationId = "explanation-q{$questionNumber}";
        $explanationNodes = $xpath->query(".//div[@id='{$explanationId}']", $container);

        if ($explanationNodes->length > 0) {
            $explanation = trim($explanationNodes->item(0)->textContent);
            // Remove "Explanation:" prefix
            $explanation = preg_replace('/^Explanation:\s*/i', '', $explanation);
            $questionData['explanation'] = $explanation;
        }

        // Get correct answer
        if (isset($answers[$questionNumber - 1])) {
            $questionData['correct_answer'] = $answers[$questionNumber - 1];
            $questionData['correct_index'] = letterToIndex($answers[$questionNumber - 1]);
        }

        $questionData['order_index'] = $questionNumber;
        $questionData['points'] = 1;

        $questions[] = $questionData;
        $questionNumber++;
    }

    return $questions;
}

// Generate SQL INSERT statement
function generateSQL($moduleId, $questions) {
    $sql = '';

    foreach ($questions as $q) {
        if (count($q['options']) !== 4) {
            $sql .= "-- WARNING: Module $moduleId Question {$q['order_index']} has " . count($q['options']) . " options instead of 4\n";
            continue;
        }

        $question = sqlEscape($q['question']);
        $option1 = sqlEscape($q['options'][0]);
        $option2 = sqlEscape($q['options'][1]);
        $option3 = sqlEscape($q['options'][2]);
        $option4 = sqlEscape($q['options'][3]);
        $explanation = sqlEscape($q['explanation']);
        $correctIndex = $q['correct_index'];
        $points = $q['points'];
        $orderIndex = $q['order_index'];

        $sql .= "(
    (SELECT id FROM quizzes WHERE module_id = {$moduleId} LIMIT 1),
    '{$question}',
    JSON_ARRAY('{$option1}', '{$option2}', '{$option3}', '{$option4}'),
    {$correctIndex},
    '{$explanation}',
    {$points},
    {$orderIndex}
)";

        // Add comma if not last question
        $sql .= ",\n";
    }

    // Remove trailing comma and newline
    $sql = rtrim($sql, ",\n");

    return $sql;
}

// Main execution
echo "-- Migration 018: Populate Quiz Questions for Modules 2-6\n";
echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
echo "-- Phase 5 Cleanup: Migrate questions from static HTML to database\n";
echo "-- Prerequisites: Quizzes for modules 2-6 must exist (created by migration 014)\n\n";

$baseDir = '/var/www/html/sci-bono-aifluency';

echo "INSERT INTO quiz_questions (quiz_id, question_text, options, correct_option, explanation, points, order_index)\n";
echo "VALUES\n";

$allSQL = [];

// Process each module
foreach ($answerKeys as $moduleId => $answers) {
    $filePath = "{$baseDir}/module{$moduleId}Quiz.html";
    $questions = extractQuestions($moduleId, $filePath, $answers);

    if (empty($questions)) {
        echo "-- ERROR: No questions extracted from module{$moduleId}Quiz.html\n";
        continue;
    }

    echo "-- Module {$moduleId}: " . count($questions) . " questions\n";

    $sql = generateSQL($moduleId, $questions);
    $allSQL[] = $sql;
}

// Combine all SQL with commas
echo implode(",\n", $allSQL);
echo ";\n\n";

echo "-- Verification query:\n";
echo "-- SELECT m.id, m.title, q.title as quiz, COUNT(qq.id) as questions\n";
echo "-- FROM modules m\n";
echo "-- LEFT JOIN quizzes q ON q.module_id = m.id\n";
echo "-- LEFT JOIN quiz_questions qq ON qq.quiz_id = q.id\n";
echo "-- WHERE m.id BETWEEN 2 AND 6\n";
echo "-- GROUP BY m.id, q.id;\n";
