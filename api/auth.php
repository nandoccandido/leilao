<?php
/**
 * API de Autenticação - Leilão Saysix
 * Usa o WordPress para validar credenciais reais contra o banco de dados.
 *
 * POST /api/auth.php?action=login   { email, senha }
 * POST /api/auth.php?action=cadastro { nome, email, cpf, senha }
 * POST /api/auth.php?action=logout
 * GET  /api/auth.php?action=check
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Carregar WordPress (para usar wp_check_password, wp_hash_password, wpdb, etc.)
define('SHORTINIT', false);
require_once dirname(__DIR__) . '/wp-load.php';

// Iniciar sessão PHP
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400 * 7,
        'path'     => '/',
        'secure'   => true,
        'httponly'  => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {

    // ─── LOGIN ──────────────────────────────
    case 'login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'erro' => 'Método não permitido']);
            exit;
        }

        $body  = json_decode(file_get_contents('php://input'), true);
        $email = isset($body['email']) ? sanitize_email($body['email']) : '';
        $senha = isset($body['senha']) ? $body['senha'] : '';

        if (!$email || !$senha) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'erro' => 'E-mail e senha são obrigatórios']);
            exit;
        }

        // Buscar usuário pelo e-mail
        $user = get_user_by('email', $email);
        if (!$user) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'erro' => 'E-mail ou senha incorretos']);
            exit;
        }

        // Verificar senha com hash do WordPress
        if (!wp_check_password($senha, $user->user_pass, $user->ID)) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'erro' => 'E-mail ou senha incorretos']);
            exit;
        }

        // Criar sessão
        $_SESSION['user_id'] = $user->ID;

        // Buscar metadados
        $meta = get_user_meta($user->ID);

        echo json_encode([
            'ok'      => true,
            'usuario' => build_user_response($user, $meta),
        ]);
        break;

    // ─── CADASTRO ───────────────────────────
    case 'cadastro':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'erro' => 'Método não permitido']);
            exit;
        }

        $body  = json_decode(file_get_contents('php://input'), true);
        $nome  = isset($body['nome'])  ? sanitize_text_field($body['nome'])  : '';
        $email = isset($body['email']) ? sanitize_email($body['email'])      : '';
        $cpf   = isset($body['cpf'])   ? sanitize_text_field($body['cpf'])   : '';
        $senha = isset($body['senha']) ? $body['senha']                      : '';

        if (!$nome || !$email || !$senha) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'erro' => 'Nome, e-mail e senha são obrigatórios']);
            exit;
        }

        if (strlen($senha) < 6) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'erro' => 'A senha deve ter no mínimo 6 caracteres']);
            exit;
        }

        // Verificar se já existe
        if (get_user_by('email', $email)) {
            http_response_code(409);
            echo json_encode(['ok' => false, 'erro' => 'Este e-mail já está cadastrado']);
            exit;
        }

        // Criar usuário no WordPress
        $user_login = sanitize_user(strtolower(explode('@', $email)[0]), true);
        // Garantir login único
        if (username_exists($user_login)) {
            $user_login = $user_login . '_' . wp_rand(100, 999);
        }

        $user_id = wp_insert_user([
            'user_login'   => $user_login,
            'user_pass'    => $senha, // wp_insert_user já faz hash
            'user_email'   => $email,
            'display_name' => $nome,
            'role'         => 'customer', // Role do WooCommerce
        ]);

        if (is_wp_error($user_id)) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'erro' => 'Erro ao criar conta: ' . $user_id->get_error_message()]);
            exit;
        }

        // Salvar CPF como meta
        if ($cpf) {
            update_user_meta($user_id, 'billing_cpf', $cpf);
        }

        // Salvar nome e sobrenome
        $partes = explode(' ', $nome, 2);
        update_user_meta($user_id, 'first_name', $partes[0]);
        update_user_meta($user_id, 'last_name', isset($partes[1]) ? $partes[1] : '');
        update_user_meta($user_id, 'nickname', $partes[0]);

        // Criar sessão
        $_SESSION['user_id'] = $user_id;

        $user = get_user_by('ID', $user_id);
        $meta = get_user_meta($user_id);

        echo json_encode([
            'ok'      => true,
            'usuario' => build_user_response($user, $meta),
        ]);
        break;

    // ─── ME (formato admin.js — { success, data }) ──────────────
    case 'me':
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Não autenticado']);
            exit;
        }

        $user = get_user_by('ID', $_SESSION['user_id']);
        if (!$user) {
            session_destroy();
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
            exit;
        }

        $meta = get_user_meta($user->ID);
        echo json_encode([
            'success' => true,
            'data'    => build_user_response($user, $meta),
        ]);
        break;

    // ─── CHECK (sessão ativa?) ──────────────
    case 'check':
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'erro' => 'Não autenticado']);
            exit;
        }

        $user = get_user_by('ID', $_SESSION['user_id']);
        if (!$user) {
            session_destroy();
            http_response_code(401);
            echo json_encode(['ok' => false, 'erro' => 'Usuário não encontrado']);
            exit;
        }

        $meta = get_user_meta($user->ID);
        echo json_encode([
            'ok'      => true,
            'usuario' => build_user_response($user, $meta),
        ]);
        break;

    // ─── LOGOUT ─────────────────────────────
    case 'logout':
        session_destroy();
        echo json_encode(['ok' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['ok' => false, 'erro' => 'Ação inválida']);
}

// ─── HELPER ─────────────────────────────────
function build_user_response($user, $meta) {
    $first = isset($meta['first_name'][0]) ? $meta['first_name'][0] : '';
    $last  = isset($meta['last_name'][0])  ? $meta['last_name'][0]  : '';
    $nome_completo = trim($first . ' ' . $last);
    if (!$nome_completo) {
        $nome_completo = $user->display_name;
    }

    return [
        'id'       => $user->ID,
        'nome'     => $nome_completo,
        'email'    => $user->user_email,
        'cpf'      => isset($meta['billing_cpf'][0])   ? $meta['billing_cpf'][0]   : '',
        'telefone' => isset($meta['billing_phone'][0])  ? $meta['billing_phone'][0] : '',
        'endereco' => [
            'cep'    => isset($meta['billing_postcode'][0])  ? $meta['billing_postcode'][0]  : '',
            'rua'    => isset($meta['billing_address_1'][0]) ? $meta['billing_address_1'][0] : '',
            'cidade' => isset($meta['billing_city'][0])      ? $meta['billing_city'][0]      : '',
            'uf'     => isset($meta['billing_state'][0])     ? $meta['billing_state'][0]     : '',
        ],
        'roles'    => array_values($user->roles),
    ];
}
