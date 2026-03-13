<?php
/**
 * Proxy de imagens — Pixabay API
 * 
 * Busca imagens por tipo de imóvel e faz cache local (24h).
 * GET /api/imagens.php?tipo=Apartamento
 * GET /api/imagens.php?q=casa+curitiba
 * 
 * Registre-se em https://pixabay.com/accounts/register/ para obter sua chave gratuita.
 */

// ─── CONFIG ──────────────────────────────────
define('PIXABAY_KEY', ''); // ← Cole sua chave da Pixabay aqui
define('CACHE_DIR', __DIR__ . '/../wp-content/cache/imagens/');
define('CACHE_TTL', 86400); // 24 horas (exigido pela Pixabay)

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if (empty(PIXABAY_KEY)) {
    http_response_code(503);
    echo json_encode(['erro' => 'Chave da Pixabay não configurada']);
    exit;
}

// ─── MAPEAMENTO tipo → query de busca ────────
$TIPO_QUERIES = [
    'apartamento' => 'apartment+building+interior',
    'casa'        => 'house+residential',
    'sobrado'     => 'house+two+story',
    'terreno'     => 'land+lot+empty',
    'sala'        => 'office+commercial+room',
    'galpão'      => 'warehouse+industrial',
    'galpao'      => 'warehouse+industrial',
    'sítio'       => 'farm+rural+country',
    'sitio'       => 'farm+rural+country',
    'cobertura'   => 'penthouse+luxury+apartment',
    'duplex'      => 'duplex+apartment+modern',
    'studio'      => 'studio+apartment+small',
    'imóvel'      => 'real+estate+property',
    'imovel'      => 'real+estate+property',
];

// ─── PARÂMETROS ──────────────────────────────
$tipo = isset($_GET['tipo']) ? trim($_GET['tipo']) : '';
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$quantidade = isset($_GET['n']) ? max(1, min(20, intval($_GET['n']))) : 5;

if (empty($tipo) && empty($query)) {
    http_response_code(400);
    echo json_encode(['erro' => 'Informe ?tipo=Apartamento ou ?q=termo+de+busca']);
    exit;
}

// Determinar query de busca
if (!empty($tipo)) {
    $tipoNorm = mb_strtolower(trim($tipo));
    $searchQuery = isset($TIPO_QUERIES[$tipoNorm]) ? $TIPO_QUERIES[$tipoNorm] : 'real+estate+' . urlencode($tipo);
} else {
    $searchQuery = urlencode($query);
}

// ─── CACHE ───────────────────────────────────
$cacheKey = md5($searchQuery . '_' . $quantidade);
$cacheFile = CACHE_DIR . $cacheKey . '.json';

if (!is_dir(CACHE_DIR)) {
    mkdir(CACHE_DIR, 0755, true);
}

// Verificar cache válido
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < CACHE_TTL) {
    echo file_get_contents($cacheFile);
    exit;
}

// ─── BUSCAR NA PIXABAY ──────────────────────
$url = 'https://pixabay.com/api/?'
    . 'key=' . PIXABAY_KEY
    . '&q=' . $searchQuery
    . '&image_type=photo'
    . '&orientation=horizontal'
    . '&category=buildings'
    . '&min_width=400'
    . '&safesearch=true'
    . '&per_page=' . $quantidade
    . '&lang=pt';

$context = stream_context_create([
    'http' => [
        'timeout' => 10,
        'user_agent' => 'QatarLeiloes/1.0',
    ]
]);

$response = @file_get_contents($url, false, $context);

if ($response === false) {
    // Tentar sem categoria (buildings pode não ter resultado para certos tipos)
    $urlSemCategoria = str_replace('&category=buildings', '', $url);
    $response = @file_get_contents($urlSemCategoria, false, $context);
}

if ($response === false) {
    http_response_code(502);
    echo json_encode(['erro' => 'Falha ao comunicar com Pixabay API']);
    exit;
}

$data = json_decode($response, true);

if (!$data || !isset($data['hits'])) {
    http_response_code(502);
    echo json_encode(['erro' => 'Resposta inválida da Pixabay API']);
    exit;
}

// ─── FORMATAR RESPOSTA ──────────────────────
$imagens = [];
foreach ($data['hits'] as $hit) {
    $imagens[] = [
        'id'       => $hit['id'],
        'url'      => $hit['webformatURL'],     // 640px
        'url_sm'   => str_replace('_640', '_340', $hit['webformatURL']),  // 340px
        'preview'  => $hit['previewURL'],        // 150px
        'largura'  => $hit['webformatWidth'],
        'altura'   => $hit['webformatHeight'],
        'autor'    => $hit['user'],
        'link'     => $hit['pageURL'],
    ];
}

$resultado = [
    'total'   => $data['totalHits'],
    'query'   => $searchQuery,
    'imagens' => $imagens,
];

$json = json_encode($resultado, JSON_UNESCAPED_UNICODE);

// Salvar cache
file_put_contents($cacheFile, $json, LOCK_EX);

echo $json;
