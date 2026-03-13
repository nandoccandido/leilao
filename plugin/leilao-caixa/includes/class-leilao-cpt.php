<?php
/**
 * Custom Post Types: Imóvel
 */

defined('ABSPATH') || exit;

class Leilao_CPT {

    public static function init() {
        add_action('init', [__CLASS__, 'register']);
    }

    public static function register() {
        // CPT: Imóvel (lote de leilão)
        register_post_type('imovel', [
            'labels' => [
                'name'               => 'Imóveis',
                'singular_name'      => 'Imóvel',
                'add_new'            => 'Adicionar Imóvel',
                'add_new_item'       => 'Adicionar Novo Imóvel',
                'edit_item'          => 'Editar Imóvel',
                'new_item'           => 'Novo Imóvel',
                'view_item'          => 'Ver Imóvel',
                'search_items'       => 'Buscar Imóveis',
                'not_found'          => 'Nenhum imóvel encontrado',
                'not_found_in_trash' => 'Nenhum imóvel na lixeira',
                'menu_name'          => 'Imóveis',
            ],
            'public'             => true,
            'has_archive'        => true,
            'rewrite'            => ['slug' => 'imoveis'],
            'supports'           => ['title', 'editor', 'thumbnail', 'excerpt'],
            'menu_icon'          => 'dashicons-building',
            'show_in_rest'       => true,
            'capability_type'    => 'post',
        ]);

        // Taxonomia: Tipo de Imóvel
        register_taxonomy('tipo_imovel', 'imovel', [
            'labels' => [
                'name'          => 'Tipos de Imóvel',
                'singular_name' => 'Tipo de Imóvel',
                'add_new_item'  => 'Adicionar Tipo',
                'search_items'  => 'Buscar Tipos',
            ],
            'hierarchical' => true,
            'show_in_rest'  => true,
            'rewrite'       => ['slug' => 'tipo-imovel'],
        ]);

        // Taxonomia: Estado
        register_taxonomy('estado_imovel', 'imovel', [
            'labels' => [
                'name'          => 'Estados',
                'singular_name' => 'Estado',
                'add_new_item'  => 'Adicionar Estado',
            ],
            'hierarchical' => true,
            'show_in_rest'  => true,
            'rewrite'       => ['slug' => 'estado'],
        ]);

        // Taxonomia: Cidade
        register_taxonomy('cidade_imovel', 'imovel', [
            'labels' => [
                'name'          => 'Cidades',
                'singular_name' => 'Cidade',
                'add_new_item'  => 'Adicionar Cidade',
            ],
            'hierarchical' => false,
            'show_in_rest'  => true,
            'rewrite'       => ['slug' => 'cidade'],
        ]);

        // Taxonomia: Modalidade de Venda
        register_taxonomy('modalidade_venda', 'imovel', [
            'labels' => [
                'name'          => 'Modalidades de Venda',
                'singular_name' => 'Modalidade',
                'add_new_item'  => 'Adicionar Modalidade',
            ],
            'hierarchical' => true,
            'show_in_rest'  => true,
            'rewrite'       => ['slug' => 'modalidade'],
        ]);

        // Termos padrão para tipo de imóvel
        $tipos = ['Apartamento', 'Casa', 'Terreno', 'Comercial', 'Rural', 'Galpão'];
        foreach ($tipos as $tipo) {
            if (!term_exists($tipo, 'tipo_imovel')) {
                wp_insert_term($tipo, 'tipo_imovel');
            }
        }

        // Termos padrão para modalidade
        $modalidades = ['1º Leilão', '2º Leilão', 'Venda Direta', 'Licitação Aberta', 'Licitação Fechada'];
        foreach ($modalidades as $mod) {
            if (!term_exists($mod, 'modalidade_venda')) {
                wp_insert_term($mod, 'modalidade_venda');
            }
        }
    }

    /**
     * Meta fields do imóvel
     */
    public static function get_meta_fields(): array {
        return [
            '_leilao_status'          => 'Status do Leilão',       // ativo, encerrado, cancelado, agendado
            '_leilao_inicio'          => 'Início do Leilão',       // datetime
            '_leilao_fim'             => 'Fim do Leilão',          // datetime
            '_leilao_valor_avaliacao' => 'Valor de Avaliação',     // decimal
            '_leilao_valor_minimo'    => 'Valor Mínimo (Lance)',   // decimal
            '_leilao_incremento'      => 'Incremento Mínimo',      // decimal
            '_leilao_vencedor_id'     => 'ID do Vencedor',         // int
            '_leilao_valor_final'     => 'Valor Final',            // decimal
            '_imovel_endereco'        => 'Endereço',
            '_imovel_bairro'          => 'Bairro',
            '_imovel_cep'             => 'CEP',
            '_imovel_area_total'      => 'Área Total (m²)',
            '_imovel_area_privativa'  => 'Área Privativa (m²)',
            '_imovel_quartos'         => 'Quartos',
            '_imovel_garagem'         => 'Vagas Garagem',
            '_imovel_matricula'       => 'Matrícula',
            '_imovel_edital'          => 'Edital (URL)',
            '_imovel_fotos_extra'     => 'Fotos Extras (JSON)',
            '_imovel_caixa_id'        => 'ID Caixa',              // ID original no site da Caixa
            '_imovel_latitude'        => 'Latitude',
            '_imovel_longitude'       => 'Longitude',
        ];
    }
}
