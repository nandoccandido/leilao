<?php
/**
 * Leilão SaySix Theme Functions
 */

defined('ABSPATH') || exit;

/**
 * Theme setup
 */
add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'comment-form', 'gallery', 'caption']);
    add_theme_support('woocommerce');

    register_nav_menus([
        'primary' => 'Menu Principal',
        'footer'  => 'Menu Rodapé',
    ]);
});

/**
 * Enqueue styles & fonts
 */
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('google-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap', [], null);
    wp_enqueue_style('leilao-theme', get_stylesheet_uri(), ['google-fonts'], '1.1.0');

    // IA Triage JS apenas na front page
    if (is_front_page()) {
        wp_enqueue_script('leilao-ai-triage', get_template_directory_uri() . '/assets/js/ai-triage.js', [], '1.1.0', true);
        wp_localize_script('leilao-ai-triage', 'leilaoAI', [
            'ajaxUrl'     => admin_url('admin-ajax.php'),
            'restUrl'     => esc_url_raw(rest_url()),
            'catalogoUrl' => home_url('/catalogo/'),
            'nonce'       => wp_create_nonce('wp_rest'),
        ]);
    }

    // Modal de Contato JS — front page + single imóvel
    if (is_front_page() || is_singular('imovel') || is_post_type_archive('imovel')) {
        wp_enqueue_script('leilao-contato-modal', get_template_directory_uri() . '/assets/js/contato-modal.js', [], '1.0.0', true);
        wp_localize_script('leilao-contato-modal', 'leilaoContato', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
        ]);
    }
});

/**
 * Remove admin bar for arrematantes
 */
add_action('after_setup_theme', function () {
    if (current_user_can('arrematante') || !current_user_can('edit_posts')) {
        show_admin_bar(false);
    }
});

/**
 * Redirect arrematantes away from wp-admin
 */
add_action('admin_init', function () {
    if (current_user_can('arrematante') && !wp_doing_ajax()) {
        wp_redirect(home_url('/painel-arrematante/'));
        exit;
    }
});

/**
 * Custom page title
 */
add_filter('wp_title', function ($title) {
    if (is_front_page()) return 'Qatar Leilões - Imóveis da Caixa';
    return $title . ' | Qatar Leilões';
}, 10);

/**
 * Leilão stats helper
 */
function leilao_get_stats(): array {
    global $wpdb;
    $table = $wpdb->prefix . 'leilao_lances';

    return [
        'imoveis' => wp_count_posts('imovel')->publish ?? 0,
        'lances'  => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}"),
        'ativos'  => count(get_posts([
            'post_type'      => 'imovel',
            'posts_per_page' => -1,
            'meta_key'       => '_leilao_status',
            'meta_value'     => 'ativo',
        ])),
        'users'   => count(get_users(['role' => 'arrematante'])),
    ];
}
