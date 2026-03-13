<?php
/**
 * API Administração — painel admin
 * Requer autenticação como administrator via sessão PHP.
 *
 * GET  ?action=stats
 * GET  ?action=usuarios  &pagina=1 &busca= &por_pagina=20
 * GET  ?action=imoveis   &pagina=1 &busca= &por_pagina=20
 * POST action=usuario_delete  { id }
 * POST action=usuario_role    { id, role }
 * POST action=imovel_delete   { id }
 * POST action=importar        { tipo }
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

// ── Helpers de resposta ───────────────────────────────────────────────────────
function adm_ok(array $data = [], string $msg = ''): void {
    $resp = ['success' => true];
    if ($msg)  $resp['message'] = $msg;
    if ($data) $resp['data']    = $data;
    echo json_encode($resp);
    exit;
}

function adm_err(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

// ── Autenticação — apenas administradores ─────────────────────────────────────
$user_id = (int) ($_SESSION['user_id'] ?? 0);
if (!$user_id) adm_err('Não autenticado.', 401);

$user = get_userdata($user_id);
if (!$user) adm_err('Usuário inválido.', 401);

if (!in_array('administrator', (array) $user->roles, true)) {
    adm_err('Acesso negado. Apenas administradores.', 403);
}

global $wpdb;
$method = $_SERVER['REQUEST_METHOD'];

// Determina action (GET ou POST com JSON body)
if ($method === 'POST') {
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = sanitize_text_field($body['action'] ?? '');
} else {
    $body   = [];
    $action = sanitize_text_field($_GET['action'] ?? '');
}

switch ($action) {

    // ─── ESTATÍSTICAS ────────────────────────────────────────────────────────
    case 'stats':
        $total_usuarios = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");
        $total_imoveis  = (int) (wp_count_posts('imovel')->publish ?? 0);

        $veiculo_counts = wp_count_posts('veiculo');
        $total_veiculos = $veiculo_counts ? (int) ($veiculo_counts->publish ?? 0) : 0;

        $hoje       = current_time('Y-m-d');
        $novos_hoje = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->users} WHERE DATE(user_registered) = %s",
            $hoje
        ));

        adm_ok([
            'total_usuarios' => $total_usuarios,
            'total_imoveis'  => $total_imoveis,
            'total_veiculos' => $total_veiculos,
            'novos_hoje'     => $novos_hoje,
        ]);

    // ─── USUÁRIOS ─────────────────────────────────────────────────────────────
    case 'usuarios':
        $pagina     = max(1, (int) ($_GET['pagina']     ?? 1));
        $por_pagina = max(5, (int) ($_GET['por_pagina'] ?? 20));
        $busca      = sanitize_text_field($_GET['busca'] ?? '');

        $args = [
            'number'  => $por_pagina,
            'offset'  => ($pagina - 1) * $por_pagina,
            'orderby' => 'registered',
            'order'   => 'DESC',
        ];

        if ($busca) {
            $args['search']         = '*' . $busca . '*';
            $args['search_columns'] = ['user_login', 'user_email', 'display_name'];
        }

        $q     = new WP_User_Query($args);
        $total = $q->get_total();

        $items = array_map(function (WP_User $u) {
            return [
                'ID'           => $u->ID,
                'user_login'   => $u->user_login,
                'user_email'   => $u->user_email,
                'display_name' => $u->display_name,
                'roles'        => array_values($u->roles),
                'registered'   => $u->user_registered,
            ];
        }, $q->get_results());

        adm_ok([
            'items'   => $items,
            'total'   => $total,
            'pagina'  => $pagina,
            'paginas' => (int) ceil($total / max(1, $por_pagina)),
        ]);

    // ─── IMÓVEIS ──────────────────────────────────────────────────────────────
    case 'imoveis':
        $pagina     = max(1, (int) ($_GET['pagina']     ?? 1));
        $por_pagina = max(5, (int) ($_GET['por_pagina'] ?? 20));
        $busca      = sanitize_text_field($_GET['busca'] ?? '');

        $q_args = [
            'post_type'      => 'imovel',
            'post_status'    => 'any',
            'posts_per_page' => $por_pagina,
            'paged'          => $pagina,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => false,
        ];

        if ($busca) {
            $q_args['s'] = $busca;
        }

        $q     = new WP_Query($q_args);
        $total = (int) $q->found_posts;

        $items = array_map(function (WP_Post $p) {
            $cidades = wp_get_object_terms($p->ID, 'cidade_imovel', ['fields' => 'names']);
            $estados = wp_get_object_terms($p->ID, 'estado_imovel', ['fields' => 'names']);

            return [
                'ID'            => $p->ID,
                'post_title'    => $p->post_title,
                'status'        => $p->post_status,
                'numero_edital' => get_post_meta($p->ID, '_imovel_edital', true) ?: '',
                'cidade'        => (!is_wp_error($cidades) && $cidades) ? $cidades[0] : '',
                'estado'        => (!is_wp_error($estados) && $estados) ? $estados[0] : '',
            ];
        }, $q->posts);

        wp_reset_postdata();

        adm_ok([
            'items'   => $items,
            'total'   => $total,
            'pagina'  => $pagina,
            'paginas' => (int) ceil($total / max(1, $por_pagina)),
        ]);

    // ─── DELETAR USUÁRIO ──────────────────────────────────────────────────────
    case 'usuario_delete':
        if ($method !== 'POST') adm_err('Método não permitido.', 405);

        $id = (int) ($body['id'] ?? 0);
        if (!$id) adm_err('ID inválido.');
        if ($id === $user_id) adm_err('Você não pode excluir a própria conta.');

        if (!function_exists('wp_delete_user')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }

        if (!wp_delete_user($id)) adm_err('Usuário não encontrado.', 404);

        adm_ok([], 'Usuário excluído com sucesso.');

    // ─── ALTERAR PAPEL ────────────────────────────────────────────────────────
    case 'usuario_role':
        if ($method !== 'POST') adm_err('Método não permitido.', 405);

        $id   = (int)  ($body['id']   ?? 0);
        $role = sanitize_text_field($body['role'] ?? '');

        $roles_permitidos = ['administrator', 'editor', 'author', 'contributor', 'subscriber', 'customer'];
        if (!$id || !in_array($role, $roles_permitidos, true)) adm_err('Parâmetros inválidos.');
        if ($id === $user_id && $role !== 'administrator') adm_err('Não é possível rebaixar sua própria conta.');

        $u = get_userdata($id);
        if (!$u) adm_err('Usuário não encontrado.', 404);

        $u->set_role($role);
        adm_ok([], 'Papel atualizado para "' . $role . '".');

    // ─── DELETAR IMÓVEL ───────────────────────────────────────────────────────
    case 'imovel_delete':
        if ($method !== 'POST') adm_err('Método não permitido.', 405);

        $id = (int) ($body['id'] ?? 0);
        if (!$id) adm_err('ID inválido.');

        $post = get_post($id);
        if (!$post || !in_array($post->post_type, ['imovel', 'veiculo'], true)) {
            adm_err('Post não encontrado.', 404);
        }

        if (!wp_delete_post($id, true)) adm_err('Não foi possível excluir o post.');

        adm_ok([], 'Imóvel excluído com sucesso.');

    // ─── IMPORTAR ─────────────────────────────────────────────────────────────
    case 'importar':
        if ($method !== 'POST') adm_err('Método não permitido.', 405);

        $tipo = sanitize_text_field($body['tipo'] ?? '');
        if (!$tipo) adm_err('Tipo de importação não informado.');

        if (!class_exists('Leilao_Caixa_Importer')) {
            adm_err('Importador não disponível. Verifique se o plugin está ativo.', 500);
        }

        // Importa por estado ou todos
        $ufs = ['AC','AL','AM','AP','BA','CE','DF','ES','GO','MA',
                'MG','MS','MT','PA','PB','PE','PI','PR','RJ','RN',
                'RO','RR','RS','SC','SE','SP','TO'];

        $log   = '';
        $total = 0;

        foreach ($ufs as $uf) {
            try {
                $result = Leilao_Caixa_Importer::importar_estado($uf);
                $n       = (int) ($result['importados'] ?? 0);
                $total  += $n;
                if ($n > 0) $log .= "> {$uf}: {$n} imóvel(is) importado(s)\n";
            } catch (Throwable $e) {
                $log .= "> {$uf}: erro — " . $e->getMessage() . "\n";
            }
        }

        $log .= "> Total: {$total} imóvel(is) processado(s)\n";
        adm_ok(['log' => $log], "Importação concluída. {$total} imóvel(is) importado(s).");

    default:
        adm_err('Ação inválida.');
}
