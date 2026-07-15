<?php
require_once __DIR__ . '/supabase.php';

/** Redirects to login when no staff session exists. */
function require_login(): void
{
    if (empty($_SESSION['user'])) {
        header('Location: login.php');
        exit;
    }
}

function current_user(): array
{
    return $_SESSION['user'] ?? [];
}

// ---- CSRF --------------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

/** Call at the top of every POST handler. */
function csrf_check(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST'
        && !hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Invalid request token. I-refresh ang page at subukan ulit.');
    }
}

// ---- Flash messages ----------------------------------------------
function flash(string $msg, string $type = 'ok'): void
{
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

function get_flash(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}
