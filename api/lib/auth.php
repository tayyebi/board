<?php
declare(strict_types=1);

/**
 * Authentication library
 */

function init_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
}

function get_csrf_token(): string {
    return $_SESSION['csrf'] ?? '';
}

function verify_csrf(string $token): bool {
    return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}
