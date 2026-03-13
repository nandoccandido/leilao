<?php
/**
 * Sistema de Assessoria Q4 - Fluxo Pós-Arrematação
 * 
 * Gerencia:
 * - Contratação de assessoria jurídica
 * - Geração de contrato e procuração
 * - Assinatura digital (integração com Clicksign/DocuSign)
 * - Acompanhamento do processo
 * - Notificações ao cliente
 */

defined('ABSPATH') || exit;

class Leilao_Assessoria {

    // Status do contrato de assessoria
    const STATUS_PENDENTE          = 'pendente';
    const STATUS_AGUARDANDO_ASSINATURA = 'aguardando_assinatura';
    const STATUS_ASSINADO          = 'assinado';
    const STATUS_EM_ANALISE        = 'em_analise';
    const STATUS_APROVADO          = 'aprovado';
    const STATUS_EM_PROCESSO       = 'em_processo';
    const STATUS_CONCLUIDO         = 'concluido';
    const STATUS_CANCELADO         = 'cancelado';

    // Etapas do processo de assessoria
    const ETAPAS_PROCESSO = [
        'habilitacao' => [
            'ordem' => 1,
            'titulo' => 'Habilitação no Processo',
            'descricao' => 'Protocolo da Procuração no processo judicial',
            'icon' => '📋',
        ],
        'carta_arrematacao' => [
            'ordem' => 2,
            'titulo' => 'Pedido de Carta de Arrematação',
            'descricao' => 'Solicitação da Carta de Arrematação ou Escritura',
            'icon' => '📜',
        ],
        'guia_impostos' => [
            'ordem' => 3,
            'titulo' => 'Guia de Impostos Emitida',
            'descricao' => 'Emissão de guias de ITBI/ITCMD',
            'icon' => '💰',
        ],
        'protocolo_cartorio' => [
            'ordem' => 4,
            'titulo' => 'Protocolo no Cartório/Detran',
            'descricao' => 'Protocolo para registro da propriedade',
            'icon' => '🏛️',
        ],
        'propriedade_registrada' => [
            'ordem' => 5,
            'titulo' => 'Propriedade Registrada',
            'descricao' => 'Registro concluído - Entrega final',
            'icon' => '🏠',
        ],
    ];

    // Documentos necessários por tipo
    const DOCUMENTOS_PESSOA_FISICA = [
        'rg_cnh'           => ['label' => 'RG ou CNH (frente e verso)', 'obrigatorio' => true, 'categoria' => 'identificacao'],
        'cpf'              => ['label' => 'CPF', 'obrigatorio' => true, 'categoria' => 'identificacao'],
        'certidao_civil'   => ['label' => 'Certidão de Casamento ou Nascimento', 'obrigatorio' => true, 'categoria' => 'identificacao'],
        'comprovante_residencia' => ['label' => 'Comprovante de Residência (últimos 3 meses)', 'obrigatorio' => true, 'categoria' => 'endereco'],
    ];

    const DOCUMENTOS_PESSOA_JURIDICA = [
        'contrato_social'  => ['label' => 'Contrato Social e Alterações', 'obrigatorio' => true, 'categoria' => 'empresa'],
        'cnpj'             => ['label' => 'Cartão CNPJ', 'obrigatorio' => true, 'categoria' => 'empresa'],
        'rg_socios'        => ['label' => 'RG/CNH dos Sócios', 'obrigatorio' => true, 'categoria' => 'identificacao'],
        'cpf_socios'       => ['label' => 'CPF dos Sócios', 'obrigatorio' => true, 'categoria' => 'identificacao'],
    ];

    const DOCUMENTOS_FINANCEIROS = [
        'comprovante_lance' => ['label' => 'Comprovante de Pagamento do Lance (Guia Judicial ou Boleto)', 'obrigatorio' => true, 'categoria' => 'financeiro'],
        'comprovante_comissao' => ['label' => 'Comprovante da Comissão do Leiloeiro', 'obrigatorio' => true, 'categoria' => 'financeiro'],
    ];

    /**
     * Init hooks
     */
    public static function init() {
        // Admin
        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        add_action('wp_ajax_leilao_assessoria_aprovar_contrato', [__CLASS__, 'ajax_aprovar_contrato']);
        add_action('wp_ajax_leilao_assessoria_reprovar_contrato', [__CLASS__, 'ajax_reprovar_contrato']);
        add_action('wp_ajax_leilao_assessoria_atualizar_etapa', [__CLASS__, 'ajax_atualizar_etapa']);
        add_action('wp_ajax_leilao_assessoria_salvar_analise_ia', [__CLASS__, 'ajax_salvar_analise_ia']);
        add_action('wp_ajax_leilao_assessoria_aprovar_doc', [__CLASS__, 'ajax_aprovar_doc']);
        add_action('wp_ajax_leilao_assessoria_reprovar_doc', [__CLASS__, 'ajax_reprovar_doc']);

        // Frontend
        add_action('wp_ajax_leilao_contratar_assessoria', [__CLASS__, 'ajax_contratar_assessoria']);
        add_action('wp_ajax_leilao_assinar_contrato', [__CLASS__, 'ajax_assinar_contrato']);
        add_action('wp_ajax_leilao_upload_documento_assessoria', [__CLASS__, 'ajax_upload_documento']);
        add_action('wp_ajax_leilao_get_progresso_assessoria', [__CLASS__, 'ajax_get_progresso']);

        // Shortcodes
        add_shortcode('leilao_contratar_assessoria', [__CLASS__, 'shortcode_contratar']);
        add_shortcode('leilao_acompanhar_processo', [__CLASS__, 'shortcode_acompanhamento']);

        // Webhook para assinatura digital
        add_action('rest_api_init', [__CLASS__, 'register_webhook_routes']);
    }

    /**
     * Criar tabelas
     */
    public static function criar_tabelas() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        // Tabela de assessorias contratadas
        $table_assessorias = $wpdb->prefix . 'leilao_assessorias';
        $sql1 = "CREATE TABLE IF NOT EXISTS {$table_assessorias} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            arrematacao_id BIGINT UNSIGNED NOT NULL,
            imovel_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            tipo_pessoa ENUM('fisica','juridica') DEFAULT 'fisica',
            status VARCHAR(50) DEFAULT 'pendente',
            
            -- Dados do contrato
            contrato_gerado_em DATETIME NULL,
            contrato_url VARCHAR(500) NULL,
            procuracao_url VARCHAR(500) NULL,
            
            -- Assinatura digital
            assinatura_provider VARCHAR(50) NULL,
            assinatura_doc_id VARCHAR(255) NULL,
            assinatura_status VARCHAR(50) NULL,
            assinado_em DATETIME NULL,
            assinatura_ip VARCHAR(45) NULL,
            
            -- Análise IA
            analise_ia_data TEXT NULL,
            custas_estimadas DECIMAL(15,2) NULL,
            itbi_estimado DECIMAL(15,2) NULL,
            
            -- Processo
            etapa_atual VARCHAR(50) NULL,
            observacoes TEXT NULL,
            
