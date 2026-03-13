<?php
/**
 * Admin: Metaboxes, painel de importação, configurações
 */

defined('ABSPATH') || exit;

class Leilao_Admin {

    public static function init() {
        add_action('add_meta_boxes', [__CLASS__, 'add_metaboxes']);
        add_action('save_post_imovel', [__CLASS__, 'save_metabox']);
        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'admin_scripts']);
    }

    /**
     * Admin menu
     */
    public static function admin_menu() {
        add_menu_page(
            'Leilão Caixa',
            'Leilão Caixa',
            'manage_options',
            'leilao-caixa',
            [__CLASS__, 'page_dashboard'],
            'dashicons-hammer',
            26
        );

        add_submenu_page(
            'leilao-caixa',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'leilao-caixa',
            [__CLASS__, 'page_dashboard']
        );

        add_submenu_page(
            'leilao-caixa',
            'Importar Imóveis',
            'Importar da Caixa',
            'manage_options',
            'leilao-importar',
            [__CLASS__, 'page_importar']
        );

        add_submenu_page(
            'leilao-caixa',
            'Cadastrar Imóvel',
            'Cadastrar Manual',
            'manage_options',
            'leilao-cadastrar',
            [__CLASS__, 'page_cadastrar']
        );

        add_submenu_page(
            'leilao-caixa',
            'Configurações',
            'Configurações',
            'manage_options',
            'leilao-config',
            [__CLASS__, 'page_config']
        );
    }

    /**
     * Admin scripts
     */
    public static function admin_scripts($hook) {
        if (strpos($hook, 'leilao') === false && get_post_type() !== 'imovel') return;

        wp_enqueue_style('leilao-admin', LEILAO_CAIXA_URL . 'assets/css/admin.css', [], LEILAO_CAIXA_VERSION);
        wp_enqueue_script('leilao-admin', LEILAO_CAIXA_URL . 'assets/js/admin.js', ['jquery'], LEILAO_CAIXA_VERSION, true);
        wp_localize_script('leilao-admin', 'leilaoAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('leilao_nonce'),
        ]);

        wp_enqueue_script('leilao-arrematacao', LEILAO_CAIXA_URL . 'assets/js/arrematacao-admin.js', ['jquery', 'leilao-admin'], LEILAO_CAIXA_VERSION, true);
    }

    /**
     * Metaboxes do imóvel
     */
    public static function add_metaboxes() {
        add_meta_box(
            'leilao_dados_imovel',
            'Dados do Imóvel',
            [__CLASS__, 'metabox_dados_imovel'],
            'imovel',
            'normal',
            'high'
        );

        add_meta_box(
            'leilao_dados_leilao',
            'Dados do Leilão',
            [__CLASS__, 'metabox_dados_leilao'],
            'imovel',
            'normal',
            'high'
        );

        add_meta_box(
            'leilao_lances_box',
            'Lances',
            [__CLASS__, 'metabox_lances'],
            'imovel',
            'side',
            'default'
        );
    }

    /**
     * Metabox: Dados do Imóvel
     */
    public static function metabox_dados_imovel($post) {
        wp_nonce_field('leilao_save_meta', 'leilao_meta_nonce');
        $fields = [
            ['_imovel_endereco', 'Endereço', 'text'],
            ['_imovel_bairro', 'Bairro', 'text'],
            ['_imovel_cep', 'CEP', 'text'],
            ['_imovel_area_total', 'Área Total (m²)', 'text'],
            ['_imovel_area_privativa', 'Área Privativa (m²)', 'text'],
            ['_imovel_quartos', 'Quartos', 'number'],
            ['_imovel_garagem', 'Vagas Garagem', 'number'],
            ['_imovel_matricula', 'Matrícula', 'text'],
            ['_imovel_edital', 'URL do Edital', 'url'],
            ['_imovel_caixa_id', 'ID Caixa', 'text'],
            ['_imovel_latitude', 'Latitude', 'text'],
            ['_imovel_longitude', 'Longitude', 'text'],
        ];
        ?>
        <table class="form-table leilao-metabox-table">
            <?php foreach ($fields as [$key, $label, $type]): ?>
                <tr>
                    <th><label for="<?php echo $key; ?>"><?php echo $label; ?></label></th>
                    <td>
                        <input type="<?php echo $type; ?>"
                               id="<?php echo $key; ?>"
                               name="<?php echo $key; ?>"
                               value="<?php echo esc_attr(get_post_meta($post->ID, $key, true)); ?>"
                               class="regular-text" />
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        <?php
    }

    /**
     * Metabox: Dados do Leilão
     */
    public static function metabox_dados_leilao($post) {
        $status = get_post_meta($post->ID, '_leilao_status', true) ?: 'agendado';
        $statuses = ['agendado' => 'Agendado', 'ativo' => 'Ativo', 'encerrado' => 'Encerrado', 'cancelado' => 'Cancelado'];
        ?>
        <table class="form-table leilao-metabox-table">
            <tr>
                <th><label>Status</label></th>
                <td>
                    <select name="_leilao_status">
                        <?php foreach ($statuses as $val => $label): ?>
                            <option value="<?php echo $val; ?>" <?php selected($status, $val); ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label>Início do Leilão</label></th>
                <td>
                    <input type="datetime-local" name="_leilao_inicio"
                           value="<?php echo esc_attr(str_replace(' ', 'T', get_post_meta($post->ID, '_leilao_inicio', true))); ?>" />
                </td>
            </tr>
            <tr>
                <th><label>Fim do Leilão</label></th>
                <td>
                    <input type="datetime-local" name="_leilao_fim"
                           value="<?php echo esc_attr(str_replace(' ', 'T', get_post_meta($post->ID, '_leilao_fim', true))); ?>" />
                </td>
            </tr>
            <tr>
                <th><label>Valor de Avaliação (R$)</label></th>
                <td>
                    <input type="number" step="0.01" name="_leilao_valor_avaliacao"
                           value="<?php echo esc_attr(get_post_meta($post->ID, '_leilao_valor_avaliacao', true)); ?>" />
                </td>
            </tr>
            <tr>
                <th><label>Lance Mínimo (R$)</label></th>
                <td>
                    <input type="number" step="0.01" name="_leilao_valor_minimo"
                           value="<?php echo esc_attr(get_post_meta($post->ID, '_leilao_valor_minimo', true)); ?>" />
                </td>
            </tr>
            <tr>
                <th><label>Incremento Mínimo (R$)</label></th>
                <td>
                    <input type="number" step="0.01" name="_leilao_incremento"
                           value="<?php echo esc_attr(get_post_meta($post->ID, '_leilao_incremento', true) ?: 500); ?>" />
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Metabox: Lances na sidebar
     */
    public static function metabox_lances($post) {
        $maior = Leilao_Bidding::get_maior_lance($post->ID);
        $total = get_post_meta($post->ID, '_leilao_total_lances', true) ?: 0;
        $vencedor_id = get_post_meta($post->ID, '_leilao_vencedor_id', true);
        ?>
        <div class="leilao-lances-sidebar">
            <p><strong>Total de lances:</strong> <?php echo $total; ?></p>
            <?php if ($maior): ?>
                <p><strong>Maior lance:</strong> R$ <?php echo number_format($maior['valor'], 2, ',', '.'); ?></p>
                <p><strong>Arrematante:</strong> <?php echo esc_html($maior['nome_usuario']); ?></p>
            <?php else: ?>
                <p><em>Nenhum lance ainda.</em></p>
            <?php endif; ?>
            <?php if ($vencedor_id): ?>
                <hr />
                <p><strong>Vencedor:</strong> <?php echo esc_html(get_userdata($vencedor_id)->display_name ?? 'N/A'); ?></p>
                <p><strong>Valor final:</strong> R$ <?php echo number_format(floatval(get_post_meta($post->ID, '_leilao_valor_final', true)), 2, ',', '.'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Save metabox data
     */
    public static function save_metabox($post_id) {
        if (!isset($_POST['leilao_meta_nonce']) || !wp_verify_nonce($_POST['leilao_meta_nonce'], 'leilao_save_meta')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $meta_keys = array_keys(Leilao_CPT::get_meta_fields());

        foreach ($meta_keys as $key) {
            if (isset($_POST[$key])) {
                $value = $_POST[$key];

                // Converter datetime-local para mysql format
                if (in_array($key, ['_leilao_inicio', '_leilao_fim'])) {
                    $value = str_replace('T', ' ', $value);
                }

                update_post_meta($post_id, $key, sanitize_text_field($value));
            }
        }
    }

    /**
     * Page: Dashboard
     */
    public static function page_dashboard() {
        global $wpdb;
        $table = $wpdb->prefix . 'leilao_lances';

        $total_imoveis = wp_count_posts('imovel')->publish ?? 0;
        $total_lances  = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        $total_users   = count(get_users(['role' => 'arrematante']));

        $ativos = get_posts([
            'post_type'      => 'imovel',
            'posts_per_page' => -1,
            'meta_key'       => '_leilao_status',
            'meta_value'     => 'ativo',
        ]);
        ?>
        <div class="wrap">
            <h1>Leilão Caixa - Dashboard</h1>

            <div class="leilao-admin-stats">
                <div class="leilao-stat-card">
                    <span class="leilao-stat-number"><?php echo $total_imoveis; ?></span>
                    <span class="leilao-stat-label">Imóveis</span>
                </div>
                <div class="leilao-stat-card">
                    <span class="leilao-stat-number"><?php echo count($ativos); ?></span>
                    <span class="leilao-stat-label">Leilões Ativos</span>
                </div>
                <div class="leilao-stat-card">
                    <span class="leilao-stat-number"><?php echo $total_lances; ?></span>
                    <span class="leilao-stat-label">Total de Lances</span>
                </div>
                <div class="leilao-stat-card">
                    <span class="leilao-stat-number"><?php echo $total_users; ?></span>
                    <span class="leilao-stat-label">Arrematantes</span>
                </div>
            </div>

            <?php if (!empty($ativos)): ?>
                <h2>Leilões em Andamento</h2>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th>Imóvel</th>
                            <th>Fim</th>
                            <th>Maior Lance</th>
                            <th>Total Lances</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ativos as $imovel):
                            $maior = Leilao_Bidding::get_maior_lance($imovel->ID);
                            $fim = get_post_meta($imovel->ID, '_leilao_fim', true);
                            $total = get_post_meta($imovel->ID, '_leilao_total_lances', true) ?: 0;
                            ?>
                            <tr>
                                <td><a href="<?php echo get_edit_post_link($imovel->ID); ?>"><?php echo esc_html($imovel->post_title); ?></a></td>
                                <td><?php echo $fim ? date('d/m/Y H:i', strtotime($fim)) : '-'; ?></td>
                                <td><?php echo $maior ? 'R$ ' . number_format($maior['valor'], 2, ',', '.') : '-'; ?></td>
                                <td><?php echo $total; ?></td>
                                <td><a href="<?php echo get_permalink($imovel->ID); ?>" target="_blank" class="button button-small">Ver</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Page: Importar da Caixa
     */
    public static function page_importar() {
        $estados = [
            'AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS',
            'MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'
        ];
        ?>
        <div class="wrap">
            <h1>Importar Imóveis da Caixa</h1>
            <div class="card">
                <h2>Importar por Estado</h2>
                <p>Selecione o estado para buscar imóveis no site da Caixa Econômica Federal.</p>
                <form id="form-importar-caixa">
                    <select id="import-estado" style="width:200px">
                        <option value="">Selecione...</option>
                        <?php foreach ($estados as $uf): ?>
                            <option value="<?php echo $uf; ?>"><?php echo $uf; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="button button-primary" id="btn-importar">Importar</button>
                </form>
                <div id="import-result" style="margin-top:15px"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Page: Cadastrar Manualmente
     */
    public static function page_cadastrar() {
        ?>
        <div class="wrap">
            <h1>Cadastrar Imóvel Manualmente</h1>
            <div class="card" style="max-width:800px;padding:20px">
                <form id="form-cadastrar-manual" class="leilao-admin-form">
                    <table class="form-table">
                        <tr><th>Título *</th><td><input type="text" name="titulo" class="regular-text" required /></td></tr>
                        <tr><th>Descrição</th><td><textarea name="descricao" rows="4" class="large-text"></textarea></td></tr>
                        <tr><th>Tipo</th><td>
                            <select name="tipo">
                                <option value="">Selecione</option>
                                <option>Apartamento</option><option>Casa</option><option>Terreno</option>
                                <option>Comercial</option><option>Rural</option><option>Galpão</option>
                            </select>
                        </td></tr>
                        <tr><th>Modalidade</th><td>
                            <select name="modalidade">
                                <option value="">Selecione</option>
                                <option>1º Leilão</option><option>2º Leilão</option><option>Venda Direta</option>
                                <option>Licitação Aberta</option><option>Licitação Fechada</option>
                            </select>
                        </td></tr>
                        <tr><th>Endereço</th><td><input type="text" name="endereco" class="regular-text" /></td></tr>
                        <tr><th>Bairro</th><td><input type="text" name="bairro" class="regular-text" /></td></tr>
                        <tr><th>Cidade</th><td><input type="text" name="cidade" class="regular-text" /></td></tr>
                        <tr><th>Estado</th><td><input type="text" name="estado" class="regular-text" maxlength="2" /></td></tr>
                        <tr><th>CEP</th><td><input type="text" name="cep" class="regular-text" /></td></tr>
                        <tr><th>Área Total (m²)</th><td><input type="text" name="area_total" /></td></tr>
                        <tr><th>Área Privativa (m²)</th><td><input type="text" name="area_privativa" /></td></tr>
                        <tr><th>Quartos</th><td><input type="number" name="quartos" /></td></tr>
                        <tr><th>Garagem</th><td><input type="number" name="garagem" /></td></tr>
                        <tr><th>Matrícula</th><td><input type="text" name="matricula" class="regular-text" /></td></tr>
                        <tr><th>URL do Edital</th><td><input type="url" name="edital" class="regular-text" /></td></tr>
                        <tr><th>Valor Avaliação (R$)</th><td><input type="number" step="0.01" name="valor_avaliacao" /></td></tr>
                        <tr><th>Lance Mínimo (R$)</th><td><input type="number" step="0.01" name="valor_minimo" /></td></tr>
                        <tr><th>Incremento (R$)</th><td><input type="number" step="0.01" name="incremento" value="500" /></td></tr>
                        <tr><th>Início Leilão</th><td><input type="datetime-local" name="inicio" /></td></tr>
                        <tr><th>Fim Leilão</th><td><input type="datetime-local" name="fim" /></td></tr>
                    </table>
                    <p class="submit">
                        <button type="submit" class="button button-primary button-hero">Cadastrar Imóvel</button>
                    </p>
                    <div id="cadastro-result"></div>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Page: Configurações
     */
    public static function page_config() {
        if (isset($_POST['leilao_save_config']) && check_admin_referer('leilao_config')) {
            update_option('leilao_caixa_estados_importar', array_filter(array_map('sanitize_text_field', $_POST['estados'] ?? [])));
            update_option('leilao_caixa_email_admin', sanitize_email($_POST['email_admin'] ?? ''));
            update_option('leilao_caixa_incremento_padrao', floatval($_POST['incremento_padrao'] ?? 500));
            if (isset($_POST['openai_api_key'])) {
                Leilao_ChatGPT::save_api_key($_POST['openai_api_key']);
            }
            echo '<div class="updated"><p>Configurações salvas!</p></div>';
        }

        $estados_importar = get_option('leilao_caixa_estados_importar', ['SP', 'RJ', 'MG']);
        $email_admin      = get_option('leilao_caixa_email_admin', get_option('admin_email'));
        $incremento       = get_option('leilao_caixa_incremento_padrao', 500);
        $openai_key       = Leilao_ChatGPT::get_api_key();
        ?>
        <div class="wrap">
            <h1>Configurações do Leilão</h1>
            <form method="post">
                <?php wp_nonce_field('leilao_config'); ?>
                <table class="form-table">
                    <tr>
                        <th>E-mail de notificações</th>
                        <td><input type="email" name="email_admin" value="<?php echo esc_attr($email_admin); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th>Incremento padrão (R$)</th>
                        <td><input type="number" step="0.01" name="incremento_padrao" value="<?php echo esc_attr($incremento); ?>" /></td>
                    </tr>
                    <tr>
                        <th>API Key OpenAI (ChatGPT)</th>
                        <td>
                            <input type="password" name="openai_api_key" value="<?php echo esc_attr($openai_key); ?>" class="regular-text" />
                            <p class="description">Chave da API do OpenAI para a IA de triagem na home. Modelo: gpt-4o-mini</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Estados para importação automática</th>
                        <td>
                            <?php
                            $all_states = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
                            foreach ($all_states as $uf) {
                                $checked = in_array($uf, $estados_importar) ? 'checked' : '';
                                echo "<label style='margin-right:10px'><input type='checkbox' name='estados[]' value='{$uf}' {$checked} /> {$uf}</label>";
                            }
                            ?>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" name="leilao_save_config" class="button button-primary">Salvar</button>
                </p>
            </form>
        </div>
        <?php
    }
}
