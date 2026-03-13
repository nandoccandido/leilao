<?php
/**
 * API Arrematação — Front-end
 * Autenticação via PHP session (igual a api/admin.php)
 *
 * --- ARREMATANTE ---
 * GET  ?action=meus_lances                       → histórico de lances do usuário
 * GET  ?action=minha_arrematacao&imovel_id=X     → dados da sua arrematação
 * POST action=upload_doc (multipart)             → enviar documento
 *
 * --- ADMIN ---
 * GET  ?action=listar_admin&page=1&status=&search=
 * GET  ?action=detalhe_admin&id=X
 * POST action=revisar_doc   { doc_id, acao, observacao }
 * POST action=alterar_status { arrematacao_id, status }
 * POST action=enviar_notif   { arrematacao_id, assunto, mensagem }
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

function arr_err(string $msg, int $code = 400): void {
    http_response_code($code);
    wp_send_json(['ok' => false, 'erro' => $msg]);
    exit;
}

// ── Autenticação ──────────────────────────────────────────────────────────────
$user_id = (int) ($_SESSION['user_id'] ?? 0);
if (!$user_id) arr_err('Não autenticado.', 401);

$user = get_userdata($user_id);
if (!$user) arr_err('Usuário inválido.', 401);

$is_admin = in_array('administrator', (array) $user->roles, true);

global $wpdb;
$method = $_SERVER['REQUEST_METHOD'];
$action = sanitize_text_field($_GET['action'] ?? ($_POST['action'] ?? ''));

// ── Labels constantes ─────────────────────────────────────────────────────────
$STATUS_LABELS = [
    'aguardando_confirmacao' => 'Aguardando Confirmação',
    'aguardando_documentos'  => 'Aguardando Documentos',
    'documentos_enviados'    => 'Documentos Enviados',
    'em_analise'             => 'Em Análise',
    'aprovado'               => 'Documentos Aprovados',
    'reprovado'              => 'Documentos Reprovados',
    'pagamento_pendente'     => 'Pagamento Pendente',
    'concluido'              => 'Concluído',
];

$DOC_TIPOS = [
    // Pessoa Física
    'identidade'               => 'RG / CNH',
    'cpf'                      => 'CPF',
    'comprovante_renda'        => 'Comprovante de Renda',
    'comprovante_end'          => 'Comprovante de Endereço',
    'certidao_civil'           => 'Certidão de Estado Civil',
    'declaracao_ir'            => 'Declaração de Imposto de Renda',
    // Pessoa Jurídica
    'contrato_social'          => 'Contrato Social e Alterações',
    'cnpj'                     => 'Cartão CNPJ',
    'identidade_socio'         => 'RG / CNH dos Sócios',
    'cpf_socio'                => 'CPF dos Sócios',
    'comprovante_end_empresa'  => 'Comprovante de Endereço da Empresa',
    'balanco_patrimonial'      => 'Balanço Patrimonial',
    'certidoes_negativas'      => 'Certidões Negativas',
    // Geral
    'outros'                   => 'Outros',
];

// ── Helpers ───────────────────────────────────────────────────────────────────
function arr_get_docs(int $arr_id): array {
    global $wpdb;
    $t = $wpdb->prefix . 'leilao_arrematacao_docs';
    return $wpdb->get_results($wpdb->prepare(
        "SELECT d.*, u.display_name AS uploader_name
           FROM {$t} d
           LEFT JOIN {$wpdb->users} u ON u.ID = d.uploaded_by
          WHERE d.arrematacao_id = %d
          ORDER BY d.uploaded_em ASC",
        $arr_id
    ));
}

function arr_get_timeline(int $arr_id): array {
    global $wpdb;
    $t = $wpdb->prefix . 'leilao_arrematacao_log';
    return $wpdb->get_results($wpdb->prepare(
        "SELECT l.*, u.display_name
           FROM {$t} l
           LEFT JOIN {$wpdb->users} u ON u.ID = l.user_id
          WHERE l.arrematacao_id = %d
          ORDER BY l.criado_em ASC",
        $arr_id
    ));
}

function arr_add_log(int $arr_id, string $acao, string $descricao): void {
    global $wpdb, $user_id;
    $wpdb->insert(
        $wpdb->prefix . 'leilao_arrematacao_log',
        [
            'arrematacao_id' => $arr_id,
            'user_id'        => $user_id,
            'acao'           => $acao,
            'descricao'      => $descricao,
            'criado_em'      => current_time('mysql'),
        ],
        ['%d', '%d', '%s', '%s', '%s']
    );
}

function arr_format_docs(array $docs, array $tipos): array {
    return array_map(fn($d) => [
        'id'          => (int) $d->id,
        'tipo'        => $tipos[$d->tipo] ?? $d->tipo,
        'tipo_key'    => $d->tipo,
        'nome'        => $d->nome,
        'url'         => $d->arquivo_url,
        'status'      => $d->status,
        'observacao'  => $d->observacao,
        'uploader'    => $d->uploader_name ?? 'N/A',
        'uploaded_em' => $d->uploaded_em,
    ], $docs);
}

function arr_format_timeline(array $timeline): array {
    return array_map(fn($t) => [
        'acao'         => $t->acao,
        'descricao'    => $t->descricao,
        'usuario_nome' => $t->display_name ?? 'Sistema',
        'criado_em'    => $t->criado_em,
    ], $timeline);
}

// ── Switch de ações ───────────────────────────────────────────────────────────
switch ($action) {

    // =========================================================================
    // ARREMATANTE: Histórico de lances + status de arrematações
    // =========================================================================
    case 'meus_lances':
        $tl = $wpdb->prefix . 'leilao_lances';
        $ta = $wpdb->prefix . 'leilao_arrematacoes';

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT
                l.imovel_id,
                p.post_title       AS titulo,
                pm_img.meta_value  AS thumb_id,
                pm_st.meta_value   AS status_leilao,
                pm_vc.meta_value   AS vencedor_id,
                pm_vf.meta_value   AS valor_final_meta,
                MAX(l.valor)       AS meu_lance,
                a.id               AS arr_id,
                a.status           AS arr_status,
                a.data_limite_docs
             FROM {$tl} l
             LEFT JOIN {$wpdb->posts} p         ON p.ID = l.imovel_id AND p.post_status = 'publish'
             LEFT JOIN {$wpdb->postmeta} pm_img ON pm_img.post_id = l.imovel_id AND pm_img.meta_key = '_thumbnail_id'
             LEFT JOIN {$wpdb->postmeta} pm_st  ON pm_st.post_id  = l.imovel_id AND pm_st.meta_key  = '_leilao_status'
             LEFT JOIN {$wpdb->postmeta} pm_vc  ON pm_vc.post_id  = l.imovel_id AND pm_vc.meta_key  = '_leilao_vencedor_id'
             LEFT JOIN {$wpdb->postmeta} pm_vf  ON pm_vf.post_id  = l.imovel_id AND pm_vf.meta_key  = '_leilao_valor_final'
             LEFT JOIN {$ta} a                  ON a.imovel_id = l.imovel_id
             WHERE l.user_id = %d AND p.ID IS NOT NULL
             GROUP BY l.imovel_id
             ORDER BY meu_lance DESC",
            $user_id
        ));

        $lances = array_map(function ($r) use ($user_id) {
            $ganhou = ((int) $r->vencedor_id === $user_id && $r->status_leilao === 'encerrado');
            $status = $ganhou ? 'ganho'
                : ($r->status_leilao === 'encerrado' ? 'perdido'
                    : ($r->status_leilao ?? 'ativo'));

            $imagem = '';
            if ($r->thumb_id) {
                $imagem = wp_get_attachment_image_url((int) $r->thumb_id, 'medium') ?: '';
            }

            return [
                'imovel_id'       => (int) $r->imovel_id,
                'titulo'          => $r->titulo ?? '',
                'imagem'          => $imagem,
                'status'          => $status,
                'meu_lance'       => (float) $r->meu_lance,
                'valor_final'     => (float) $r->valor_final_meta,
                'arrematacao_id'  => $r->arr_id ? (int) $r->arr_id : null,
                'arr_status'      => $r->arr_status,
                'data_limite_docs'=> $r->data_limite_docs,
            ];
        }, $rows);

        wp_send_json(['ok' => true, 'lances' => $lances]);
        break;

    // =========================================================================
    // ARREMATANTE: Detalhes da arrematação de um imóvel
    // =========================================================================
    case 'minha_arrematacao':
        $imovel_id = absint($_GET['imovel_id'] ?? 0);
        $arr_id_q  = absint($_GET['arr_id']    ?? 0);
        if (!$imovel_id && !$arr_id_q) arr_err('imovel_id ou arr_id obrigatório.');

        $ta = $wpdb->prefix . 'leilao_arrematacoes';
        if ($arr_id_q) {
            $arr = $wpdb->get_row($wpdb->prepare(
                "SELECT a.*, p.post_title AS imovel_titulo
                   FROM {$ta} a
                   LEFT JOIN {$wpdb->posts} p ON p.ID = a.imovel_id
                  WHERE a.id = %d AND a.user_id = %d",
                $arr_id_q, $user_id
            ));
        } else {
            $arr = $wpdb->get_row($wpdb->prepare(
                "SELECT a.*, p.post_title AS imovel_titulo
                   FROM {$ta} a
                   LEFT JOIN {$wpdb->posts} p ON p.ID = a.imovel_id
                  WHERE a.imovel_id = %d AND a.user_id = %d",
                $imovel_id, $user_id
            ));
        }
        if (!$arr) arr_err('Processo de arrematação não encontrado.', 404);

        $docs     = arr_get_docs($arr->id);
        $timeline = arr_get_timeline($arr->id);

        wp_send_json([
            'ok'  => true,
            'arr' => [
                'id'               => (int) $arr->id,
                'imovel_id'        => (int) $arr->imovel_id,
                'titulo'           => $arr->imovel_titulo ?? '',
                'status'           => $arr->status,
                'status_label'     => $GLOBALS['STATUS_LABELS'][$arr->status] ?? $arr->status,
                'valor_final'      => (float) $arr->valor_final,
                'tipo_pessoa'      => $arr->tipo_pessoa ?? 'fisica',
                'prazo_documentos' => (int) $arr->prazo_documentos,
                'data_limite_docs' => $arr->data_limite_docs,
                'observacoes'      => $arr->observacoes,
                'confirmado_em'    => $arr->confirmado_em,
            ],
            'docs'          => arr_format_docs($docs, $GLOBALS['DOC_TIPOS']),
            'timeline'      => arr_format_timeline($timeline),
            'doc_tipos'     => $GLOBALS['DOC_TIPOS'],
            'status_labels' => $GLOBALS['STATUS_LABELS'],
        ]);
        break;

    // =========================================================================
    // ARREMATANTE: Upload de documento
    // =========================================================================
    case 'upload_doc':
        if ($method !== 'POST') arr_err('Método inválido.');

        $imovel_id   = absint($_POST['imovel_id'] ?? 0);
        $arr_id_post = absint($_POST['arr_id']     ?? 0);
        $tipo        = sanitize_text_field($_POST['tipo_doc'] ?? ($_POST['tipo'] ?? 'outros'));

        if (!array_key_exists($tipo, $GLOBALS['DOC_TIPOS'])) {
            $tipo = 'outros';
        }

        $ta = $wpdb->prefix . 'leilao_arrematacoes';
        if ($arr_id_post) {
            $arr = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$ta} WHERE id = %d AND user_id = %d",
                $arr_id_post, $user_id
            ));
        } else {
            $arr = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$ta} WHERE imovel_id = %d AND user_id = %d",
                $imovel_id, $user_id
            ));
        }
        if (!$arr) arr_err('Arrematação não encontrada.');

        $status_bloqueados = ['aguardando_confirmacao', 'aprovado', 'pagamento_pendente', 'concluido'];
        if (in_array($arr->status, $status_bloqueados, true)) {
            arr_err('Não é possível enviar documentos com status: ' . ($GLOBALS['STATUS_LABELS'][$arr->status] ?? $arr->status));
        }

        $file_key = isset($_FILES['arquivo']) ? 'arquivo' : 'documento';
        if (empty($_FILES[$file_key]) || $_FILES[$file_key]['error'] !== UPLOAD_ERR_OK) {
            arr_err('Nenhum arquivo válido selecionado.');
        }

        $file          = $_FILES[$file_key];
        $allowed_types = [
            'application/pdf',
            'image/jpeg', 'image/png', 'image/gif',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];

        if (!in_array($file['type'], $allowed_types, true)) {
            arr_err('Tipo de arquivo não permitido. Use PDF, JPG, PNG ou DOC.');
        }

        if ($file['size'] > 10 * 1024 * 1024) {
            arr_err('Arquivo muito grande. O máximo é 10 MB.');
        }

        $upload_dir = wp_upload_dir();
        $dest_dir   = $upload_dir['basedir'] . '/leilao-docs/' . $arr->id;
        if (!wp_mkdir_p($dest_dir)) arr_err('Erro ao criar diretório de upload.');

        $safe_name = sanitize_file_name($file['name']);
        $unique    = wp_unique_filename($dest_dir, $safe_name);
        $dest_path = $dest_dir . '/' . $unique;
        $dest_url  = $upload_dir['baseurl'] . '/leilao-docs/' . $arr->id . '/' . $unique;

        if (!move_uploaded_file($file['tmp_name'], $dest_path)) {
            arr_err('Erro ao salvar o arquivo.');
        }

        $wpdb->insert(
            $wpdb->prefix . 'leilao_arrematacao_docs',
            [
                'arrematacao_id' => $arr->id,
                'tipo'           => $tipo,
                'nome'           => $safe_name,
                'arquivo_url'    => $dest_url,
                'arquivo_path'   => $dest_path,
                'status'         => 'pendente',
                'uploaded_by'    => $user_id,
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%d']
        );

        // Se ainda estava "aguardando_documentos", avança para "documentos_enviados"
        if ($arr->status === 'aguardando_documentos') {
            $wpdb->update(
                $ta,
                ['status' => 'documentos_enviados'],
                ['id' => $arr->id],
                ['%s'], ['%d']
            );
        }

        arr_add_log($arr->id, 'documento_enviado', "Documento enviado pelo arrematante: {$safe_name}");

        wp_send_json(['ok' => true, 'mensagem' => 'Documento enviado com sucesso!']);
        break;

    // =========================================================================
    // ADMIN: Listar arrematações com filtros
    // =========================================================================
    case 'listar_admin':
        if (!$is_admin) arr_err('Sem permissão.', 403);

        $page     = max(1, absint($_GET['page'] ?? 1));
        $per_page = 15;
        $offset   = ($page - 1) * $per_page;
        $status_f = sanitize_text_field($_GET['status'] ?? '');
        $search   = sanitize_text_field($_GET['search'] ?? '');

        $ta = $wpdb->prefix . 'leilao_arrematacoes';

        $where  = 'WHERE 1=1';
        $params = [];

        if ($status_f && isset($GLOBALS['STATUS_LABELS'][$status_f])) {
            $where .= ' AND a.status = %s';
            $params[] = $status_f;
        }
        if ($search) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where .= ' AND (p.post_title LIKE %s OR u.user_email LIKE %s OR u.display_name LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql_count = "SELECT COUNT(*) FROM {$ta} a
                      LEFT JOIN {$wpdb->posts} p ON p.ID = a.imovel_id
                      LEFT JOIN {$wpdb->users} u ON u.ID = a.user_id
                      {$where}";

        $total = $params
            ? (int) $wpdb->get_var($wpdb->prepare($sql_count, ...$params))
            : (int) $wpdb->get_var($sql_count);

        $sql = "SELECT a.*, p.post_title AS imovel_titulo,
                       u.display_name AS arrematante_nome, u.user_email AS arrematante_email
                FROM {$ta} a
                LEFT JOIN {$wpdb->posts} p ON p.ID = a.imovel_id
                LEFT JOIN {$wpdb->users} u ON u.ID = a.user_id
                {$where}
                ORDER BY a.id DESC
                LIMIT %d OFFSET %d";

        $all_params = array_merge($params, [$per_page, $offset]);
        $rows       = $wpdb->get_results($wpdb->prepare($sql, ...$all_params));

        // Stats por status
        $stats_rows = $wpdb->get_results("SELECT status, COUNT(*) AS total FROM {$ta} GROUP BY status");
        $stats = [];
        $total_geral = 0;
        foreach ($stats_rows as $s) {
            $stats[$s->status] = (int) $s->total;
            $total_geral += (int) $s->total;
        }
        $stats['total'] = $total_geral;

        $items = array_map(fn($r) => [
            'id'               => (int) $r->id,
            'imovel_id'        => (int) $r->imovel_id,
            'imovel_titulo'    => $r->imovel_titulo ?? '',
            'arrematante'      => $r->arrematante_nome ?? '',
            'email'            => $r->arrematante_email ?? '',
            'valor_final'      => (float) $r->valor_final,
            'status'           => $r->status,
            'status_label'     => $GLOBALS['STATUS_LABELS'][$r->status] ?? $r->status,
            'data_limite_docs' => $r->data_limite_docs,
            'confirmado_em'    => $r->confirmado_em,
        ], $rows);

        wp_send_json([
            'ok'           => true,
            'total'        => $total,
            'paginas'      => (int) ceil($total / $per_page),
            'pagina_atual' => $page,
            'itens'        => $items,
            'stats'        => $stats,
            'status_labels'=> $GLOBALS['STATUS_LABELS'],
        ]);
        break;

    // =========================================================================
    // ADMIN: Detalhes de uma arrematação
    // =========================================================================
    case 'detalhe_admin':
        if (!$is_admin) arr_err('Sem permissão.', 403);

        $id = absint($_GET['id'] ?? 0);
        if (!$id) arr_err('ID obrigatório.');

        $ta = $wpdb->prefix . 'leilao_arrematacoes';
        $arr = $wpdb->get_row($wpdb->prepare(
            "SELECT a.*, p.post_title AS imovel_titulo,
                    u.display_name AS arrematante_nome, u.user_email AS arrematante_email
               FROM {$ta} a
               LEFT JOIN {$wpdb->posts} p ON p.ID = a.imovel_id
               LEFT JOIN {$wpdb->users} u ON u.ID = a.user_id
              WHERE a.id = %d",
            $id
        ));
        if (!$arr) arr_err('Arrematação não encontrada.', 404);

        $docs     = arr_get_docs($id);
        $timeline = arr_get_timeline($id);

        wp_send_json([
            'ok'  => true,
            'arr' => [
                'id'               => (int) $arr->id,
                'imovel_id'        => (int) $arr->imovel_id,
                'imovel_titulo'    => $arr->imovel_titulo ?? '',
                'arrematante'      => $arr->arrematante_nome ?? '',
                'email'            => $arr->arrematante_email ?? '',
                'status'           => $arr->status,
                'status_label'     => $GLOBALS['STATUS_LABELS'][$arr->status] ?? $arr->status,
                'valor_final'      => (float) $arr->valor_final,
                'tipo_pessoa'      => $arr->tipo_pessoa,
                'prazo_documentos' => (int) $arr->prazo_documentos,
                'data_limite_docs' => $arr->data_limite_docs,
                'observacoes'      => $arr->observacoes,
                'confirmado_em'    => $arr->confirmado_em,
            ],
            'docs'          => arr_format_docs($docs, $GLOBALS['DOC_TIPOS']),
            'timeline'      => arr_format_timeline($timeline),
            'status_labels' => $GLOBALS['STATUS_LABELS'],
            'doc_tipos'     => $GLOBALS['DOC_TIPOS'],
        ]);
        break;

    // =========================================================================
    // ADMIN: Aprovar / reprovar documento
    // =========================================================================
    case 'revisar_doc':
        if (!$is_admin || $method !== 'POST') arr_err('Sem permissão.', 403);

        $doc_id     = absint($_POST['doc_id'] ?? 0);
        $acao       = sanitize_text_field($_POST['acao'] ?? '');
        $observacao = sanitize_textarea_field($_POST['observacao'] ?? '');

        if (!$doc_id || !in_array($acao, ['aprovado', 'reprovado'], true)) {
            arr_err('Dados inválidos.');
        }

        $doc_table = $wpdb->prefix . 'leilao_arrematacao_docs';
        $doc = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$doc_table} WHERE id = %d", $doc_id));
        if (!$doc) arr_err('Documento não encontrado.', 404);

        $wpdb->update(
            $doc_table,
            [
                'status'      => $acao,
                'observacao'  => $observacao ?: null,
                'reviewed_by' => $user_id,
                'reviewed_em' => current_time('mysql'),
            ],
            ['id' => $doc_id],
            ['%s', '%s', '%d', '%s'],
            ['%d']
        );

        $acao_text = $acao === 'aprovado' ? 'aprovado' : 'reprovado';
        $descricao = "Documento \"{$doc->nome}\" {$acao_text}" . ($observacao ? ". Obs: {$observacao}" : '');
        arr_add_log($doc->arrematacao_id, 'documento_' . $acao_text, $descricao);

        wp_send_json(['ok' => true, 'mensagem' => 'Documento ' . $acao_text . '!']);
        break;

    // =========================================================================
    // ADMIN: Alterar status do processo
    // =========================================================================
    case 'alterar_status':
        if (!$is_admin || $method !== 'POST') arr_err('Sem permissão.', 403);

        $arr_id      = absint($_POST['arrematacao_id'] ?? 0);
        $novo_status = sanitize_text_field($_POST['status'] ?? '');

        if (!$arr_id || !isset($GLOBALS['STATUS_LABELS'][$novo_status])) {
            arr_err('Dados inválidos.');
        }

        $ta = $wpdb->prefix . 'leilao_arrematacoes';
        $old = $wpdb->get_var($wpdb->prepare("SELECT status FROM {$ta} WHERE id = %d", $arr_id));

        $wpdb->update($ta, ['status' => $novo_status], ['id' => $arr_id], ['%s'], ['%d']);

        arr_add_log(
            $arr_id,
            'status_alterado',
            'Status alterado de "' . ($GLOBALS['STATUS_LABELS'][$old] ?? $old)
            . '" para "' . $GLOBALS['STATUS_LABELS'][$novo_status] . '"'
        );

        wp_send_json([
            'ok'           => true,
            'mensagem'     => 'Status atualizado.',
            'status_label' => $GLOBALS['STATUS_LABELS'][$novo_status],
        ]);
        break;

    // =========================================================================
    // ADMIN: Enviar notificação por e-mail
    // =========================================================================
    case 'enviar_notif':
        if (!$is_admin || $method !== 'POST') arr_err('Sem permissão.', 403);

        $arr_id   = absint($_POST['arrematacao_id'] ?? 0);
        $assunto  = sanitize_text_field($_POST['assunto'] ?? '');
        $mensagem = sanitize_textarea_field($_POST['mensagem'] ?? '');

        if (!$arr_id || !$assunto || !$mensagem) {
            arr_err('Assunto, mensagem e ID da arrematação são obrigatórios.');
        }

        $ta = $wpdb->prefix . 'leilao_arrematacoes';
        $arr = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$ta} WHERE id = %d", $arr_id));
        if (!$arr) arr_err('Arrematação não encontrada.', 404);

        $dest = get_userdata($arr->user_id);
        if (!$dest) arr_err('Usuário arrematante não encontrado.');

        $result = wp_mail($dest->user_email, $assunto, $mensagem);
        if (!$result) arr_err('Erro ao enviar e-mail. Verifique as configurações SMTP.');

        arr_add_log(
            $arr_id,
            'notificacao',
            "Notificação enviada para {$dest->user_email}: {$assunto}"
        );

        wp_send_json(['ok' => true, 'mensagem' => 'E-mail enviado para ' . $dest->user_email]);
        break;

    // =========================================================================
    // ARREMATANTE: Lista de processos de arrematação (painel Documentação)
    // =========================================================================
    case 'meus_processos':
        $ta = $wpdb->prefix . 'leilao_arrematacoes';
        $td = $wpdb->prefix . 'leilao_arrematacao_docs';

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT a.*,
                    p.post_title AS titulo,
                    COUNT(d.id)                                                 AS total_docs,
                    SUM(CASE WHEN d.status = 'aprovado'  THEN 1 ELSE 0 END)    AS docs_aprovados,
                    SUM(CASE WHEN d.status = 'pendente'  THEN 1 ELSE 0 END)    AS docs_pendentes,
                    SUM(CASE WHEN d.status = 'reprovado' THEN 1 ELSE 0 END)    AS docs_reprovados
             FROM {$ta} a
             LEFT JOIN {$wpdb->posts} p ON p.ID = a.imovel_id AND p.post_status = 'publish'
             LEFT JOIN {$td} d          ON d.arrematacao_id = a.id
             WHERE a.user_id = %d AND p.ID IS NOT NULL
             GROUP BY a.id
             ORDER BY a.id DESC",
            $user_id
        ));

        $processos = array_map(fn($r) => [
            'id'               => (int) $r->id,
            'imovel_id'        => (int) $r->imovel_id,
            'titulo'           => $r->titulo ?? '',
            'status'           => $r->status,
            'status_label'     => $GLOBALS['STATUS_LABELS'][$r->status] ?? $r->status,
            'valor_final'      => (float) $r->valor_final,
            'tipo_pessoa'      => $r->tipo_pessoa ?? 'fisica',
            'data_limite_docs' => $r->data_limite_docs,
            'total_docs'       => (int) $r->total_docs,
            'docs_aprovados'   => (int) $r->docs_aprovados,
            'docs_pendentes'   => (int) $r->docs_pendentes,
            'docs_reprovados'  => (int) $r->docs_reprovados,
        ], $rows);

        wp_send_json(['ok' => true, 'processos' => $processos]);
        break;

    default:
        arr_err('Ação inválida.');
}