            -- Aprovação
            aprovado_por BIGINT UNSIGNED NULL,
            aprovado_em DATETIME NULL,
            
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_arrematacao (arrematacao_id),
            KEY idx_user (user_id),
            KEY idx_status (status)
        ) {$charset};";

        // Tabela de etapas do processo
        $table_etapas = $wpdb->prefix . 'leilao_assessoria_etapas';
        $sql2 = "CREATE TABLE IF NOT EXISTS {$table_etapas} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            assessoria_id BIGINT UNSIGNED NOT NULL,
            etapa_key VARCHAR(50) NOT NULL,
            status ENUM('pendente','em_andamento','concluido') DEFAULT 'pendente',
            observacao TEXT NULL,
            documento_url VARCHAR(500) NULL,
            concluido_por BIGINT UNSIGNED NULL,
            concluido_em DATETIME NULL,
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_assessoria (assessoria_id),
            KEY idx_etapa (etapa_key)
        ) {$charset};";

        // Tabela de documentos da assessoria
        $table_docs = $wpdb->prefix . 'leilao_assessoria_documentos';
        $sql3 = "CREATE TABLE IF NOT EXISTS {$table_docs} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            assessoria_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            tipo_documento VARCHAR(100) NOT NULL,
            categoria VARCHAR(50) DEFAULT 'geral',
            nome_arquivo VARCHAR(255) NOT NULL,
            caminho_arquivo VARCHAR(500) NOT NULL,
            tamanho BIGINT UNSIGNED DEFAULT 0,
            mime_type VARCHAR(100) DEFAULT '',
            status ENUM('enviado','aprovado','reprovado','pendente_correcao') DEFAULT 'enviado',
            motivo_reprovacao TEXT NULL,
            aprovado_por BIGINT UNSIGNED NULL,
            aprovado_em DATETIME NULL,
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_assessoria (assessoria_id),
            KEY idx_tipo (tipo_documento)
        ) {$charset};";

        // Tabela de histórico
        $table_historico = $wpdb->prefix . 'leilao_assessoria_historico';
        $sql4 = "CREATE TABLE IF NOT EXISTS {$table_historico} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            assessoria_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            acao VARCHAR(100) NOT NULL,
            descricao TEXT NULL,
            dados_extras TEXT NULL,
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_assessoria (assessoria_id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);
        dbDelta($sql4);
    }

    /**
     * Admin menu
     */
    public static function admin_menu() {
        add_submenu_page(
            'leilao-caixa',
            'Assessorias Q4',
            'Assessorias',
            'edit_posts',
            'leilao-assessorias',
            [__CLASS__, 'page_assessorias']
        );

        add_submenu_page(
            'leilao-caixa',
            'Documentação Pendente',
            'Documentação',
            'edit_posts',
            'leilao-documentacao',
            [__CLASS__, 'page_documentacao']
        );
    }

    /**
     * Labels de status
     */
    public static function get_status_labels(): array {
        return [
            self::STATUS_PENDENTE              => 'Pendente',
            self::STATUS_AGUARDANDO_ASSINATURA => 'Aguardando Assinatura',
            self::STATUS_ASSINADO              => 'Contrato Assinado',
            self::STATUS_EM_ANALISE            => 'Em Análise',
            self::STATUS_APROVADO              => 'Aprovado',
            self::STATUS_EM_PROCESSO           => 'Em Processo',
            self::STATUS_CONCLUIDO             => 'Concluído',
            self::STATUS_CANCELADO             => 'Cancelado',
        ];
    }

    /**
     * AJAX: Contratar assessoria (cliente aceita)
     */
    public static function ajax_contratar_assessoria() {
        check_ajax_referer('leilao_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Faça login.']);
        }

        global $wpdb;
        $user_id = get_current_user_id();
        $arrematacao_id = absint($_POST['arrematacao_id'] ?? 0);
        $tipo_pessoa = sanitize_text_field($_POST['tipo_pessoa'] ?? 'fisica');

        if (!$arrematacao_id) {
            wp_send_json_error(['message' => 'Dados inválidos.']);
        }

        // Verificar se arrematação pertence ao usuário
        $table_arr = $wpdb->prefix . 'leilao_arrematacoes';
        $arrematacao = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_arr} WHERE id = %d AND user_id = %d",
            $arrematacao_id,
            $user_id
        ));

        if (!$arrematacao) {
            wp_send_json_error(['message' => 'Arrematação não encontrada.']);
        }

        // Verificar se já existe assessoria
        $table = $wpdb->prefix . 'leilao_assessorias';
        $existe = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE arrematacao_id = %d",
            $arrematacao_id
        ));

        if ($existe) {
            wp_send_json_error(['message' => 'Assessoria já contratada para esta arrematação.']);
        }

        // Criar assessoria
        $wpdb->insert($table, [
            'arrematacao_id' => $arrematacao_id,
            'imovel_id'      => $arrematacao->imovel_id,
            'user_id'        => $user_id,
            'tipo_pessoa'    => $tipo_pessoa,
            'status'         => self::STATUS_PENDENTE,
        ]);

        $assessoria_id = $wpdb->insert_id;

        // Criar etapas do processo
        self::criar_etapas_processo($assessoria_id);

        // Gerar contrato e procuração
        $docs = self::gerar_documentos_contrato($assessoria_id);

        // Atualizar com URLs dos documentos
        $wpdb->update($table, [
            'contrato_url'      => $docs['contrato_url'],
            'procuracao_url'    => $docs['procuracao_url'],
            'contrato_gerado_em'=> current_time('mysql'),
            'status'            => self::STATUS_AGUARDANDO_ASSINATURA,
        ], ['id' => $assessoria_id]);

        // Registrar histórico
        self::registrar_historico($assessoria_id, 'Assessoria Contratada', 'Cliente aceitou contratar assessoria Q4.');

        wp_send_json_success([
            'message' => 'Assessoria contratada! Agora assine os documentos.',
            'assessoria_id' => $assessoria_id,
            'redirect' => home_url('/painel-arrematante/?tab=assessoria&id=' . $assessoria_id),
        ]);
    }

    /**
     * Criar etapas do processo
     */
    private static function criar_etapas_processo(int $assessoria_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'leilao_assessoria_etapas';

        foreach (self::ETAPAS_PROCESSO as $key => $etapa) {
            $wpdb->insert($table, [
                'assessoria_id' => $assessoria_id,
                'etapa_key'     => $key,
                'status'        => 'pendente',
            ]);
        }
    }

    /**
     * Gerar documentos de contrato e procuração
     */
    public static function gerar_documentos_contrato(int $assessoria_id): array {
        global $wpdb;
        $table = $wpdb->prefix . 'leilao_assessorias';
        $assessoria = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $assessoria_id));

        if (!$assessoria) {
            return ['contrato_url' => '', 'procuracao_url' => ''];
        }

        $user = get_userdata($assessoria->user_id);
        $imovel = get_post($assessoria->imovel_id);

        // Dados do arrematante
        $table_arr = $wpdb->prefix . 'leilao_arrematacoes';
        $arrematacao = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_arr} WHERE id = %d",
            $assessoria->arrematacao_id
        ));

        $dados = [
            'nome_cliente'    => $user->display_name,
            'cpf_cnpj'        => get_user_meta($assessoria->user_id, '_leilao_cpf', true),
            'email'           => $user->user_email,
            'telefone'        => get_user_meta($assessoria->user_id, '_leilao_telefone', true),
            'imovel_titulo'   => $imovel->post_title,
            'imovel_endereco' => get_post_meta($imovel->ID, '_imovel_endereco', true),
            'valor_arrematado'=> $arrematacao->valor_arrematado ?? 0,
            'data_atual'      => date('d/m/Y'),
            'tipo_pessoa'     => $assessoria->tipo_pessoa,
        ];

        // Criar diretório para documentos
        $upload_dir = wp_upload_dir();
        $doc_dir = $upload_dir['basedir'] . '/leilao-contratos/' . $assessoria_id;
        if (!file_exists($doc_dir)) {
            wp_mkdir_p($doc_dir);
        }

        // Gerar contrato HTML
        $contrato_html = self::get_template_contrato($dados);
        $contrato_file = $doc_dir . '/contrato_assessoria.html';
        file_put_contents($contrato_file, $contrato_html);
        $contrato_url = $upload_dir['baseurl'] . '/leilao-contratos/' . $assessoria_id . '/contrato_assessoria.html';

        // Gerar procuração HTML
        $procuracao_html = self::get_template_procuracao($dados);
        $procuracao_file = $doc_dir . '/procuracao_ad_judicia.html';
        file_put_contents($procuracao_file, $procuracao_html);
        $procuracao_url = $upload_dir['baseurl'] . '/leilao-contratos/' . $assessoria_id . '/procuracao_ad_judicia.html';

        return [
            'contrato_url'   => $contrato_url,
            'procuracao_url' => $procuracao_url,
        ];
    }

    /**
     * Template do contrato de assessoria
     */
    private static function get_template_contrato(array $dados): string {
        $valor_formatado = number_format($dados['valor_arrematado'], 2, ',', '.');
        $tipo_pessoa = $dados['tipo_pessoa'] === 'juridica' ? 'Pessoa Jurídica' : 'Pessoa Física';

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Contrato de Prestação de Serviços - Qatar Leilões</title>
    <style>
        body { font-family: 'Times New Roman', serif; font-size: 12pt; line-height: 1.6; max-width: 800px; margin: 40px auto; padding: 40px; }
        h1 { text-align: center; font-size: 16pt; margin-bottom: 30px; }
        h2 { font-size: 14pt; margin-top: 25px; }
        .header { text-align: center; margin-bottom: 40px; }
        .header img { max-width: 200px; }
        .clausula { margin-bottom: 15px; text-align: justify; }
        .dados-box { background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .assinatura-box { margin-top: 60px; display: flex; justify-content: space-between; }
        .assinatura-item { text-align: center; width: 45%; }
        .linha-assinatura { border-top: 1px solid #000; margin-top: 60px; padding-top: 10px; }
        .footer { margin-top: 40px; font-size: 10pt; color: #666; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>CONTRATO DE PRESTAÇÃO DE SERVIÇOS DE ASSESSORIA JURÍDICA E ADMINISTRATIVA</h1>
    </div>

    <div class="dados-box">
        <strong>CONTRATANTE:</strong><br>
        Nome: {$dados['nome_cliente']}<br>
        CPF/CNPJ: {$dados['cpf_cnpj']}<br>
        E-mail: {$dados['email']}<br>
        Telefone: {$dados['telefone']}<br>
        Tipo: {$tipo_pessoa}
    </div>

    <div class="dados-box">
        <strong>BEM ARREMATADO:</strong><br>
        Descrição: {$dados['imovel_titulo']}<br>
        Endereço: {$dados['imovel_endereco']}<br>
        Valor Arrematado: R$ {$valor_formatado}
    </div>

    <div class="dados-box">
        <strong>CONTRATADA:</strong><br>
        QATAR LEILÕES ASSESSORIA JURÍDICA LTDA<br>
        CNPJ: XX.XXX.XXX/0001-XX<br>
        Endereço: [Endereço da empresa]
    </div>

    <h2>CLÁUSULA 1ª - DO OBJETO</h2>
    <p class="clausula">O presente contrato tem por objeto a prestação de serviços de assessoria jurídica e administrativa pela CONTRATADA ao CONTRATANTE, visando a regularização do bem arrematado em leilão judicial/extrajudicial, incluindo:</p>
    <ul>
        <li>Habilitação no processo judicial;</li>
        <li>Elaboração e protocolo de petições;</li>
        <li>Solicitação de Carta de Arrematação ou Escritura;</li>
        <li>Acompanhamento junto aos órgãos competentes;</li>
        <li>Orientação sobre pagamento de impostos (ITBI/ITCMD);</li>
        <li>Protocolo no Cartório de Registro de Imóveis ou Detran;</li>
        <li>Acompanhamento até o registro definitivo da propriedade.</li>
    </ul>

    <h2>CLÁUSULA 2ª - DO PRAZO</h2>
    <p class="clausula">O presente contrato vigorará pelo prazo necessário à conclusão dos serviços, estimado em 90 (noventa) a 180 (cento e oitenta) dias, podendo ser prorrogado em caso de pendências junto aos órgãos públicos.</p>

    <h2>CLÁUSULA 3ª - DOS HONORÁRIOS</h2>
    <p class="clausula">Pelos serviços prestados, o CONTRATANTE pagará à CONTRATADA o valor acordado no momento da contratação, conforme tabela de honorários vigente.</p>

    <h2>CLÁUSULA 4ª - DAS OBRIGAÇÕES DO CONTRATANTE</h2>
    <p class="clausula">O CONTRATANTE se obriga a:</p>
    <ul>
        <li>Fornecer todos os documentos solicitados no prazo estabelecido;</li>
        <li>Manter seus dados cadastrais atualizados;</li>
        <li>Efetuar os pagamentos de impostos e taxas quando solicitado;</li>
        <li>Acompanhar as notificações enviadas pela plataforma.</li>
    </ul>

    <h2>CLÁUSULA 5ª - DAS OBRIGAÇÕES DA CONTRATADA</h2>
    <p class="clausula">A CONTRATADA se obriga a:</p>
    <ul>
        <li>Prestar os serviços com diligência e zelo;</li>
        <li>Manter o CONTRATANTE informado sobre o andamento do processo;</li>
        <li>Guardar sigilo sobre as informações do CONTRATANTE;</li>
        <li>Disponibilizar acesso ao painel de acompanhamento online.</li>
    </ul>

    <h2>CLÁUSULA 6ª - DA RESCISÃO</h2>
    <p class="clausula">O presente contrato poderá ser rescindido por qualquer das partes, mediante comunicação prévia de 30 (trinta) dias, sem prejuízo dos valores já devidos pelos serviços prestados.</p>

    <h2>CLÁUSULA 7ª - DO FORO</h2>
    <p class="clausula">Fica eleito o foro da Comarca de São Paulo/SP para dirimir quaisquer questões oriundas do presente contrato.</p>

    <p style="margin-top: 30px;">E, por estarem assim justas e contratadas, as partes assinam o presente instrumento em via digital.</p>

    <p style="text-align: center; margin-top: 30px;">{$dados['data_atual']}</p>

    <div class="assinatura-box">
        <div class="assinatura-item">
            <div class="linha-assinatura">
                <strong>CONTRATANTE</strong><br>
                {$dados['nome_cliente']}<br>
                CPF/CNPJ: {$dados['cpf_cnpj']}
            </div>
        </div>
        <div class="assinatura-item">
            <div class="linha-assinatura">
                <strong>CONTRATADA</strong><br>
                QATAR LEILÕES ASSESSORIA JURÍDICA LTDA<br>
                CNPJ: XX.XXX.XXX/0001-XX
            </div>
        </div>
    </div>

    <div class="footer">
        <p>Documento gerado eletronicamente em {$dados['data_atual']}</p>
        <p>Válido mediante assinatura digital</p>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Template da procuração
     */
    private static function get_template_procuracao(array $dados): string {
        $tipo_pessoa = $dados['tipo_pessoa'] === 'juridica' ? 'Pessoa Jurídica' : 'Pessoa Física';
        $qualificacao = $dados['tipo_pessoa'] === 'juridica' 
            ? "pessoa jurídica de direito privado, inscrita no CNPJ sob o nº {$dados['cpf_cnpj']}"
            : "brasileiro(a), inscrito(a) no CPF sob o nº {$dados['cpf_cnpj']}";

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Procuração Ad Judicia - Qatar Leilões</title>
    <style>
        body { font-family: 'Times New Roman', serif; font-size: 12pt; line-height: 1.8; max-width: 800px; margin: 40px auto; padding: 40px; }
        h1 { text-align: center; font-size: 18pt; margin-bottom: 40px; text-transform: uppercase; }
        .conteudo { text-align: justify; }
        .destaque { font-weight: bold; }
        .dados-box { background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .assinatura-box { margin-top: 80px; text-align: center; }
        .linha-assinatura { border-top: 1px solid #000; width: 50%; margin: 60px auto 10px; padding-top: 10px; }
        .footer { margin-top: 40px; font-size: 10pt; color: #666; text-align: center; }
    </style>
</head>
<body>
    <h1>PROCURAÇÃO AD JUDICIA ET EXTRA</h1>

    <div class="conteudo">
        <p><span class="destaque">OUTORGANTE:</span> {$dados['nome_cliente']}, {$qualificacao}, residente e domiciliado(a) no endereço cadastrado em nosso sistema, e-mail: {$dados['email']}, telefone: {$dados['telefone']}.</p>

        <p><span class="destaque">OUTORGADOS:</span> Os advogados integrantes do escritório QATAR LEILÕES ASSESSORIA JURÍDICA, com endereço profissional em [ENDEREÇO], inscritos na OAB/SP sob os números [NÚMEROS OAB], onde receberão intimações e notificações.</p>

        <p><span class="destaque">PODERES:</span> Pelo presente instrumento particular de procuração, o(a) OUTORGANTE nomeia e constitui os OUTORGADOS como seus bastantes procuradores, a quem confere amplos poderes para o foro em geral, com a cláusula "AD JUDICIA ET EXTRA", podendo:</p>

        <ul>
            <li>Representar o(a) OUTORGANTE em qualquer juízo, instância ou tribunal;</li>
            <li>Propor ações e defender em todos os seus termos;</li>
            <li>Recorrer, desistir, transigir, firmar compromissos ou acordos;</li>
            <li>Receber e dar quitação;</li>
            <li>Substabelecer esta procuração no todo ou em parte, com ou sem reservas de poderes;</li>
            <li>Praticar todos os atos necessários ao fiel cumprimento deste mandato, especialmente no que tange à regularização do bem arrematado em leilão.</li>
        </ul>

        <div class="dados-box">
            <strong>OBJETO ESPECÍFICO:</strong><br>
            Esta procuração é outorgada especificamente para representação nos processos relacionados ao bem:<br><br>
            <strong>Descrição:</strong> {$dados['imovel_titulo']}<br>
            <strong>Endereço:</strong> {$dados['imovel_endereco']}<br>
            <strong>Valor Arrematado:</strong> R$ {$dados['valor_arrematado']}
        </div>

        <p>A presente procuração é válida pelo prazo necessário à conclusão dos serviços contratados, podendo ser revogada a qualquer tempo mediante notificação por escrito.</p>
    </div>

    <div class="assinatura-box">
        <p style="margin-bottom: 60px;">{$dados['data_atual']}</p>
        
        <div class="linha-assinatura">
            <strong>{$dados['nome_cliente']}</strong><br>
            CPF/CNPJ: {$dados['cpf_cnpj']}<br>
            OUTORGANTE
        </div>
    </div>

    <div class="footer">
        <p>Documento gerado eletronicamente em {$dados['data_atual']}</p>
        <p>Válido mediante assinatura digital conforme MP 2.200-2/2001 e Lei 14.063/2020</p>
    </div>
</body>
</html>
HTML;
    }

    /**
     * AJAX: Assinar contrato (assinatura digital simplificada)
     */
    public static function ajax_assinar_contrato() {
        check_ajax_referer('leilao_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Faça login.']);
        }

        global $wpdb;
        $user_id = get_current_user_id();
        $assessoria_id = absint($_POST['assessoria_id'] ?? 0);
        $aceite_termos = filter_var($_POST['aceite_termos'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (!$assessoria_id) {
            wp_send_json_error(['message' => 'Dados inválidos.']);
        }

        if (!$aceite_termos) {
            wp_send_json_error(['message' => 'Você precisa aceitar os termos para prosseguir.']);
        }

        $table = $wpdb->prefix . 'leilao_assessorias';
        $assessoria = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND user_id = %d",
            $assessoria_id,
            $user_id
        ));

        if (!$assessoria) {
            wp_send_json_error(['message' => 'Assessoria não encontrada.']);
        }

        if ($assessoria->status !== self::STATUS_AGUARDANDO_ASSINATURA && $assessoria->status !== self::STATUS_PENDENTE) {
            wp_send_json_error(['message' => 'Contrato já foi assinado ou não está disponível para assinatura.']);
        }

        // Registrar assinatura
        $wpdb->update($table, [
            'status'           => self::STATUS_ASSINADO,
            'assinatura_status'=> 'assinado_plataforma',
            'assinado_em'      => current_time('mysql'),
            'assinatura_ip'    => $_SERVER['REMOTE_ADDR'] ?? '',
        ], ['id' => $assessoria_id]);

        // Atualizar status da arrematação para "Em processo"
        $table_arr = $wpdb->prefix . 'leilao_arrematacoes';
        $wpdb->update($table_arr, [
            'status' => 'em_processo',
        ], ['id' => $assessoria->arrematacao_id]);

        // Registrar histórico
        self::registrar_historico($assessoria_id, 'Contrato Assinado', 'Cliente assinou o contrato e a procuração digitalmente.');

        // Notificar admin
        self::notificar_admin_nova_assinatura($assessoria_id);

        // Enviar e-mail de confirmação
        self::enviar_email_cliente($assessoria_id, 'assinatura_confirmada');

        wp_send_json_success([
            'message' => 'Contrato assinado com sucesso! Agora envie a documentação necessária.',
            'redirect' => home_url('/painel-arrematante/?tab=assessoria&id=' . $assessoria_id . '&step=documentos'),
        ]);
    }

    /**
     * AJAX: Upload de documento
     */
    public static function ajax_upload_documento() {
        check_ajax_referer('leilao_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Faça login.']);
        }

        $user_id = get_current_user_id();
        $assessoria_id = absint($_POST['assessoria_id'] ?? 0);
        $tipo_documento = sanitize_text_field($_POST['tipo_documento'] ?? '');
        $categoria = sanitize_text_field($_POST['categoria'] ?? 'geral');

        if (!$assessoria_id || !$tipo_documento) {
            wp_send_json_error(['message' => 'Dados inválidos.']);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'leilao_assessorias';
        $assessoria = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND user_id = %d",
            $assessoria_id,
            $user_id
        ));

        if (!$assessoria) {
            wp_send_json_error(['message' => 'Assessoria não encontrada.']);
        }

        if (empty($_FILES['documento'])) {
            wp_send_json_error(['message' => 'Nenhum arquivo enviado.']);
        }

        $allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
        $file = $_FILES['documento'];

        if (!in_array($file['type'], $allowed)) {
            wp_send_json_error(['message' => 'Tipo de arquivo não permitido. Use PDF, JPG ou PNG.']);
        }

        if ($file['size'] > 10 * 1024 * 1024) {
            wp_send_json_error(['message' => 'Arquivo muito grande. Máximo: 10MB.']);
        }

        // Upload
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $upload_dir = wp_upload_dir();
        $doc_dir = $upload_dir['basedir'] . '/leilao-assessoria-docs/' . $assessoria_id;

        if (!file_exists($doc_dir)) {
            wp_mkdir_p($doc_dir);
            file_put_contents($doc_dir . '/.htaccess', 'deny from all');
        }

        $filename = sanitize_file_name($tipo_documento . '_' . time() . '_' . $file['name']);
        $filepath = $doc_dir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            wp_send_json_error(['message' => 'Erro ao salvar arquivo.']);
        }

        $file_url = $upload_dir['baseurl'] . '/leilao-assessoria-docs/' . $assessoria_id . '/' . $filename;

        // Salvar no banco
        $table_docs = $wpdb->prefix . 'leilao_assessoria_documentos';

        // Remover documento anterior do mesmo tipo
        $old_doc = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_docs} WHERE assessoria_id = %d AND tipo_documento = %s",
            $assessoria_id,
            $tipo_documento
        ));
        if ($old_doc && $old_doc->status !== 'aprovado') {
            $wpdb->delete($table_docs, ['id' => $old_doc->id]);
        }

        $wpdb->insert($table_docs, [
            'assessoria_id'   => $assessoria_id,
            'user_id'         => $user_id,
            'tipo_documento'  => $tipo_documento,
            'categoria'       => $categoria,
            'nome_arquivo'    => $file['name'],
            'caminho_arquivo' => $file_url,
            'tamanho'         => $file['size'],
            'mime_type'       => $file['type'],
            'status'          => 'enviado',
        ]);

        self::registrar_historico($assessoria_id, 'Documento Enviado', "Tipo: {$tipo_documento}");

        // Verificar se todos documentos foram enviados
        self::verificar_documentos_completos($assessoria_id);

        wp_send_json_success(['message' => 'Documento enviado com sucesso!']);
    }

    /**
     * Verificar se todos documentos foram enviados
     */
    private static function verificar_documentos_completos(int $assessoria_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'leilao_assessorias';
        $assessoria = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $assessoria_id));

        if (!$assessoria) return;

        // Documentos necessários
        $docs_pf = $assessoria->tipo_pessoa === 'juridica' ? self::DOCUMENTOS_PESSOA_JURIDICA : self::DOCUMENTOS_PESSOA_FISICA;
        $docs_financeiros = self::DOCUMENTOS_FINANCEIROS;
        $todos_docs = array_merge($docs_pf, $docs_financeiros);

        $obrigatorios = array_filter($todos_docs, fn($d) => $d['obrigatorio']);

        $table_docs = $wpdb->prefix . 'leilao_assessoria_documentos';
        $enviados = $wpdb->get_col($wpdb->prepare(
            "SELECT tipo_documento FROM {$table_docs} WHERE assessoria_id = %d",
            $assessoria_id
        ));

        $faltantes = array_diff(array_keys($obrigatorios), $enviados);

        if (empty($faltantes) && $assessoria->status === self::STATUS_ASSINADO) {
            // Atualizar para análise
            $wpdb->update($table, ['status' => self::STATUS_EM_ANALISE], ['id' => $assessoria_id]);
            self::registrar_historico($assessoria_id, 'Documentos Completos', 'Todos os documentos obrigatórios foram enviados. Aguardando análise.');
            self::notificar_admin_documentos_completos($assessoria_id);
        }
    }

    /**
     * AJAX: Admin aprovar contrato/documentos
     */
    public static function ajax_aprovar_contrato() {
        check_ajax_referer('leilao_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Sem permissão.']);
        }

        global $wpdb;
        $assessoria_id = absint($_POST['assessoria_id'] ?? 0);

        $table = $wpdb->prefix . 'leilao_assessorias';
        $wpdb->update($table, [
            'status'      => self::STATUS_EM_PROCESSO,
            'aprovado_por'=> get_current_user_id(),
            'aprovado_em' => current_time('mysql'),
            'etapa_atual' => 'habilitacao',
        ], ['id' => $assessoria_id]);

        self::registrar_historico($assessoria_id, 'Documentação Aprovada', 'Gerente/Advogado aprovou a documentação. Processo iniciado.');
        self::enviar_email_cliente($assessoria_id, 'documentacao_aprovada');

        wp_send_json_success(['message' => 'Documentação aprovada! Processo iniciado.']);
    }

    /**
     * AJAX: Admin aprovar documento
     */
    public static function ajax_aprovar_doc() {
        check_ajax_referer('leilao_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Sem permissão.']);
        }

        global $wpdb;
        $doc_id = absint($_POST['doc_id'] ?? 0);

        $table = $wpdb->prefix . 'leilao_assessoria_documentos';
        $wpdb->update($table, [
            'status'      => 'aprovado',
            'aprovado_por'=> get_current_user_id(),
            'aprovado_em' => current_time('mysql'),
        ], ['id' => $doc_id]);

        wp_send_json_success(['message' => 'Documento aprovado!']);
    }

    /**
     * AJAX: Admin reprovar documento
     */
    public static function ajax_reprovar_doc() {
        check_ajax_referer('leilao_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Sem permissão.']);
        }

        global $wpdb;
        $doc_id = absint($_POST['doc_id'] ?? 0);
        $motivo = sanitize_textarea_field($_POST['motivo'] ?? '');

        $table = $wpdb->prefix . 'leilao_assessoria_documentos';
        $wpdb->update($table, [
            'status'            => 'reprovado',
            'motivo_reprovacao' => $motivo,
        ], ['id' => $doc_id]);

        wp_send_json_success(['message' => 'Documento reprovado!']);
    }

    /**
     * AJAX: Atualizar etapa do processo
     */
    public static function ajax_atualizar_etapa() {
        check_ajax_referer('leilao_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Sem permissão.']);
        }

        global $wpdb;
        $assessoria_id = absint($_POST['assessoria_id'] ?? 0);
        $etapa_key = sanitize_text_field($_POST['etapa_key'] ?? '');
        $novo_status = sanitize_text_field($_POST['status'] ?? 'concluido');
        $observacao = sanitize_textarea_field($_POST['observacao'] ?? '');

        $table_etapas = $wpdb->prefix . 'leilao_assessoria_etapas';
        $table = $wpdb->prefix . 'leilao_assessorias';

        // Atualizar etapa
        $updates = ['status' => $novo_status];
        if ($novo_status === 'concluido') {
            $updates['concluido_por'] = get_current_user_id();
            $updates['concluido_em'] = current_time('mysql');
        }
        if ($observacao) {
            $updates['observacao'] = $observacao;
        }

        $wpdb->update($table_etapas, $updates, [
            'assessoria_id' => $assessoria_id,
            'etapa_key'     => $etapa_key,
        ]);

        // Atualizar etapa atual na assessoria
        $wpdb->update($table, ['etapa_atual' => $etapa_key], ['id' => $assessoria_id]);

        // Verificar se é a última etapa
        if ($etapa_key === 'propriedade_registrada' && $novo_status === 'concluido') {
            $wpdb->update($table, ['status' => self::STATUS_CONCLUIDO], ['id' => $assessoria_id]);
            self::enviar_email_cliente($assessoria_id, 'processo_concluido');
        }

        $etapa_info = self::ETAPAS_PROCESSO[$etapa_key] ?? [];
        self::registrar_historico($assessoria_id, 'Etapa Atualizada', "{$etapa_info['titulo']}: {$novo_status}");

        // Notificar cliente
        self::enviar_email_cliente($assessoria_id, 'etapa_atualizada', [
            'etapa' => $etapa_info['titulo'] ?? $etapa_key,
            'status' => $novo_status,
        ]);

        wp_send_json_success(['message' => 'Etapa atualizada!']);
    }

    /**
     * AJAX: Salvar análise da IA
     */
    public static function ajax_salvar_analise_ia() {
        check_ajax_referer('leilao_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Sem permissão.']);
        }

        global $wpdb;
        $assessoria_id = absint($_POST['assessoria_id'] ?? 0);
        $analise_data = sanitize_textarea_field($_POST['analise_data'] ?? '');
        $custas_estimadas = floatval($_POST['custas_estimadas'] ?? 0);
        $itbi_estimado = floatval($_POST['itbi_estimado'] ?? 0);

        $table = $wpdb->prefix . 'leilao_assessorias';
        $wpdb->update($table, [
            'analise_ia_data'   => $analise_data,
            'custas_estimadas'  => $custas_estimadas,
            'itbi_estimado'     => $itbi_estimado,
        ], ['id' => $assessoria_id]);

        self::registrar_historico($assessoria_id, 'Análise IA Salva', "Custas estimadas: R$ " . number_format($custas_estimadas, 2, ',', '.'));

        wp_send_json_success(['message' => 'Análise salva!']);
    }

    /**
     * Registrar histórico
     */
    public static function registrar_historico(int $assessoria_id, string $acao, string $descricao = '', array $dados_extras = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'leilao_assessoria_historico';

        $wpdb->insert($table, [
            'assessoria_id' => $assessoria_id,
            'user_id'       => get_current_user_id() ?: null,
            'acao'          => $acao,
            'descricao'     => $descricao,
            'dados_extras'  => !empty($dados_extras) ? json_encode($dados_extras) : null,
        ]);
    }

    /**
     * Get assessoria
     */
    public static function get_assessoria(int $assessoria_id): ?object {
        global $wpdb;
        $table = $wpdb->prefix . 'leilao_assessorias';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $assessoria_id));
    }

    /**
     * Get assessoria by arrematação
     */
    public static function get_assessoria_by_arrematacao(int $arrematacao_id): ?object {
        global $wpdb;
        $table = $wpdb->prefix . 'leilao_assessorias';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE arrematacao_id = %d", $arrematacao_id));
    }

    /**
     * Get assessorias do usuário
     */
    public static function get_assessorias_usuario(int $user_id): array {
        global $wpdb;
        $table = $wpdb->prefix . 'leilao_assessorias';
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE user_id = %d ORDER BY criado_em DESC", $user_id));
    }

    /**
     * Get documentos da assessoria
     */
    public static function get_documentos_assessoria(int $assessoria_id): array {
        global $wpdb;
        $table = $wpdb->prefix . 'leilao_assessoria_documentos';
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE assessoria_id = %d ORDER BY criado_em DESC", $assessoria_id));
    }

    /**
     * Get etapas da assessoria
     */
    public static function get_etapas_assessoria(int $assessoria_id): array {
        global $wpdb;
        $table = $wpdb->prefix . 'leilao_assessoria_etapas';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE assessoria_id = %d ORDER BY id ASC",
            $assessoria_id
        ));
    }

    /**
     * Get histórico
     */
    public static function get_historico_assessoria(int $assessoria_id): array {
        global $wpdb;
        $table = $wpdb->prefix . 'leilao_assessoria_historico';
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE assessoria_id = %d ORDER BY criado_em DESC", $assessoria_id));
    }

    /**
     * Enviar e-mail ao cliente
     */
    public static function enviar_email_cliente(int $assessoria_id, string $tipo, array $extras = []) {
        $assessoria = self::get_assessoria($assessoria_id);
        if (!$assessoria) return;

        $user = get_userdata($assessoria->user_id);
        if (!$user) return;

        $imovel = get_post($assessoria->imovel_id);
        $site_name = get_bloginfo('name');

        $assuntos = [
            'assinatura_confirmada' => "✅ Contrato assinado com sucesso - {$imovel->post_title}",
            'documentacao_aprovada' => "🎉 Documentação aprovada! Processo iniciado",
            'etapa_atualizada'      => "📊 Atualização no seu processo - {$imovel->post_title}",
            'processo_concluido'    => "🏠 Parabéns! Seu imóvel está regularizado!",
        ];

        $assunto = $assuntos[$tipo] ?? "Atualização - Qatar Leilões";

        $mensagens = [
            'assinatura_confirmada' => "
                <h2>Contrato assinado com sucesso!</h2>
                <p>Olá {$user->display_name},</p>
                <p>Seu contrato de assessoria jurídica foi assinado com sucesso.</p>
                <p>Agora você precisa enviar a documentação necessária para darmos continuidade ao processo.</p>
                <p><a href='" . home_url('/painel-arrematante/?tab=assessoria&id=' . $assessoria_id) . "' style='display:inline-block;padding:12px 24px;background:#28a745;color:#fff;text-decoration:none;border-radius:5px;'>Enviar Documentos</a></p>
            ",
            'documentacao_aprovada' => "
                <h2>Documentação Aprovada!</h2>
                <p>Olá {$user->display_name},</p>
                <p>Sua documentação foi analisada e aprovada pela nossa equipe jurídica.</p>
                <p>O processo de regularização do seu imóvel já foi iniciado!</p>
                <p>Acompanhe o progresso em tempo real no seu painel.</p>
                <p><a href='" . home_url('/painel-arrematante/?tab=assessoria&id=' . $assessoria_id) . "' style='display:inline-block;padding:12px 24px;background:#007bff;color:#fff;text-decoration:none;border-radius:5px;'>Acompanhar Processo</a></p>
            ",
            'etapa_atualizada' => "
                <h2>Atualização no seu processo</h2>
                <p>Olá {$user->display_name},</p>
                <p>Há uma atualização no processo do seu imóvel <strong>{$imovel->post_title}</strong>.</p>
                <p><strong>Etapa:</strong> " . ($extras['etapa'] ?? '') . "</p>
                <p><strong>Status:</strong> " . ($extras['status'] ?? '') . "</p>
                <p><a href='" . home_url('/painel-arrematante/?tab=assessoria&id=' . $assessoria_id) . "' style='display:inline-block;padding:12px 24px;background:#17a2b8;color:#fff;text-decoration:none;border-radius:5px;'>Ver Detalhes</a></p>
            ",
            'processo_concluido' => "
                <h2>🎉 Parabéns! Processo Concluído!</h2>
                <p>Olá {$user->display_name},</p>
                <p>É com grande satisfação que informamos que o processo de regularização do seu imóvel foi concluído com sucesso!</p>
                <p><strong>Imóvel:</strong> {$imovel->post_title}</p>
                <p>Sua propriedade está devidamente registrada em seu nome.</p>
                <p>Agradecemos pela confiança na Qatar Leilões!</p>
            ",
        ];

        $mensagem = $mensagens[$tipo] ?? "<p>Confira as atualizações no seu painel.</p>";

        $html = "
            <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
                <div style='background:linear-gradient(135deg, #1a1a2e, #16213e);padding:30px;text-align:center;'>
                    <h1 style='color:#fff;margin:0;'>Qatar Leilões</h1>
                    <p style='color:#ccc;margin:5px 0 0;'>Assessoria Jurídica</p>
                </div>
                <div style='padding:30px;background:#fff;border:1px solid #ddd;'>
                    {$mensagem}
                </div>
                <div style='padding:20px;background:#f5f5f5;text-align:center;font-size:12px;color:#666;'>
                    <p>{$site_name} - Todos os direitos reservados</p>
                </div>
            </div>
        ";

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        wp_mail($user->user_email, $assunto, $html, $headers);

        self::registrar_historico($assessoria_id, 'E-mail Enviado', "Tipo: {$tipo}");
    }

    /**
     * Notificar admin sobre nova assinatura
     */
    private static function notificar_admin_nova_assinatura(int $assessoria_id) {
        $assessoria = self::get_assessoria($assessoria_id);
        if (!$assessoria) return;

        $user = get_userdata($assessoria->user_id);
        $imovel = get_post($assessoria->imovel_id);
        $admin_email = defined('LEILAO_CAIXA_EMAIL') ? LEILAO_CAIXA_EMAIL : get_option('admin_email');

        $assunto = "📝 Novo contrato assinado - {$imovel->post_title}";
        $mensagem = "
            <h2>Novo contrato de assessoria assinado</h2>
            <p><strong>Cliente:</strong> {$user->display_name} ({$user->user_email})</p>
            <p><strong>Imóvel:</strong> {$imovel->post_title}</p>
            <p><strong>Data:</strong> " . date('d/m/Y H:i') . "</p>
            <p>O cliente precisa enviar a documentação. Aguarde os documentos para análise.</p>
            <p><a href='" . admin_url("admin.php?page=leilao-assessorias") . "'>Ver no Admin</a></p>
        ";

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        wp_mail($admin_email, $assunto, $mensagem, $headers);
    }

    /**
     * Notificar admin sobre documentos completos
     */
    private static function notificar_admin_documentos_completos(int $assessoria_id) {
        $assessoria = self::get_assessoria($assessoria_id);
        if (!$assessoria) return;

        $user = get_userdata($assessoria->user_id);
        $imovel = get_post($assessoria->imovel_id);
        $admin_email = defined('LEILAO_CAIXA_EMAIL') ? LEILAO_CAIXA_EMAIL : get_option('admin_email');

        $assunto = "📋 Documentação completa para análise - {$imovel->post_title}";
        $mensagem = "
            <h2>Documentação pronta para análise</h2>
            <p><strong>Cliente:</strong> {$user->display_name} ({$user->user_email})</p>
            <p><strong>Imóvel:</strong> {$imovel->post_title}</p>
            <p>Todos os documentos obrigatórios foram enviados.</p>
            <p><strong>Ação necessária:</strong> Analisar e aprovar a documentação.</p>
            <p><a href='" . admin_url("admin.php?page=leilao-documentacao") . "'>Analisar Documentos</a></p>
        ";

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        wp_mail($admin_email, $assunto, $mensagem, $headers);
    }

    /**
     * Webhook routes para assinatura digital
     */
    public static function register_webhook_routes() {
        register_rest_route('leilao/v1', '/assinatura-webhook', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'handle_assinatura_webhook'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Handle webhook de assinatura (Clicksign/DocuSign)
     */
    public static function handle_assinatura_webhook($request) {
        $data = $request->get_params();

        // Log do webhook
        error_log('Webhook assinatura: ' . json_encode($data));

        // Implementar conforme o provider de assinatura
        // Ex: Clicksign envia event_type, document.key, etc.

        return new WP_REST_Response(['status' => 'received'], 200);
    }

    /**
     * Shortcode: Área de contratação
     */
    public static function shortcode_contratar($atts): string {
        if (!is_user_logged_in()) {
            return '<div class="leilao-msg"><p>Faça login para continuar.</p></div>';
        }

        $arrematacao_id = absint($_GET['arrematacao'] ?? $atts['arrematacao_id'] ?? 0);
        if (!$arrematacao_id) {
            return '<div class="leilao-msg"><p>Arrematação não especificada.</p></div>';
        }

        $user_id = get_current_user_id();

        global $wpdb;
        $table_arr = $wpdb->prefix . 'leilao_arrematacoes';
        $arrematacao = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_arr} WHERE id = %d AND user_id = %d",
            $arrematacao_id,
            $user_id
        ));

        if (!$arrematacao) {
            return '<div class="leilao-msg leilao-msg-error"><p>Arrematação não encontrada.</p></div>';
        }

        // Verificar se já contratou
        $assessoria = self::get_assessoria_by_arrematacao($arrematacao_id);
        if ($assessoria) {
            return '<div class="leilao-msg"><p>Você já contratou a assessoria. <a href="' . home_url('/painel-arrematante/?tab=assessoria&id=' . $assessoria->id) . '">Acompanhar processo</a></p></div>';
        }

        $imovel = get_post($arrematacao->imovel_id);

        ob_start();
        ?>
        <div class="leilao-contratar-assessoria">
            <div class="leilao-contratar-header">
                <h2>🎉 Parabéns pela arrematação!</h2>
                <p>Você arrematou: <strong><?php echo esc_html($imovel->post_title); ?></strong></p>
                <p class="leilao-valor-grande">R$ <?php echo number_format($arrematacao->valor_arrematado, 2, ',', '.'); ?></p>
            </div>

            <div class="leilao-assessoria-oferta">
                <h3>📋 Regularize seu imóvel com a Qatar Leilões</h3>
                <p>Nossa assessoria jurídica cuida de todo o processo para você:</p>
                
                <ul class="leilao-beneficios">
                    <li>✅ Habilitação no processo judicial</li>
                    <li>✅ Pedido de Carta de Arrematação</li>
                    <li>✅ Orientação sobre ITBI e impostos</li>
                    <li>✅ Protocolo no Cartório de Registro</li>
                    <li>✅ Acompanhamento até a transferência final</li>
                </ul>

                <div class="leilao-tipo-pessoa">
                    <label>Tipo de cadastro:</label>
                    <select id="tipo_pessoa_assessoria">
                        <option value="fisica">Pessoa Física</option>
                        <option value="juridica">Pessoa Jurídica</option>
                    </select>
                </div>

                <button type="button" class="leilao-btn leilao-btn-accent leilao-btn-full" id="btn-contratar-assessoria" data-arrematacao="<?php echo $arrematacao_id; ?>">
                    Contratar Assessoria Jurídica
                </button>

                <p class="leilao-disclaimer">Ao contratar, você será direcionado para assinar o contrato e a procuração digitalmente.</p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Shortcode: Acompanhamento do processo
     */
    public static function shortcode_acompanhamento($atts): string {
        if (!is_user_logged_in()) {
            return '<div class="leilao-msg"><p>Faça login para continuar.</p></div>';
        }

        $assessoria_id = absint($_GET['id'] ?? $atts['assessoria_id'] ?? 0);
        $step = sanitize_text_field($_GET['step'] ?? 'info');
        $user_id = get_current_user_id();

        if (!$assessoria_id) {
            // Listar assessorias do usuário
            return self::render_lista_assessorias($user_id);
        }

        // Verificar se pertence ao usuário
        $assessoria = self::get_assessoria($assessoria_id);
        if (!$assessoria || $assessoria->user_id != $user_id) {
            return '<div class="leilao-msg leilao-msg-error"><p>Assessoria não encontrada.</p></div>';
        }

        return self::render_painel_assessoria($assessoria, $step);
    }

    /**
     * Render lista de assessorias
     */
    private static function render_lista_assessorias(int $user_id): string {
        $assessorias = self::get_assessorias_usuario($user_id);
        $status_labels = self::get_status_labels();

        if (empty($assessorias)) {
            return '<div class="leilao-msg"><p>Você ainda não possui assessorias contratadas.</p></div>';
        }

        ob_start();
        ?>
        <div class="leilao-assessorias-lista">
            <h2>Minhas Assessorias</h2>
            <div class="leilao-grid">
                <?php foreach ($assessorias as $ass):
                    $imovel = get_post($ass->imovel_id);
                    if (!$imovel) continue;
                    $etapas = self::get_etapas_assessoria($ass->id);
                    $concluidas = count(array_filter($etapas, fn($e) => $e->status === 'concluido'));
                    $total = count($etapas);
                    $progresso = $total > 0 ? round(($concluidas / $total) * 100) : 0;
                    ?>
                    <div class="leilao-card-assessoria">
                        <div class="leilao-card-img">
                            <?php echo get_the_post_thumbnail($imovel->ID, 'medium'); ?>
                        </div>
                        <div class="leilao-card-body">
                            <h4><?php echo esc_html($imovel->post_title); ?></h4>
                            <span class="leilao-status-badge leilao-status-<?php echo esc_attr($ass->status); ?>">
                                <?php echo esc_html($status_labels[$ass->status] ?? $ass->status); ?>
                            </span>
                            
                            <?php if ($ass->status === 'em_processo'): ?>
                                <div class="leilao-progresso-mini">
                                    <div class="leilao-progresso-bar">
                                        <div class="leilao-progresso-fill" style="width: <?php echo $progresso; ?>%"></div>
                                    </div>
                                    <span><?php echo $progresso; ?>% concluído</span>
                                </div>
                            <?php endif; ?>

                            <p>
                                <a href="?tab=assessoria&id=<?php echo $ass->id; ?>" class="leilao-btn leilao-btn-sm">
                                    Ver Detalhes
                                </a>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render painel da assessoria
     */
    private static function render_painel_assessoria(object $assessoria, string $step): string {
        $imovel = get_post($assessoria->imovel_id);
        $status_labels = self::get_status_labels();
        $etapas = self::get_etapas_assessoria($assessoria->id);
        $documentos = self::get_documentos_assessoria($assessoria->id);
        $historico = self::get_historico_assessoria($assessoria->id);

        ob_start();
        ?>
        <div class="leilao-painel-assessoria">
            <a href="?tab=assessoria" class="leilao-btn leilao-btn-sm">← Voltar</a>

            <div class="leilao-assessoria-header">
                <div class="leilao-assessoria-info">
                    <h2><?php echo esc_html($imovel->post_title); ?></h2>
                    <span class="leilao-status-badge leilao-status-<?php echo esc_attr($assessoria->status); ?>">
                        <?php echo esc_html($status_labels[$assessoria->status] ?? $assessoria->status); ?>
                    </span>
                </div>
            </div>

            <?php if ($assessoria->status === 'aguardando_assinatura' || $assessoria->status === 'pendente'): ?>
                <!-- PASSO 1: Assinatura -->
                <?php echo self::render_step_assinatura($assessoria); ?>
            <?php elseif ($assessoria->status === 'assinado' || $step === 'documentos'): ?>
                <!-- PASSO 2: Upload de documentos -->
                <?php echo self::render_step_documentos($assessoria, $documentos); ?>
            <?php elseif ($assessoria->status === 'em_analise'): ?>
                <!-- AGUARDANDO ANÁLISE -->
                <div class="leilao-info-box leilao-info-warning">
                    <h3>📋 Documentação em Análise</h3>
                    <p>Sua documentação está sendo analisada pela nossa equipe jurídica.</p>
                    <p>Você será notificado assim que a análise for concluída.</p>
                </div>
            <?php elseif (in_array($assessoria->status, ['aprovado', 'em_processo', 'concluido'])): ?>
                <!-- PASSO 3: Acompanhamento -->
                <?php echo self::render_step_acompanhamento($assessoria, $etapas); ?>
            <?php endif; ?>

            <!-- Histórico -->
            <div class="leilao-historico-section">
                <h3>📜 Histórico</h3>
                <div class="leilao-timeline">
                    <?php foreach ($historico as $item): ?>
                        <div class="leilao-timeline-item">
                            <span class="leilao-timeline-date"><?php echo date('d/m/Y H:i', strtotime($item->criado_em)); ?></span>
                            <strong><?php echo esc_html($item->acao); ?></strong>
                            <?php if ($item->descricao): ?>
                                <br><span class="leilao-timeline-desc"><?php echo esc_html($item->descricao); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render step assinatura
     */
    private static function render_step_assinatura(object $assessoria): string {
        ob_start();
        ?>
        <div class="leilao-step-assinatura">
            <h3>📝 Assine o Contrato e a Procuração</h3>
            <p>Leia os documentos abaixo e assine digitalmente para prosseguir.</p>

            <div class="leilao-documentos-assinar">
                <div class="leilao-doc-assinar">
                    <h4>Contrato de Prestação de Serviços</h4>
                    <p>Contrato de assessoria jurídica para regularização do seu imóvel.</p>
                    <a href="<?php echo esc_url($assessoria->contrato_url); ?>" target="_blank" class="leilao-btn leilao-btn-outline leilao-btn-sm">
                        📄 Visualizar Contrato
                    </a>
                </div>

                <div class="leilao-doc-assinar">
                    <h4>Procuração Ad Judicia</h4>
                    <p>Procuração para representação nos processos judiciais e administrativos.</p>
                    <a href="<?php echo esc_url($assessoria->procuracao_url); ?>" target="_blank" class="leilao-btn leilao-btn-outline leilao-btn-sm">
                        📄 Visualizar Procuração
                    </a>
                </div>
            </div>

            <div class="leilao-aceite-box">
                <label class="leilao-checkbox">
                    <input type="checkbox" id="aceite_termos_assessoria">
                    <span>Li e aceito os termos do <strong>Contrato de Prestação de Serviços</strong> e da <strong>Procuração Ad Judicia</strong>, conferindo poderes aos advogados da Qatar Leilões para representar-me no processo de regularização do imóvel arrematado.</span>
                </label>
            </div>

            <button type="button" class="leilao-btn leilao-btn-success leilao-btn-full" id="btn-assinar-contrato" data-assessoria="<?php echo $assessoria->id; ?>">
                ✍️ Assinar Digitalmente
            </button>

            <p class="leilao-disclaimer">Sua assinatura digital tem validade jurídica conforme a MP 2.200-2/2001 e Lei 14.063/2020.</p>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render step documentos
     */
    private static function render_step_documentos(object $assessoria, array $documentos): string {
        $docs_pessoa = $assessoria->tipo_pessoa === 'juridica' ? self::DOCUMENTOS_PESSOA_JURIDICA : self::DOCUMENTOS_PESSOA_FISICA;
        $docs_financeiros = self::DOCUMENTOS_FINANCEIROS;

        $docs_enviados = [];
        foreach ($documentos as $doc) {
            $docs_enviados[$doc->tipo_documento] = $doc;
        }

        ob_start();
        ?>
        <div class="leilao-step-documentos">
            <h3>📤 Envie sua Documentação</h3>
            <p>Envie os documentos abaixo para darmos continuidade ao processo. Formatos aceitos: PDF, JPG, PNG (máx. 10MB).</p>

            <!-- Documentos de Identificação -->
            <div class="leilao-docs-categoria">
                <h4>📋 Documentos de <?php echo $assessoria->tipo_pessoa === 'juridica' ? 'Pessoa Jurídica' : 'Identificação'; ?></h4>
                <?php echo self::render_lista_docs_upload($assessoria->id, $docs_pessoa, $docs_enviados); ?>
            </div>

            <!-- Documentos Financeiros -->
            <div class="leilao-docs-categoria">
                <h4>💰 Comprovantes Financeiros</h4>
                <?php echo self::render_lista_docs_upload($assessoria->id, $docs_financeiros, $docs_enviados, 'financeiro'); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render lista de docs para upload
     */
    private static function render_lista_docs_upload(int $assessoria_id, array $docs, array $enviados, string $categoria = 'identificacao'): string {
        ob_start();
        ?>
        <div class="leilao-docs-list">
            <?php foreach ($docs as $tipo => $info):
                $enviado = $enviados[$tipo] ?? null;
                $obg = $info['obrigatorio'] ? ' <span class="obrigatorio">*</span>' : '';
                ?>
                <div class="leilao-doc-item <?php echo $enviado ? 'enviado' : ''; ?>">
                    <div class="leilao-doc-info-row">
                        <span class="leilao-doc-label"><?php echo $info['label'] . $obg; ?></span>
                        <?php if ($enviado): ?>
                            <span class="leilao-doc-status-badge status-<?php echo $enviado->status; ?>">
                                <?php
                                $icons = ['enviado' => '⏳', 'aprovado' => '✅', 'reprovado' => '❌'];
                                echo $icons[$enviado->status] ?? '';
                                echo ' ' . ucfirst($enviado->status);
                                ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if ($enviado && $enviado->status === 'reprovado'): ?>
                        <div class="leilao-doc-reprovado-msg">
                            <strong>Motivo:</strong> <?php echo esc_html($enviado->motivo_reprovacao); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!$enviado || $enviado->status === 'reprovado'): ?>
                        <form class="leilao-upload-form-assessoria" enctype="multipart/form-data">
                            <input type="hidden" name="assessoria_id" value="<?php echo $assessoria_id; ?>">
                            <input type="hidden" name="tipo_documento" value="<?php echo esc_attr($tipo); ?>">
                            <input type="hidden" name="categoria" value="<?php echo esc_attr($categoria); ?>">
                            <input type="file" name="documento" accept=".pdf,.jpg,.jpeg,.png" required>
                            <button type="submit" class="leilao-btn leilao-btn-sm">Enviar</button>
                        </form>
                    <?php elseif ($enviado->status === 'enviado'): ?>
                        <p class="leilao-doc-aguardando">Aguardando análise...</p>
                    <?php elseif ($enviado->status === 'aprovado'): ?>
                        <p class="leilao-doc-aprovado">✅ Documento aprovado</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render step acompanhamento
     */
    private static function render_step_acompanhamento(object $assessoria, array $etapas): string {
        $etapas_config = self::ETAPAS_PROCESSO;
        $concluidas = count(array_filter($etapas, fn($e) => $e->status === 'concluido'));
        $total = count($etapas);
        $progresso = $total > 0 ? round(($concluidas / $total) * 100) : 0;

        ob_start();
        ?>
        <div class="leilao-step-acompanhamento">
            <h3>📊 Acompanhamento do Processo</h3>

            <!-- Barra de progresso geral -->
            <div class="leilao-progresso-geral">
                <div class="leilao-progresso-bar-grande">
                    <div class="leilao-progresso-fill" style="width: <?php echo $progresso; ?>%"></div>
                </div>
                <p class="leilao-progresso-texto"><?php echo $progresso; ?>% concluído - <?php echo $concluidas; ?> de <?php echo $total; ?> etapas</p>
            </div>

            <!-- Etapas -->
            <div class="leilao-etapas-lista">
                <?php foreach ($etapas as $etapa):
                    $config = $etapas_config[$etapa->etapa_key] ?? [];
                    $status_class = $etapa->status;
                    ?>
                    <div class="leilao-etapa-item <?php echo $status_class; ?>">
                        <div class="leilao-etapa-icon">
                            <?php echo $config['icon'] ?? '📋'; ?>
                        </div>
                        <div class="leilao-etapa-content">
                            <h4><?php echo esc_html($config['titulo'] ?? $etapa->etapa_key); ?></h4>
                            <p><?php echo esc_html($config['descricao'] ?? ''); ?></p>
                            <?php if ($etapa->observacao): ?>
                                <p class="leilao-etapa-obs"><em><?php echo esc_html($etapa->observacao); ?></em></p>
                            <?php endif; ?>
                            <?php if ($etapa->concluido_em): ?>
                                <span class="leilao-etapa-data">Concluído em <?php echo date('d/m/Y', strtotime($etapa->concluido_em)); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="leilao-etapa-status">
                            <?php if ($etapa->status === 'concluido'): ?>
                                <span class="leilao-check">✅</span>
                            <?php elseif ($etapa->status === 'em_andamento'): ?>
                                <span class="leilao-loading-icon">⏳</span>
                            <?php else: ?>
                                <span class="leilao-pending">○</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($assessoria->analise_ia_data): ?>
                <div class="leilao-analise-ia">
                    <h4>🤖 Análise do Processo</h4>
                    <div class="leilao-custas-box">
                        <?php if ($assessoria->itbi_estimado): ?>
                            <div class="leilao-custa-item">
                                <span>ITBI Estimado</span>
                                <strong>R$ <?php echo number_format($assessoria->itbi_estimado, 2, ',', '.'); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if ($assessoria->custas_estimadas): ?>
                            <div class="leilao-custa-item">
                                <span>Custas Estimadas</span>
                                <strong>R$ <?php echo number_format($assessoria->custas_estimadas, 2, ',', '.'); ?></strong>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($assessoria->analise_ia_data): ?>
                        <div class="leilao-analise-texto">
                            <?php echo nl2br(esc_html($assessoria->analise_ia_data)); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Página admin: Assessorias
     */
    public static function page_assessorias() {
        global $wpdb;
        $table = $wpdb->prefix . 'leilao_assessorias';
        $status_labels = self::get_status_labels();

        $status_filter = sanitize_text_field($_GET['status'] ?? '');
        $where = $status_filter ? $wpdb->prepare(" WHERE status = %s", $status_filter) : '';

        $assessorias = $wpdb->get_results("SELECT * FROM {$table}{$where} ORDER BY criado_em DESC");
        ?>
        <div class="wrap">
            <h1>Assessorias Q4</h1>

            <div class="leilao-filtros">
                <a href="?page=leilao-assessorias" class="button <?php echo !$status_filter ? 'button-primary' : ''; ?>">Todas</a>
                <?php foreach ($status_labels as $val => $label): ?>
                    <a href="?page=leilao-assessorias&status=<?php echo $val; ?>" 
                       class="button <?php echo $status_filter === $val ? 'button-primary' : ''; ?>">
                        <?php echo $label; ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Imóvel</th>
                        <th>Cliente</th>
                        <th>Status</th>
                        <th>Etapa Atual</th>
                        <th>Data</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($assessorias)): ?>
                        <tr><td colspan="7">Nenhuma assessoria encontrada.</td></tr>
                    <?php else: ?>
                        <?php foreach ($assessorias as $ass):
                            $imovel = get_post($ass->imovel_id);
                            $user = get_userdata($ass->user_id);
                            $etapa_config = self::ETAPAS_PROCESSO[$ass->etapa_atual] ?? [];
                            ?>
                            <tr>
                                <td><?php echo $ass->id; ?></td>
                                <td>
                                    <a href="<?php echo get_edit_post_link($ass->imovel_id); ?>">
                                        <?php echo esc_html($imovel->post_title ?? 'N/A'); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php echo esc_html($user->display_name ?? 'N/A'); ?>
                                    <br><small><?php echo esc_html($user->user_email ?? ''); ?></small>
                                </td>
                                <td>
                                    <span class="leilao-status-badge leilao-status-<?php echo esc_attr($ass->status); ?>">
                                        <?php echo esc_html($status_labels[$ass->status] ?? $ass->status); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($etapa_config['titulo'] ?? '-'); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($ass->criado_em)); ?></td>
                                <td>
                                    <a href="<?php echo admin_url("admin.php?page=leilao-assessoria-detalhes&id={$ass->id}"); ?>" class="button button-small">Gerenciar</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Página admin: Documentação pendente
     */
    public static function page_documentacao() {
        global $wpdb;
        $table = $wpdb->prefix . 'leilao_assessorias';
        $status_labels = self::get_status_labels();

        // Assessorias aguardando análise de documentação
        $pendentes = $wpdb->get_results("SELECT * FROM {$table} WHERE status IN ('assinado', 'em_analise') ORDER BY criado_em ASC");
        ?>
        <div class="wrap">
            <h1>📋 Documentação Pendente de Análise</h1>

            <?php if (empty($pendentes)): ?>
                <div class="notice notice-info">
                    <p>Nenhuma documentação pendente de análise.</p>
                </div>
            <?php else: ?>
                <?php foreach ($pendentes as $ass):
                    $imovel = get_post($ass->imovel_id);
                    $user = get_userdata($ass->user_id);
                    $documentos = self::get_documentos_assessoria($ass->id);
                    ?>
                    <div class="leilao-doc-review-card">
                        <div class="leilao-doc-review-header">
                            <h3><?php echo esc_html($imovel->post_title ?? 'N/A'); ?></h3>
                            <span class="leilao-status-badge leilao-status-<?php echo esc_attr($ass->status); ?>">
                                <?php echo esc_html($status_labels[$ass->status] ?? $ass->status); ?>
                            </span>
                        </div>

                        <div class="leilao-doc-review-info">
                            <p><strong>Cliente:</strong> <?php echo esc_html($user->display_name ?? 'N/A'); ?> (<?php echo esc_html($user->user_email ?? ''); ?>)</p>
                            <p><strong>Tipo:</strong> <?php echo $ass->tipo_pessoa === 'juridica' ? 'Pessoa Jurídica' : 'Pessoa Física'; ?></p>
                            <p><strong>Documentos enviados:</strong> <?php echo count($documentos); ?></p>
                        </div>

                        <?php if (!empty($documentos)): ?>
                            <table class="leilao-docs-table">
                                <thead>
                                    <tr>
                                        <th>Documento</th>
                                        <th>Arquivo</th>
                                        <th>Data</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($documentos as $doc): ?>
                                        <tr>
                                            <td><?php echo esc_html($doc->tipo_documento); ?></td>
                                            <td>
                                                <a href="<?php echo esc_url($doc->caminho_arquivo); ?>" target="_blank">
                                                    <?php echo esc_html($doc->nome_arquivo); ?>
                                                </a>
                                            </td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($doc->criado_em)); ?></td>
                                            <td><?php echo ucfirst($doc->status); ?></td>
                                            <td>
                                                <?php if ($doc->status === 'enviado'): ?>
                                                    <button class="button button-small btn-aprovar-doc-assessoria" data-id="<?php echo $doc->id; ?>">✓ Aprovar</button>
                                                    <button class="button button-small btn-reprovar-doc-assessoria" data-id="<?php echo $doc->id; ?>">✗ Reprovar</button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>

                        <div class="leilao-doc-review-actions">
                            <?php if ($ass->status === 'em_analise'): ?>
                                <button class="button button-primary btn-aprovar-assessoria" data-id="<?php echo $ass->id; ?>">
                                    ✅ Aprovar Documentação e Iniciar Processo
                                </button>
                            <?php endif; ?>

                            <a href="<?php echo esc_url($ass->contrato_url); ?>" target="_blank" class="button">📄 Ver Contrato</a>
                            <a href="<?php echo esc_url($ass->procuracao_url); ?>" target="_blank" class="button">📄 Ver Procuração</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <style>
            .leilao-doc-review-card { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
            .leilao-doc-review-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 15px; }
            .leilao-doc-review-header h3 { margin: 0; }
            .leilao-doc-review-info p { margin: 5px 0; }
            .leilao-doc-review-actions { margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee; display: flex; gap: 10px; }
        </style>
        <?php
    }
}
