<?php
/**
 * User management: registro, login, painel do arrematante
 */

defined('ABSPATH') || exit;

class Leilao_User {

    public static function init() {
        add_action('wp_ajax_nopriv_leilao_register', [__CLASS__, 'ajax_register']);
        add_action('wp_ajax_nopriv_leilao_login', [__CLASS__, 'ajax_login']);
        add_shortcode('painel_arrematante', [__CLASS__, 'shortcode_painel']);
        add_shortcode('leilao_login', [__CLASS__, 'shortcode_login']);
        add_shortcode('leilao_registro', [__CLASS__, 'shortcode_registro']);
        add_shortcode('leilao_catalogo', [__CLASS__, 'shortcode_catalogo']);

        // Criar página do painel na ativação via admin_init
        add_action('admin_init', [__CLASS__, 'criar_paginas']);
    }

    /**
     * Criar páginas necessárias
     */
    public static function criar_paginas() {
        if (get_option('leilao_pages_created')) return;

        $pages = [
            'painel-arrematante' => [
                'title'   => 'Meu Painel',
                'content' => '[painel_arrematante]',
            ],
            'login-leilao' => [
                'title'   => 'Entrar',
                'content' => '[leilao_login]',
            ],
            'registro-leilao' => [
                'title'   => 'Criar Conta',
                'content' => '[leilao_registro]',
            ],
            'catalogo' => [
                'title'   => 'Catálogo de Imóveis',
                'content' => '[leilao_catalogo]',
            ],
        ];

        foreach ($pages as $slug => $page) {
            if (!get_page_by_path($slug)) {
                wp_insert_post([
                    'post_type'    => 'page',
                    'post_title'   => $page['title'],
                    'post_content' => $page['content'],
                    'post_status'  => 'publish',
                    'post_name'    => $slug,
                ]);
            }
        }

        update_option('leilao_pages_created', true);
    }

    /**
     * AJAX: Registro de arrematante
     */
    public static function ajax_register() {
        check_ajax_referer('leilao_nonce', 'nonce');

        $nome    = sanitize_text_field($_POST['nome'] ?? '');
        $email   = sanitize_email($_POST['email'] ?? '');
        $cpf     = sanitize_text_field($_POST['cpf'] ?? '');
        $phone   = sanitize_text_field($_POST['telefone'] ?? '');
        $senha   = $_POST['senha'] ?? '';

        if (!$nome || !$email || !$cpf || !$senha) {
            wp_send_json_error(['message' => 'Preencha todos os campos obrigatórios.']);
        }

        if (!is_email($email)) {
            wp_send_json_error(['message' => 'E-mail inválido.']);
        }

        if (email_exists($email)) {
            wp_send_json_error(['message' => 'Este e-mail já está cadastrado.']);
        }

        if (!self::validar_cpf($cpf)) {
            wp_send_json_error(['message' => 'CPF inválido.']);
        }

        $user_id = wp_create_user($email, $senha, $email);
        if (is_wp_error($user_id)) {
            wp_send_json_error(['message' => $user_id->get_error_message()]);
        }

        wp_update_user([
            'ID'           => $user_id,
            'display_name' => $nome,
            'first_name'   => explode(' ', $nome)[0],
            'last_name'    => implode(' ', array_slice(explode(' ', $nome), 1)),
            'role'         => 'arrematante',
        ]);

        update_user_meta($user_id, '_leilao_cpf', $cpf);
        update_user_meta($user_id, '_leilao_telefone', $phone);

        // Auto-login
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);

