<?php
/**
 * Importador de veículos — endpoint de front-end (admin)
 * GET  /api/importar-veiculos.php
 * Returns: { ok, mensagem }
 *
 * Nota: módulo de veículos será implementado em versão futura.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

define('SHORTINIT', false);
require_once dirname(__DIR__) . '/wp-load.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400 * 7,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Apenas administradores
$user_id = (int) ($_SESSION['user_id'] ?? 0);
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'mensagem' => 'Não autenticado.']);
    exit;
}

$user = get_userdata($user_id);
if (!$user || !in_array('administrator', (array) $user->roles, true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'mensagem' => 'Acesso negado.']);
    exit;
}

// Módulo de veículos ainda não implementado
echo json_encode([
    'ok'       => false,
    'mensagem' => 'A importação de veículos ainda não está disponível nesta versão.',
]);
