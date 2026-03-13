<?php
/**
 * Plugin Name: Leilão Caixa - Sistema de Leilões de Imóveis
 * Description: Sistema completo de leilão de imóveis da Caixa Econômica Federal com lances ao vivo, lances automáticos, importação de imóveis e painel do arrematante.
 * Version: 1.0.0
 * Author: SaySix
 * Text Domain: leilao-caixa
 * Domain Path: /languages
 * Requires PHP: 8.0
 */

defined('ABSPATH') || exit;

define('LEILAO_CAIXA_VERSION', '1.0.0');
define('LEILAO_CAIXA_FILE', __FILE__);
define('LEILAO_CAIXA_DIR', plugin_dir_path(__FILE__));
define('LEILAO_CAIXA_URL', plugin_dir_url(__FILE__));

/**
 * Autoload classes
 */
spl_autoload_register(function ($class) {
    $prefix = 'Leilao_';
    if (strpos($class, $prefix) !== 0) return;

    $file = strtolower(str_replace('_', '-', $class));
    $path = LEILAO_CAIXA_DIR . 'includes/class-' . $file . '.php';

    if (file_exists($path)) {
        require_once $path;
    }
});

/**
 * Plugin activation
 */
register_activation_hook(__FILE__, function () {
    require_once LEILAO_CAIXA_DIR . 'includes/class-leilao-cpt.php';
    Leilao_CPT::register();
    flush_rewrite_rules();

    // Criar tabela de lances
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $table   = $wpdb->prefix . 'leilao_lances';

    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        imovel_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        valor DECIMAL(15,2) NOT NULL,
        tipo ENUM('manual','automatico') DEFAULT 'manual',
        ip VARCHAR(45) DEFAULT '',
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_imovel (imovel_id),
        KEY idx_user (user_id),
        KEY idx_valor (imovel_id, valor DESC)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    // Tabela de lances automáticos
    $table_auto = $wpdb->prefix . 'leilao_auto_lances';
    $sql_auto = "CREATE TABLE IF NOT EXISTS {$table_auto} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        imovel_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        valor_maximo DECIMAL(15,2) NOT NULL,
        incremento DECIMAL(15,2) NOT NULL DEFAULT 500.00,
        ativo TINYINT(1) DEFAULT 1,
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_imovel_user (imovel_id, user_id),
        KEY idx_ativo (ativo)
    ) {$charset};";

    dbDelta($sql_auto);

    // Tabela de arrematacoes
    require_once LEILAO_CAIXA_DIR . 'includes/class-leilao-arrematacao.php';
    Leilao_Arrematacao::create_table();
    // Role arrematante
    add_role('arrematante', 'Arrematante', [
        'read' => true,
        'leilao_bid' => true,
    ]);

    update_option('leilao_caixa_version', LEILAO_CAIXA_VERSION);
});

/**
 * Plugin deactivation
 */
register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('leilao_caixa_check_encerrados');
    flush_rewrite_rules();
});

/**
 * Initialize plugin
 */
add_action('plugins_loaded', function () {
    // Load includes
    $includes = [
        'class-leilao-cpt.php',
        'class-leilao-caixa-importer.php',
        'class-leilao-bidding.php',
        'class-leilao-rest-api.php',
        'class-leilao-user.php',
        'class-leilao-admin.php',
        'class-leilao-chatgpt.php',
        'class-leilao-contato.php',
        'class-leilao-consultas.php',
        'class-leilao-veiculo-cpt.php',
        'class-leilao-veiculo-rest.php',
        'class-leilao-arrematacao.php',
    ];

    foreach ($includes as $file) {
        $path = LEILAO_CAIXA_DIR . 'includes/' . $file;
        if (file_exists($path)) {
            require_once $path;
        }
    }

    // Init classes
    Leilao_CPT::init();
    Leilao_Bidding::init();
    Leilao_Rest_API::init();
    Leilao_User::init();
    Leilao_Admin::init();
    Leilao_Caixa_Importer::init();
    Leilao_ChatGPT::init();
    Leilao_Contato::init();
    Leilao_Consultas::init();
    Leilao_Veiculo_CPT::init();
    Leilao_Veiculo_REST::init();
    Leilao_Arrematacao::init();
});

