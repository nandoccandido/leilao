<?php
/**
 * Script avulso — cria tabelas de documentos e log de arrematação
 * Executar: php /tmp/criar_tabelas_arr.php
 */

define('SHORTINIT', false);
require_once '/var/www/sites/qatarleiloes.com.br/wp-load.php';

global $wpdb;
$charset = $wpdb->get_charset_collate();
require_once ABSPATH . 'wp-admin/includes/upgrade.php';

// ── Tabela de documentos ──────────────────────────────────────────────────
$t2 = $wpdb->prefix . 'leilao_arrematacao_docs';
$sql2 = "CREATE TABLE IF NOT EXISTS {$t2} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    arrematacao_id BIGINT UNSIGNED NOT NULL,
    tipo VARCHAR(60) NOT NULL DEFAULT 'outros',
    nome VARCHAR(255) NOT NULL,
    arquivo_url TEXT NOT NULL,
    arquivo_path TEXT NOT NULL,
    status VARCHAR(30) DEFAULT 'pendente',
    observacao TEXT NULL,
    uploaded_by BIGINT UNSIGNED NOT NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    uploaded_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    reviewed_em DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_arrematacao (arrematacao_id),
    KEY idx_status (status)
) {$charset};";
dbDelta($sql2);
echo "Tabela {$t2}: OK\n";

// ── Tabela de timeline / log ──────────────────────────────────────────────
$t3 = $wpdb->prefix . 'leilao_arrematacao_log';
$sql3 = "CREATE TABLE IF NOT EXISTS {$t3} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    arrematacao_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    acao VARCHAR(60) NOT NULL,
    descricao TEXT NOT NULL,
    meta_json TEXT NULL,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_arrematacao (arrematacao_id),
    KEY idx_criado (criado_em)
) {$charset};";
dbDelta($sql3);
echo "Tabela {$t3}: OK\n";

// ── Verificar ─────────────────────────────────────────────────────────────
$tables = $wpdb->get_results("SHOW TABLES LIKE 'wp_leilao_%'");
echo "\nTabelas leilao no banco:\n";
foreach ($tables as $row) {
    foreach ($row as $v) {
        echo "  - {$v}\n";
    }
}
echo "\nConcluído!\n";
