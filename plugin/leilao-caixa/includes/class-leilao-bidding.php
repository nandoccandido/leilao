<?php
/**
 * Sistema de Lances (Bidding Engine)
 */

defined('ABSPATH') || exit;

class Leilao_Bidding {

    public static function init() {
        add_action('wp_ajax_leilao_dar_lance', [__CLASS__, 'ajax_dar_lance']);
        add_action('wp_ajax_leilao_get_lances', [__CLASS__, 'ajax_get_lances']);
        add_action('wp_ajax_nopriv_leilao_get_lances', [__CLASS__, 'ajax_get_lances']);
        add_action('wp_ajax_leilao_auto_lance', [__CLASS__, 'ajax_configurar_auto_lance']);
        add_action('wp_ajax_leilao_cancelar_auto_lance', [__CLASS__, 'ajax_cancelar_auto_lance']);
    }

    /**
     * Dar lance manual
     */
    public static function ajax_dar_lance() {
        check_ajax_referer('leilao_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Faça login para dar lances.']);
        }

        $user_id   = get_current_user_id();
        $imovel_id = absint($_POST['imovel_id'] ?? 0);
        $valor     = floatval($_POST['valor'] ?? 0);

        if (!$imovel_id || !$valor) {
            wp_send_json_error(['message' => 'Dados inválidos.']);
        }

        $result = self::registrar_lance($imovel_id, $user_id, $valor, 'manual');

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        // Processar lances automáticos de outros participantes
        self::processar_auto_lances($imovel_id, $user_id);

        wp_send_json_success([
            'message'    => 'Lance registrado com sucesso!',
            'lance'      => $result,
            'maior_lance' => self::get_maior_lance($imovel_id),
        ]);
    }

    /**
     * Registrar lance no banco
     */
    public static function registrar_lance(int $imovel_id, int $user_id, float $valor, string $tipo = 'manual') {
        global $wpdb;

        // Verificar se leilão está ativo
        $status = get_post_meta($imovel_id, '_leilao_status', true);
        if ($status !== 'ativo') {
            return new WP_Error('leilao_encerrado', 'Este leilão não está mais ativo.');
        }

        // Verificar se dentro do período
        $inicio = get_post_meta($imovel_id, '_leilao_inicio', true);
        $fim    = get_post_meta($imovel_id, '_leilao_fim', true);
        $agora  = current_time('mysql');

        if ($inicio && $agora < $inicio) {
            return new WP_Error('leilao_nao_iniciado', 'O leilão ainda não começou.');
        }
        if ($fim && $agora > $fim) {
            return new WP_Error('leilao_encerrado', 'O leilão já foi encerrado.');
        }

        // Verificar valor mínimo
        $valor_minimo = floatval(get_post_meta($imovel_id, '_leilao_valor_minimo', true));
        $incremento   = floatval(get_post_meta($imovel_id, '_leilao_incremento', true)) ?: 500;

        $maior = self::get_maior_lance($imovel_id);
        $lance_minimo = $maior ? ($maior['valor'] + $incremento) : $valor_minimo;

        if ($valor < $lance_minimo) {
            return new WP_Error(
                'valor_insuficiente',
                sprintf('O lance mínimo é R$ %s', number_format($lance_minimo, 2, ',', '.'))
            );
        }

        // Verificar se o usuário não é o último lance (não pode dar lance sobre si mesmo)
        if ($maior && (int)$maior['user_id'] === $user_id && $tipo === 'manual') {
            return new WP_Error('lance_proprio', 'Você já tem o maior lance. Aguarde outro participante.');
        }

        $table = $wpdb->prefix . 'leilao_lances';
        $wpdb->insert($table, [
            'imovel_id' => $imovel_id,
            'user_id'   => $user_id,
            'valor'     => $valor,
            'tipo'      => $tipo,
            'ip'        => $_SERVER['REMOTE_ADDR'] ?? '',
        ], ['%d', '%d', '%f', '%s', '%s']);

        $lance_id = $wpdb->insert_id;

        // Estender leilão se menos de 3 minutos
        if ($fim) {
            $diff = strtotime($fim) - strtotime($agora);
            if ($diff < 180 && $diff > 0) {
                $novo_fim = date('Y-m-d H:i:s', strtotime($agora) + 180);
                update_post_meta($imovel_id, '_leilao_fim', $novo_fim);
            }
        }

        // Atualizar contagem de lances
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE imovel_id = %d",
            $imovel_id
        ));
        update_post_meta($imovel_id, '_leilao_total_lances', $total);

        return [
            'id'       => $lance_id,
            'valor'    => $valor,
            'user_id'  => $user_id,
            'tipo'     => $tipo,
            'data'     => current_time('mysql'),
        ];
    }

    /**
     * Processar lances automáticos após lance manual
     */
    public static function processar_auto_lances(int $imovel_id, int $lance_user_id) {
        global $wpdb;

        $table_auto = $wpdb->prefix . 'leilao_auto_lances';
        $incremento = floatval(get_post_meta($imovel_id, '_leilao_incremento', true)) ?: 500;

        // Pegar todos os auto-lances ativos desse imóvel (exceto do usuário que acabou de dar lance)
        $auto_lances = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_auto}
             WHERE imovel_id = %d AND user_id != %d AND ativo = 1
             ORDER BY valor_maximo DESC",
            $imovel_id,
            $lance_user_id
        ));

        if (empty($auto_lances)) return;

        $maior = self::get_maior_lance($imovel_id);
        if (!$maior) return;

        foreach ($auto_lances as $auto) {
            $valor_novo = $maior['valor'] + ($auto->incremento ?: $incremento);

            if ($valor_novo <= $auto->valor_maximo) {
                $result = self::registrar_lance($imovel_id, $auto->user_id, $valor_novo, 'automatico');

                if (!is_wp_error($result)) {
                    // Auto lance executado, atualizar maior
                    $maior = self::get_maior_lance($imovel_id);

                    // Processar auto-lances recursivos (se lance_user_id também tem auto)
                    $auto_lance_user = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$table_auto}
                         WHERE imovel_id = %d AND user_id = %d AND ativo = 1",
                        $imovel_id,
                        $lance_user_id
                    ));

                    if ($auto_lance_user) {
                        $valor_contra = $maior['valor'] + ($auto_lance_user->incremento ?: $incremento);
                        if ($valor_contra <= $auto_lance_user->valor_maximo) {
                            self::registrar_lance($imovel_id, $lance_user_id, $valor_contra, 'automatico');
                            // Recursão limitada: chamar novamente
                            self::processar_auto_lances($imovel_id, $lance_user_id);
                        }
                    }
                } else {
                    // Desativar auto-lance se falhou
                    $wpdb->update($table_auto, ['ativo' => 0], ['id' => $auto->id]);
                }

                break; // Apenas o maior auto-lance compete
            } else {
                // Valor máximo atingido, desativar
                $wpdb->update($table_auto, ['ativo' => 0], ['id' => $auto->id]);
            }
        }
    }

    /**
     * Obter maior lance de um imóvel
     */
    public static function get_maior_lance(int $imovel_id): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'leilao_lances';

        $lance = $wpdb->get_row($wpdb->prepare(
            "SELECT l.*, u.display_name as nome_usuario
             FROM {$table} l
             JOIN {$wpdb->users} u ON u.ID = l.user_id
             WHERE l.imovel_id = %d
             ORDER BY l.valor DESC
             LIMIT 1",
            $imovel_id
        ), ARRAY_A);

        return $lance ?: null;
    }

    /**
     * Obter últimos lances de um imóvel
     */
    public static function get_lances(int $imovel_id, int $limit = 20): array {
        global $wpdb;
        $table = $wpdb->prefix . 'leilao_lances';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT l.id, l.valor, l.tipo, l.criado_em,
                    CONCAT(LEFT(u.display_name, 3), '***') as nome_anonimo,
                    l.user_id
             FROM {$table} l
             JOIN {$wpdb->users} u ON u.ID = l.user_id
             WHERE l.imovel_id = %d
             ORDER BY l.criado_em DESC
             LIMIT %d",
            $imovel_id,
            $limit
        ), ARRAY_A);
    }

    /**
     * AJAX: Obter lances (polling)
     */
    public static function ajax_get_lances() {
        $imovel_id = absint($_GET['imovel_id'] ?? $_POST['imovel_id'] ?? 0);
        if (!$imovel_id) {
            wp_send_json_error(['message' => 'ID inválido.']);
        }

        $lances = self::get_lances($imovel_id);
        $maior  = self::get_maior_lance($imovel_id);
        $status = get_post_meta($imovel_id, '_leilao_status', true);
        $fim    = get_post_meta($imovel_id, '_leilao_fim', true);
        $total  = get_post_meta($imovel_id, '_leilao_total_lances', true) ?: 0;

        wp_send_json_success([
            'lances'      => $lances,
            'maior_lance' => $maior,
            'status'      => $status ?: 'ativo',
            'fim'         => $fim,
            'total_lances' => (int) $total,
        ]);
    }

    /**
     * AJAX: Configurar lance automático
     */
    public static function ajax_configurar_auto_lance() {
        check_ajax_referer('leilao_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Faça login.']);
        }

        global $wpdb;
        $user_id      = get_current_user_id();
        $imovel_id    = absint($_POST['imovel_id'] ?? 0);
        $valor_maximo = floatval($_POST['valor_maximo'] ?? 0);
        $incremento   = floatval($_POST['incremento'] ?? 500);

        if (!$imovel_id || $valor_maximo <= 0) {
            wp_send_json_error(['message' => 'Dados inválidos.']);
        }

        $table = $wpdb->prefix . 'leilao_auto_lances';

        // Desativar auto-lance anterior
        $wpdb->update($table, ['ativo' => 0], [
            'imovel_id' => $imovel_id,
            'user_id'   => $user_id,
        ]);

        // Inserir novo
        $wpdb->insert($table, [
            'imovel_id'    => $imovel_id,
            'user_id'      => $user_id,
            'valor_maximo' => $valor_maximo,
            'incremento'   => $incremento,
            'ativo'        => 1,
        ]);

        wp_send_json_success(['message' => 'Lance automático configurado!']);
    }

    /**
     * AJAX: Cancelar lance automático
     */
    public static function ajax_cancelar_auto_lance() {
        check_ajax_referer('leilao_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Faça login.']);
        }

        global $wpdb;
        $user_id   = get_current_user_id();
        $imovel_id = absint($_POST['imovel_id'] ?? 0);

        $table = $wpdb->prefix . 'leilao_auto_lances';
        $wpdb->update($table, ['ativo' => 0], [
            'imovel_id' => $imovel_id,
            'user_id'   => $user_id,
        ]);

        wp_send_json_success(['message' => 'Lance automático cancelado.']);
    }

    /**
     * Contar lances de um usuário em um imóvel
     */
    public static function contar_lances_usuario(int $imovel_id, int $user_id): int {
        global $wpdb;
        $table = $wpdb->prefix . 'leilao_lances';
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE imovel_id = %d AND user_id = %d",
            $imovel_id,
            $user_id
        ));
    }

    /**
     * Leilões ganhos por um usuário
     */
    public static function get_leiloes_ganhos(int $user_id): array {
        return get_posts([
            'post_type'   => 'imovel',
            'meta_query'  => [
                [
                    'key'   => '_leilao_vencedor_id',
                    'value' => $user_id,
                ],
            ],
            'posts_per_page' => -1,
        ]);
    }
}
