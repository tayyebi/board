<?php
declare(strict_types=1);

/**
 * Helper functions library
 */

function b64e(string $s): string {
    return base64_encode($s);
}

function b64d(?string $s): string {
    if ($s === null || $s === '') return '';
    $dec = base64_decode($s, true);
    return ($dec === false ? $s : $dec);
}

function json_response(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error(string $message, int $code = 400): void {
    json_response(['error' => $message], $code);
}

function get_json_input(): array {
    $input = file_get_contents('php://input');
    if (empty($input)) return [];
    $data = json_decode($input, true);
    return is_array($data) ? $data : [];
}

function cors_headers(): void {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}
