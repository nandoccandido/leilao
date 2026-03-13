<?php
/**
 * Fluxo de Documentação Pós-Arrematação
 *
 * - Metabox no post do imóvel com abas (Resumo, Documentos, Status, Notificações, Timeline)
 * - Página admin Leilão Caixa > Arrematações
 * - Tabelas: wp_leilao_arrematacoes, wp_leilao_arrematacao_docs, wp_leilao_arrematacao_log
 */

defined('ABSPATH') || exit;

class Leilao_Arrematacao {

    const STATUS_AGUARDANDO_CONFIRMACAO = 'aguardando_confirmacao';
    const STATUS_AGUARDANDO_DOCUMENTOS  = 'aguardando_documentos';
    const STATUS_DOCUMENTOS_ENVIADOS    = 'documentos_enviados';
    const STATUS_EM_ANALISE             = 'em_analise';
    const STATUS_APROVADO               = 'aprovado';
    const STATUS_REPROVADO              = 'reprovado';
    const STATUS_CONCLUIDO              = 'concluido';

    const DOC_PENDENTE  = 'pendente';
    const DOC_APROVADO  = 'aprovado';
    const DOC_REPROVADO = 'reprovado';

    public static function init() {
        add_action('add_meta_boxes', [__CLASS__, 'add_metabox']);
        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        // AJAX — Fluxo principal
        add_action('wp_ajax_leilao_confirmar_arrematante', [__CLASS__, 'ajax_confirmar_arrematante']);
        add_action('wp_ajax_leilao_atualizar_arrematacao', [__CLASS__, 'ajax_atualizar_status']);
        // AJAX — Documentos
        add_action('wp_ajax_leilao_upload_documento', [__CLASS__, 'ajax_upload_documento']);
        add_action('wp_ajax_leilao_revisar_documento', [__CLASS__, 'ajax_revisar_documento']);
        add_action('wp_ajax_leilao_excluir_documento', [__CLASS__, 'ajax_excluir_documento']);
        // AJAX — Notificações
        add_action('wp_ajax_leilao_enviar_notificacao', [__CLASS__, 'ajax_enviar_notificacao']);
    }

    /* ========================================================================
       Labels e helpers
       ======================================================================== */

    public static function get_status_labels(): array {
        return [
            self::STATUS_AGUARDANDO_CONFIRMACAO => 'Aguardando Confirmação',
            self::STATUS_AGUARDANDO_DOCUMENTOS  => 'Aguardando Documentos',
            self::STATUS_DOCUMENTOS_ENVIADOS    => 'Documentos Enviados',
            self::STATUS_EM_ANALISE             => 'Em Análise',
            self::STATUS_APROVADO               => 'Aprovado',
            self::STATUS_REPROVADO              => 'Reprovado',
            self::STATUS_CONCLUIDO              => 'Concluído',
        ];
    }

    public static function get_status_color(string $status): string {
        $colors = [
            self::STATUS_AGUARDANDO_CONFIRMACAO => '#f0ad4e',
            self::STATUS_AGUARDANDO_DOCUMENTOS  => '#5bc0de',
            self::STATUS_DOCUMENTOS_ENVIADOS    => '#337ab7',
            self::STATUS_EM_ANALISE             => '#9b59b6',
            self::STATUS_APROVADO               => '#5cb85c',
            self::STATUS_REPROVADO              => '#d9534f',
            self::STATUS_CONCLUIDO              => '#1a5276',
        ];
        return $colors[$status] ?? '#999';
    }

    public static function get_doc_tipos(): array {
        return [
            'rg_cpf'                => 'RG / CPF',
            'comprovante_residencia'=> 'Comprovante de Residência',
            'comprovante_renda'     => 'Comprovante de Renda',
            'certidao_estado_civil' => 'Certidão de Estado Civil',
            'contrato_social'       => 'Contrato Social (PJ)',
            'cnpj'                  => 'Cartão CNPJ (PJ)',
            'procuracao'            => 'Procuração (PJ)',
            'outros'                => 'Outros',
        ];
    }

    public static function get_doc_status_label(string $status): string {
        $map = [
            self::DOC_PENDENTE  => 'Pendente',
            self::DOC_APROVADO  => 'Aprovado',
            self::DOC_REPROVADO => 'Reprovado',
        ];
        return $map[$status] ?? $status;
    }

    /* ========================================================================
       Banco de dados
       ======================================================================== */

    public static function create_table() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Tabela principal de arrematações
        $t1 = $wpdb->prefix . 'leilao_arrematacoes';
        dbDelta("CREATE TABLE IF NOT EXISTS {$t1} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            imovel_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            valor_final DECIMAL(15,2) NOT NULL,
            tipo_pessoa ENUM('fisica','juridica') DEFAULT 'fisica',
            prazo_documentos INT DEFAULT 15,
            data_limite_docs DATETIME NULL,
            status VARCHAR(50) DEFAULT 'aguardando_confirmacao',
            observacoes TEXT NULL,
            confirmado_por BIGINT UNSIGNED NULL,
            confirmado_em DATETIME NULL,
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_imovel (imovel_id),
            KEY idx_user (user_id),
            KEY idx_status (status)
        ) {$charset};");

