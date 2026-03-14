<?php
/**
 * Leilao Consultas — Sistema de consultas imobiliárias (ONR/RIB)
 *
 * Serviços oferecidos dentro do site com markup de 50%.
 * Admin processa manualmente via portais oficiais e faz upload do resultado.
 */

defined('ABSPATH') || exit;

class Leilao_Consultas {

    /** Serviços disponíveis com preços (já com markup 50%) */
    const SERVICOS = [
        'certidao' => [
            'nome'      => 'Certidão de Matrícula',
            'desc'      => 'Certidão digital da matrícula do imóvel com validade jurídica de 30 dias. Assinada com certificado ICP-Brasil.',
            'icon'      => '📜',
            'preco'     => 119.90,
            'prazo'     => '1 a 3 dias úteis',
            'campos'    => ['matricula', 'cartorio', 'estado', 'cidade'],
        ],
        'pesquisa_bens' => [
            'nome'      => 'Pesquisa de Bens',
            'desc'      => 'Busca por CPF ou CNPJ em todos os registros de imóveis. Descubra se há bens, imóveis ou direitos reais vinculados.',
            'icon'      => '🔎',
            'preco'     => 89.90,
            'prazo'     => '2 a 5 dias úteis',
            'campos'    => ['cpf_cnpj', 'nome_completo', 'estado', 'cidade'],
        ],
        'matricula_online' => [
            'nome'      => 'Matrícula Online',
            'desc'      => 'Visualização digital da matrícula do imóvel, tal como a existente no cartório. Consulta rápida e econômica.',
            'icon'      => '🏠',
            'preco'     => 49.90,
            'prazo'     => 'Até 24 horas',
            'campos'    => ['matricula', 'cartorio', 'estado', 'cidade'],
        ],
        'certidao_onus' => [
            'nome'      => 'Certidão de Ônus Reais',
            'desc'      => 'Certidão que informa se existem ônus (hipotecas, penhoras, etc.) sobre determinado imóvel.',
            'icon'      => '⚖️',
            'preco'     => 139.90,
            'prazo'     => '1 a 3 dias úteis',
            'campos'    => ['matricula', 'cartorio', 'estado', 'cidade'],
        ],
        'certidao_negativa' => [
            'nome'      => 'Certidão Negativa de Propriedade',
            'desc'      => 'Certifica que determinada pessoa (CPF/CNPJ) não possui imóveis registrados em determinada circunscrição.',
            'icon'      => '📋',
            'preco'     => 99.90,
            'prazo'     => '2 a 5 dias úteis',
            'campos'    => ['cpf_cnpj', 'nome_completo', 'estado', 'cidade'],
        ],
    ];

    /** Estados brasileiros */
    const ESTADOS = [
        'AC'=>'Acre','AL'=>'Alagoas','AP'=>'Amapá','AM'=>'Amazonas','BA'=>'Bahia',
        'CE'=>'Ceará','DF'=>'Distrito Federal','ES'=>'Espírito Santo','GO'=>'Goiás',
        'MA'=>'Maranhão','MT'=>'Mato Grosso','MS'=>'Mato Grosso do Sul','MG'=>'Minas Gerais',
        'PA'=>'Pará','PB'=>'Paraíba','PR'=>'Paraná','PE'=>'Pernambuco','PI'=>'Piauí',
        'RJ'=>'Rio de Janeiro','RN'=>'Rio Grande do Norte','RS'=>'Rio Grande do Sul',
        'RO'=>'Rondônia','RR'=>'Roraima','SC'=>'Santa Catarina','SP'=>'São Paulo',
        'SE'=>'Sergipe','TO'=>'Tocantins',
    ];

    public static function init(): void {
        // Shortcodes
        add_shortcode('leilao_consultas', [__CLASS__, 'shortcode_consultas']);

        // AJAX — logado
        add_action('wp_ajax_leilao_solicitar_consulta',  [__CLASS__, 'ajax_solicitar']);
        add_action('wp_ajax_leilao_minhas_consultas',    [__CLASS__, 'ajax_minhas']);

        // AJAX — admin
        add_action('wp_ajax_leilao_admin_consultas',        [__CLASS__, 'ajax_admin_list']);
        add_action('wp_ajax_leilao_admin_consulta_update',  [__CLASS__, 'ajax_admin_update']);
        add_action('wp_ajax_leilao_admin_consulta_upload',  [__CLASS__, 'ajax_admin_upload']);

        // Admin menu
        add_action('admin_menu', [__CLASS__, 'admin_menu']);

        // Criar tabela
        add_action('admin_init', [__CLASS__, 'maybe_create_table']);

        // Criar página
        add_action('admin_init', [__CLASS__, 'criar_pagina']);
    }

    /* ------------------------------------------------------------------ */
    /*  DATABASE                                                          */
    /* ------------------------------------------------------------------ */

    public static function maybe_create_table(): void {
        if (get_option('leilao_consultas_table_v', 0) >= 1) return;
        self::create_table();
        update_option('leilao_consultas_table_v', 1);
    }

