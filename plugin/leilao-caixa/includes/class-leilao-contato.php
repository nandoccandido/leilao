<?php
/**
 * Leilao Contato — Formulário de contato por imóvel
 *
 * Registra o AJAX handler e envia e-mail ao admin quando
 * um visitante preenche o modal "CONTATAR" em qualquer imóvel.
 */

defined('ABSPATH') || exit;

class Leilao_Contato {

    public static function init(): void {
        // AJAX — logado e não-logado
        add_action('wp_ajax_leilao_contato',        [__CLASS__, 'ajax_contato']);
        add_action('wp_ajax_nopriv_leilao_contato',  [__CLASS__, 'ajax_contato']);

        // Cria tabela na ativação (chamado externamente)
        add_action('leilao_caixa_activation', [__CLASS__, 'create_table']);
    }

    /**
     * Cria tabela para armazenar contatos
     */
    public static function create_table(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $table   = $wpdb->prefix . 'leilao_contatos';

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            imovel_id BIGINT UNSIGNED NOT NULL,
            nome VARCHAR(200) NOT NULL,
            email VARCHAR(200) NOT NULL,
            telefone VARCHAR(30) DEFAULT '',
            mensagem TEXT NOT NULL,
            ip VARCHAR(45) DEFAULT '',
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_imovel (imovel_id),
            KEY idx_email (email)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * AJAX handler — recebe e processa o formulário
     */
    public static function ajax_contato(): void {
        // Sanitize
        $imovel_id = absint($_POST['imovel_id'] ?? 0);
        $nome      = sanitize_text_field($_POST['nome'] ?? '');
        $email     = sanitize_email($_POST['email'] ?? '');
        $telefone  = sanitize_text_field($_POST['telefone'] ?? '');
        $mensagem  = sanitize_textarea_field($_POST['mensagem'] ?? '');

        // Validação
        if (!$imovel_id || !$nome || !$email || !$mensagem) {
            wp_send_json_error(['msg' => 'Preencha todos os campos obrigatórios.']);
        }

        if (!is_email($email)) {
            wp_send_json_error(['msg' => 'E-mail inválido.']);
        }

        // Verificar se o imóvel existe
        $imovel = get_post($imovel_id);
        if (!$imovel || $imovel->post_type !== 'imovel') {
            wp_send_json_error(['msg' => 'Imóvel não encontrado.']);
        }

        // Rate-limit simples: max 5 mensagens do mesmo IP em 1 hora
        global $wpdb;
        $table = $wpdb->prefix . 'leilao_contatos';
        $ip    = self::get_ip();

        $recentes = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE ip = %s AND criado_em > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
            $ip
        ));

        if ($recentes >= 5) {
            wp_send_json_error(['msg' => 'Muitas mensagens enviadas. Tente novamente em alguns minutos.']);
        }

        // Salvar no banco
        $wpdb->insert($table, [
            'imovel_id' => $imovel_id,
            'nome'      => $nome,
            'email'     => $email,
            'telefone'  => $telefone,
            'mensagem'  => $mensagem,
            'ip'        => $ip,
        ], ['%d', '%s', '%s', '%s', '%s', '%s']);

        // Montar e-mail
        $titulo_imovel = $imovel->post_title;
        $link_imovel   = get_permalink($imovel_id);
        $admin_email   = get_option('admin_email');

        $assunto = "[Qatar Leilões] Novo contato sobre: {$titulo_imovel}";

        $corpo  = "Um visitante entrou em contato sobre um imóvel.\n\n";
        $corpo .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $corpo .= "IMÓVEL: {$titulo_imovel}\n";
        $corpo .= "LINK:   {$link_imovel}\n";
        $corpo .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $corpo .= "NOME:     {$nome}\n";
        $corpo .= "E-MAIL:   {$email}\n";
        $corpo .= "TELEFONE: " . ($telefone ?: '—') . "\n\n";
        $corpo .= "MENSAGEM:\n{$mensagem}\n\n";
        $corpo .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $corpo .= "Enviado em: " . wp_date('d/m/Y H:i') . "\n";
        $corpo .= "IP: {$ip}\n";

        $headers = [
            "From: Qatar Leilões <noreply@qatarleiloes.com.br>",
            "Reply-To: {$nome} <{$email}>",
        ];

        wp_mail($admin_email, $assunto, $corpo, $headers);

        wp_send_json_success([
            'msg' => 'Mensagem enviada com sucesso! Entraremos em contato em breve.',
        ]);
    }

    /**
     * Retorna IP do visitante (Cloudflare-aware)
     */
    private static function get_ip(): string {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return sanitize_text_field($_SERVER['HTTP_CF_CONNECTING_IP']);
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return sanitize_text_field(trim($ips[0]));
        }
        return sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }
}
