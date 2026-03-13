<?php
/**
 * API Usuário — dados de perfil, endereço e senha
 *
 * POST /api/user.php?action=perfil   { nome, telefone }
 * POST /api/user.php?action=endereco { cep, rua, cidade, uf }
 * POST /api/user.php?action=senha    { senha_atual, nova_senha }
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

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

// ── Autenticação ──────────────────────────────────────────────────────────────
$user_id = (int) ($_SESSION['user_id'] ?? 0);
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'erro' => 'Não autenticado']);
    exit;
}

$user = get_userdata($user_id);
if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'erro' => 'Usuário inválido']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'erro' => 'Método não permitido']);
    exit;
}

$action = sanitize_text_field($_GET['action'] ?? '');
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($action) {

    // ─── PERFIL ──────────────────────────────────────────────────────────────
    case 'perfil':
        $nome     = isset($body['nome'])     ? sanitize_text_field($body['nome'])     : '';
        $telefone = isset($body['telefone']) ? sanitize_text_field($body['telefone']) : null;

        if (!$nome) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'erro' => 'Nome é obrigatório']);
            exit;
        }

        $partes = explode(' ', $nome, 2);
        wp_update_user(['ID' => $user_id, 'display_name' => $nome]);
        update_user_meta($user_id, 'first_name', $partes[0]);
        update_user_meta($user_id, 'last_name',  $partes[1] ?? '');
        update_user_meta($user_id, 'nickname',   $partes[0]);

        if ($telefone !== null) {
            update_user_meta($user_id, 'billing_phone', $telefone);
        }

        $user_upd = get_userdata($user_id);
        $meta     = get_user_meta($user_id);

        echo json_encode(['ok' => true, 'usuario' => build_user_resp($user_upd, $meta)]);
        break;

    // ─── ENDEREÇO ────────────────────────────────────────────────────────────
    case 'endereco':
        $cep    = isset($body['cep'])    ? sanitize_text_field($body['cep'])    : '';
        $rua    = isset($body['rua'])    ? sanitize_text_field($body['rua'])    : '';
        $cidade = isset($body['cidade']) ? sanitize_text_field($body['cidade']) : '';
        $uf     = isset($body['uf'])     ? sanitize_text_field($body['uf'])     : '';

        update_user_meta($user_id, 'billing_postcode',  $cep);
        update_user_meta($user_id, 'billing_address_1', $rua);
        update_user_meta($user_id, 'billing_city',      $cidade);
        update_user_meta($user_id, 'billing_state',     $uf);

        echo json_encode(['ok' => true]);
        break;

    // ─── SENHA ───────────────────────────────────────────────────────────────
    case 'senha':
        $senha_atual = $body['senha_atual'] ?? '';
        $nova_senha  = $body['nova_senha']  ?? '';

        if (!$senha_atual || !$nova_senha) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'erro' => 'Campos obrigatórios ausentes']);
            exit;
        }

        if (strlen($nova_senha) < 6) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'erro' => 'A nova senha deve ter no mínimo 6 caracteres']);
            exit;
        }

        if (!wp_check_password($senha_atual, $user->user_pass, $user_id)) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'erro' => 'Senha atual incorreta']);
            exit;
        }

        wp_set_password($nova_senha, $user_id);

        echo json_encode(['ok' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['ok' => false, 'erro' => 'Ação inválida']);
}

// ── Helper ────────────────────────────────────────────────────────────────────
function build_user_resp(WP_User $user, array $meta): array {
    $first = $meta['first_name'][0] ?? '';
    $last  = $meta['last_name'][0]  ?? '';
    $nome  = trim("$first $last") ?: $user->display_name;

    return [
        'id'       => $user->ID,
        'nome'     => $nome,
        'email'    => $user->user_email,
        'cpf'      => $meta['billing_cpf'][0]       ?? '',
        'telefone' => $meta['billing_phone'][0]      ?? '',
        'endereco' => [
            'cep'    => $meta['billing_postcode'][0]  ?? '',
            'rua'    => $meta['billing_address_1'][0] ?? '',
            'cidade' => $meta['billing_city'][0]      ?? '',
            'uf'     => $meta['billing_state'][0]     ?? '',
        ],
        'roles' => array_values($user->roles),
    ];
}
