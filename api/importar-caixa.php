<?php
/**
 * Importador de imóveis da Caixa — endpoint de front-end (admin)
 * GET  /api/importar-caixa.php
 * Returns: { ok, mensagem }
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

define('SHORTINIT', false);
require_once dirname(__DIR__) . '/wp-load.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400 * 7,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Apenas administradores
$user_id = (int) ($_SESSION['user_id'] ?? 0);
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'mensagem' => 'Não autenticado.']);
    exit;
}

$user = get_userdata($user_id);
if (!$user || !in_array('administrator', (array) $user->roles, true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'mensagem' => 'Acesso negado.']);
    exit;
}

if (!class_exists('Leilao_Caixa_Importer')) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensagem' => 'Importador não disponível. Verifique se o plugin está ativo.']);
    exit;
}

$ufs = ['AC','AL','AM','AP','BA','CE','DF','ES','GO','MA',
        'MG','MS','MT','PA','PB','PE','PI','PR','RJ','RN',
        'RO','RR','RS','SC','SE','SP','TO'];

$total = 0;
$erros = 0;

foreach ($ufs as $uf) {
    try {
        $result = Leilao_Caixa_Importer::importar_estado($uf);
        $total += (int) ($result['importados'] ?? 0);
    } catch (Throwable $e) {
        $erros++;
    }
}

echo json_encode([
    'ok'       => true,
    'mensagem' => "Importação concluída. {$total} imóvel(is) importado(s)."
                  . ($erros ? " ({$erros} estado(s) com erro)" : ''),
]);