        wp_send_json_success([
            'message'  => 'Conta criada com sucesso!',
            'redirect' => home_url('/catalogo/'),
        ]);
    }

    /**
     * AJAX: Login
     */
    public static function ajax_login() {
        check_ajax_referer('leilao_nonce', 'nonce');

        $email = sanitize_email($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';

        $user = wp_authenticate($email, $senha);

        if (is_wp_error($user)) {
            wp_send_json_error(['message' => 'E-mail ou senha incorretos.']);
        }

        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID);

        wp_send_json_success([
            'message'  => 'Login realizado!',
            'redirect' => home_url('/catalogo/'),
        ]);
    }

    /**
     * Shortcode: Painel do arrematante
     */
    public static function shortcode_painel(): string {
        if (!is_user_logged_in()) {
            return '<div class="leilao-msg"><p>Faça <a href="' . home_url('/login-leilao/') . '">login</a> para acessar seu painel.</p></div>';
        }

        $user = wp_get_current_user();
        $tab = sanitize_text_field($_GET['tab'] ?? 'meus-lances');
        
        ob_start();
        ?>
        <div id="painel-arrematante" class="leilao-painel">
            <div class="leilao-painel-header">
                <h2>Olá, <?php echo esc_html($user->display_name); ?></h2>
                <a href="<?php echo wp_logout_url(home_url()); ?>" class="leilao-btn leilao-btn-sm">Sair</a>
            </div>

            <div class="leilao-painel-tabs">
                <button class="leilao-tab <?php echo $tab === 'meus-lances' ? 'active' : ''; ?>" data-tab="meus-lances">Meus Lances</button>
                <button class="leilao-tab <?php echo $tab === 'ganhos' ? 'active' : ''; ?>" data-tab="ganhos">Arrematados</button>
                <button class="leilao-tab <?php echo $tab === 'documentos' ? 'active' : ''; ?>" data-tab="documentos">📁 Documentação</button>
                <button class="leilao-tab <?php echo $tab === 'assessoria' ? 'active' : ''; ?>" data-tab="assessoria">⚖️ Assessoria</button>
                <button class="leilao-tab <?php echo $tab === 'perfil' ? 'active' : ''; ?>" data-tab="perfil">Meu Perfil</button>
            </div>

            <div class="leilao-tab-content <?php echo $tab === 'meus-lances' ? 'active' : ''; ?>" id="tab-meus-lances">
                <div id="lista-meus-lances" class="leilao-loading">Carregando...</div>
            </div>

            <div class="leilao-tab-content <?php echo $tab === 'ganhos' ? 'active' : ''; ?>" id="tab-ganhos">
                <?php
                $ganhos = Leilao_Bidding::get_leiloes_ganhos($user->ID);
                if (empty($ganhos)) {
                    echo '<p class="leilao-empty">Nenhum imóvel arrematado ainda.</p>';
                } else {
                    echo '<div class="leilao-grid">';
                    foreach ($ganhos as $imovel) {
                        $valor = get_post_meta($imovel->ID, '_leilao_valor_final', true);
                        echo '<div class="leilao-card leilao-card-ganho">';
                        echo '<div class="leilao-card-img">' . get_the_post_thumbnail($imovel->ID, 'medium') . '</div>';
                        echo '<div class="leilao-card-body">';
                        echo '<h4>' . esc_html($imovel->post_title) . '</h4>';
                        echo '<p class="leilao-valor">R$ ' . number_format(floatval($valor), 2, ',', '.') . '</p>';
                        echo '<span class="leilao-badge arrematado">Arrematado</span>';
                        echo '</div></div>';
                    }
                    echo '</div>';
                }
                ?>
            </div>

            <div class="leilao-tab-content <?php echo $tab === 'documentos' ? 'active' : ''; ?>" id="tab-documentos">
                <?php echo do_shortcode('[leilao_area_documentos]'); ?>
            </div>

            <div class="leilao-tab-content <?php echo $tab === 'assessoria' ? 'active' : ''; ?>" id="tab-assessoria">
                <?php echo do_shortcode('[leilao_acompanhar_processo]'); ?>
            </div>

            <div class="leilao-tab-content <?php echo $tab === 'perfil' ? 'active' : ''; ?>" id="tab-perfil">
                <form class="leilao-form" id="form-perfil">
                    <div class="leilao-field">
                        <label>Nome Completo</label>
                        <input type="text" value="<?php echo esc_attr($user->display_name); ?>" name="nome" />
                    </div>
                    <div class="leilao-field">
                        <label>E-mail</label>
                        <input type="email" value="<?php echo esc_attr($user->user_email); ?>" disabled />
                    </div>
                    <div class="leilao-field">
                        <label>CPF</label>
                        <input type="text" value="<?php echo esc_attr(get_user_meta($user->ID, '_leilao_cpf', true)); ?>" disabled />
                    </div>
                    <div class="leilao-field">
                        <label>Telefone</label>
                        <input type="text" name="telefone" value="<?php echo esc_attr(get_user_meta($user->ID, '_leilao_telefone', true)); ?>" />
                    </div>
                    <button type="submit" class="leilao-btn">Salvar Alterações</button>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Shortcode: Login
     */
    public static function shortcode_login(): string {
        if (is_user_logged_in()) {
            return '<div class="leilao-msg"><p>Você já está logado. <a href="' . home_url('/painel-arrematante/') . '">Ir para o painel</a>.</p></div>';
        }

        ob_start();
        ?>
        <div class="leilao-auth-container">
            <div class="leilao-auth-card">
                <h2>Entrar</h2>
                <form id="leilao-login-form" class="leilao-form">
                    <input type="hidden" name="action" value="leilao_login" />
                    <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('leilao_nonce'); ?>" />
                    <div class="leilao-field">
                        <label>E-mail</label>
                        <input type="email" name="email" required />
                    </div>
                    <div class="leilao-field">
                        <label>Senha</label>
                        <input type="password" name="senha" required />
                    </div>
                    <div class="leilao-msg-box" id="login-msg"></div>
                    <button type="submit" class="leilao-btn leilao-btn-full">Entrar</button>
                </form>
                <p class="leilao-auth-link">Não tem conta? <a href="<?php echo home_url('/registro-leilao/'); ?>">Criar conta</a></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Shortcode: Registro
     */
    public static function shortcode_registro(): string {
        if (is_user_logged_in()) {
            return '<div class="leilao-msg"><p>Você já está logado. <a href="' . home_url('/painel-arrematante/') . '">Ir para o painel</a>.</p></div>';
        }

        ob_start();
        ?>
        <div class="leilao-auth-container">
            <div class="leilao-auth-card">
                <h2>Criar Conta</h2>
                <form id="leilao-register-form" class="leilao-form">
                    <input type="hidden" name="action" value="leilao_register" />
                    <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('leilao_nonce'); ?>" />
                    <div class="leilao-field">
                        <label>Nome Completo *</label>
                        <input type="text" name="nome" required />
                    </div>
                    <div class="leilao-field">
                        <label>CPF *</label>
                        <input type="text" name="cpf" required maxlength="14" placeholder="000.000.000-00" />
                    </div>
                    <div class="leilao-field">
                        <label>E-mail *</label>
                        <input type="email" name="email" required />
                    </div>
                    <div class="leilao-field">
                        <label>Telefone / WhatsApp</label>
                        <input type="text" name="telefone" placeholder="(00) 00000-0000" />
                    </div>
                    <div class="leilao-field">
                        <label>Senha *</label>
                        <input type="password" name="senha" required minlength="6" />
                    </div>
                    <div class="leilao-msg-box" id="register-msg"></div>
                    <button type="submit" class="leilao-btn leilao-btn-full">Criar Conta</button>
                </form>
                <p class="leilao-auth-link">Já tem conta? <a href="<?php echo home_url('/login-leilao/'); ?>">Entrar</a></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Shortcode: Catálogo de imóveis
     */
    public static function shortcode_catalogo(): string {
        ob_start();
        ?>
        <div id="leilao-catalogo" class="leilao-catalogo">
            <!-- Filtros -->
            <div class="leilao-filtros">
                <div class="leilao-filtro-group">
                    <select id="filtro-estado" class="leilao-select">
                        <option value="">Todos os Estados</option>
                    </select>
                    <select id="filtro-cidade" class="leilao-select">
                        <option value="">Todas as Cidades</option>
                    </select>
                    <select id="filtro-tipo" class="leilao-select">
                        <option value="">Todos os Tipos</option>
                    </select>
                    <select id="filtro-modalidade" class="leilao-select">
                        <option value="">Todas as Modalidades</option>
                    </select>
                </div>
                <div class="leilao-filtro-actions">
                    <button id="btn-filtrar" class="leilao-btn">Filtrar</button>
                    <button id="btn-limpar" class="leilao-btn leilao-btn-outline">Limpar</button>
                </div>
            </div>

            <!-- Grid de imóveis -->
            <div id="leilao-grid" class="leilao-grid leilao-loading">
                Carregando imóveis...
            </div>

            <!-- Paginação -->
            <div id="leilao-pagination" class="leilao-pagination"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Validar CPF
     */
    private static function validar_cpf(string $cpf): bool {
        $cpf = preg_replace('/\D/', '', $cpf);
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += $cpf[$i] * (($t + 1) - $i);
            }
            $digit = ((10 * $sum) % 11) % 10;
            if ($cpf[$t] != $digit) {
                return false;
            }
        }

        return true;
    }
}
