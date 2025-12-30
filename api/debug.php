<?php
header('Content-Type: application/json');

$rawInput = file_get_contents('php://input');

$debug = [
    'method' => $_SERVER['REQUEST_METHOD'],
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
    'raw_input' => $rawInput,
    'raw_input_length' => strlen($rawInput),
    'post_data' => $_POST,
    'json_decode_result' => json_decode($rawInput, true),
    'json_last_error' => json_last_error(),
    'json_last_error_msg' => json_last_error_msg(),
    'request_uri' => $_SERVER['REQUEST_URI']
];

echo json_encode($debug, JSON_PRETTY_PRINT);