    public static function create_table(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $table   = $wpdb->prefix . 'leilao_consultas';

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            servico VARCHAR(50) NOT NULL,
            dados TEXT NOT NULL,
            preco DECIMAL(10,2) NOT NULL,
            status VARCHAR(30) DEFAULT 'aguardando_pagamento',
            comprovante_url VARCHAR(500) DEFAULT '',
            resultado_url VARCHAR(500) DEFAULT '',
            admin_nota TEXT DEFAULT '',
            ip VARCHAR(45) DEFAULT '',
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user (user_id),
            KEY idx_status (status),
            KEY idx_servico (servico)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /* ------------------------------------------------------------------ */
    /*  CRIAR PÁGINA                                                      */
    /* ------------------------------------------------------------------ */

    public static function criar_pagina(): void {
        if (get_option('leilao_consultas_page_created')) return;
        if (!get_page_by_path('consultas')) {
            wp_insert_post([
                'post_type'    => 'page',
                'post_title'   => 'Consultas',
                'post_content' => '[leilao_consultas]',
                'post_status'  => 'publish',
                'post_name'    => 'consultas',
            ]);
        }
        update_option('leilao_consultas_page_created', true);
    }

    /* ------------------------------------------------------------------ */
    /*  SHORTCODE — PÁGINA PRINCIPAL                                      */
    /* ------------------------------------------------------------------ */

    public static function shortcode_consultas(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $user        = wp_get_current_user();
        $is_logged   = is_user_logged_in() || !empty($_SESSION['user_id']);
        $nonce       = wp_create_nonce('leilao_nonce');
        $pix_key     = get_option('leilao_pix_key', 'contato@qatarleiloes.com.br');
        $pix_nome    = get_option('leilao_pix_nome', 'Qatar Leilões');

        ob_start();
        ?>
        <style>
        /* ── Consultas Page ── */
        .consultas-page{font-family:'Inter',sans-serif;color:#1e293b;max-width:1100px;margin:0 auto;padding:0 20px 60px}

        /* Hero */
        .consultas-hero{text-align:center;padding:48px 20px 32px}
        .consultas-hero h1{font-size:2rem;font-weight:800;color:#1a5276;margin:0 0 10px}
        .consultas-hero p{font-size:1.05rem;color:#64748b;max-width:640px;margin:0 auto;line-height:1.6}

        /* Grid de Cards */
        .consultas-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:24px;margin-bottom:48px}
        .consulta-card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:28px 24px;transition:box-shadow .2s,transform .2s;display:flex;flex-direction:column}
        .consulta-card:hover{box-shadow:0 8px 30px rgba(26,82,118,.12);transform:translateY(-3px)}
        .consulta-card-icon{font-size:2.2rem;margin-bottom:10px}
        .consulta-card h3{font-size:1.15rem;font-weight:700;color:#1a5276;margin:0 0 8px}
        .consulta-card-desc{font-size:.9rem;color:#64748b;line-height:1.55;flex:1;margin:0 0 14px}
        .consulta-card-meta{display:flex;align-items:center;gap:14px;margin-bottom:16px;font-size:.88rem}
        .consulta-preco{font-weight:700;color:#e67e22;font-size:1.05rem}
        .consulta-prazo{color:#94a3b8}

        /* Botões */
        .consulta-btn-solicitar{display:inline-block;background:#1a5276;color:#fff!important;border:none;padding:10px 28px;border-radius:8px;font-size:.92rem;font-weight:600;cursor:pointer;transition:background .2s}
        .consulta-btn-solicitar:hover{background:#154360}

        /* ── Modal / Overlay ── */
        .consulta-overlay{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px}
        .consulta-modal{background:#fff;border-radius:16px;width:100%;max-width:540px;max-height:90vh;overflow-y:auto;padding:32px;position:relative;box-shadow:0 20px 60px rgba(0,0,0,.2)}
        .consulta-modal-close{position:absolute;top:14px;right:16px;background:none;border:none;font-size:1.6rem;color:#94a3b8;cursor:pointer;line-height:1}
        .consulta-modal-close:hover{color:#1e293b}
        .consulta-modal-header{display:flex;align-items:flex-start;gap:14px;margin-bottom:24px}
        .consulta-modal-icon{font-size:2.4rem}
        .consulta-modal-header h3{font-size:1.2rem;font-weight:700;color:#1a5276;margin:0 0 4px}
        .consulta-modal-desc{font-size:.88rem;color:#64748b;margin:0;line-height:1.5}

        /* Login msg */
        .consulta-login-msg{text-align:center;padding:20px;background:#f0f9ff;border-radius:10px;font-size:.95rem;color:#334155}
        .consulta-login-msg a{color:#1a5276;font-weight:600;text-decoration:underline}

        /* Form fields */
        .consulta-form .leilao-field{margin-bottom:16px}
        .consulta-form .leilao-field label{display:block;font-size:.85rem;font-weight:600;color:#334155;margin-bottom:5px}
        .consulta-form .leilao-field input,
        .consulta-form .leilao-field select,
        .consulta-form .leilao-field textarea{width:100%;padding:10px 14px;border:1px solid #cbd5e1;border-radius:8px;font-size:.92rem;color:#1e293b;background:#f8fafc;transition:border-color .2s;box-sizing:border-box}
        .consulta-form .leilao-field input:focus,
        .consulta-form .leilao-field select:focus,
        .consulta-form .leilao-field textarea:focus{border-color:#1a5276;outline:none;background:#fff}

        /* Resumo */
        .consulta-resumo{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px;margin:20px 0}
        .consulta-resumo-line{display:flex;justify-content:space-between;align-items:center;font-size:.9rem;padding:4px 0}
        .consulta-resumo-total{border-top:1px solid #e2e8f0;margin-top:8px;padding-top:10px;font-size:1.05rem}
        .consulta-resumo-total strong{color:#e67e22}

        /* PIX box */
        .consulta-pix-box{background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:18px;margin-bottom:20px}
        .consulta-pix-box h4{margin:0 0 8px;font-size:.95rem;color:#92400e}
        .consulta-pix-box>p{font-size:.85rem;color:#78350f;margin:0 0 12px}
        .consulta-pix-info{background:#fff;border-radius:8px;padding:12px;margin-bottom:14px}
        .consulta-pix-row{display:flex;align-items:center;gap:8px;font-size:.88rem;padding:4px 0}
        .consulta-pix-row span:first-child{color:#78350f}
        .consulta-pix-row strong{color:#1e293b;word-break:break-all}
        .consulta-pix-copy{background:none;border:none;cursor:pointer;font-size:1.1rem;padding:2px 6px}

        /* Messages */
        .consulta-msg{font-size:.88rem;padding:10px 14px;border-radius:8px;text-align:center;margin-top:12px;display:none}
        .consulta-msg-ok{display:block;background:#d1fae5;color:#065f46;border:1px solid #6ee7b7}
        .consulta-msg-err{display:block;background:#fee2e2;color:#991b1b;border:1px solid #fca5a5}

        /* Submit btn */
        .consulta-btn-enviar{display:block;width:100%;background:#1a5276;color:#fff!important;border:none;padding:14px;border-radius:10px;font-size:1rem;font-weight:700;cursor:pointer;transition:background .2s}
        .consulta-btn-enviar:hover{background:#154360}
        .consulta-btn-enviar:disabled{opacity:.6;cursor:not-allowed}

        /* ── Minhas Consultas ── */
        .consultas-minhas{margin-top:40px}
        .consultas-minhas h2{font-size:1.4rem;font-weight:700;color:#1a5276;margin:0 0 18px}
        .consultas-table-wrap{overflow-x:auto;border:1px solid #e2e8f0;border-radius:12px}
        .consultas-table{width:100%;border-collapse:collapse;font-size:.88rem}
        .consultas-table thead{background:#f1f5f9}
        .consultas-table th{padding:12px 16px;text-align:left;font-weight:600;color:#475569;font-size:.8rem;text-transform:uppercase;letter-spacing:.03em}
        .consultas-table td{padding:12px 16px;border-top:1px solid #f1f5f9}
        .consultas-table tbody tr:hover{background:#f8fafc}

        /* Status badges */
        .consulta-status{display:inline-block;padding:4px 10px;border-radius:20px;font-size:.78rem;font-weight:600}
        .status-aguardando-pagamento{background:#fef3c7;color:#92400e}
        .status-pago{background:#dbeafe;color:#1e40af}
        .status-processando{background:#e0e7ff;color:#3730a3}
        .status-concluido{background:#d1fae5;color:#065f46}
        .status-cancelado{background:#fee2e2;color:#991b1b}

        .consulta-download{color:#1a5276;font-weight:600;text-decoration:none}
        .consulta-download:hover{text-decoration:underline}
        .consultas-empty{text-align:center;color:#94a3b8;padding:30px;font-size:.95rem}
        .consultas-lista-loading{text-align:center;color:#94a3b8;padding:30px}

        /* ── Responsivo ── */
        @media(max-width:640px){
            .consultas-hero h1{font-size:1.5rem}
            .consultas-grid{grid-template-columns:1fr}
            .consulta-modal{padding:20px;margin:10px}
        }
        </style>
        <div class="consultas-page">

            <!-- Hero -->
            <div class="consultas-hero">
                <h1>Consultas Imobiliárias</h1>
                <p>Certidões, matrículas e pesquisas de bens com entrega digital. Serviços oficiais do Registro de Imóveis processados com agilidade.</p>
            </div>

            <!-- Grade de serviços -->
            <div class="consultas-grid">
                <?php foreach (self::SERVICOS as $key => $svc): ?>
                <div class="consulta-card" data-servico="<?php echo esc_attr($key); ?>">
                    <div class="consulta-card-icon"><?php echo $svc['icon']; ?></div>
                    <h3><?php echo esc_html($svc['nome']); ?></h3>
                    <p class="consulta-card-desc"><?php echo esc_html($svc['desc']); ?></p>
                    <div class="consulta-card-meta">
                        <span class="consulta-preco">R$ <?php echo number_format($svc['preco'], 2, ',', '.'); ?></span>
                        <span class="consulta-prazo">⏱ <?php echo esc_html($svc['prazo']); ?></span>
                    </div>
                    <button type="button" class="leilao-btn consulta-btn-solicitar" onclick="abrirConsulta('<?php echo esc_attr($key); ?>')">
                        Solicitar
                    </button>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($is_logged): ?>
            <!-- Minhas Consultas -->
            <div class="consultas-minhas" id="minhasConsultas">
                <h2>Minhas Consultas</h2>
                <div id="listaMinhasConsultas" class="consultas-lista-loading">Carregando...</div>
            </div>
            <?php endif; ?>

            <!-- Modal de Solicitação -->
            <div class="consulta-overlay" id="consultaOverlay" style="display:none" onclick="fecharConsulta(event)">
                <div class="consulta-modal" onclick="event.stopPropagation()">
                    <button type="button" class="consulta-modal-close" onclick="fecharConsulta()">&times;</button>
                    <div class="consulta-modal-header">
                        <span id="modalIcon" class="consulta-modal-icon"></span>
                        <div>
                            <h3 id="modalTitle"></h3>
                            <p id="modalDesc" class="consulta-modal-desc"></p>
                        </div>
                    </div>

                    <?php if (!$is_logged): ?>
                    <div class="consulta-login-msg">
                        <p>Faça <a href="#" onclick="abrirAuth('login');return false;">login</a> ou <a href="#" onclick="abrirAuth('cadastro');return false;">crie uma conta</a> para solicitar consultas.</p>
                    </div>
                    <?php else: ?>
                    <form id="formConsulta" class="consulta-form">
                        <input type="hidden" name="action" value="leilao_solicitar_consulta" />
                        <input type="hidden" name="nonce" value="<?php echo $nonce; ?>" />
                        <input type="hidden" name="servico" id="formServico" value="" />

                        <!-- Campos dinâmicos -->
                        <div id="camposDinamicos"></div>

                        <!-- Observações -->
                        <div class="leilao-field">
                            <label>Observações (opcional)</label>
                            <textarea name="observacoes" rows="2" placeholder="Informações adicionais..."></textarea>
                        </div>

                        <!-- Resumo preço -->
                        <div class="consulta-resumo">
                            <div class="consulta-resumo-line">
                                <span>Serviço</span>
                                <strong id="resumoNome"></strong>
                            </div>
                            <div class="consulta-resumo-line consulta-resumo-total">
                                <span>Total</span>
                                <strong id="resumoPreco"></strong>
                            </div>
                        </div>

                        <!-- Pagamento PIX -->
                        <div class="consulta-pix-box">
                            <h4>💳 Pagamento via PIX</h4>
                            <p>Faça a transferência e anexe o comprovante:</p>
                            <div class="consulta-pix-info">
                                <div class="consulta-pix-row">
                                    <span>Chave PIX:</span>
                                    <strong id="pixKey"><?php echo esc_html($pix_key); ?></strong>
                                    <button type="button" class="consulta-pix-copy" onclick="copiarPix()">📋</button>
                                </div>
                                <div class="consulta-pix-row">
                                    <span>Titular:</span>
                                    <strong><?php echo esc_html($pix_nome); ?></strong>
                                </div>
                            </div>
                            <div class="leilao-field">
                                <label>Comprovante de Pagamento *</label>
                                <input type="file" name="comprovante" id="inputComprovante" accept="image/*,.pdf" required />
                            </div>
                        </div>

                        <div class="consulta-msg" id="consultaMsg"></div>
                        <button type="submit" class="leilao-btn leilao-btn-full consulta-btn-enviar" id="btnEnviar">
                            Enviar Solicitação
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <script>
        (function(){
            var SERVICOS = <?php echo json_encode(self::SERVICOS, JSON_UNESCAPED_UNICODE); ?>;
            var ESTADOS  = <?php echo json_encode(self::ESTADOS, JSON_UNESCAPED_UNICODE); ?>;
            var ajaxUrl  = '<?php echo admin_url("admin-ajax.php"); ?>';
            var nonce    = '<?php echo $nonce; ?>';

            /* ---------- Campos por tipo ---------- */
            var CAMPOS = {
                matricula: {label:'Nº da Matrícula *', type:'text', placeholder:'Ex: 12345', required:true},
                cartorio:  {label:'Cartório / Serventia', type:'text', placeholder:'Ex: 1º Registro de Imóveis de SP', required:false},
                estado:    {label:'Estado (UF) *', type:'select', options: ESTADOS, required:true},
                cidade:    {label:'Cidade *', type:'text', placeholder:'Ex: São Paulo', required:true},
                cpf_cnpj:  {label:'CPF ou CNPJ *', type:'text', placeholder:'000.000.000-00', required:true},
                nome_completo: {label:'Nome Completo da Pessoa *', type:'text', placeholder:'Nome como consta no registro', required:true},
            };

            window.abrirConsulta = function(key) {
                var svc = SERVICOS[key];
                if (!svc) return;

                document.getElementById('modalIcon').textContent  = svc.icon;
                document.getElementById('modalTitle').textContent = svc.nome;
                document.getElementById('modalDesc').textContent  = svc.desc;
                document.getElementById('resumoNome').textContent = svc.nome;
                document.getElementById('resumoPreco').textContent = 'R$ ' + parseFloat(svc.preco).toFixed(2).replace('.', ',');

                var fs = document.getElementById('formServico');
                if (fs) fs.value = key;

                // Montar campos dinâmicos
                var container = document.getElementById('camposDinamicos');
                if (container) {
                    container.innerHTML = '';
                    (svc.campos || []).forEach(function(cKey){
                        var cfg = CAMPOS[cKey];
                        if (!cfg) return;
                        var div = document.createElement('div');
                        div.className = 'leilao-field';

                        var lbl = document.createElement('label');
                        lbl.textContent = cfg.label;
                        div.appendChild(lbl);

                        if (cfg.type === 'select') {
                            var sel = document.createElement('select');
                            sel.name = cKey;
                            if (cfg.required) sel.required = true;
                            var opt0 = document.createElement('option');
                            opt0.value = '';
                            opt0.textContent = 'Selecione...';
                            sel.appendChild(opt0);
                            for (var k in cfg.options) {
                                var opt = document.createElement('option');
                                opt.value = k;
                                opt.textContent = cfg.options[k];
                                sel.appendChild(opt);
                            }
                            div.appendChild(sel);
                        } else {
                            var inp = document.createElement('input');
                            inp.type = cfg.type || 'text';
                            inp.name = cKey;
                            inp.placeholder = cfg.placeholder || '';
                            if (cfg.required) inp.required = true;
                            div.appendChild(inp);
                        }
                        container.appendChild(div);
                    });
                }

                // Reset
                var msg = document.getElementById('consultaMsg');
                if (msg) { msg.textContent = ''; msg.className = 'consulta-msg'; }
                var btn = document.getElementById('btnEnviar');
                if (btn) { btn.disabled = false; btn.textContent = 'Enviar Solicitação'; }

                document.getElementById('consultaOverlay').style.display = 'flex';
                document.body.style.overflow = 'hidden';
            };

            window.fecharConsulta = function(e) {
                if (e && e.target !== e.currentTarget) return;
                document.getElementById('consultaOverlay').style.display = 'none';
                document.body.style.overflow = '';
            };

            window.copiarPix = function() {
                var txt = document.getElementById('pixKey').textContent;
                navigator.clipboard.writeText(txt).then(function(){
                    var btn = document.querySelector('.consulta-pix-copy');
                    btn.textContent = '✅';
                    setTimeout(function(){ btn.textContent = '📋'; }, 2000);
                });
            };

            /* ---------- Submit ---------- */
            var form = document.getElementById('formConsulta');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    var btn = document.getElementById('btnEnviar');
                    var msg = document.getElementById('consultaMsg');
                    btn.disabled = true;
                    btn.textContent = 'Enviando...';
                    msg.textContent = '';
                    msg.className = 'consulta-msg';

                    var fd = new FormData(form);

                    fetch(ajaxUrl, { method:'POST', body: fd, credentials:'same-origin' })
                        .then(function(r){ return r.json(); })
                        .then(function(r){
                            if (r.success) {
                                msg.textContent = r.data.message;
                                msg.className = 'consulta-msg consulta-msg-ok';
                                btn.textContent = '✅ Enviado!';
                                carregarMinhas();
                                setTimeout(function(){ window.fecharConsulta(); }, 2500);
                            } else {
                                msg.textContent = r.data.message || 'Erro ao enviar.';
                                msg.className = 'consulta-msg consulta-msg-err';
                                btn.disabled = false;
                                btn.textContent = 'Enviar Solicitação';
                            }
                        })
                        .catch(function(){
                            msg.textContent = 'Erro de conexão.';
                            msg.className = 'consulta-msg consulta-msg-err';
                            btn.disabled = false;
                            btn.textContent = 'Enviar Solicitação';
                        });
                });
            }

            /* ---------- Minhas Consultas ---------- */
            function carregarMinhas() {
                var el = document.getElementById('listaMinhasConsultas');
                if (!el) return;

                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: {'Content-Type':'application/x-www-form-urlencoded'},
                    body: 'action=leilao_minhas_consultas&nonce=' + nonce,
                    credentials: 'same-origin'
                })
                .then(function(r){ return r.json(); })
                .then(function(r){
                    if (!r.success || !r.data.length) {
                        el.innerHTML = '<p class="consultas-empty">Nenhuma consulta solicitada ainda.</p>';
                        return;
                    }
                    var html = '<div class="consultas-table-wrap"><table class="consultas-table"><thead><tr><th>Serviço</th><th>Data</th><th>Valor</th><th>Status</th><th>Resultado</th></tr></thead><tbody>';
                    r.data.forEach(function(c){
                        var statusClass = 'status-' + c.status.replace(/_/g, '-');
                        var statusLabel = {
                            'aguardando_pagamento':'Aguardando Pagamento',
                            'pago':'Pagamento Confirmado',
                            'processando':'Processando',
                            'concluido':'Concluído',
                            'cancelado':'Cancelado'
                        }[c.status] || c.status;

                        var resultado = c.resultado_url ? '<a href="'+c.resultado_url+'" target="_blank" class="consulta-download">📥 Baixar</a>' : '—';

                        html += '<tr>';
                        html += '<td><strong>' + c.servico_nome + '</strong></td>';
                        html += '<td>' + c.data + '</td>';
                        html += '<td>R$ ' + c.preco + '</td>';
                        html += '<td><span class="consulta-status ' + statusClass + '">' + statusLabel + '</span></td>';
                        html += '<td>' + resultado + '</td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table></div>';
                    el.innerHTML = html;
                });
            }

            // Carregar ao abrir página
            if (document.getElementById('listaMinhasConsultas')) {
                carregarMinhas();
            }

            // Auto-abrir modal se URL tiver hash com nome do serviço
            var hash = window.location.hash.replace('#', '');
            if (hash && SERVICOS[hash]) {
                window.abrirConsulta(hash);
            }
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    /* ------------------------------------------------------------------ */
    /*  AJAX — SOLICITAR CONSULTA                                         */
    /* ------------------------------------------------------------------ */

    public static function ajax_solicitar(): void {
        check_ajax_referer('leilao_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Faça login para solicitar consultas.']);
        }

        $servico = sanitize_text_field($_POST['servico'] ?? '');
        if (!isset(self::SERVICOS[$servico])) {
            wp_send_json_error(['message' => 'Serviço inválido.']);
        }

        $svc = self::SERVICOS[$servico];

        // Coletar dados do formulário
        $dados = [];
        foreach ($svc['campos'] as $campo) {
            $dados[$campo] = sanitize_text_field($_POST[$campo] ?? '');
            if (in_array($campo, ['matricula', 'estado', 'cidade', 'cpf_cnpj', 'nome_completo'])) {
                if (empty($dados[$campo])) {
                    wp_send_json_error(['message' => 'Preencha todos os campos obrigatórios.']);
                }
            }
        }
        $dados['observacoes'] = sanitize_textarea_field($_POST['observacoes'] ?? '');

        // Upload do comprovante
        if (empty($_FILES['comprovante']) || $_FILES['comprovante']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(['message' => 'Anexe o comprovante de pagamento PIX.']);
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $upload = wp_handle_upload($_FILES['comprovante'], ['test_form' => false]);
        if (isset($upload['error'])) {
            wp_send_json_error(['message' => 'Erro no upload: ' . $upload['error']]);
        }

        // Inserir no banco
        global $wpdb;
        $table = $wpdb->prefix . 'leilao_consultas';

        $wpdb->insert($table, [
            'user_id'          => get_current_user_id(),
            'servico'          => $servico,
            'dados'            => wp_json_encode($dados, JSON_UNESCAPED_UNICODE),
            'preco'            => $svc['preco'],
            'status'           => 'pago',
            'comprovante_url'  => esc_url_raw($upload['url']),
            'ip'               => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
        ], ['%d', '%s', '%s', '%f', '%s', '%s', '%s']);

        $id = $wpdb->insert_id;

        // E-mail para admin
        $user = wp_get_current_user();
        $admin_email = get_option('admin_email');
        $subject = '[Qatar Leilões] Nova Consulta #' . $id . ' — ' . $svc['nome'];
        $body  = "Nova solicitação de consulta:\n\n";
        $body .= "ID: #{$id}\n";
        $body .= "Serviço: {$svc['nome']}\n";
        $body .= "Valor: R$ " . number_format($svc['preco'], 2, ',', '.') . "\n";
        $body .= "Cliente: {$user->display_name} ({$user->user_email})\n\n";
        foreach ($dados as $k => $v) {
            if ($v) $body .= ucfirst(str_replace('_', ' ', $k)) . ": {$v}\n";
        }
        $body .= "\nComprovante: {$upload['url']}\n";
        $body .= "\nGerencie em: " . admin_url('admin.php?page=leilao-consultas');

        wp_mail($admin_email, $subject, $body);

        wp_send_json_success([
            'message' => 'Solicitação #' . $id . ' enviada com sucesso! Você receberá o resultado em até ' . $svc['prazo'] . '.',
            'id'      => $id,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  AJAX — MINHAS CONSULTAS                                           */
    /* ------------------------------------------------------------------ */

    public static function ajax_minhas(): void {
        check_ajax_referer('leilao_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Não autenticado.']);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'leilao_consultas';
        $rows  = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d ORDER BY criado_em DESC LIMIT 50",
            get_current_user_id()
        ));

        $result = [];
        foreach ($rows as $row) {
            $svc = self::SERVICOS[$row->servico] ?? null;
            $result[] = [
                'id'            => $row->id,
                'servico'       => $row->servico,
                'servico_nome'  => $svc ? $svc['nome'] : $row->servico,
                'preco'         => number_format(floatval($row->preco), 2, ',', '.'),
                'status'        => $row->status,
                'data'          => date('d/m/Y H:i', strtotime($row->criado_em)),
                'resultado_url' => $row->resultado_url ?: '',
            ];
        }

        wp_send_json_success($result);
    }

    /* ------------------------------------------------------------------ */
    /*  ADMIN MENU                                                        */
    /* ------------------------------------------------------------------ */

    public static function admin_menu(): void {
        add_menu_page(
            'Consultas',
            'Consultas',
            'manage_options',
            'leilao-consultas',
            [__CLASS__, 'admin_page'],
            'dashicons-search',
            30
        );
    }

    /* ------------------------------------------------------------------ */
    /*  ADMIN PAGE                                                        */
    /* ------------------------------------------------------------------ */

    public static function admin_page(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'leilao_consultas';

        // Handle status update
        if (isset($_POST['consulta_action']) && wp_verify_nonce($_POST['_wpnonce'] ?? '', 'leilao_consulta_admin')) {
            $cid     = intval($_POST['consulta_id']);
            $action  = sanitize_text_field($_POST['consulta_action']);

            if ($action === 'upload_resultado' && !empty($_FILES['resultado'])) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                $upload = wp_handle_upload($_FILES['resultado'], ['test_form' => false]);
                if (!isset($upload['error'])) {
                    $wpdb->update($table,
                        ['resultado_url' => esc_url_raw($upload['url']), 'status' => 'concluido'],
                        ['id' => $cid], ['%s', '%s'], ['%d']
                    );

                    // Notificar usuário
                    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $cid));
                    if ($row) {
                        $user = get_user_by('id', $row->user_id);
                        $svc  = self::SERVICOS[$row->servico] ?? null;
                        if ($user && $svc) {
                            wp_mail(
                                $user->user_email,
                                '[Qatar Leilões] Sua consulta está pronta — ' . $svc['nome'],
                                "Olá {$user->display_name},\n\n"
                                . "Sua solicitação de {$svc['nome']} (#{$cid}) foi concluída!\n\n"
                                . "Acesse seu painel para baixar o resultado:\n"
                                . home_url('/consultas/') . "\n\n"
                                . "Equipe Qatar Leilões"
                            );
                        }
                    }
                }
            } elseif (in_array($action, ['processando', 'cancelado', 'pago'])) {
                $wpdb->update($table, ['status' => $action], ['id' => $cid], ['%s'], ['%d']);

                // Se cancelado, notificar
                if ($action === 'cancelado') {
                    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $cid));
                    if ($row) {
                        $user = get_user_by('id', $row->user_id);
                        $svc  = self::SERVICOS[$row->servico] ?? null;
                        $nota = sanitize_textarea_field($_POST['admin_nota'] ?? '');
                        if ($nota) {
                            $wpdb->update($table, ['admin_nota' => $nota], ['id' => $cid]);
                        }
                        if ($user && $svc) {
                            wp_mail(
                                $user->user_email,
                                '[Qatar Leilões] Consulta cancelada — ' . $svc['nome'],
                                "Olá {$user->display_name},\n\n"
                                . "Sua solicitação de {$svc['nome']} (#{$cid}) foi cancelada.\n"
                                . ($nota ? "Motivo: {$nota}\n" : '')
                                . "\nEquipe Qatar Leilões"
                            );
                        }
                    }
                }
            }

            echo '<div class="updated"><p>Consulta #' . $cid . ' atualizada.</p></div>';
        }

        // List
        $status_filter = sanitize_text_field($_GET['status'] ?? '');
        $where = '';
        if ($status_filter) {
            $where = $wpdb->prepare(" WHERE status = %s", $status_filter);
        }

        $rows = $wpdb->get_results("SELECT * FROM {$table}{$where} ORDER BY criado_em DESC LIMIT 200");
        $counts = $wpdb->get_results("SELECT status, COUNT(*) as total FROM {$table} GROUP BY status");
        $count_map = [];
        foreach ($counts as $c) $count_map[$c->status] = $c->total;

        ?>
        <div class="wrap">
            <h1>📋 Consultas Imobiliárias</h1>

            <!-- Filtros -->
            <div class="tablenav top" style="margin-bottom:16px">
                <a href="?page=leilao-consultas" class="button <?php echo !$status_filter ? 'button-primary' : ''; ?>">
                    Todas (<?php echo array_sum(array_map('intval', $count_map)); ?>)
                </a>
                <?php
                $statuses = ['pago'=>'Pagos', 'processando'=>'Processando', 'concluido'=>'Concluídos', 'cancelado'=>'Cancelados', 'aguardando_pagamento'=>'Aguardando'];
                foreach ($statuses as $sk => $sl):
                ?>
                <a href="?page=leilao-consultas&status=<?php echo $sk; ?>" class="button <?php echo $status_filter===$sk ? 'button-primary' : ''; ?>">
                    <?php echo $sl; ?> (<?php echo intval($count_map[$sk] ?? 0); ?>)
                </a>
                <?php endforeach; ?>
            </div>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Serviço</th>
                        <th>Cliente</th>
                        <th>Dados</th>
                        <th>Valor</th>
                        <th>Status</th>
                        <th>Comprovante</th>
                        <th>Data</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="9">Nenhuma consulta encontrada.</td></tr>
                <?php else: foreach ($rows as $row):
                    $user = get_user_by('id', $row->user_id);
                    $svc  = self::SERVICOS[$row->servico] ?? null;
                    $dados = json_decode($row->dados, true) ?: [];
                ?>
                    <tr>
                        <td><strong>#<?php echo $row->id; ?></strong></td>
                        <td><?php echo $svc ? esc_html($svc['nome']) : esc_html($row->servico); ?></td>
                        <td>
                            <?php echo $user ? esc_html($user->display_name) . '<br><small>' . esc_html($user->user_email) . '</small>' : 'ID: '.$row->user_id; ?>
                        </td>
                        <td style="max-width:200px;font-size:12px">
                            <?php foreach ($dados as $k => $v): if ($v && $k !== 'observacoes'): ?>
                                <strong><?php echo ucfirst(str_replace('_', ' ', $k)); ?>:</strong> <?php echo esc_html($v); ?><br>
                            <?php endif; endforeach; ?>
                            <?php if (!empty($dados['observacoes'])): ?>
                                <em>Obs: <?php echo esc_html($dados['observacoes']); ?></em>
                            <?php endif; ?>
                        </td>
                        <td>R$ <?php echo number_format(floatval($row->preco), 2, ',', '.'); ?></td>
                        <td>
                            <?php
                            $badges = [
                                'aguardando_pagamento' => 'background:#f0ad4e;color:#fff',
                                'pago'        => 'background:#5bc0de;color:#fff',
                                'processando' => 'background:#0275d8;color:#fff',
                                'concluido'   => 'background:#5cb85c;color:#fff',
                                'cancelado'   => 'background:#d9534f;color:#fff',
                            ];
                            $labels = [
                                'aguardando_pagamento' => 'Aguardando',
                                'pago'        => 'Pago',
                                'processando' => 'Processando',
                                'concluido'   => 'Concluído',
                                'cancelado'   => 'Cancelado',
                            ];
                            $style = $badges[$row->status] ?? 'background:#999;color:#fff';
                            ?>
                            <span style="<?php echo $style; ?>;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600">
                                <?php echo $labels[$row->status] ?? $row->status; ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($row->comprovante_url): ?>
                                <a href="<?php echo esc_url($row->comprovante_url); ?>" target="_blank">📎 Ver</a>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td style="font-size:12px"><?php echo date('d/m/Y H:i', strtotime($row->criado_em)); ?></td>
                        <td style="min-width:180px">
                            <?php if ($row->status === 'pago'): ?>
                                <form method="post" style="display:inline">
                                    <?php wp_nonce_field('leilao_consulta_admin'); ?>
                                    <input type="hidden" name="consulta_id" value="<?php echo $row->id; ?>" />
                                    <input type="hidden" name="consulta_action" value="processando" />
                                    <button type="submit" class="button button-primary button-small">▶ Processar</button>
                                </form>
                            <?php endif; ?>

                            <?php if (in_array($row->status, ['pago', 'processando'])): ?>
                                <form method="post" enctype="multipart/form-data" style="margin-top:6px">
                                    <?php wp_nonce_field('leilao_consulta_admin'); ?>
                                    <input type="hidden" name="consulta_id" value="<?php echo $row->id; ?>" />
                                    <input type="hidden" name="consulta_action" value="upload_resultado" />
                                    <input type="file" name="resultado" accept=".pdf,image/*" style="font-size:11px;margin-bottom:4px" required />
                                    <button type="submit" class="button button-small">📤 Enviar Resultado</button>
                                </form>
                            <?php endif; ?>

                            <?php if ($row->status === 'concluido' && $row->resultado_url): ?>
                                <a href="<?php echo esc_url($row->resultado_url); ?>" target="_blank" class="button button-small">📥 Ver Resultado</a>
                            <?php endif; ?>

                            <?php if (!in_array($row->status, ['concluido', 'cancelado'])): ?>
                                <form method="post" style="margin-top:6px">
                                    <?php wp_nonce_field('leilao_consulta_admin'); ?>
                                    <input type="hidden" name="consulta_id" value="<?php echo $row->id; ?>" />
                                    <input type="hidden" name="consulta_action" value="cancelado" />
                                    <input type="text" name="admin_nota" placeholder="Motivo..." style="font-size:11px;width:120px" />
                                    <button type="submit" class="button button-small" style="color:#d9534f" onclick="return confirm('Cancelar esta consulta?')">✖ Cancelar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>

            <div style="margin-top:20px;padding:16px;background:#f9f9f9;border-radius:8px;font-size:13px">
                <h3>⚙️ Configurações PIX</h3>
                <form method="post" action="options.php">
                    <?php settings_fields('leilao_consultas_settings'); ?>
                    <table class="form-table">
                        <tr>
                            <th>Chave PIX</th>
                            <td><input type="text" name="leilao_pix_key" value="<?php echo esc_attr(get_option('leilao_pix_key', 'contato@qatarleiloes.com.br')); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th>Nome do Titular</th>
                            <td><input type="text" name="leilao_pix_nome" value="<?php echo esc_attr(get_option('leilao_pix_nome', 'Qatar Leilões')); ?>" class="regular-text" /></td>
                        </tr>
                    </table>
                    <?php submit_button('Salvar Configurações PIX'); ?>
                </form>
            </div>
        </div>
        <?php
    }
}

// Register settings
add_action('admin_init', function() {
    register_setting('leilao_consultas_settings', 'leilao_pix_key');
    register_setting('leilao_consultas_settings', 'leilao_pix_nome');
});
