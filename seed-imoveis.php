<?php
/**
 * Script para criar imóveis de demonstração
 * Executar via: wp eval-file seed-imoveis.php --allow-root
 */

$imoveis = [
    [
        'titulo'  => 'Apartamento 2 Quartos - Centro de São Paulo',
        'cidade'  => 'São Paulo', 'estado' => 'SP', 'tipo' => 'Apartamento',
        'modalidade' => '1º Leilão', 'endereco' => 'Rua Augusta, 1200',
        'bairro' => 'Centro', 'area_total' => '68', 'area_priv' => '55',
        'quartos' => '2', 'garagem' => '1', 'avaliacao' => 380000, 'minimo' => 228000,
    ],
    [
        'titulo'  => 'Casa 3 Quartos com Quintal - Curitiba',
        'cidade'  => 'Curitiba', 'estado' => 'PR', 'tipo' => 'Casa',
        'modalidade' => '2º Leilão', 'endereco' => 'Rua XV de Novembro, 450',
        'bairro' => 'Batel', 'area_total' => '180', 'area_priv' => '120',
        'quartos' => '3', 'garagem' => '2', 'avaliacao' => 520000, 'minimo' => 312000,
    ],
    [
        'titulo'  => 'Apartamento Duplex - Copacabana, RJ',
        'cidade'  => 'Rio de Janeiro', 'estado' => 'RJ', 'tipo' => 'Apartamento',
        'modalidade' => '1º Leilão', 'endereco' => 'Av. Atlântica, 3100',
        'bairro' => 'Copacabana', 'area_total' => '95', 'area_priv' => '85',
        'quartos' => '3', 'garagem' => '1', 'avaliacao' => 890000, 'minimo' => 534000,
    ],
    [
        'titulo'  => 'Terreno 500m² - Florianópolis',
        'cidade'  => 'Florianópolis', 'estado' => 'SC', 'tipo' => 'Terreno',
        'modalidade' => 'Venda Direta', 'endereco' => 'Rod. SC-401, km 12',
        'bairro' => 'Jurerê', 'area_total' => '500', 'area_priv' => '',
        'quartos' => '', 'garagem' => '', 'avaliacao' => 350000, 'minimo' => 175000,
    ],
    [
        'titulo'  => 'Sala Comercial 45m² - Belo Horizonte',
        'cidade'  => 'Belo Horizonte', 'estado' => 'MG', 'tipo' => 'Comercial',
        'modalidade' => 'Licitação Aberta', 'endereco' => 'Av. Afonso Pena, 2800',
        'bairro' => 'Funcionários', 'area_total' => '45', 'area_priv' => '40',
        'quartos' => '', 'garagem' => '1', 'avaliacao' => 290000, 'minimo' => 203000,
    ],
    [
        'titulo'  => 'Casa 4 Quartos - Salvador, BA',
        'cidade'  => 'Salvador', 'estado' => 'BA', 'tipo' => 'Casa',
        'modalidade' => '1º Leilão', 'endereco' => 'Rua Chile, 120',
        'bairro' => 'Pelourinho', 'area_total' => '220', 'area_priv' => '180',
        'quartos' => '4', 'garagem' => '2', 'avaliacao' => 650000, 'minimo' => 455000,
    ],
    [
        'titulo'  => 'Apartamento Studio - Porto Alegre',
        'cidade'  => 'Porto Alegre', 'estado' => 'RS', 'tipo' => 'Apartamento',
        'modalidade' => '2º Leilão', 'endereco' => 'Rua da República, 680',
        'bairro' => 'Cidade Baixa', 'area_total' => '35', 'area_priv' => '30',
        'quartos' => '1', 'garagem' => '0', 'avaliacao' => 180000, 'minimo' => 108000,
    ],
    [
        'titulo'  => 'Galpão Industrial 800m² - Campinas',
        'cidade'  => 'Campinas', 'estado' => 'SP', 'tipo' => 'Galpão',
        'modalidade' => 'Venda Direta', 'endereco' => 'Rod. Anhanguera, km 95',
        'bairro' => 'Distrito Industrial', 'area_total' => '800', 'area_priv' => '750',
        'quartos' => '', 'garagem' => '5', 'avaliacao' => 1200000, 'minimo' => 720000,
    ],
    [
        'titulo'  => 'Casa em Condomínio - Goiânia',
        'cidade'  => 'Goiânia', 'estado' => 'GO', 'tipo' => 'Casa',
        'modalidade' => '1º Leilão', 'endereco' => 'Alameda das Rosas, 300',
        'bairro' => 'Setor Bueno', 'area_total' => '250', 'area_priv' => '200',
        'quartos' => '4', 'garagem' => '3', 'avaliacao' => 780000, 'minimo' => 546000,
    ],
    [
        'titulo'  => 'Apartamento 2 Quartos - Recife',
        'cidade'  => 'Recife', 'estado' => 'PE', 'tipo' => 'Apartamento',
        'modalidade' => '2º Leilão', 'endereco' => 'Av. Boa Viagem, 1500',
        'bairro' => 'Boa Viagem', 'area_total' => '72', 'area_priv' => '60',
        'quartos' => '2', 'garagem' => '1', 'avaliacao' => 420000, 'minimo' => 252000,
    ],
    [
        'titulo'  => 'Sítio Rural 5 Hectares - Minas Gerais',
        'cidade'  => 'Lavras', 'estado' => 'MG', 'tipo' => 'Rural',
        'modalidade' => 'Licitação Fechada', 'endereco' => 'Estrada Municipal, km 8',
        'bairro' => 'Zona Rural', 'area_total' => '50000', 'area_priv' => '',
        'quartos' => '2', 'garagem' => '', 'avaliacao' => 420000, 'minimo' => 210000,
    ],
    [
        'titulo'  => 'Apartamento 3 Quartos - Fortaleza',
        'cidade'  => 'Fortaleza', 'estado' => 'CE', 'tipo' => 'Apartamento',
        'modalidade' => '1º Leilão', 'endereco' => 'Av. Beira Mar, 2200',
        'bairro' => 'Meireles', 'area_total' => '110', 'area_priv' => '95',
        'quartos' => '3', 'garagem' => '2', 'avaliacao' => 560000, 'minimo' => 392000,
    ],
];

