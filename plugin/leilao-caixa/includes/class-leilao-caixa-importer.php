<?php
/**
 * Importador de imóveis da Caixa Econômica Federal
 * Scraper do site venda-imoveis.caixa.gov.br
 */

defined('ABSPATH') || exit;

class Leilao_Caixa_Importer {

    const CAIXA_BASE_URL = 'https://venda-imoveis.caixa.gov.br';
    const CAIXA_LIST_URL = 'https://venda-imoveis.caixa.gov.br/listaweb/Lista_imoveis_%s.htm';

    public static function init() {
        add_action('wp_ajax_leilao_importar_caixa', [__CLASS__, 'ajax_importar']);
        add_action('wp_ajax_leilao_importar_manual', [__CLASS__, 'ajax_importar_manual']);
        add_action('leilao_cron_importar_caixa', [__CLASS__, 'cron_importar']);
    }

    /**
     * AJAX: Importar imóveis por estado
     */
    public static function ajax_importar() {
        check_ajax_referer('leilao_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Sem permissão.']);
        }

        $estado = sanitize_text_field($_POST['estado'] ?? '');
        if (!$estado) {
            wp_send_json_error(['message' => 'Informe o estado.']);
        }

        $result = self::importar_estado($estado);
        wp_send_json_success($result);
    }

    /**
     * AJAX: Importar imóvel manualmente
     */
    public static function ajax_importar_manual() {
        check_ajax_referer('leilao_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Sem permissão.']);
        }

        $data = [
            'titulo'          => sanitize_text_field($_POST['titulo'] ?? ''),
            'descricao'       => wp_kses_post($_POST['descricao'] ?? ''),
            'endereco'        => sanitize_text_field($_POST['endereco'] ?? ''),
            'bairro'          => sanitize_text_field($_POST['bairro'] ?? ''),
            'cidade'          => sanitize_text_field($_POST['cidade'] ?? ''),
            'estado'          => sanitize_text_field($_POST['estado'] ?? ''),
            'cep'             => sanitize_text_field($_POST['cep'] ?? ''),
            'tipo'            => sanitize_text_field($_POST['tipo'] ?? ''),
            'modalidade'      => sanitize_text_field($_POST['modalidade'] ?? ''),
            'valor_avaliacao' => floatval($_POST['valor_avaliacao'] ?? 0),
            'valor_minimo'    => floatval($_POST['valor_minimo'] ?? 0),
            'incremento'      => floatval($_POST['incremento'] ?? 500),
            'area_total'      => sanitize_text_field($_POST['area_total'] ?? ''),
            'area_privativa'  => sanitize_text_field($_POST['area_privativa'] ?? ''),
            'quartos'         => sanitize_text_field($_POST['quartos'] ?? ''),
            'garagem'         => sanitize_text_field($_POST['garagem'] ?? ''),
            'matricula'       => sanitize_text_field($_POST['matricula'] ?? ''),
            'edital'          => esc_url_raw($_POST['edital'] ?? ''),
            'inicio'          => sanitize_text_field($_POST['inicio'] ?? ''),
            'fim'             => sanitize_text_field($_POST['fim'] ?? ''),
        ];

        $post_id = self::criar_imovel($data);

        if (is_wp_error($post_id)) {
            wp_send_json_error(['message' => $post_id->get_error_message()]);
        }

        wp_send_json_success([
            'message'  => 'Imóvel cadastrado com sucesso!',
            'post_id'  => $post_id,
            'edit_url' => get_edit_post_link($post_id, 'raw'),
        ]);
    }

    /**
     * Importar imóveis de um estado via scraping da Caixa
     */
    public static function importar_estado(string $uf): array {
        $codigos_uf = [
            'AC' => '12', 'AL' => '27', 'AP' => '16', 'AM' => '13', 'BA' => '29',
            'CE' => '23', 'DF' => '53', 'ES' => '32', 'GO' => '52', 'MA' => '21',
            'MT' => '51', 'MS' => '50', 'MG' => '31', 'PA' => '15', 'PB' => '25',
            'PR' => '41', 'PE' => '26', 'PI' => '22', 'RJ' => '33', 'RN' => '24',
            'RS' => '43', 'RO' => '11', 'RR' => '14', 'SC' => '42', 'SP' => '35',
            'SE' => '28', 'TO' => '17',
        ];

        $uf = strtoupper($uf);
        $codigo = $codigos_uf[$uf] ?? null;
        if (!$codigo) {
            return ['importados' => 0, 'erros' => 0, 'message' => 'UF inválida.'];
        }

        $url = sprintf(self::CAIXA_LIST_URL, $codigo);

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ],
        ]);

        if (is_wp_error($response)) {
            return ['importados' => 0, 'erros' => 0, 'message' => 'Erro ao acessar Caixa: ' . $response->get_error_message()];
        }

        $body = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);

        if ($code !== 200 || empty($body)) {
            return ['importados' => 0, 'erros' => 0, 'message' => "Caixa retornou código {$code}. Dados podem estar indisponíveis."];
        }

        $imoveis = self::parse_lista_caixa($body, $uf);

        $importados = 0;
        $erros = 0;

        foreach ($imoveis as $imovel) {
            $result = self::criar_imovel($imovel);
            if (is_wp_error($result)) {
                $erros++;
            } else {
                $importados++;
            }
        }

        return [
            'importados' => $importados,
            'erros'      => $erros,
            'total'      => count($imoveis),
            'message'    => "Importação concluída: {$importados} imóveis importados, {$erros} erros.",
        ];
    }

    /**
     * Parse HTML da lista da Caixa
     */
    private static function parse_lista_caixa(string $html, string $uf): array {
        $imoveis = [];

        if (!class_exists('DOMDocument')) {
            return $imoveis;
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        // Tentar parsear tabela ou lista (formato varia)
        $rows = $xpath->query('//table//tr[position()>1]');

        if ($rows->length === 0) {
            // Tentar formato alternativo com links
            $links = $xpath->query('//a[contains(@href, "detalhe-imovel")]');
            foreach ($links as $link) {
                $href = $link->getAttribute('href');
                $text = trim($link->textContent);
                if ($text) {
                    $imoveis[] = [
                        'titulo'   => $text,
                        'estado'   => $uf,
                        'caixa_id' => self::extract_caixa_id($href),
                    ];
                }
            }
        } else {
            foreach ($rows as $row) {
                $cells = $row->getElementsByTagName('td');
                if ($cells->length >= 5) {
                    $imoveis[] = [
                        'titulo'          => trim($cells->item(1)->textContent ?? ''),
                        'endereco'        => trim($cells->item(2)->textContent ?? ''),
                        'cidade'          => trim($cells->item(3)->textContent ?? ''),
                        'estado'          => $uf,
                        'valor_avaliacao' => self::parse_valor(trim($cells->item(4)->textContent ?? '')),
                        'valor_minimo'    => self::parse_valor(trim($cells->item(5)->textContent ?? '')),
                    ];
                }
            }
        }

        return $imoveis;
    }

    /**
     * Extrair ID do imóvel da URL da Caixa
     */
    private static function extract_caixa_id(string $url): string {
        preg_match('/hdnimovel=(\d+)/i', $url, $m);
        return $m[1] ?? '';
    }

    /**
     * Parse valor monetário brasileiro
     */
    private static function parse_valor(string $valor): float {
        $valor = preg_replace('/[^\d,.]/', '', $valor);
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
        return floatval($valor);
    }

    /**
     * Criar ou atualizar imóvel no WP
     */
    public static function criar_imovel(array $data): int|WP_Error {
        $titulo = $data['titulo'] ?? '';
        if (!$titulo) {
            return new WP_Error('sem_titulo', 'Título é obrigatório.');
        }

        // Verificar se já existe (pelo caixa_id ou título)
        $caixa_id = $data['caixa_id'] ?? '';
        if ($caixa_id) {
            $existing = get_posts([
                'post_type'   => 'imovel',
                'meta_key'    => '_imovel_caixa_id',
                'meta_value'  => $caixa_id,
                'numberposts' => 1,
            ]);
            if (!empty($existing)) {
                return $existing[0]->ID; // Já existe
            }
        }

        $post_id = wp_insert_post([
            'post_type'    => 'imovel',
            'post_title'   => $titulo,
            'post_content' => $data['descricao'] ?? '',
            'post_status'  => 'publish',
        ]);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Meta fields
        $meta_map = [
            '_imovel_endereco'        => 'endereco',
            '_imovel_bairro'          => 'bairro',
            '_imovel_cep'             => 'cep',
            '_imovel_area_total'      => 'area_total',
            '_imovel_area_privativa'  => 'area_privativa',
            '_imovel_quartos'         => 'quartos',
            '_imovel_garagem'         => 'garagem',
            '_imovel_matricula'       => 'matricula',
            '_imovel_edital'          => 'edital',
            '_imovel_caixa_id'        => 'caixa_id',
            '_leilao_valor_avaliacao' => 'valor_avaliacao',
            '_leilao_valor_minimo'    => 'valor_minimo',
            '_leilao_incremento'      => 'incremento',
            '_leilao_inicio'          => 'inicio',
            '_leilao_fim'             => 'fim',
        ];

        foreach ($meta_map as $meta_key => $data_key) {
            if (!empty($data[$data_key])) {
                update_post_meta($post_id, $meta_key, $data[$data_key]);
            }
        }

        // Status padrão
        $status = 'agendado';
        if (!empty($data['inicio']) && !empty($data['fim'])) {
            $agora = current_time('mysql');
            if ($agora >= $data['inicio'] && $agora <= $data['fim']) {
                $status = 'ativo';
            } elseif ($agora > $data['fim']) {
                $status = 'encerrado';
            }
        }
        update_post_meta($post_id, '_leilao_status', $status);

        // Taxonomias
        if (!empty($data['tipo'])) {
            wp_set_object_terms($post_id, $data['tipo'], 'tipo_imovel');
        }
        if (!empty($data['estado'])) {
            wp_set_object_terms($post_id, $data['estado'], 'estado_imovel');
        }
        if (!empty($data['cidade'])) {
            wp_set_object_terms($post_id, $data['cidade'], 'cidade_imovel');
        }
        if (!empty($data['modalidade'])) {
            wp_set_object_terms($post_id, $data['modalidade'], 'modalidade_venda');
        }

        return $post_id;
    }

    /**
     * Cron de importação automática
     */
    public static function cron_importar() {
        $estados = get_option('leilao_caixa_estados_importar', ['SP', 'RJ', 'MG']);
        foreach ($estados as $uf) {
            self::importar_estado($uf);
            sleep(2); // Rate limiting
        }
    }
}