        // Tabela de documentos
        $t2 = $wpdb->prefix . 'leilao_arrematacao_docs';
        dbDelta("CREATE TABLE IF NOT EXISTS {$t2} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            arrematacao_id BIGINT UNSIGNED NOT NULL,
            tipo VARCHAR(60) NOT NULL DEFAULT 'outros',
            nome VARCHAR(255) NOT NULL,
            arquivo_url TEXT NOT NULL,
            arquivo_path TEXT NOT NULL,
            status VARCHAR(30) DEFAULT 'pendente',
            observacao TEXT NULL,
            uploaded_by BIGINT UNSIGNED NOT NULL,
            reviewed_by BIGINT UNSIGNED NULL,
            uploaded_em DATETIME DEFAULT CURRENT_TIMESTAMP,
            reviewed_em DATETIME NULL,
            PRIMARY KEY (id),
            KEY idx_arrematacao (arrematacao_id),
            KEY idx_status (status)
        ) {$charset};");

        // Tabela de timeline / log
        $t3 = $wpdb->prefix . 'leilao_arrematacao_log';
        dbDelta("CREATE TABLE IF NOT EXISTS {$t3} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            arrematacao_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            acao VARCHAR(60) NOT NULL,
            descricao TEXT NOT NULL,
            meta_json TEXT NULL,
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_arrematacao (arrematacao_id),
            KEY idx_criado (criado_em)
        ) {$charset};");
    }

    public static function get_by_imovel(int $imovel_id): ?object {
        global $wpdb;
        $table = $wpdb->prefix . 'leilao_arrematacoes';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE imovel_id = %d ORDER BY id DESC LIMIT 1",
            $imovel_id
        ));
    }

    private static function get_docs(int $arrematacao_id): array {
        global $wpdb;
        $table = $wpdb->prefix . 'leilao_arrematacao_docs';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE arrematacao_id = %d ORDER BY uploaded_em DESC",
            $arrematacao_id
        )) ?: [];
    }

    private static function get_timeline(int $arrematacao_id): array {
        global $wpdb;
        $table = $wpdb->prefix . 'leilao_arrematacao_log';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT l.*, u.display_name FROM {$table} l LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID WHERE l.arrematacao_id = %d ORDER BY l.criado_em DESC",
            $arrematacao_id
        )) ?: [];
    }

    private static function add_log(int $arrematacao_id, string $acao, string $descricao, array $meta = []) {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'leilao_arrematacao_log', [
            'arrematacao_id' => $arrematacao_id,
            'user_id'        => get_current_user_id(),
            'acao'           => $acao,
            'descricao'      => $descricao,
            'meta_json'      => $meta ? wp_json_encode($meta) : null,
        ], ['%d', '%d', '%s', '%s', '%s']);
    }

    /* ========================================================================
       Admin menu + metabox
       ======================================================================== */

    public static function admin_menu() {
        add_submenu_page(
            'leilao-caixa',
            'Arrematações',
            '🏆 Arrematações',
            'manage_options',
            'leilao-arrematacoes',
            [__CLASS__, 'page_arrematacoes']
        );
    }

    public static function add_metabox() {
        add_meta_box(
            'leilao_arrematacao_box',
            '🏆 Arrematação — Gerenciamento Completo',
            [__CLASS__, 'metabox_arrematacao'],
            'imovel',
            'normal',
            'high'
        );
    }

    /* ========================================================================
       METABOX — Renderização principal
       ======================================================================== */

    public static function metabox_arrematacao($post) {
        $status_leilao = get_post_meta($post->ID, '_leilao_status', true);
        $vencedor_id   = get_post_meta($post->ID, '_leilao_vencedor_id', true);
        $valor_final   = get_post_meta($post->ID, '_leilao_valor_final', true);
        $arrematacao   = self::get_by_imovel($post->ID);

        // Leilão ainda não encerrado
        if ($status_leilao !== 'encerrado' || !$vencedor_id) {
            $status_text = $status_leilao ?: 'não definido';
            echo '<div class="leilao-arr-notice">';
            echo '<p>⏳ O leilão está com status <strong>' . esc_html($status_text) . '</strong>.</p>';
            if (!$vencedor_id) {
                echo '<p>Nenhum arrematante identificado ainda. O fluxo será disponibilizado quando o leilão encerrar com lances.</p>';
            }
            echo '</div>';
            return;
        }

        $user = get_userdata($vencedor_id);

        // Ainda não confirmado → formulário de confirmação
        if (!$arrematacao || $arrematacao->status === self::STATUS_AGUARDANDO_CONFIRMACAO) {
            self::render_confirmacao($post, $user, $vencedor_id, $valor_final);
            return;
        }

        // Já confirmado → metabox completa com abas
        self::render_metabox_completa($arrematacao, $user, $post->ID);
    }

    /* -----------------------------------------------------------------------
       Formulário de confirmação (antes de iniciar o fluxo)
       ----------------------------------------------------------------------- */

    private static function render_confirmacao($post, $user, $vencedor_id, $valor_final) {
        wp_nonce_field('leilao_arrematacao_nonce', '_arrematacao_nonce');
        ?>
        <div class="leilao-arr-confirm" id="arrematacao-confirm-box">
            <h3 style="margin-top:0;">Dados da Arrematação</h3>
            <table class="form-table leilao-metabox-table">
                <tr>
                    <th>Arrematante</th>
                    <td>
                        <strong><?php echo esc_html($user->display_name ?? 'N/A'); ?></strong>
                        <br><small><?php echo esc_html($user->user_email ?? ''); ?> — ID: <?php echo esc_html($vencedor_id); ?></small>
                    </td>
                </tr>
                <tr>
                    <th>Maior Lance</th>
                    <td><strong style="font-size:1.3em;color:#1a5276;">R$ <?php echo number_format(floatval($valor_final), 2, ',', '.'); ?></strong></td>
                </tr>
                <tr>
                    <th>Total de Lances</th>
                    <td><?php echo esc_html(get_post_meta($post->ID, '_leilao_total_lances', true) ?: 0); ?></td>
                </tr>
                <tr>
                    <th>Tipo de Pessoa</th>
                    <td>
                        <label><input type="radio" name="arr_tipo_pessoa" value="fisica" checked /> Pessoa Física</label>&nbsp;&nbsp;
                        <label><input type="radio" name="arr_tipo_pessoa" value="juridica" /> Pessoa Jurídica</label>
                    </td>
                </tr>
                <tr>
                    <th>Prazo para Documentos</th>
                    <td>
                        <input type="number" name="arr_prazo_dias" value="15" min="1" max="90" style="width:80px;" /> dias
                        <p class="description">Prazo que o arrematante terá para enviar a documentação.</p>
                    </td>
                </tr>
                <tr>
                    <th>Observações</th>
                    <td><textarea name="arr_observacoes" rows="3" class="large-text" placeholder="Observações internas..."></textarea></td>
                </tr>
            </table>
            <p style="margin-top:16px;">
                <button type="button" class="button button-primary button-hero" id="btn-confirmar-arrematante"
                        data-imovel="<?php echo $post->ID; ?>">
                    ✅ Confirmar Arrematante e Iniciar Fluxo
                </button>
            </p>
            <div id="arrematacao-result" style="margin-top:12px;"></div>
        </div>
        <?php
    }

    /* -----------------------------------------------------------------------
       Metabox completa com abas
       ----------------------------------------------------------------------- */

    private static function render_metabox_completa(object $arr, ?object $user, int $imovel_id) {
        $labels  = self::get_status_labels();
        $color   = self::get_status_color($arr->status);
        $docs    = self::get_docs($arr->id);
        $timeline = self::get_timeline($arr->id);
        $doc_tipos = self::get_doc_tipos();

        $total_docs     = count($docs);
        $docs_aprovados = count(array_filter($docs, fn($d) => $d->status === self::DOC_APROVADO));
        $docs_pendentes = count(array_filter($docs, fn($d) => $d->status === self::DOC_PENDENTE));
        $docs_reprovados = count(array_filter($docs, fn($d) => $d->status === self::DOC_REPROVADO));

        $confirmador = $arr->confirmado_por ? get_userdata($arr->confirmado_por) : null;
        ?>
        <div class="arr-metabox-wrap" data-arr-id="<?php echo $arr->id; ?>" data-imovel-id="<?php echo $imovel_id; ?>">

            <!-- Header com status -->
            <div class="leilao-arr-header">
                <div>
                    <h3 style="margin:0 0 4px;">Arrematação #<?php echo $arr->id; ?></h3>
                    <small>Confirmada em <?php echo wp_date('d/m/Y H:i', strtotime($arr->confirmado_em)); ?>
                        por <?php echo esc_html($confirmador->display_name ?? 'Sistema'); ?></small>
                </div>
                <span class="leilao-arr-badge status-<?php echo esc_attr($arr->status); ?>">
                    <?php echo esc_html($labels[$arr->status] ?? $arr->status); ?>
                </span>
            </div>

            <!-- Abas -->
            <div class="arr-tabs">
                <button class="arr-tab active" data-tab="resumo">📋 Resumo</button>
                <button class="arr-tab" data-tab="documentos">
                    📄 Documentos
                    <?php if ($docs_pendentes): ?>
                        <span class="arr-tab-count pending"><?php echo $docs_pendentes; ?></span>
                    <?php endif; ?>
                </button>
                <button class="arr-tab" data-tab="status">🔄 Status</button>
                <button class="arr-tab" data-tab="notificacoes">📧 Notificações</button>
                <button class="arr-tab" data-tab="timeline">
                    📅 Timeline
                    <span class="arr-tab-count"><?php echo count($timeline); ?></span>
                </button>
            </div>

            <!-- ========== ABA RESUMO ========== -->
            <div class="arr-tab-content active" id="arr-tab-resumo">
                <table class="form-table leilao-metabox-table">
                    <tr>
                        <th>Arrematante</th>
                        <td>
                            <strong><?php echo esc_html($user->display_name ?? 'N/A'); ?></strong>
                            (<?php echo esc_html($user->user_email ?? ''); ?>)
                            — <em><?php echo $arr->tipo_pessoa === 'juridica' ? 'Pessoa Jurídica' : 'Pessoa Física'; ?></em>
                        </td>
                    </tr>
                    <tr>
                        <th>Valor Final</th>
                        <td><strong style="font-size:1.2em;">R$ <?php echo number_format($arr->valor_final, 2, ',', '.'); ?></strong></td>
                    </tr>
                    <tr>
                        <th>Prazo Documentos</th>
                        <td>
                            <?php echo intval($arr->prazo_documentos); ?> dias
                            <?php if ($arr->data_limite_docs): ?>
                                — Limite: <strong><?php echo wp_date('d/m/Y', strtotime($arr->data_limite_docs)); ?></strong>
                                <?php
                                $diff = strtotime($arr->data_limite_docs) - current_time('timestamp');
                                if ($diff > 0) {
                                    echo " <span style='color:#5cb85c;'>(" . ceil($diff / 86400) . " dias restantes)</span>";
                                } else {
                                    echo " <span style='color:#d9534f;font-weight:bold;'>(Prazo expirado)</span>";
                                }
                                ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Documentos</th>
                        <td>
                            <span style="color:#5cb85c;">✓ <?php echo $docs_aprovados; ?> aprovados</span> &nbsp;
                            <span style="color:#f0ad4e;">⏳ <?php echo $docs_pendentes; ?> pendentes</span> &nbsp;
                            <span style="color:#d9534f;">✗ <?php echo $docs_reprovados; ?> reprovados</span> &nbsp;
                            de <?php echo $total_docs; ?> total
                        </td>
                    </tr>
                    <?php if ($arr->observacoes): ?>
                    <tr>
                        <th>Observações</th>
                        <td><?php echo nl2br(esc_html($arr->observacoes)); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>

            <!-- ========== ABA DOCUMENTOS ========== -->
            <div class="arr-tab-content" id="arr-tab-documentos">

                <!-- Upload de documento -->
                <div class="arr-doc-upload-box">
                    <h4>Adicionar Documento</h4>
                    <div class="arr-doc-upload-form">
                        <select id="arr-doc-tipo">
                            <?php foreach ($doc_tipos as $val => $label): ?>
                                <option value="<?php echo esc_attr($val); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="file" id="arr-doc-file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" />
                        <button type="button" class="button button-primary" id="btn-upload-doc">📤 Enviar</button>
                    </div>
                    <div id="arr-upload-result" style="margin-top:8px;"></div>
                </div>

                <!-- Lista de Documentos -->
                <div id="arr-docs-list">
                    <?php if (empty($docs)): ?>
                        <p class="arr-empty-state">Nenhum documento enviado ainda.</p>
                    <?php else: ?>
                        <table class="wp-list-table widefat striped arr-docs-table">
                            <thead>
                                <tr>
                                    <th style="width:30%;">Documento</th>
                                    <th>Tipo</th>
                                    <th>Enviado por</th>
                                    <th>Data</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($docs as $doc):
                                    $uploader = get_userdata($doc->uploaded_by);
                                    $reviewer = $doc->reviewed_by ? get_userdata($doc->reviewed_by) : null;
                                    $ext = strtolower(pathinfo($doc->nome, PATHINFO_EXTENSION));
                                    $icon = in_array($ext, ['pdf']) ? '📕' : (in_array($ext, ['jpg','jpeg','png','gif']) ? '🖼️' : '📄');
                                ?>
                                <tr id="doc-row-<?php echo $doc->id; ?>" class="arr-doc-row status-<?php echo esc_attr($doc->status); ?>">
                                    <td>
                                        <?php echo $icon; ?>
                                        <a href="<?php echo esc_url($doc->arquivo_url); ?>" target="_blank" rel="noopener">
                                            <?php echo esc_html($doc->nome); ?>
                                        </a>
                                    </td>
                                    <td><?php echo esc_html($doc_tipos[$doc->tipo] ?? $doc->tipo); ?></td>
                                    <td><?php echo esc_html($uploader->display_name ?? 'N/A'); ?></td>
                                    <td><?php echo wp_date('d/m/Y H:i', strtotime($doc->uploaded_em)); ?></td>
                                    <td>
                                        <span class="arr-doc-badge doc-<?php echo esc_attr($doc->status); ?>">
                                            <?php echo esc_html(self::get_doc_status_label($doc->status)); ?>
                                        </span>
                                        <?php if ($doc->reviewed_em): ?>
                                            <br><small>por <?php echo esc_html($reviewer->display_name ?? '?'); ?>
                                            em <?php echo wp_date('d/m/Y', strtotime($doc->reviewed_em)); ?></small>
                                        <?php endif; ?>
                                        <?php if ($doc->observacao): ?>
                                            <br><small class="arr-doc-obs"><?php echo esc_html($doc->observacao); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="arr-doc-actions">
                                        <?php if ($doc->status === self::DOC_PENDENTE): ?>
                                            <button type="button" class="button button-small btn-doc-review" data-doc="<?php echo $doc->id; ?>" data-acao="aprovado" title="Aprovar">✅</button>
                                            <button type="button" class="button button-small btn-doc-review" data-doc="<?php echo $doc->id; ?>" data-acao="reprovado" title="Reprovar">❌</button>
                                        <?php elseif ($doc->status === self::DOC_REPROVADO): ?>
                                            <button type="button" class="button button-small btn-doc-review" data-doc="<?php echo $doc->id; ?>" data-acao="aprovado" title="Reaprovar">✅ Reaprovar</button>
                                        <?php elseif ($doc->status === self::DOC_APROVADO): ?>
                                            <button type="button" class="button button-small btn-doc-review" data-doc="<?php echo $doc->id; ?>" data-acao="reprovado" title="Revogar">❌ Revogar</button>
                                        <?php endif; ?>
                                        <button type="button" class="button button-small btn-doc-delete" data-doc="<?php echo $doc->id; ?>" title="Excluir">🗑️</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ========== ABA STATUS ========== -->
            <div class="arr-tab-content" id="arr-tab-status">
                <div class="arr-status-pipeline">
                    <?php
                    $steps = [
                        self::STATUS_AGUARDANDO_DOCUMENTOS,
                        self::STATUS_DOCUMENTOS_ENVIADOS,
                        self::STATUS_EM_ANALISE,
                        self::STATUS_APROVADO,
                        self::STATUS_CONCLUIDO,
                    ];
                    $current_idx = array_search($arr->status, $steps);
                    foreach ($steps as $i => $step):
                        $done = ($current_idx !== false && $i <= $current_idx);
                        $is_current = ($step === $arr->status);
                        $cls = $is_current ? 'current' : ($done ? 'done' : '');
                    ?>
                    <div class="arr-pipeline-step <?php echo $cls; ?>">
                        <div class="arr-pipeline-dot"></div>
                        <span><?php echo esc_html($labels[$step]); ?></span>
                    </div>
                    <?php if ($i < count($steps) - 1): ?>
                        <div class="arr-pipeline-line <?php echo $done && !$is_current ? 'done' : ''; ?>"></div>
                    <?php endif; ?>
                    <?php endforeach; ?>

                    <?php if ($arr->status === self::STATUS_REPROVADO): ?>
                    <div class="arr-pipeline-step current" style="border-color:#d9534f;">
                        <div class="arr-pipeline-dot" style="background:#d9534f;"></div>
                        <span style="color:#d9534f;"><?php echo esc_html($labels[self::STATUS_REPROVADO]); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="arr-status-change">
                    <h4>Alterar Status do Processo</h4>
                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                        <select id="arr-novo-status" style="min-width:220px;">
                            <?php foreach ($labels as $val => $label):
                                if ($val === self::STATUS_AGUARDANDO_CONFIRMACAO) continue;
                            ?>
                                <option value="<?php echo esc_attr($val); ?>" <?php selected($arr->status, $val); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="button button-primary" id="btn-atualizar-arrematacao"
                                data-id="<?php echo $arr->id; ?>">
                            🔄 Atualizar Status
                        </button>
                        <span id="arr-update-result"></span>
                    </div>
                </div>
            </div>

            <!-- ========== ABA NOTIFICAÇÕES ========== -->
            <div class="arr-tab-content" id="arr-tab-notificacoes">
                <div class="arr-notif-box">
                    <h4>Enviar Notificação ao Arrematante</h4>
                    <p class="description">Enviar e-mail para <strong><?php echo esc_html($user->user_email ?? ''); ?></strong></p>

                    <div class="arr-notif-form">
                        <div class="arr-notif-field">
                            <label>Assunto:</label>
                            <input type="text" id="arr-notif-assunto" class="regular-text" style="width:100%;"
                                   value="<?php echo esc_attr('Atualização sobre arrematação — ' . get_the_title($imovel_id)); ?>" />
                        </div>
                        <div class="arr-notif-field">
                            <label>Modelo rápido:</label>
                            <select id="arr-notif-template">
                                <option value="">— Selecionar modelo —</option>
                                <option value="docs_pendentes">Lembrete: documentos pendentes</option>
                                <option value="docs_reprovados">Aviso: documentos reprovados</option>
                                <option value="aprovacao">Parabéns: documentação aprovada</option>
                                <option value="prazo_expirado">Urgente: prazo expirado</option>
                                <option value="concluido">Processo concluído</option>
                            </select>
                        </div>
                        <div class="arr-notif-field">
                            <label>Mensagem:</label>
                            <textarea id="arr-notif-mensagem" rows="8" class="large-text" placeholder="Digite a mensagem para o arrematante..."></textarea>
                        </div>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <button type="button" class="button button-primary" id="btn-enviar-notificacao">📧 Enviar E-mail</button>
                            <span id="arr-notif-result"></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========== ABA TIMELINE ========== -->
            <div class="arr-tab-content" id="arr-tab-timeline">
                <?php if (empty($timeline)): ?>
                    <p class="arr-empty-state">Nenhum registro na timeline ainda.</p>
                <?php else: ?>
                    <div class="arr-timeline">
                        <?php foreach ($timeline as $entry):
                            $icon_map = [
                                'confirmacao'       => '✅',
                                'status_alterado'   => '🔄',
                                'documento_enviado' => '📤',
                                'documento_aprovado'=> '✅',
                                'documento_reprovado'=> '❌',
                                'documento_excluido'=> '🗑️',
                                'notificacao'       => '📧',
                            ];
                            $icon = $icon_map[$entry->acao] ?? '📌';
                        ?>
                        <div class="arr-timeline-item">
                            <div class="arr-timeline-dot"><?php echo $icon; ?></div>
                            <div class="arr-timeline-content">
                                <div class="arr-timeline-header">
                                    <strong><?php echo esc_html($entry->display_name ?? 'Sistema'); ?></strong>
                                    <time><?php echo wp_date('d/m/Y H:i', strtotime($entry->criado_em)); ?></time>
                                </div>
                                <p><?php echo esc_html($entry->descricao); ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div><!-- /.arr-metabox-wrap -->
        <?php
    }

    /* ========================================================================
       AJAX Handlers
       ======================================================================== */

    /**
     * Confirmar arrematante e iniciar fluxo
     */
    public static function ajax_confirmar_arrematante() {
        check_ajax_referer('leilao_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Sem permissão.']);
        }

        $imovel_id   = absint($_POST['imovel_id'] ?? 0);
        $tipo_pessoa = in_array($_POST['tipo_pessoa'] ?? '', ['fisica', 'juridica'], true) ? $_POST['tipo_pessoa'] : 'fisica';
        $prazo_dias  = absint($_POST['prazo_dias'] ?? 15) ?: 15;
        $observacoes = sanitize_textarea_field($_POST['observacoes'] ?? '');

        if (!$imovel_id) {
            wp_send_json_error(['message' => 'ID do imóvel inválido.']);
        }

        $vencedor_id = get_post_meta($imovel_id, '_leilao_vencedor_id', true);
        $valor_final = get_post_meta($imovel_id, '_leilao_valor_final', true);

        if (!$vencedor_id || !$valor_final) {
            wp_send_json_error(['message' => 'Nenhum arrematante encontrado para este imóvel.']);
        }

        $existente = self::get_by_imovel($imovel_id);
        if ($existente && $existente->status !== self::STATUS_AGUARDANDO_CONFIRMACAO) {
            wp_send_json_error(['message' => 'Já existe uma arrematação confirmada para este imóvel.']);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'leilao_arrematacoes';
        $data_limite = gmdate('Y-m-d H:i:s', strtotime("+{$prazo_dias} days", current_time('timestamp')));
        $agora = current_time('mysql');

        if ($existente) {
            $wpdb->update($table, [
                'tipo_pessoa'      => $tipo_pessoa,
                'prazo_documentos' => $prazo_dias,
                'data_limite_docs' => $data_limite,
                'status'           => self::STATUS_AGUARDANDO_DOCUMENTOS,
                'observacoes'      => $observacoes,
                'confirmado_por'   => get_current_user_id(),
                'confirmado_em'    => $agora,
            ], ['id' => $existente->id], ['%s', '%d', '%s', '%s', '%s', '%d', '%s'], ['%d']);
            $arr_id = $existente->id;
        } else {
            $wpdb->insert($table, [
                'imovel_id'        => $imovel_id,
                'user_id'          => $vencedor_id,
                'valor_final'      => $valor_final,
                'tipo_pessoa'      => $tipo_pessoa,
                'prazo_documentos' => $prazo_dias,
                'data_limite_docs' => $data_limite,
                'status'           => self::STATUS_AGUARDANDO_DOCUMENTOS,
                'observacoes'      => $observacoes,
                'confirmado_por'   => get_current_user_id(),
                'confirmado_em'    => $agora,
            ], ['%d', '%d', '%f', '%s', '%d', '%s', '%s', '%s', '%d', '%s']);
            $arr_id = $wpdb->insert_id;
        }

        self::add_log($arr_id, 'confirmacao', sprintf(
            'Arrematação confirmada. Tipo: %s. Prazo: %d dias. Valor: R$ %s',
            $tipo_pessoa === 'juridica' ? 'PJ' : 'PF',
            $prazo_dias,
            number_format(floatval($valor_final), 2, ',', '.')
        ));

        // Notificar arrematante
        $user = get_userdata($vencedor_id);
        if ($user) {
            $titulo = get_the_title($imovel_id);
            wp_mail(
                $user->user_email,
                "Documentação solicitada - {$titulo}",
                sprintf(
                    "Olá %s,\n\nSua arrematação do imóvel \"%s\" foi confirmada!\n\nValor: R$ %s\nTipo: %s\nPrazo para documentos: %d dias (até %s)\n\nPor favor, providencie a documentação necessária dentro do prazo.\n\nEquipe Qatar Leilões",
                    $user->display_name,
                    $titulo,
                    number_format(floatval($valor_final), 2, ',', '.'),
                    $tipo_pessoa === 'juridica' ? 'Pessoa Jurídica' : 'Pessoa Física',
                    $prazo_dias,
                    wp_date('d/m/Y', strtotime($data_limite))
                )
            );
        }

        wp_send_json_success(['message' => 'Arrematação confirmada! Fluxo de documentação iniciado.']);
    }

    /**
     * Atualizar status do processo
     */
    public static function ajax_atualizar_status() {
        check_ajax_referer('leilao_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Sem permissão.']);
        }

        $arr_id      = absint($_POST['arrematacao_id'] ?? 0);
        $novo_status = sanitize_text_field($_POST['status'] ?? '');

        $labels = self::get_status_labels();
        if (!$arr_id || !isset($labels[$novo_status])) {
            wp_send_json_error(['message' => 'Dados inválidos.']);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'leilao_arrematacoes';
        $old = $wpdb->get_var($wpdb->prepare("SELECT status FROM {$table} WHERE id = %d", $arr_id));

        $wpdb->update($table, ['status' => $novo_status], ['id' => $arr_id], ['%s'], ['%d']);

        self::add_log($arr_id, 'status_alterado', sprintf(
            'Status alterado de "%s" para "%s"',
            $labels[$old] ?? $old,
            $labels[$novo_status]
        ));

        wp_send_json_success([
            'message' => 'Status atualizado para: ' . $labels[$novo_status],
            'status'  => $novo_status,
            'label'   => $labels[$novo_status],
            'color'   => self::get_status_color($novo_status),
        ]);
    }

    /**
     * Upload de documento
     */
    public static function ajax_upload_documento() {
        check_ajax_referer('leilao_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Sem permissão.']);
        }

        $arr_id = absint($_POST['arrematacao_id'] ?? 0);
        $tipo   = sanitize_text_field($_POST['tipo'] ?? 'outros');

        if (!$arr_id) {
            wp_send_json_error(['message' => 'ID da arrematação inválido.']);
        }

        if (empty($_FILES['documento'])) {
            wp_send_json_error(['message' => 'Nenhum arquivo selecionado.']);
        }

        $allowed_types = [
            'application/pdf',
            'image/jpeg', 'image/png', 'image/gif',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];

        $file = $_FILES['documento'];
        if (!in_array($file['type'], $allowed_types, true)) {
            wp_send_json_error(['message' => 'Tipo de arquivo não permitido. Use PDF, JPG, PNG ou DOC.']);
        }

        $max_size = 10 * 1024 * 1024; // 10MB
        if ($file['size'] > $max_size) {
            wp_send_json_error(['message' => 'Arquivo muito grande. Máximo: 10MB.']);
        }

        $upload_dir = wp_upload_dir();
        $dest_dir   = $upload_dir['basedir'] . '/leilao-docs/' . $arr_id;
        if (!wp_mkdir_p($dest_dir)) {
            wp_send_json_error(['message' => 'Erro ao criar diretório de upload.']);
        }

        $safe_name = sanitize_file_name($file['name']);
        $unique    = wp_unique_filename($dest_dir, $safe_name);
        $dest_path = $dest_dir . '/' . $unique;
        $dest_url  = $upload_dir['baseurl'] . '/leilao-docs/' . $arr_id . '/' . $unique;

        if (!move_uploaded_file($file['tmp_name'], $dest_path)) {
            wp_send_json_error(['message' => 'Erro ao salvar arquivo.']);
        }

        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'leilao_arrematacao_docs', [
            'arrematacao_id' => $arr_id,
            'tipo'           => $tipo,
            'nome'           => $safe_name,
            'arquivo_url'    => $dest_url,
            'arquivo_path'   => $dest_path,
            'status'         => self::DOC_PENDENTE,
            'uploaded_by'    => get_current_user_id(),
        ], ['%d', '%s', '%s', '%s', '%s', '%s', '%d']);

        $doc_tipos = self::get_doc_tipos();
        self::add_log($arr_id, 'documento_enviado', sprintf(
            'Documento enviado: "%s" (tipo: %s)',
            $safe_name,
            $doc_tipos[$tipo] ?? $tipo
        ));

        wp_send_json_success(['message' => 'Documento enviado com sucesso!']);
    }

    /**
     * Aprovar ou reprovar documento
     */
    public static function ajax_revisar_documento() {
        check_ajax_referer('leilao_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Sem permissão.']);
        }

        $doc_id    = absint($_POST['doc_id'] ?? 0);
        $acao      = sanitize_text_field($_POST['acao'] ?? '');
        $observacao = sanitize_textarea_field($_POST['observacao'] ?? '');

        if (!$doc_id || !in_array($acao, [self::DOC_APROVADO, self::DOC_REPROVADO], true)) {
            wp_send_json_error(['message' => 'Dados inválidos.']);
        }

        global $wpdb;
        $doc_table = $wpdb->prefix . 'leilao_arrematacao_docs';
        $doc = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$doc_table} WHERE id = %d", $doc_id));

        if (!$doc) {
            wp_send_json_error(['message' => 'Documento não encontrado.']);
        }

        $wpdb->update($doc_table, [
            'status'      => $acao,
            'observacao'  => $observacao ?: null,
            'reviewed_by' => get_current_user_id(),
            'reviewed_em' => current_time('mysql'),
        ], ['id' => $doc_id], ['%s', '%s', '%d', '%s'], ['%d']);

        $acao_log = $acao === self::DOC_APROVADO ? 'documento_aprovado' : 'documento_reprovado';
        $acao_text = $acao === self::DOC_APROVADO ? 'aprovado' : 'reprovado';
        self::add_log($doc->arrematacao_id, $acao_log, sprintf(
            'Documento "%s" %s%s',
            $doc->nome,
            $acao_text,
            $observacao ? ". Obs: {$observacao}" : ''
        ));

        wp_send_json_success([
            'message' => 'Documento ' . $acao_text . '!',
            'status'  => $acao,
            'label'   => self::get_doc_status_label($acao),
        ]);
    }

    /**
     * Excluir documento
     */
    public static function ajax_excluir_documento() {
        check_ajax_referer('leilao_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Sem permissão.']);
        }

        $doc_id = absint($_POST['doc_id'] ?? 0);
        if (!$doc_id) {
            wp_send_json_error(['message' => 'ID inválido.']);
        }

        global $wpdb;
        $doc_table = $wpdb->prefix . 'leilao_arrematacao_docs';
        $doc = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$doc_table} WHERE id = %d", $doc_id));

        if (!$doc) {
            wp_send_json_error(['message' => 'Documento não encontrado.']);
        }

        // Remover arquivo físico
        if ($doc->arquivo_path && file_exists($doc->arquivo_path)) {
            wp_delete_file($doc->arquivo_path);
        }

        $wpdb->delete($doc_table, ['id' => $doc_id], ['%d']);

        self::add_log($doc->arrematacao_id, 'documento_excluido', sprintf(
            'Documento "%s" excluído',
            $doc->nome
        ));

        wp_send_json_success(['message' => 'Documento excluído.']);
    }

    /**
     * Enviar notificação manual
     */
    public static function ajax_enviar_notificacao() {
        check_ajax_referer('leilao_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Sem permissão.']);
        }

        $arr_id   = absint($_POST['arrematacao_id'] ?? 0);
        $assunto  = sanitize_text_field($_POST['assunto'] ?? '');
        $mensagem = sanitize_textarea_field($_POST['mensagem'] ?? '');

        if (!$arr_id || !$assunto || !$mensagem) {
            wp_send_json_error(['message' => 'Preencha assunto e mensagem.']);
        }

        global $wpdb;
        $arr = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}leilao_arrematacoes WHERE id = %d", $arr_id
        ));

        if (!$arr) {
            wp_send_json_error(['message' => 'Arrematação não encontrada.']);
        }

        $user = get_userdata($arr->user_id);
        if (!$user || !$user->user_email) {
            wp_send_json_error(['message' => 'E-mail do arrematante não encontrado.']);
        }

        $corpo = $mensagem . "\n\n—\nEquipe Qatar Leilões";
        $enviado = wp_mail($user->user_email, $assunto, $corpo);

        if (!$enviado) {
            wp_send_json_error(['message' => 'Falha ao enviar e-mail. Verifique configurações de SMTP.']);
        }

        self::add_log($arr_id, 'notificacao', sprintf(
            'E-mail enviado para %s. Assunto: "%s"',
            $user->user_email,
            $assunto
        ), ['assunto' => $assunto, 'mensagem' => $mensagem]);

        wp_send_json_success(['message' => 'E-mail enviado com sucesso para ' . $user->user_email]);
    }

    /* ========================================================================
       Página admin — Lista de Arrematações
       ======================================================================== */

    public static function page_arrematacoes() {
        global $wpdb;
        $table = $wpdb->prefix . 'leilao_arrematacoes';
        $labels = self::get_status_labels();

        $filtro_status = sanitize_text_field($_GET['status_filter'] ?? '');

        $where = '';
        if ($filtro_status && isset($labels[$filtro_status])) {
            $where = $wpdb->prepare(" WHERE a.status = %s", $filtro_status);
        }

        $arrematacoes = $wpdb->get_results(
            "SELECT a.*, p.post_title as imovel_titulo, u.display_name as arrematante_nome, u.user_email as arrematante_email
             FROM {$table} a
             LEFT JOIN {$wpdb->posts} p ON a.imovel_id = p.ID
             LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
             {$where}
             ORDER BY a.criado_em DESC"
        );

        $stats = $wpdb->get_results("SELECT status, COUNT(*) as total FROM {$table} GROUP BY status");
        $stat_map = [];
        $total_geral = 0;
        foreach ($stats as $s) {
            $stat_map[$s->status] = $s->total;
            $total_geral += $s->total;
        }
        ?>
        <div class="wrap">
            <h1>🏆 Arrematações</h1>

            <div class="leilao-admin-stats" style="margin-bottom:24px;">
                <div class="leilao-stat-card">
                    <span class="leilao-stat-number"><?php echo $total_geral; ?></span>
                    <span class="leilao-stat-label">Total</span>
                </div>
                <?php
                $highlight = [
                    self::STATUS_AGUARDANDO_DOCUMENTOS,
                    self::STATUS_DOCUMENTOS_ENVIADOS,
                    self::STATUS_EM_ANALISE,
                    self::STATUS_APROVADO,
                    self::STATUS_CONCLUIDO,
                ];
                foreach ($highlight as $hs):
                    $count = $stat_map[$hs] ?? 0;
                    $color = self::get_status_color($hs);
                ?>
                <div class="leilao-stat-card" style="border-top:3px solid <?php echo esc_attr($color); ?>;">
                    <span class="leilao-stat-number" style="color:<?php echo esc_attr($color); ?>;"><?php echo $count; ?></span>
                    <span class="leilao-stat-label"><?php echo esc_html($labels[$hs]); ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <div style="margin-bottom:16px;">
                <form method="get" style="display:inline-flex;gap:8px;align-items:center;">
                    <input type="hidden" name="page" value="leilao-arrematacoes" />
                    <select name="status_filter">
                        <option value="">Todos os status</option>
                        <?php foreach ($labels as $val => $label): ?>
                            <option value="<?php echo esc_attr($val); ?>" <?php selected($filtro_status, $val); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="button">Filtrar</button>
                    <?php if ($filtro_status): ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=leilao-arrematacoes')); ?>" class="button">Limpar</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if (empty($arrematacoes)): ?>
                <div class="notice notice-info"><p>Nenhuma arrematação encontrada.</p></div>
            <?php else: ?>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Imóvel</th>
                            <th>Arrematante</th>
                            <th>Tipo</th>
                            <th>Valor Final</th>
                            <th>Prazo Docs</th>
                            <th>Status</th>
                            <th>Confirmado em</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($arrematacoes as $arr):
                            $color = self::get_status_color($arr->status);
                            $prazo_ok = true;
                            if ($arr->data_limite_docs && $arr->status === self::STATUS_AGUARDANDO_DOCUMENTOS) {
                                $prazo_ok = strtotime($arr->data_limite_docs) > current_time('timestamp');
                            }
                        ?>
                        <tr<?php echo !$prazo_ok ? ' style="background:#fff3f3;"' : ''; ?>>
                            <td><?php echo $arr->id; ?></td>
                            <td>
                                <a href="<?php echo esc_url(get_edit_post_link($arr->imovel_id)); ?>">
                                    <?php echo esc_html($arr->imovel_titulo ?: "#{$arr->imovel_id}"); ?>
                                </a>
                            </td>
                            <td>
                                <?php echo esc_html($arr->arrematante_nome ?: 'N/A'); ?>
                                <br><small><?php echo esc_html($arr->arrematante_email ?: ''); ?></small>
                            </td>
                            <td><?php echo $arr->tipo_pessoa === 'juridica' ? 'PJ' : 'PF'; ?></td>
                            <td><strong>R$ <?php echo number_format($arr->valor_final, 2, ',', '.'); ?></strong></td>
                            <td>
                                <?php if ($arr->data_limite_docs): ?>
                                    <?php echo wp_date('d/m/Y', strtotime($arr->data_limite_docs)); ?>
                                    <?php if (!$prazo_ok): ?>
                                        <br><small style="color:#d9534f;font-weight:bold;">⚠ Expirado</small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="leilao-arr-badge status-<?php echo esc_attr($arr->status); ?>">
                                    <?php echo esc_html($labels[$arr->status] ?? $arr->status); ?>
                                </span>
                            </td>
                            <td><?php echo $arr->confirmado_em ? wp_date('d/m/Y H:i', strtotime($arr->confirmado_em)) : '-'; ?></td>
                            <td>
                                <a href="<?php echo esc_url(get_edit_post_link($arr->imovel_id)); ?>#leilao_arrematacao_box" class="button button-small">
                                    Gerenciar
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
}
