<?php
/**
 * REST API endpoints para o sistema de leilão
 */

defined('ABSPATH') || exit;

class Leilao_Rest_API {

    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes() {
        $namespace = 'leilao/v1';

        // Listar imóveis em leilão
        register_rest_route($namespace, '/imoveis', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_imoveis'],
            'permission_callback' => '__return_true',
            'args'                => [
                'estado'     => ['type' => 'string'],
                'cidade'     => ['type' => 'string'],
                'tipo'       => ['type' => 'string'],
                'modalidade' => ['type' => 'string'],
                'status'     => ['type' => 'string', 'default' => 'ativo'],
                'page'       => ['type' => 'integer', 'default' => 1],
                'per_page'   => ['type' => 'integer', 'default' => 12],
                'orderby'    => ['type' => 'string', 'default' => 'date'],
                'order'      => ['type' => 'string', 'default' => 'DESC'],
            ],
        ]);

        // Detalhe de um imóvel
        register_rest_route($namespace, '/imoveis/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_imovel'],
            'permission_callback' => '__return_true',
        ]);

        // Lances de um imóvel
        register_rest_route($namespace, '/imoveis/(?P<id>\d+)/lances', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_lances'],
            'permission_callback' => '__return_true',
        ]);

        // Dar lance
        register_rest_route($namespace, '/imoveis/(?P<id>\d+)/lance', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'post_lance'],
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ]);

        // Painel do arrematante
        register_rest_route($namespace, '/meus-lances', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_meus_lances'],
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ]);

        // Filtros disponíveis
        register_rest_route($namespace, '/filtros', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_filtros'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * GET /leilao/v1/imoveis
     */
    public static function get_imoveis(WP_REST_Request $request): WP_REST_Response {
        $args = [
            'post_type'      => 'imovel',
            'post_status'    => 'publish',
            'posts_per_page' => $request->get_param('per_page'),
            'paged'          => $request->get_param('page'),
            'orderby'        => $request->get_param('orderby'),
            'order'          => $request->get_param('order'),
        ];

        $meta_query = [];
        $tax_query  = [];

        $status = $request->get_param('status');
        if ($status) {
            $meta_query[] = [
                'key'   => '_leilao_status',
                'value' => $status,
            ];
        }

        $estado = $request->get_param('estado');
        if ($estado) {
            $tax_query[] = [
                'taxonomy' => 'estado_imovel',
                'field'    => 'slug',
                'terms'    => $estado,
            ];
        }

        $cidade = $request->get_param('cidade');
        if ($cidade) {
            $tax_query[] = [
                'taxonomy' => 'cidade_imovel',
                'field'    => 'slug',
                'terms'    => $cidade,
            ];
        }

        $tipo = $request->get_param('tipo');
        if ($tipo) {
            $tax_query[] = [
                'taxonomy' => 'tipo_imovel',
                'field'    => 'slug',
                'terms'    => $tipo,
            ];
        }

        $modalidade = $request->get_param('modalidade');
        if ($modalidade) {
            $tax_query[] = [
                'taxonomy' => 'modalidade_venda',
                'field'    => 'slug',
                'terms'    => $modalidade,
            ];
        }

        if (!empty($meta_query)) {
            $args['meta_query'] = $meta_query;
        }
        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }

        $query = new WP_Query($args);
        $items = [];

        foreach ($query->posts as $post) {
            $items[] = self::format_imovel($post);
        }

        return new WP_REST_Response([
            'items'       => $items,
            'total'       => $query->found_posts,
            'total_pages' => $query->max_num_pages,
            'page'        => (int) $request->get_param('page'),
        ], 200);
    }

    /**
     * GET /leilao/v1/imoveis/{id}
     */
    public static function get_imovel(WP_REST_Request $request): WP_REST_Response {
        $post = get_post($request->get_param('id'));

        if (!$post || $post->post_type !== 'imovel') {
            return new WP_REST_Response(['message' => 'Imóvel não encontrado.'], 404);
        }

        $data = self::format_imovel($post, true);

        return new WP_REST_Response($data, 200);
    }

    /**
     * GET /leilao/v1/imoveis/{id}/lances
     */
    public static function get_lances(WP_REST_Request $request): WP_REST_Response {
        $imovel_id = (int) $request->get_param('id');
        $lances = Leilao_Bidding::get_lances($imovel_id, 50);
        $maior  = Leilao_Bidding::get_maior_lance($imovel_id);

        return new WP_REST_Response([
            'lances'       => $lances,
            'maior_lance'  => $maior,
            'total_lances' => (int) get_post_meta($imovel_id, '_leilao_total_lances', true),
        ], 200);
    }

    /**
     * POST /leilao/v1/imoveis/{id}/lance
     */
    public static function post_lance(WP_REST_Request $request): WP_REST_Response {
        $imovel_id = (int) $request->get_param('id');
        $valor     = floatval($request->get_param('valor'));
        $user_id   = get_current_user_id();

        $result = Leilao_Bidding::registrar_lance($imovel_id, $user_id, $valor, 'manual');

        if (is_wp_error($result)) {
            return new WP_REST_Response([
                'message' => $result->get_error_message(),
            ], 400);
        }

        Leilao_Bidding::processar_auto_lances($imovel_id, $user_id);

        return new WP_REST_Response([
            'message'     => 'Lance registrado!',
            'lance'       => $result,
            'maior_lance' => Leilao_Bidding::get_maior_lance($imovel_id),
        ], 200);
    }

    /**
     * GET /leilao/v1/meus-lances
     */
    public static function get_meus_lances(): WP_REST_Response {
        global $wpdb;
        $user_id = get_current_user_id();
        $table   = $wpdb->prefix . 'leilao_lances';

        $lances = $wpdb->get_results($wpdb->prepare(
            "SELECT l.imovel_id, MAX(l.valor) as meu_maior_lance, COUNT(*) as total_lances,
                    p.post_title as titulo
             FROM {$table} l
             JOIN {$wpdb->posts} p ON p.ID = l.imovel_id
             WHERE l.user_id = %d
             GROUP BY l.imovel_id
             ORDER BY MAX(l.criado_em) DESC",
            $user_id
        ), ARRAY_A);

        foreach ($lances as &$lance) {
            $maior = Leilao_Bidding::get_maior_lance($lance['imovel_id']);
            $lance['status'] = get_post_meta($lance['imovel_id'], '_leilao_status', true);
            $lance['esta_ganhando'] = $maior && (int)$maior['user_id'] === $user_id;
            $lance['maior_lance'] = $maior ? $maior['valor'] : 0;
            $lance['url'] = get_permalink($lance['imovel_id']);
            $lance['thumb'] = get_the_post_thumbnail_url($lance['imovel_id'], 'medium');
        }

        return new WP_REST_Response($lances, 200);
    }

    /**
     * GET /leilao/v1/filtros
     */
    public static function get_filtros(): WP_REST_Response {
        $get_terms = function ($taxonomy) {
            return array_map(function ($term) {
                return [
                    'id'    => $term->term_id,
                    'name'  => $term->name,
                    'slug'  => $term->slug,
                    'count' => $term->count,
                ];
            }, get_terms(['taxonomy' => $taxonomy, 'hide_empty' => true]));
        };

        return new WP_REST_Response([
            'estados'     => $get_terms('estado_imovel'),
            'cidades'     => $get_terms('cidade_imovel'),
            'tipos'       => $get_terms('tipo_imovel'),
            'modalidades' => $get_terms('modalidade_venda'),
        ], 200);
    }

    /**
     * Formatar dados do imóvel para a API
     */
    private static function format_imovel(WP_Post $post, bool $full = false): array {
        $meta = get_post_meta($post->ID);
        $maior = Leilao_Bidding::get_maior_lance($post->ID);

        $data = [
            'id'               => $post->ID,
            'titulo'           => $post->post_title,
            'slug'             => $post->post_name,
            'url'              => get_permalink($post->ID),
            'thumb'            => get_the_post_thumbnail_url($post->ID, 'medium_large') ?: '',
            'status'           => $meta['_leilao_status'][0] ?? 'agendado',
            'inicio'           => $meta['_leilao_inicio'][0] ?? '',
            'fim'              => $meta['_leilao_fim'][0] ?? '',
            'valor_avaliacao'  => floatval($meta['_leilao_valor_avaliacao'][0] ?? 0),
            'valor_minimo'     => floatval($meta['_leilao_valor_minimo'][0] ?? 0),
            'incremento'       => floatval($meta['_leilao_incremento'][0] ?? 500),
            'maior_lance'      => $maior ? floatval($maior['valor']) : 0,
            'total_lances'     => (int)($meta['_leilao_total_lances'][0] ?? 0),
            'endereco'         => $meta['_imovel_endereco'][0] ?? '',
            'bairro'           => $meta['_imovel_bairro'][0] ?? '',
            'area_total'       => $meta['_imovel_area_total'][0] ?? '',
            'quartos'          => $meta['_imovel_quartos'][0] ?? '',
            'garagem'          => $meta['_imovel_garagem'][0] ?? '',
            'tipo'             => wp_get_post_terms($post->ID, 'tipo_imovel', ['fields' => 'names'])[0] ?? '',
            'estado'           => wp_get_post_terms($post->ID, 'estado_imovel', ['fields' => 'names'])[0] ?? '',
            'cidade'           => wp_get_post_terms($post->ID, 'cidade_imovel', ['fields' => 'names'])[0] ?? '',
            'modalidade'       => wp_get_post_terms($post->ID, 'modalidade_venda', ['fields' => 'names'])[0] ?? '',
        ];

        if ($full) {
            $data['conteudo']       = apply_filters('the_content', $post->post_content);
            $data['excerpt']        = $post->post_excerpt;
            $data['cep']            = $meta['_imovel_cep'][0] ?? '';
            $data['area_privativa'] = $meta['_imovel_area_privativa'][0] ?? '';
            $data['matricula']      = $meta['_imovel_matricula'][0] ?? '';
            $data['edital']         = $meta['_imovel_edital'][0] ?? '';
            $data['latitude']       = $meta['_imovel_latitude'][0] ?? '';
            $data['longitude']      = $meta['_imovel_longitude'][0] ?? '';
            $data['caixa_id']       = $meta['_imovel_caixa_id'][0] ?? '';
            $data['valor_final']    = floatval($meta['_leilao_valor_final'][0] ?? 0);
            $data['vencedor_id']    = (int)($meta['_leilao_vencedor_id'][0] ?? 0);
            $data['fotos']          = json_decode($meta['_imovel_fotos_extra'][0] ?? '[]', true);

            // Galeria de imagens do post
            $gallery = get_attached_media('image', $post->ID);
            $data['galeria'] = array_map(function ($img) {
                return [
                    'id'     => $img->ID,
                    'url'    => wp_get_attachment_url($img->ID),
                    'thumb'  => wp_get_attachment_image_url($img->ID, 'thumbnail'),
                    'medium' => wp_get_attachment_image_url($img->ID, 'medium_large'),
                ];
            }, array_values($gallery));
        }

        return $data;
    }
}