$now    = current_time('mysql');
$in_1h  = date('Y-m-d H:i:s', strtotime('+1 hour'));
$in_3d  = date('Y-m-d H:i:s', strtotime('+3 days'));
$in_7d  = date('Y-m-d H:i:s', strtotime('+7 days'));
$in_14d = date('Y-m-d H:i:s', strtotime('+14 days'));

$statuses = ['ativo', 'ativo', 'ativo', 'agendado', 'ativo', 'ativo', 'agendado', 'ativo', 'ativo', 'agendado', 'ativo', 'ativo'];
$fins     = [$in_3d, $in_7d, $in_14d, $in_7d, $in_3d, $in_14d, $in_7d, $in_3d, $in_14d, $in_7d, $in_3d, $in_14d];

$count = 0;
foreach ($imoveis as $i => $data) {
    $post_id = wp_insert_post([
        'post_title'   => $data['titulo'],
        'post_type'    => 'imovel',
        'post_status'  => 'publish',
        'post_content' => 'Imóvel disponível para arrematação via leilão da Caixa Econômica Federal. Excelente oportunidade com desconto significativo sobre o valor de avaliação. Verifique o edital para condições completas.',
    ]);

    if (is_wp_error($post_id)) {
        WP_CLI::warning("Erro ao criar: {$data['titulo']}");
        continue;
    }

    // Meta dados do imóvel
    update_post_meta($post_id, '_imovel_endereco', $data['endereco']);
    update_post_meta($post_id, '_imovel_bairro', $data['bairro']);
    update_post_meta($post_id, '_imovel_area_total', $data['area_total']);
    update_post_meta($post_id, '_imovel_area_privativa', $data['area_priv']);
    update_post_meta($post_id, '_imovel_quartos', $data['quartos']);
    update_post_meta($post_id, '_imovel_garagem', $data['garagem']);

    // Meta dados do leilão
    update_post_meta($post_id, '_leilao_valor_avaliacao', $data['avaliacao']);
    update_post_meta($post_id, '_leilao_valor_minimo', $data['minimo']);
    update_post_meta($post_id, '_leilao_incremento', 500);
    update_post_meta($post_id, '_leilao_status', $statuses[$i]);
    update_post_meta($post_id, '_leilao_inicio', $now);
    update_post_meta($post_id, '_leilao_fim', $fins[$i]);
    update_post_meta($post_id, '_leilao_total_lances', 0);

    // Taxonomias
    wp_set_object_terms($post_id, $data['tipo'], 'tipo_imovel');
    wp_set_object_terms($post_id, $data['estado'], 'estado_imovel');
    wp_set_object_terms($post_id, $data['cidade'], 'cidade_imovel');
    wp_set_object_terms($post_id, $data['modalidade'], 'modalidade_venda');

    $count++;
    WP_CLI::log("✅ Criado: {$data['titulo']} (ID: {$post_id}) — Status: {$statuses[$i]}");
}

WP_CLI::success("$count imóveis de demonstração criados!");