/**
 * Enqueue frontend assets
 */
add_action('wp_enqueue_scripts', function () {
    if (!is_singular('imovel') && !is_post_type_archive('imovel') && !is_page('painel-arrematante')) {
        return;
    }

    wp_enqueue_style(
        'leilao-caixa-css',
        LEILAO_CAIXA_URL . 'assets/css/leilao.css',
        [],
        LEILAO_CAIXA_VERSION
    );

    wp_enqueue_script(
        'leilao-caixa-js',
        LEILAO_CAIXA_URL . 'assets/js/leilao.js',
        ['jquery'],
        LEILAO_CAIXA_VERSION,
        true
    );

    wp_localize_script('leilao-caixa-js', 'leilaoCaixa', [
        'ajaxUrl'  => admin_url('admin-ajax.php'),
        'restUrl'  => rest_url('leilao/v1/'),
        'nonce'    => wp_create_nonce('leilao_nonce'),
        'restNonce' => wp_create_nonce('wp_rest'),
        'userId'   => get_current_user_id(),
        'currency' => 'R$',
        'i18n'     => [
            'lance_enviado'   => 'Lance enviado com sucesso!',
            'lance_erro'      => 'Erro ao enviar lance.',
            'valor_minimo'    => 'O valor mínimo é',
            'leilao_encerrado' => 'Este leilão foi encerrado.',
            'confirmar_lance' => 'Confirmar lance de',
            'login_required'  => 'Faça login para dar lances.',
        ],
    ]);
});

/**
 * Cron: verificar leilões encerrados
 */
add_action('init', function () {
    if (!wp_next_scheduled('leilao_caixa_check_encerrados')) {
        wp_schedule_event(time(), 'every_minute', 'leilao_caixa_check_encerrados');
    }
});

add_filter('cron_schedules', function ($schedules) {
    $schedules['every_minute'] = [
        'interval' => 60,
        'display'  => 'A cada minuto',
    ];
    return $schedules;
});

add_action('leilao_caixa_check_encerrados', function () {
    $agora = current_time('mysql');

    $imoveis = get_posts([
        'post_type'   => 'imovel',
        'post_status' => 'publish',
        'meta_query'  => [
            'relation' => 'AND',
            [
                'key'     => '_leilao_status',
                'value'   => 'ativo',
            ],
            [
                'key'     => '_leilao_fim',
                'value'   => $agora,
                'compare' => '<=',
                'type'    => 'DATETIME',
            ],
        ],
        'posts_per_page' => -1,
    ]);

    foreach ($imoveis as $imovel) {
        update_post_meta($imovel->ID, '_leilao_status', 'encerrado');

        // Pegar lance vencedor
        global $wpdb;
        $table = $wpdb->prefix . 'leilao_lances';
        $vencedor = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE imovel_id = %d ORDER BY valor DESC LIMIT 1",
            $imovel->ID
        ));

        if ($vencedor) {
            update_post_meta($imovel->ID, '_leilao_vencedor_id', $vencedor->user_id);
            update_post_meta($imovel->ID, '_leilao_valor_final', $vencedor->valor);

            // Notificar vencedor
            $user = get_user_by('id', $vencedor->user_id);
            if ($user) {
                wp_mail(
                    $user->user_email,
                    'Parabéns! Você venceu o leilão - ' . get_the_title($imovel->ID),
                    sprintf(
                        "Olá %s,\n\nVocê venceu o leilão do imóvel: %s\nValor final: R$ %s\n\nAcesse seu painel para mais detalhes.\n\nEquipe Qatar Leilões",
                        $user->display_name,
                        get_the_title($imovel->ID),
                        number_format($vencedor->valor, 2, ',', '.')
                    )
                );
            }
        }
    }
});
