#!/usr/bin/env python3
"""
Patch: Gerenciamento de Documentos no Front-end
- api/auth.php  → adiciona campo 'roles' na resposta
- dashboard.html → nav admin, painel admin (arrematações, usuários, imóveis),
                   modal doc arrematante, modal detalhe admin
- dashboard.js  → carrega lances via API, renderiza cards com botão doc,
                  funções admin arrematações, funções arrematante
"""

# ─── Helpers ─────────────────────────────────────────────────────────────────

def patch(src, old, new, label):
    if old not in src:
        print(f"  [SKIP] Já aplicado ou âncora não encontrada: {label}")
        return src
    print(f"  [OK]   {label}")
    return src.replace(old, new, 1)

# ═══════════════════════════════════════════════════════════════════════════════
# PATCH auth.php - adicionar campo 'roles'
# ═══════════════════════════════════════════════════════════════════════════════
print("\n── Patching api/auth.php ──")

with open('api/auth.php', 'r', encoding='utf-8') as f:
    auth = f.read()

auth = patch(auth,
    "        'endereco' => [\n            'cep'    => isset($meta['billing_postcode'][0])  ? $meta['billing_postcode'][0]  : '',\n            'rua'    => isset($meta['billing_address_1'][0]) ? $meta['billing_address_1'][0] : '',\n            'cidade' => isset($meta['billing_city'][0])      ? $meta['billing_city'][0]      : '',\n            'uf'     => isset($meta['billing_state'][0])     ? $meta['billing_state'][0]     : '',\n        ],\n    ];\n}",
    "        'roles'    => array_values($user->roles),\n        'endereco' => [\n            'cep'    => isset($meta['billing_postcode'][0])  ? $meta['billing_postcode'][0]  : '',\n            'rua'    => isset($meta['billing_address_1'][0]) ? $meta['billing_address_1'][0] : '',\n            'cidade' => isset($meta['billing_city'][0])      ? $meta['billing_city'][0]      : '',\n            'uf'     => isset($meta['billing_state'][0])     ? $meta['billing_state'][0]     : '',\n        ],\n    ];\n}",
    "adicionar campo roles"
)

with open('api/auth.php', 'w', encoding='utf-8') as f:
    f.write(auth)

# ═══════════════════════════════════════════════════════════════════════════════
# PATCH dashboard.html
# ═══════════════════════════════════════════════════════════════════════════════
print("\n── Patching dashboard.html ──")

with open('dashboard.html', 'r', encoding='utf-8') as f:
    html = f.read()

# 1. Admin nav items (antes de </nav>)
ADMIN_NAV = '''
        <!--  ── Admin nav (oculto por padrão) ── -->
        <hr class="dash__nav-admin" style="display:none;border:none;border-top:1px solid #e4e8f0;margin:8px 12px;">
        <p class="dash__nav-admin" style="display:none;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#a0aec0;padding:4px 20px 2px">Administração</p>
        <button class="dash__nav-item dash__nav-admin" data-painel="adminArrematacoes"
                onclick="trocarPainel('adminArrematacoes', this)" style="display:none">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
          Arrematações
          <span class="dash__nav-badge dash__nav-admin" id="badgeAdmArr" style="display:none">0</span>
        </button>
        <button class="dash__nav-item dash__nav-admin" data-painel="adminUsuarios"
                onclick="trocarPainel('adminUsuarios', this)" style="display:none">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          Usuários
          <span class="dash__nav-badge dash__nav-admin" id="badgeAdmUsuarios" style="display:none">0</span>
        </button>
        <button class="dash__nav-item dash__nav-admin" data-painel="adminImoveis"
                onclick="trocarPainel('adminImoveis', this)" style="display:none">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
          Imóveis
          <span class="dash__nav-badge dash__nav-admin" id="badgeAdmImoveis" style="display:none">0</span>
        </button>
        <button class="dash__nav-item dash__nav-admin" data-painel="adminImportacao"
                onclick="trocarPainel('adminImportacao', this)" style="display:none">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>
          Importação
        </button>
      </nav>'''

html = patch(html, '      </nav>', ADMIN_NAV, 'admin nav items')

# 2. Painel Admin Arrematações (antes de </main>)
ADMIN_PANELS = '''

      <!-- ══════ PAINEL ADMIN: ARREMATAÇÕES ══════ -->
      <section class="dash__painel" id="painelAdminArrematacoes">
        <div class="dash__painel-header">
          <div>
            <h1 class="dash__titulo">🏆 Arrematações</h1>
            <p class="dash__subtitulo">Gerencie todos os processos de documentação pós-arrematação</p>
          </div>
        </div>

        <!-- Stats -->
        <div class="dash__kpis" id="admArrStats">
          <div class="dash__kpi"><div class="dash__kpi-icone" style="background:#fff3cd">🏆</div><div class="dash__kpi-info"><span class="dash__kpi-valor" id="admArrStatTotal">0</span><span class="dash__kpi-label">Total</span></div></div>
          <div class="dash__kpi"><div class="dash__kpi-icone" style="background:#fde8d8">📤</div><div class="dash__kpi-info"><span class="dash__kpi-valor" id="admArrStatAguardando">0</span><span class="dash__kpi-label">Aguardando Docs</span></div></div>
          <div class="dash__kpi"><div class="dash__kpi-icone" style="background:#d4edda">✅</div><div class="dash__kpi-info"><span class="dash__kpi-valor" id="admArrStatAprovado">0</span><span class="dash__kpi-label">Aprovados</span></div></div>
          <div class="dash__kpi"><div class="dash__kpi-icone" style="background:#d1ecf1">🔍</div><div class="dash__kpi-info"><span class="dash__kpi-valor" id="admArrStatAnalise">0</span><span class="dash__kpi-label">Em Análise</span></div></div>
        </div>

        <!-- Filtros -->
        <div class="dash__filtros" style="display:flex;gap:10px;flex-wrap:wrap;margin:16px 0;">
          <input type="text" id="admArrBusca" placeholder="Buscar por imóvel, arrematante ou e-mail..." class="dash__input-busca" style="flex:1;min-width:220px;padding:8px 12px;border:1px solid #e2e8f0;border-radius:8px;">
          <select id="admArrFiltroStatus" class="dash__select" style="padding:8px 12px;border:1px solid #e2e8f0;border-radius:8px;">
            <option value="">Todos os status</option>
            <option value="aguardando_documentos">Envio de Documentos</option>
            <option value="documentos_enviados">Docs Enviados</option>
            <option value="em_analise">Em Análise</option>
            <option value="aprovado">Aprovado</option>
            <option value="reprovado">Reprovado</option>
            <option value="concluido">Concluído</option>
          </select>
          <button class="btn btn--primario btn--sm" onclick="admCarregarArrematacoes(1)">🔍 Buscar</button>
        </div>

        <!-- Tabela -->
        <div class="dash__tabela-wrap">
          <table class="dash__tabela" style="width:100%;border-collapse:collapse;">
            <thead>
              <tr style="background:#f8fafc;">
                <th style="padding:10px 12px;text-align:left;font-size:.8rem;color:#64748b;border-bottom:1px solid #e2e8f0;">Imóvel</th>
                <th style="padding:10px 12px;text-align:left;font-size:.8rem;color:#64748b;border-bottom:1px solid #e2e8f0;">Arrematante</th>
                <th style="padding:10px 12px;text-align:right;font-size:.8rem;color:#64748b;border-bottom:1px solid #e2e8f0;">Valor</th>
                <th style="padding:10px 12px;text-align:center;font-size:.8rem;color:#64748b;border-bottom:1px solid #e2e8f0;">Status</th>
                <th style="padding:10px 12px;text-align:center;font-size:.8rem;color:#64748b;border-bottom:1px solid #e2e8f0;">Prazo</th>
                <th style="padding:10px 12px;text-align:center;font-size:.8rem;color:#64748b;border-bottom:1px solid #e2e8f0;">Ações</th>
              </tr>
            </thead>
            <tbody id="tbodyAdmArr">
              <tr><td colspan="6" style="padding:32px;text-align:center;color:#94a3b8;">Carregando...</td></tr>
            </tbody>
          </table>
        </div>
        <div id="admArrPaginacao" style="display:flex;gap:8px;justify-content:center;margin-top:16px;"></div>
      </section>

      <!-- ══════ PAINEL ADMIN: USUÁRIOS ══════ -->
      <section class="dash__painel" id="painelAdminUsuarios">
        <div class="dash__painel-header">
          <div><h1 class="dash__titulo">👥 Usuários</h1><p class="dash__subtitulo">Gerencie os usuários cadastrados na plataforma</p></div>
        </div>
        <div style="display:flex;gap:10px;margin-bottom:16px;">
          <input type="text" id="admUserBusca" placeholder="Buscar por nome ou e-mail..." class="dash__input-busca" style="flex:1;padding:8px 12px;border:1px solid #e2e8f0;border-radius:8px;">
          <button class="btn btn--primario btn--sm" onclick="admBuscarUsuarios()">🔍 Buscar</button>
        </div>
        <div class="dash__tabela-wrap">
          <table class="dash__tabela" style="width:100%;border-collapse:collapse;">
            <thead><tr style="background:#f8fafc;">
              <th style="padding:10px 12px;text-align:left;font-size:.8rem;color:#64748b;border-bottom:1px solid #e2e8f0;">Nome</th>
              <th style="padding:10px 12px;text-align:left;font-size:.8rem;color:#64748b;border-bottom:1px solid #e2e8f0;">E-mail</th>
              <th style="padding:10px 12px;text-align:center;font-size:.8rem;color:#64748b;border-bottom:1px solid #e2e8f0;">Perfil</th>
              <th style="padding:10px 12px;text-align:center;font-size:.8rem;color:#64748b;border-bottom:1px solid #e2e8f0;">Cadastro</th>
              <th style="padding:10px 12px;text-align:center;font-size:.8rem;color:#64748b;border-bottom:1px solid #e2e8f0;">Ações</th>
            </tr></thead>
            <tbody id="tbodyAdmUsuarios"><tr><td colspan="5" style="padding:32px;text-align:center;color:#94a3b8;">Carregando...</td></tr></tbody>
          </table>
        </div>
        <div id="admUserPaginacao" style="display:flex;gap:8px;justify-content:center;margin-top:16px;"></div>
      </section>

      <!-- ══════ PAINEL ADMIN: IMÓVEIS ══════ -->
      <section class="dash__painel" id="painelAdminImoveis">
        <div class="dash__painel-header">
          <div><h1 class="dash__titulo">🏠 Imóveis</h1><p class="dash__subtitulo">Gerencie os imóveis cadastrados no sistema</p></div>
        </div>
        <div style="display:flex;gap:10px;margin-bottom:16px;">
          <input type="text" id="admImovBusca" placeholder="Buscar por título ou cidade..." class="dash__input-busca" style="flex:1;padding:8px 12px;border:1px solid #e2e8f0;border-radius:8px;">
          <button class="btn btn--primario btn--sm" onclick="admBuscarImoveis()">🔍 Buscar</button>
        </div>
        <div class="dash__tabela-wrap">
          <table class="dash__tabela" style="width:100%;border-collapse:collapse;">
            <thead><tr style="background:#f8fafc;">
              <th style="padding:10px 12px;text-align:left;font-size:.8rem;color:#64748b;border-bottom:1px solid #e2e8f0;">Título</th>
              <th style="padding:10px 12px;text-align:left;font-size:.8rem;color:#64748b;border-bottom:1px solid #e2e8f0;">Tipo</th>
              <th style="padding:10px 12px;text-align:right;font-size:.8rem;color:#64748b;border-bottom:1px solid #e2e8f0;">Preço</th>
              <th style="padding:10px 12px;text-align:center;font-size:.8rem;color:#64748b;border-bottom:1px solid #e2e8f0;">Status Leilão</th>
              <th style="padding:10px 12px;text-align:center;font-size:.8rem;color:#64748b;border-bottom:1px solid #e2e8f0;">Ações</th>
            </tr></thead>
            <tbody id="tbodyAdmImoveis"><tr><td colspan="5" style="padding:32px;text-align:center;color:#94a3b8;">Carregando...</td></tr></tbody>
          </table>
        </div>
        <div id="admImovPaginacao" style="display:flex;gap:8px;justify-content:center;margin-top:16px;"></div>
      </section>

      <!-- ══════ PAINEL ADMIN: IMPORTAÇÃO ══════ -->
      <section class="dash__painel" id="painelAdminImportacao">
        <div class="dash__painel-header">
          <div><h1 class="dash__titulo">⬆️ Importação</h1><p class="dash__subtitulo">Importe imóveis e veículos da Caixa Econômica Federal</p></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:16px;">
          <div class="dash__card-simples" style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:24px;">
            <h3 style="margin:0 0 8px;font-size:1rem;">🏠 Imóveis Caixa</h3>
            <p style="color:#64748b;font-size:.875rem;margin:0 0 16px;">Importar imóveis do CSV da Caixa Econômica Federal</p>
            <button class="btn btn--primario btn--sm" id="btnAdmImportImoveis" onclick="admRodarImport('imoveis')">▶ Iniciar Importação</button>
            <div id="logAdmImoveis" style="margin-top:12px;font-size:.8rem;color:#64748b;max-height:200px;overflow-y:auto;"></div>
          </div>
          <div class="dash__card-simples" style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:24px;">
            <h3 style="margin:0 0 8px;font-size:1rem;">🚗 Veículos Caixa</h3>
            <p style="color:#64748b;font-size:.875rem;margin:0 0 16px;">Importar veículos do CSV da Caixa Econômica Federal</p>
            <button class="btn btn--primario btn--sm" id="btnAdmImportVeiculos" onclick="admRodarImport('veiculos')">▶ Iniciar Importação</button>
            <div id="logAdmVeiculos" style="margin-top:12px;font-size:.8rem;color:#64748b;max-height:200px;overflow-y:auto;"></div>
          </div>
        </div>
      </section>'''

html = patch(html, '\n      <!-- ══════ PAINEL: FAVORITOS ══════ -->',
             ADMIN_PANELS + '\n\n      <!-- ══════ PAINEL: FAVORITOS ══════ -->',
             'painéis admin')

# 3. Modal arrematante + Modal detalhe admin (antes de </body>)
MODALS = '''
  <!-- ══════ MODAL: GERENCIAR DOCS (arrematante) ══════ -->
  <div id="modalDocOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9000;overflow-y:auto;padding:20px;" onclick="if(event.target===this)fecharModalDocs()">
    <div style="background:#fff;border-radius:16px;max-width:760px;margin:0 auto;overflow:hidden;">
      <div style="background:linear-gradient(135deg,#1e3a5f,#2563eb);color:#fff;padding:20px 24px;display:flex;justify-content:space-between;align-items:center;">
        <div>
          <h2 style="margin:0;font-size:1.2rem;">📄 Meu Processo de Arrematação</h2>
          <p id="mdocTitulo" style="margin:4px 0 0;font-size:.875rem;opacity:.85;"></p>
        </div>
        <button onclick="fecharModalDocs()" style="background:rgba(255,255,255,.2);border:none;color:#fff;width:32px;height:32px;border-radius:50%;cursor:pointer;font-size:1.2rem;display:flex;align-items:center;justify-content:center;">✕</button>
      </div>

      <!-- Status pipeline -->
      <div style="padding:16px 24px;background:#f8fafc;border-bottom:1px solid #e2e8f0;">
        <div id="mdocPipeline" style="display:flex;align-items:center;gap:0;flex-wrap:wrap;"></div>
        <div style="margin-top:8px;font-size:.8rem;color:#64748b;">
          <strong id="mdocStatusLabel"></strong>
          <span id="mdocPrazo"></span>
        </div>
      </div>

      <!-- Tabs -->
      <div style="display:flex;border-bottom:1px solid #e2e8f0;padding:0 24px;">
        <button class="mdoc-tab ativo" data-mdoc-tab="docs" onclick="mdocTrocarTab('docs',this)" style="padding:12px 16px;border:none;background:none;border-bottom:2px solid #2563eb;color:#2563eb;font-weight:600;cursor:pointer;font-size:.875rem;">📄 Documentos</button>
        <button class="mdoc-tab" data-mdoc-tab="timeline" onclick="mdocTrocarTab('timeline',this)" style="padding:12px 16px;border:none;background:none;border-bottom:2px solid transparent;color:#64748b;cursor:pointer;font-size:.875rem;">📅 Timeline</button>
      </div>

      <!-- Tab Documentos -->
      <div class="mdoc-tab-content ativo" id="mdoc-tab-docs" style="padding:24px;">
        <!-- Upload -->
        <div style="background:#f0f7ff;border:2px dashed #93c5fd;border-radius:12px;padding:20px;margin-bottom:20px;">
          <h4 style="margin:0 0 12px;color:#1e40af;">📤 Enviar novo documento</h4>
          <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
            <div style="flex:1;min-width:140px;">
              <label style="display:block;font-size:.8rem;color:#475569;margin-bottom:4px;">Tipo</label>
              <select id="mdocUpTipo" style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:8px;">
                <option value="identidade">RG / CNH</option>
                <option value="cpf">CPF</option>
                <option value="comprovante_renda">Comprovante de Renda</option>
                <option value="comprovante_end">Comprovante de Endereço</option>
                <option value="contrato_social">Contrato Social (PJ)</option>
                <option value="outros">Outros</option>
              </select>
            </div>
            <div style="flex:2;min-width:180px;">
              <label style="display:block;font-size:.8rem;color:#475569;margin-bottom:4px;">Arquivo (PDF, JPG, PNG, DOC — máx 10MB)</label>
              <input type="file" id="mdocUpArquivo" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" style="width:100%;padding:5px;border:1px solid #cbd5e1;border-radius:8px;font-size:.875rem;">
            </div>
            <button onclick="mdocEnviarDoc()" style="padding:9px 18px;background:#2563eb;color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:600;white-space:nowrap;">📤 Enviar</button>
          </div>
          <div id="mdocUpResult" style="margin-top:8px;font-size:.875rem;"></div>
        </div>

        <!-- Lista de documentos -->
        <div id="mdocDocsList"></div>
      </div>

      <!-- Tab Timeline -->
      <div class="mdoc-tab-content" id="mdoc-tab-timeline" style="display:none;padding:24px;">
        <div id="mdocTimeline"></div>
      </div>
    </div>
  </div>

  <!-- ══════ MODAL ADMIN: DETALHE ARREMATAÇÃO ══════ -->
  <div id="admModalArrOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9100;overflow-y:auto;padding:20px;" onclick="if(event.target===this)admFecharDetalheArr()">
    <div style="background:#fff;border-radius:16px;max-width:820px;margin:0 auto;overflow:hidden;">
      <div style="background:linear-gradient(135deg,#1e3a5f,#7c3aed);color:#fff;padding:20px 24px;display:flex;justify-content:space-between;align-items:center;">
        <div>
          <h2 style="margin:0;font-size:1.2rem;">🏆 Gerenciar Arrematação</h2>
          <p id="admArrModalTitulo" style="margin:4px 0 0;font-size:.875rem;opacity:.85;"></p>
        </div>
        <button onclick="admFecharDetalheArr()" style="background:rgba(255,255,255,.2);border:none;color:#fff;width:32px;height:32px;border-radius:50%;cursor:pointer;font-size:1.2rem;display:flex;align-items:center;justify-content:center;">✕</button>
      </div>

      <!-- Resumo -->
      <div id="admArrModalResumo" style="padding:16px 24px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;gap:24px;flex-wrap:wrap;"></div>

      <!-- Tabs -->
      <div style="display:flex;border-bottom:1px solid #e2e8f0;padding:0 24px;overflow-x:auto;">
        <button class="admArr-tab ativo" onclick="admArrTrocarTab('docs',this)" style="padding:12px 14px;border:none;background:none;border-bottom:2px solid #7c3aed;color:#7c3aed;font-weight:600;cursor:pointer;font-size:.85rem;white-space:nowrap;">📄 Documentos</button>
        <button class="admArr-tab" onclick="admArrTrocarTab('status',this)" style="padding:12px 14px;border:none;background:none;border-bottom:2px solid transparent;color:#64748b;cursor:pointer;font-size:.85rem;white-space:nowrap;">🔄 Status</button>
        <button class="admArr-tab" onclick="admArrTrocarTab('notif',this)" style="padding:12px 14px;border:none;background:none;border-bottom:2px solid transparent;color:#64748b;cursor:pointer;font-size:.85rem;white-space:nowrap;">📧 Notificações</button>
        <button class="admArr-tab" onclick="admArrTrocarTab('timeline',this)" style="padding:12px 14px;border:none;background:none;border-bottom:2px solid transparent;color:#64748b;cursor:pointer;font-size:.85rem;white-space:nowrap;">📅 Timeline</button>
      </div>

      <!-- Tab: Documentos -->
      <div class="admArr-tab-content ativo" id="admArr-tab-docs" style="padding:20px 24px;">
        <!-- Upload admin -->
        <div style="background:#f5f3ff;border:2px dashed #a78bfa;border-radius:10px;padding:16px;margin-bottom:16px;">
          <h4 style="margin:0 0 10px;color:#5b21b6;font-size:.95rem;">📤 Adicionar documento</h4>
          <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
            <select id="admArrUpTipo" style="padding:8px;border:1px solid #cbd5e1;border-radius:8px;">
              <option value="identidade">RG / CNH</option>
              <option value="cpf">CPF</option>
              <option value="comprovante_renda">Comprovante de Renda</option>
              <option value="comprovante_end">Comprovante de Endereço</option>
              <option value="contrato_social">Contrato Social (PJ)</option>
              <option value="outros">Outros</option>
            </select>
            <input type="file" id="admArrUpArquivo" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" style="flex:1;padding:5px;border:1px solid #cbd5e1;border-radius:8px;font-size:.875rem;">
            <button onclick="admUploadDoc()" style="padding:8px 16px;background:#7c3aed;color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:600;">📤 Enviar</button>
          </div>
          <div id="admArrUpResult" style="margin-top:8px;font-size:.875rem;"></div>
        </div>
        <div id="admArrDocsList"></div>
      </div>

      <!-- Tab: Status -->
      <div class="admArr-tab-content" id="admArr-tab-status" style="display:none;padding:20px 24px;">
        <div id="admArrPipeline" style="display:flex;align-items:center;gap:0;flex-wrap:wrap;margin-bottom:20px;"></div>
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px;">
          <h4 style="margin:0 0 12px;font-size:.95rem;">Alterar Status do Processo</h4>
          <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <select id="admArrNovoStatus" style="flex:1;min-width:200px;padding:9px;border:1px solid #cbd5e1;border-radius:8px;">
              <option value="aguardando_documentos">Envio de Documentos</option>
              <option value="documentos_enviados">Docs Enviados</option>
              <option value="em_analise">Em Análise</option>
              <option value="aprovado">Aprovado</option>
              <option value="reprovado">Reprovado</option>
              <option value="concluido">Concluído</option>
            </select>
            <button onclick="admAlterarStatus()" style="padding:9px 18px;background:#7c3aed;color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:600;">🔄 Atualizar</button>
            <span id="admArrStatusResult" style="font-size:.875rem;"></span>
          </div>
        </div>
      </div>

      <!-- Tab: Notificações -->
      <div class="admArr-tab-content" id="admArr-tab-notif" style="display:none;padding:20px 24px;">
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px;">
          <h4 style="margin:0 0 12px;font-size:.95rem;">📧 Enviar Notificação ao Arrematante</h4>
          <p id="admArrNotifDest" style="color:#64748b;font-size:.875rem;margin:0 0 12px;"></p>
          <div style="display:flex;gap:10px;margin-bottom:10px;flex-wrap:wrap;">
            <select id="admArrNotifTemplate" onchange="admArrAplicarTemplate()" style="flex:1;padding:9px;border:1px solid #cbd5e1;border-radius:8px;">
              <option value="">— Selecionar modelo —</option>
              <option value="docs_pendentes">Lembrete: documentos pendentes</option>
              <option value="docs_reprovados">Aviso: documentos reprovados</option>
              <option value="aprovacao">Parabéns: documentação aprovada</option>
              <option value="prazo_expirado">Urgente: prazo expirado</option>
              <option value="concluido">Processo concluído</option>
            </select>
          </div>
          <input type="text" id="admArrNotifAssunto" placeholder="Assunto do e-mail" style="width:100%;padding:9px;border:1px solid #cbd5e1;border-radius:8px;margin-bottom:10px;box-sizing:border-box;">
          <textarea id="admArrNotifMensagem" rows="7" placeholder="Mensagem para o arrematante..." style="width:100%;padding:9px;border:1px solid #cbd5e1;border-radius:8px;resize:vertical;box-sizing:border-box;"></textarea>
          <div style="display:flex;gap:10px;margin-top:10px;align-items:center;">
            <button onclick="admEnviarNotif()" style="padding:9px 18px;background:#7c3aed;color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:600;">📧 Enviar E-mail</button>
            <span id="admArrNotifResult" style="font-size:.875rem;"></span>
          </div>
        </div>
      </div>

      <!-- Tab: Timeline -->
      <div class="admArr-tab-content" id="admArr-tab-timeline" style="display:none;padding:20px 24px;">
        <div id="admArrTimeline"></div>
      </div>

      <!-- ID oculto -->
      <input type="hidden" id="admArrDetalheId" value="">
    </div>
  </div>

  <!-- ══════ MODAL ADMIN: ALTERAR ROLE USUÁRIO ══════ -->
  <div id="admModalRoleOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9200;display:none;align-items:center;justify-content:center;" onclick="if(event.target===this)admFecharModalRole()">
    <div style="background:#fff;border-radius:16px;padding:28px;max-width:420px;width:90%;margin:auto;margin-top:80px;">
      <h3 style="margin:0 0 16px;">Alterar Perfil do Usuário</h3>
      <input type="hidden" id="admRoleUserId">
      <p id="admRoleUserNome" style="color:#64748b;margin:0 0 12px;"></p>
      <select id="admRoleSelect" style="width:100%;padding:9px;border:1px solid #cbd5e1;border-radius:8px;margin-bottom:16px;">
        <option value="subscriber">Assinante (padrão)</option>
        <option value="editor">Editor</option>
        <option value="administrator">Administrador</option>
      </select>
      <div style="display:flex;gap:10px;">
        <button onclick="admSalvarRole()" style="flex:1;padding:10px;background:#2563eb;color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:600;">Salvar</button>
        <button onclick="admFecharModalRole()" style="flex:1;padding:10px;background:#f1f5f9;border:none;border-radius:8px;cursor:pointer;">Cancelar</button>
      </div>
    </div>
  </div>
'''

html = patch(html, '\n  <!-- Toasts -->', MODALS + '\n  <!-- Toasts -->', 'modais')

with open('dashboard.html', 'w', encoding='utf-8') as f:
    f.write(html)

print("  dashboard.html salvo.")

# ═══════════════════════════════════════════════════════════════════════════════
# PATCH dashboard.js
# ═══════════════════════════════════════════════════════════════════════════════
print("\n── Patching dashboard.js ──")

with open('dashboard.js', 'r', encoding='utf-8') as f:
    js = f.read()

# 1. preencherDadosUsuario → ativarModoAdmin para administradores
js = patch(js,
    "  // Config - dados pessoais",
    "  // Modo admin\n  if (u.roles && u.roles.includes('administrator')) {\n    ativarModoAdmin();\n  }\n\n  // Config - dados pessoais",
    "ativarModoAdmin no preencherDadosUsuario"
)

# 2. renderLeilaoCard → botão "Gerenciar Docs" para leilões ganhos
js = patch(js,
    "        <div class=\"dash__leilao-footer\">\n          <div>\n            <div class=\"dash__leilao-preco-label\">Meu lance</div>\n            <div class=\"dash__leilao-preco\">R$ ${item.meuLance.toLocaleString('pt-BR')}</div>\n          </div>\n          <span class=\"dash__leilao-data\">📅 ${item.dataLeilao}</span>\n        </div>",
    """        <div class="dash__leilao-footer">
          <div>
            <div class="dash__leilao-preco-label">Meu lance</div>
            <div class="dash__leilao-preco">R$ ${item.meuLance.toLocaleString('pt-BR')}</div>
          </div>
          <span class="dash__leilao-data">📅 ${item.dataLeilao}</span>
        </div>
        ${item.status === 'ganho' ? `<button class="btn btn--primario btn--sm" style="width:100%;margin-top:10px;" onclick="abrirGerenciarDocs(${item.imovel_id})">📄 Gerenciar Documentos</button>` : ''}""",
    "botão Gerenciar Docs no card de leilão ganho"
)

# 3. DOMContentLoaded → carregar lances da API
js = patch(js,
    "document.addEventListener('DOMContentLoaded', function() {",
    "document.addEventListener('DOMContentLoaded', function() {\n  carregarLancesUsuario();",
    "carregarLancesUsuario no DOMContentLoaded"
)

# 4. renderListaLeiloes → usa LEILOES_USUARIO com campo imovel_id normalizado
OLD_RENDER = "function renderListaLeiloes(filtro) {\n  const container = document.getElementById('listaLeiloes');"
NEW_RENDER = """function carregarLancesUsuario() {
  fetch('/api/arrematacao.php?action=meus_lances', { credentials: 'same-origin' })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (!data.ok) return;
      LEILOES_USUARIO = data.lances.map(function(l) {
        return {
          imovel_id:      l.imovel_id,
          titulo:         l.titulo,
          imagem:         l.imagem || '/wp-content/themes/leilao-saysix-theme/assets/img/placeholder.jpg',
          local:          '',
          detalhes:       'Valor final: R$ ' + (l.valor_final ? l.valor_final.toLocaleString('pt-BR', {minimumFractionDigits:2}) : '—'),
          status:         l.status,
          meuLance:       l.meu_lance || 0,
          dataLeilao:     l.data_limite_docs ? 'Prazo docs: ' + new Date(l.data_limite_docs).toLocaleDateString('pt-BR') : '',
          arrematacao_id: l.arrematacao_id,
          arr_status:     l.arr_status,
        };
      });
      var badge = document.getElementById('badgeLeiloes');
      if (LEILOES_USUARIO.length && badge) {
        badge.textContent = LEILOES_USUARIO.length;
        badge.style.display = '';
      }
      renderListaLeiloes('todos');
      renderResumoLeiloes();
    })
    .catch(function() {});
}

function renderListaLeiloes(filtro) {
  const container = document.getElementById('listaLeiloes');"""

js = patch(js, OLD_RENDER, NEW_RENDER, "carregarLancesUsuario + renderListaLeiloes")

# 5. Append all admin + arrematante JS
JS_APPEND = """

/* ============================================================================
   MODO ADMIN
   ============================================================================ */
var ADM_CURRENT_ARR_ID = null;

function ativarModoAdmin() {
  document.querySelectorAll('.dash__nav-admin').forEach(function(el) {
    el.style.display = '';
  });
  // Ocultar abas de usuário comum
  document.querySelectorAll('[data-painel="leiloes"],[data-painel="favoritos"]').forEach(function(b) {
    b.style.display = 'none';
  });
  admCarregarStats();
  admCarregarArrematacoes(1);
}

/* ── Stats gerais ── */
function admCarregarStats() {
  fetch('/api/admin.php?action=stats', { credentials: 'same-origin' })
    .then(function(r) { return r.json(); })
    .then(function(d) {
      if (!d.ok) return;
      var el = {
        tot:  document.getElementById('admArrStatTotal'),
        agu:  document.getElementById('admArrStatAguardando'),
        apr:  document.getElementById('admArrStatAprovado'),
        ana:  document.getElementById('admArrStatAnalise'),
      };
      // Stats de usuários para badge
      var badgeU = document.getElementById('badgeAdmUsuarios');
      if (badgeU && d.stats && d.stats.usuarios) { badgeU.textContent = d.stats.usuarios; badgeU.style.display = ''; }
      var badgeI = document.getElementById('badgeAdmImoveis');
      if (badgeI && d.stats && d.stats.imoveis) { badgeI.textContent = d.stats.imoveis; badgeI.style.display = ''; }
    }).catch(function(){});
}

/* ── Arrematações ── */
function admCarregarArrematacoes(pagina) {
  pagina = pagina || 1;
  var busca   = (document.getElementById('admArrBusca')         || {}).value || '';
  var status  = (document.getElementById('admArrFiltroStatus')  || {}).value || '';
  var url = '/api/arrematacao.php?action=listar_admin&page=' + pagina
    + '&search=' + encodeURIComponent(busca)
    + '&status=' + encodeURIComponent(status);

  fetch(url, { credentials: 'same-origin' })
    .then(function(r) { return r.json(); })
    .then(function(d) {
      if (!d.ok) { return; }

      // Stats
      var stats = d.stats || {};
      admSetEl('admArrStatTotal',    stats.total || 0);
      admSetEl('admArrStatAguardando',(stats.aguardando_documentos || 0) + (stats.documentos_enviados || 0));
      admSetEl('admArrStatAprovado', stats.aprovado || 0);
      admSetEl('admArrStatAnalise',  stats.em_analise || 0);
      var badgeArr = document.getElementById('badgeAdmArr');
      if (badgeArr) { badgeArr.textContent = d.total; badgeArr.style.display = d.total ? '' : 'none'; }

      // Tabela
      var tbody = document.getElementById('tbodyAdmArr');
      if (!tbody) return;
      if (!d.itens || !d.itens.length) {
        tbody.innerHTML = '<tr><td colspan="6" style="padding:32px;text-align:center;color:#94a3b8;">Nenhuma arrematação encontrada.</td></tr>';
        return;
      }
      tbody.innerHTML = d.itens.map(function(item) {
        var corStatus = {
          aguardando_documentos: '#f59e0b', documentos_enviados: '#3b82f6',
          em_analise: '#8b5cf6', aprovado: '#10b981',
          reprovado: '#ef4444', concluido: '#6b7280', aguardando_confirmacao: '#6b7280'
        }[item.status] || '#64748b';
        var prazo = item.data_limite_docs ? new Date(item.data_limite_docs).toLocaleDateString('pt-BR') : '—';
        var diff  = item.data_limite_docs ? Math.ceil((new Date(item.data_limite_docs) - new Date()) / 86400000) : null;
        var prazoHtml = prazo;
        if (diff !== null) {
          prazoHtml += diff < 0
            ? ' <span style="color:#ef4444;font-size:.75rem;font-weight:700;">(expirado)</span>'
            : diff <= 3
              ? ' <span style="color:#f59e0b;font-size:.75rem;">(' + diff + 'd)</span>'
              : ' <span style="color:#64748b;font-size:.75rem;">(' + diff + 'd)</span>';
        }
        return '<tr style="border-bottom:1px solid #f1f5f9;">'
          + '<td style="padding:10px 12px;font-size:.875rem;">' + escHtml(item.imovel_titulo) + '</td>'
          + '<td style="padding:10px 12px;font-size:.875rem;">' + escHtml(item.arrematante) + '<br><span style="color:#94a3b8;font-size:.75rem;">' + escHtml(item.email) + '</span></td>'
          + '<td style="padding:10px 12px;text-align:right;font-size:.875rem;font-weight:600;">R$ ' + parseFloat(item.valor_final).toLocaleString('pt-BR',{minimumFractionDigits:2}) + '</td>'
          + '<td style="padding:10px 12px;text-align:center;"><span style="background:' + corStatus + '20;color:' + corStatus + ';padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:700;">' + escHtml(item.status_label) + '</span></td>'
          + '<td style="padding:10px 12px;text-align:center;font-size:.8rem;">' + prazoHtml + '</td>'
          + '<td style="padding:10px 12px;text-align:center;"><button onclick="admAbrirDetalheArr(' + item.id + ')" style="padding:5px 12px;background:#7c3aed;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:.8rem;">Gerenciar</button></td>'
          + '</tr>';
      }).join('');

      // Paginação
      var pagDiv = document.getElementById('admArrPaginacao');
      if (pagDiv) {
        pagDiv.innerHTML = '';
        for (var p = 1; p <= d.paginas; p++) {
          var btn = document.createElement('button');
          btn.textContent = p;
          btn.style.cssText = 'padding:6px 12px;border:1px solid ' + (p === pagina ? '#7c3aed' : '#e2e8f0') + ';background:' + (p === pagina ? '#7c3aed' : '#fff') + ';color:' + (p === pagina ? '#fff' : '#475569') + ';border-radius:6px;cursor:pointer;';
          (function(pg){ btn.onclick = function(){ admCarregarArrematacoes(pg); }; })(p);
          pagDiv.appendChild(btn);
        }
      }
    }).catch(function(){});
}

/* ── Detalhe de uma arrematação (admin) ── */
function admAbrirDetalheArr(id) {
  ADM_CURRENT_ARR_ID = id;
  document.getElementById('admArrDetalheId').value = id;
  document.getElementById('admModalArrOverlay').style.display = 'block';
  document.body.style.overflow = 'hidden';

  fetch('/api/arrematacao.php?action=detalhe_admin&id=' + id, { credentials: 'same-origin' })
    .then(function(r) { return r.json(); })
    .then(function(d) {
      if (!d.ok) return;
      var a = d.arr;

      // Título
      admSetEl('admArrModalTitulo', a.imovel_titulo + ' — ' + a.arrematante);

      // Resumo
      var corStatus = {
        aguardando_documentos:'#f59e0b',documentos_enviados:'#3b82f6',
        em_analise:'#8b5cf6',aprovado:'#10b981',reprovado:'#ef4444',concluido:'#6b7280',aguardando_confirmacao:'#6b7280'
      }[a.status] || '#64748b';
      admSetInner('admArrModalResumo',
        '<div style="font-size:.875rem;"><div style="color:#64748b;margin-bottom:2px;">Arrematante</div><strong>' + escHtml(a.arrematante) + '</strong><br><span style="color:#94a3b8;">' + escHtml(a.email) + '</span><br><em style="font-size:.8rem;">' + (a.tipo_pessoa === 'juridica' ? 'Pessoa Jurídica' : 'Pessoa Física') + '</em></div>'
        + '<div style="font-size:.875rem;"><div style="color:#64748b;margin-bottom:2px;">Valor Final</div><strong style="font-size:1.1rem;color:#1e3a5f;">R$ ' + parseFloat(a.valor_final).toLocaleString('pt-BR',{minimumFractionDigits:2}) + '</strong></div>'
        + '<div style="font-size:.875rem;"><div style="color:#64748b;margin-bottom:2px;">Status</div><span style="background:' + corStatus + '20;color:' + corStatus + ';padding:3px 10px;border-radius:20px;font-size:.8rem;font-weight:700;">' + escHtml(a.status_label) + '</span></div>'
        + '<div style="font-size:.875rem;"><div style="color:#64748b;margin-bottom:2px;">Prazo Docs</div>' + (a.data_limite_docs ? new Date(a.data_limite_docs).toLocaleDateString('pt-BR') : '—') + '</div>'
      );

      // Pipeline (Aba Status)
      var steps = ['aguardando_documentos','documentos_enviados','em_analise','aprovado','concluido'];
      var stLabels = {'aguardando_documentos':'Aguardando','documentos_enviados':'Enviados','em_analise':'Em Análise','aprovado':'Aprovado','concluido':'Concluído'};
      var curIdx = steps.indexOf(a.status);
      var pipeHtml = steps.map(function(s, i) {
        var done = i <= curIdx;
        var cur  = s === a.status;
        var col  = cur ? '#7c3aed' : (done ? '#10b981' : '#cbd5e1');
        return '<div style="display:flex;flex-direction:column;align-items:center;gap:4px;min-width:80px;">'
          + '<div style="width:28px;height:28px;border-radius:50%;background:' + col + ';display:flex;align-items:center;justify-content:center;color:#fff;font-size:.75rem;">' + (done ? '✓' : (i+1)) + '</div>'
          + '<span style="font-size:.7rem;text-align:center;color:' + (cur ? '#7c3aed' : '#64748b') + ';font-weight:' + (cur ? '700' : '400') + ';">' + stLabels[s] + '</span>'
          + '</div>'
          + (i < steps.length-1 ? '<div style="flex:1;height:2px;background:' + (i < curIdx ? '#10b981' : '#e2e8f0') + ';margin:0 -4px;align-self:center;margin-top:-16px;"></div>' : '');
      }).join('');
      admSetInner('admArrPipeline', pipeHtml);

      // Status select
      var sel = document.getElementById('admArrNovoStatus');
      if (sel) sel.value = a.status;

      // Destino notificação
      admSetEl('admArrNotifDest', 'Para: ' + a.email);
      var subj = document.getElementById('admArrNotifAssunto');
      if (subj) subj.value = 'Atualização sobre arrematação — ' + a.imovel_titulo;

      // Documentos
      admRenderDocs(d.docs);

      // Timeline
      admRenderTimeline(d.timeline, 'admArrTimeline');
    }).catch(function(){});
}

function admFecharDetalheArr() {
  document.getElementById('admModalArrOverlay').style.display = 'none';
  document.body.style.overflow = '';
  ADM_CURRENT_ARR_ID = null;
  admCarregarArrematacoes(1);
}

function admRenderDocs(docs) {
  var el = document.getElementById('admArrDocsList');
  if (!el) return;
  if (!docs || !docs.length) {
    el.innerHTML = '<p style="color:#94a3b8;text-align:center;padding:16px;">Nenhum documento enviado ainda.</p>';
    return;
  }
  var cores = { pendente:'#f59e0b', aprovado:'#10b981', reprovado:'#ef4444' };
  el.innerHTML = '<table style="width:100%;border-collapse:collapse;">'
    + '<thead><tr style="background:#f8fafc;">'
    + '<th style="padding:8px 10px;text-align:left;font-size:.8rem;color:#64748b;border-bottom:1px solid #e2e8f0;">Documento</th>'
    + '<th style="padding:8px 10px;font-size:.8rem;color:#64748b;border-bottom:1px solid #e2e8f0;">Tipo</th>'
    + '<th style="padding:8px 10px;font-size:.8rem;color:#64748b;border-bottom:1px solid #e2e8f0;">Status</th>'
    + '<th style="padding:8px 10px;font-size:.8rem;color:#64748b;border-bottom:1px solid #e2e8f0;">Ações</th>'
    + '</tr></thead><tbody id="admDocsListBody">'
    + docs.map(function(d) {
        var cor = cores[d.status] || '#64748b';
        return '<tr id="admDocRow' + d.id + '" style="border-bottom:1px solid #f1f5f9;">'
          + '<td style="padding:8px 10px;font-size:.875rem;"><a href="' + d.url + '" target="_blank" style="color:#2563eb;">' + escHtml(d.nome) + '</a><br><span style="font-size:.75rem;color:#94a3b8;">' + escHtml(d.uploader) + '</span></td>'
          + '<td style="padding:8px 10px;font-size:.8rem;color:#475569;">' + escHtml(d.tipo) + '</td>'
          + '<td style="padding:8px 10px;"><span style="background:' + cor + '20;color:' + cor + ';padding:2px 8px;border-radius:20px;font-size:.75rem;font-weight:700;">' + d.status.charAt(0).toUpperCase()+d.status.slice(1) + '</span>'
          + (d.observacao ? '<br><em style="font-size:.75rem;color:#ef4444;">' + escHtml(d.observacao) + '</em>' : '') + '</td>'
          + '<td style="padding:8px 10px;display:flex;gap:6px;flex-wrap:wrap;">'
          + (d.status !== 'aprovado'  ? '<button onclick="admRevisarDoc(' + d.id + ',\\'aprovado\\')" style="padding:3px 10px;background:#10b981;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:.75rem;">✅ Aprovar</button>' : '')
          + (d.status !== 'reprovado' ? '<button onclick="admRevisarDoc(' + d.id + ',\\'reprovado\\')" style="padding:3px 10px;background:#ef4444;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:.75rem;">❌ Reprovar</button>' : '')
          + '</td></tr>';
      }).join('')
    + '</tbody></table>';
}

function admRevisarDoc(docId, acao) {
  var obs = '';
  if (acao === 'reprovado') {
    obs = prompt('Motivo da reprovação (opcional):') || '';
    if (obs === null) return; // cancelou
  }
  if (!confirm('Deseja ' + (acao === 'aprovado' ? 'aprovar' : 'reprovar') + ' este documento?')) return;

  var fd = new FormData();
  fd.append('action', 'revisar_doc');
  fd.append('doc_id', docId);
  fd.append('acao', acao);
  fd.append('observacao', obs);

  fetch('/api/arrematacao.php', { method:'POST', credentials:'same-origin', body: fd })
    .then(function(r){ return r.json(); })
    .then(function(d) {
      if (d.ok) {
        mostrarToast('ok', 'Documento ' + (acao==='aprovado'?'aprovado':'reprovado') + '!', '');
        admAbrirDetalheArr(ADM_CURRENT_ARR_ID);
      } else {
        mostrarToast('err', 'Erro', d.erro || 'Falha ao revisar.');
      }
    }).catch(function(){ mostrarToast('err','Erro','Falha de conexão.'); });
}

function admUploadDoc() {
  var arr_id = ADM_CURRENT_ARR_ID;
  var fileEl = document.getElementById('admArrUpArquivo');
  var tipo   = document.getElementById('admArrUpTipo').value;
  var result = document.getElementById('admArrUpResult');
  if (!fileEl || !fileEl.files.length) { result.innerHTML = '<span style="color:red">Selecione um arquivo.</span>'; return; }

  // Precisamos do imovel_id — usamos o AJAX do WP via admin ajax
  // Como admin, chamamos o endpoint de upload que usa o arr_id diretamente
  // Não temos o arr_id via WP ajax aqui, mas podemos reutilizar nosso endpoint
  // usando o arr_id no sessão admin (via arrematacao.php action=upload_doc_admin — não existe ainda)
  // Por ora, avisamos que o upload admin é feito pelo WP admin
  result.innerHTML = '<span style="color:#f59e0b">⚠️ Upload de documentos pelo admin disponível no WP Admin (Leilão Caixa → Arrematações).</span>';
}

function admRenderTimeline(timeline, elId) {
  var el = document.getElementById(elId || 'admArrTimeline');
  if (!el) return;
  if (!timeline || !timeline.length) {
    el.innerHTML = '<p style="color:#94a3b8;text-align:center;padding:16px;">Nenhum registro na timeline.</p>';
    return;
  }
  var icons = { confirmacao:'✅', status_alterado:'🔄', documento_enviado:'📤', documento_aprovado:'✅', documento_reprovado:'❌', documento_excluido:'🗑️', notificacao:'📧' };
  el.innerHTML = '<div style="position:relative;padding-left:28px;">'
    + timeline.map(function(t) {
        var ic = icons[t.acao] || '📌';
        var dt = t.data ? new Date(t.data).toLocaleString('pt-BR') : '';
        return '<div style="position:relative;margin-bottom:16px;">'
          + '<div style="position:absolute;left:-28px;top:0;width:22px;height:22px;border-radius:50%;background:#f1f5f9;display:flex;align-items:center;justify-content:center;font-size:.8rem;">' + ic + '</div>'
          + '<div style="background:#f8fafc;border-radius:8px;padding:10px 12px;">'
          + '<div style="display:flex;justify-content:space-between;margin-bottom:4px;"><strong style="font-size:.8rem;">' + escHtml(t.user || 'Sistema') + '</strong><time style="font-size:.75rem;color:#94a3b8;">' + dt + '</time></div>'
          + '<p style="margin:0;font-size:.875rem;color:#475569;">' + escHtml(t.descricao) + '</p>'
          + '</div></div>';
      }).join('')
    + '</div>';
}

function admArrTrocarTab(tab, btn) {
  document.querySelectorAll('.admArr-tab').forEach(function(b) {
    b.style.borderBottomColor = 'transparent';
    b.style.color = '#64748b';
    b.style.fontWeight = '400';
  });
  btn.style.borderBottomColor = '#7c3aed';
  btn.style.color = '#7c3aed';
  btn.style.fontWeight = '600';
  document.querySelectorAll('.admArr-tab-content').forEach(function(c) { c.style.display = 'none'; });
  var el = document.getElementById('admArr-tab-' + tab);
  if (el) el.style.display = 'block';
}

function admAlterarStatus() {
  var arrId  = ADM_CURRENT_ARR_ID;
  var status = document.getElementById('admArrNovoStatus').value;
  var result = document.getElementById('admArrStatusResult');
  var fd = new FormData();
  fd.append('action', 'alterar_status');
  fd.append('arrematacao_id', arrId);
  fd.append('status', status);
  fetch('/api/arrematacao.php', { method:'POST', credentials:'same-origin', body: fd })
    .then(function(r){ return r.json(); })
    .then(function(d) {
      if (d.ok) {
        result.innerHTML = '<span style="color:green">✓ ' + escHtml(d.status_label) + '</span>';
        mostrarToast('ok', 'Status atualizado!', d.mensagem);
        setTimeout(function(){ admAbrirDetalheArr(arrId); }, 800);
      } else {
        result.innerHTML = '<span style="color:red">' + escHtml(d.erro) + '</span>';
      }
    }).catch(function(){ result.innerHTML = '<span style="color:red">Erro de conexão.</span>'; });
}

var NOTIF_TEMPLATES = {
  docs_pendentes:  "Olá,\n\nExistem documentos pendentes de envio referentes à sua arrematação.\n\nPor favor, providencie o envio dentro do prazo estabelecido.\n\nAtenciosamente,\nEquipe Qatar Leilões",
  docs_reprovados: "Olá,\n\nInformamos que alguns documentos enviados foram reprovados.\n\nAcesse o sistema para verificar os motivos e reenviar os documentos corrigidos.\n\nAtenciosamente,\nEquipe Qatar Leilões",
  aprovacao:       "Olá,\n\nTemos o prazer de informar que toda a documentação foi aprovada!\n\nEm breve entraremos em contato com os próximos passos.\n\nParabéns e obrigado,\nEquipe Qatar Leilões",
  prazo_expirado:  "Olá,\n\nO prazo para envio de documentação expirou.\n\nPor favor, entre em contato conosco urgentemente para regularizar a situação.\n\nAtenciosamente,\nEquipe Qatar Leilões",
  concluido:       "Olá,\n\nO processo de arrematação foi concluído com sucesso!\n\nTodos os documentos foram verificados e aprovados. Parabéns pela aquisição!\n\nAtenciosamente,\nEquipe Qatar Leilões"
};

function admArrAplicarTemplate() {
  var key = document.getElementById('admArrNotifTemplate').value;
  if (key && NOTIF_TEMPLATES[key]) {
    document.getElementById('admArrNotifMensagem').value = NOTIF_TEMPLATES[key];
  }
}

function admEnviarNotif() {
  var arrId    = ADM_CURRENT_ARR_ID;
  var assunto  = (document.getElementById('admArrNotifAssunto')  || {}).value || '';
  var mensagem = (document.getElementById('admArrNotifMensagem') || {}).value || '';
  var result   = document.getElementById('admArrNotifResult');
  if (!assunto || !mensagem) { result.innerHTML = '<span style="color:red">Preencha o assunto e a mensagem.</span>'; return; }
  if (!confirm('Enviar e-mail ao arrematante?')) return;
  var fd = new FormData();
  fd.append('action', 'enviar_notif');
  fd.append('arrematacao_id', arrId);
  fd.append('assunto', assunto);
  fd.append('mensagem', mensagem);
  fetch('/api/arrematacao.php', { method:'POST', credentials:'same-origin', body: fd })
    .then(function(r){ return r.json(); })
    .then(function(d) {
      if (d.ok) {
        result.innerHTML = '<span style="color:green">✓ ' + escHtml(d.mensagem) + '</span>';
        mostrarToast('ok','E-mail enviado!', d.mensagem);
      } else {
        result.innerHTML = '<span style="color:red">' + escHtml(d.erro) + '</span>';
      }
    }).catch(function(){ result.innerHTML = '<span style="color:red">Erro de conexão.</span>'; });
}

/* ── Admin Usuários ── */
var ADM_USER_PAGE = 1;
function admCarregarUsuarios(p) {
  ADM_USER_PAGE = p || 1;
  var busca = (document.getElementById('admUserBusca') || {}).value || '';
  fetch('/api/admin.php?action=usuarios&page=' + ADM_USER_PAGE + '&search=' + encodeURIComponent(busca), { credentials:'same-origin' })
    .then(function(r){ return r.json(); })
    .then(function(d) {
      if (!d.ok) return;
      var tbody = document.getElementById('tbodyAdmUsuarios');
      if (!tbody) return;
      tbody.innerHTML = (d.usuarios || []).map(function(u) {
        return '<tr style="border-bottom:1px solid #f1f5f9;">'
          + '<td style="padding:10px 12px;font-size:.875rem;">' + escHtml(u.nome) + '</td>'
          + '<td style="padding:10px 12px;font-size:.875rem;">' + escHtml(u.email) + '</td>'
          + '<td style="padding:10px 12px;text-align:center;font-size:.8rem;">' + escHtml(u.role) + '</td>'
          + '<td style="padding:10px 12px;text-align:center;font-size:.8rem;">' + escHtml(u.cadastro || '') + '</td>'
          + '<td style="padding:10px 12px;text-align:center;"><button onclick="admAbrirModalRole(' + u.id + ',\'' + escHtml(u.nome) + '\',\'' + escHtml(u.role) + '\')" style="padding:4px 10px;background:#2563eb;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:.8rem;">Perfil</button> <button onclick="admDeletarUsuario(' + u.id + ')" style="padding:4px 10px;background:#ef4444;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:.8rem;">✕</button></td>'
          + '</tr>';
      }).join('') || '<tr><td colspan="5" style="padding:32px;text-align:center;color:#94a3b8;">Nenhum usuário.</td></tr>';
    }).catch(function(){});
}

function admBuscarUsuarios() { admCarregarUsuarios(1); }

function admDeletarUsuario(id) {
  if (!confirm('Excluir este usuário permanentemente?')) return;
  var fd = new FormData(); fd.append('user_id', id);
  fetch('/api/admin.php?action=usuario_delete', { method:'POST', credentials:'same-origin', body:fd })
    .then(function(r){ return r.json(); })
    .then(function(d){ if(d.ok){ admCarregarUsuarios(ADM_USER_PAGE); } else { alert(d.erro); } });
}

function admAbrirModalRole(id, nome, role) {
  document.getElementById('admRoleUserId').value = id;
  document.getElementById('admRoleUserNome').textContent = nome;
  document.getElementById('admRoleSelect').value = role;
  var el = document.getElementById('admModalRoleOverlay');
  el.style.display = 'flex';
}
function admFecharModalRole() {
  document.getElementById('admModalRoleOverlay').style.display = 'none';
}
function admSalvarRole() {
  var fd = new FormData();
  fd.append('user_id', document.getElementById('admRoleUserId').value);
  fd.append('role', document.getElementById('admRoleSelect').value);
  fetch('/api/admin.php?action=usuario_role', { method:'POST', credentials:'same-origin', body:fd })
    .then(function(r){ return r.json(); })
    .then(function(d){ if(d.ok){ admFecharModalRole(); admCarregarUsuarios(ADM_USER_PAGE); } else { alert(d.erro); } });
}

/* ── Admin Imóveis ── */
var ADM_IMOV_PAGE = 1;
function admCarregarImoveis(p) {
  ADM_IMOV_PAGE = p || 1;
  var busca = (document.getElementById('admImovBusca') || {}).value || '';
  fetch('/api/admin.php?action=imoveis&page=' + ADM_IMOV_PAGE + '&search=' + encodeURIComponent(busca), { credentials:'same-origin' })
    .then(function(r){ return r.json(); })
    .then(function(d) {
      if (!d.ok) return;
      var tbody = document.getElementById('tbodyAdmImoveis');
      if (!tbody) return;
      tbody.innerHTML = (d.imoveis || []).map(function(i) {
        return '<tr style="border-bottom:1px solid #f1f5f9;">'
          + '<td style="padding:10px 12px;font-size:.875rem;">' + escHtml(i.titulo) + '</td>'
          + '<td style="padding:10px 12px;font-size:.8rem;">' + escHtml(i.tipo || '') + '</td>'
          + '<td style="padding:10px 12px;text-align:right;font-size:.875rem;">R$ ' + (parseFloat(i.preco||0)).toLocaleString('pt-BR',{minimumFractionDigits:2}) + '</td>'
          + '<td style="padding:10px 12px;text-align:center;font-size:.8rem;">' + escHtml(i.status_leilao || '') + '</td>'
          + '<td style="padding:10px 12px;text-align:center;"><a href="/wp-admin/post.php?post=' + i.id + '&action=edit" target="_blank" style="padding:4px 10px;background:#2563eb;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:.8rem;text-decoration:none;">Editar</a> <button onclick="admDeletarImovel(' + i.id + ')" style="padding:4px 10px;background:#ef4444;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:.8rem;">✕</button></td>'
          + '</tr>';
      }).join('') || '<tr><td colspan="5" style="padding:32px;text-align:center;color:#94a3b8;">Nenhum imóvel.</td></tr>';
    }).catch(function(){});
}

function admBuscarImoveis() { admCarregarImoveis(1); }

function admDeletarImovel(id) {
  if (!confirm('Mover este imóvel para a lixeira?')) return;
  var fd = new FormData(); fd.append('post_id', id);
  fetch('/api/admin.php?action=imovel_delete', { method:'POST', credentials:'same-origin', body:fd })
    .then(function(r){ return r.json(); })
    .then(function(d){ if(d.ok){ admCarregarImoveis(ADM_IMOV_PAGE); } else { alert(d.erro); } });
}

/* ── Admin Importação ── */
function admRodarImport(tipo) {
  var btnId = tipo === 'imoveis' ? 'btnAdmImportImoveis' : 'btnAdmImportVeiculos';
  var logId = tipo === 'imoveis' ? 'logAdmImoveis' : 'logAdmVeiculos';
  var btn = document.getElementById(btnId);
  var log = document.getElementById(logId);
  if (btn) btn.disabled = true;
  if (log) log.innerHTML = '⏳ Importação em andamento...';
  var fd = new FormData();
  fd.append('tipo', tipo);
  fetch('/api/admin.php?action=importar', { method:'POST', credentials:'same-origin', body:fd })
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (log) log.innerHTML = d.ok ? '✅ ' + escHtml(d.mensagem || 'Concluído.') : '❌ ' + escHtml(d.erro || 'Erro.');
      if (btn) btn.disabled = false;
    }).catch(function(){
      if (log) log.innerHTML = '❌ Erro de conexão.';
      if (btn) btn.disabled = false;
    });
}

/* ============================================================================
   ARREMATANTE: Gerenciar docs do próprio processo
   ============================================================================ */
var MDOC_IMOVEL_ID = null;

function abrirGerenciarDocs(imovel_id) {
  MDOC_IMOVEL_ID = imovel_id;
  document.getElementById('modalDocOverlay').style.display = 'block';
  document.body.style.overflow = 'hidden';
  document.getElementById('mdocDocsList').innerHTML = '<p style="text-align:center;color:#94a3b8;padding:20px;">Carregando...</p>';
  document.getElementById('mdocTimeline').innerHTML  = '';
  document.getElementById('mdocPipeline').innerHTML  = '';
  document.getElementById('mdocTitulo').textContent  = '';
  mdocTrocarTab('docs', document.querySelector('.mdoc-tab'));

  fetch('/api/arrematacao.php?action=minha_arrematacao&imovel_id=' + imovel_id, { credentials:'same-origin' })
    .then(function(r){ return r.json(); })
    .then(function(d) {
      if (!d.ok) {
        document.getElementById('mdocDocsList').innerHTML = '<p style="text-align:center;color:#ef4444;padding:20px;">' + escHtml(d.erro || 'Processo não encontrado.') + '</p>';
        return;
      }
      var a = d.arr;

      // Titulo
      document.getElementById('mdocTitulo').textContent = 'Status: ' + a.status_label;

      // Status badge
      var corStatus = {
        aguardando_documentos:'#f59e0b',documentos_enviados:'#3b82f6',
        em_analise:'#8b5cf6',aprovado:'#10b981',reprovado:'#ef4444',concluido:'#6b7280',aguardando_confirmacao:'#6b7280'
      }[a.status] || '#64748b';
      document.getElementById('mdocStatusLabel').innerHTML = '<span style="background:' + corStatus + '20;color:' + corStatus + ';padding:3px 10px;border-radius:20px;font-size:.8rem;font-weight:700;">' + escHtml(a.status_label) + '</span>';

      var prazoEl = document.getElementById('mdocPrazo');
      if (a.data_limite_docs) {
        var diff = Math.ceil((new Date(a.data_limite_docs) - new Date()) / 86400000);
        prazoEl.innerHTML = ' &nbsp;|&nbsp; Prazo docs: <strong>' + new Date(a.data_limite_docs).toLocaleDateString('pt-BR') + '</strong>'
          + (diff < 0 ? ' <span style="color:#ef4444;">(expirado)</span>' : ' <span style="color:#64748b;">(' + diff + ' dias)</span>');
      }

      // Pipeline
      var steps = ['aguardando_documentos','documentos_enviados','em_analise','aprovado','concluido'];
      var stLabels = {'aguardando_documentos':'Enviando','documentos_enviados':'Recebidos','em_analise':'Análise','aprovado':'Aprovado','concluido':'Concluído'};
      var curIdx = steps.indexOf(a.status);
      document.getElementById('mdocPipeline').innerHTML = steps.map(function(s, i){
        var done = i <= curIdx;
        var cur  = s === a.status;
        var col  = cur ? '#2563eb' : (done ? '#10b981' : '#cbd5e1');
        return '<div style="display:flex;flex-direction:column;align-items:center;gap:3px;min-width:70px;">'
          + '<div style="width:24px;height:24px;border-radius:50%;background:' + col + ';color:#fff;display:flex;align-items:center;justify-content:center;font-size:.7rem;">' + (done ? '✓' : (i+1)) + '</div>'
          + '<span style="font-size:.65rem;text-align:center;color:' + (cur ? '#2563eb' : '#64748b') + ';font-weight:' + (cur ? '700':'400') + ';">' + stLabels[s] + '</span>'
          + '</div>'
          + (i < steps.length-1 ? '<div style="flex:1;height:2px;background:' + (i<curIdx?'#10b981':'#e2e8f0') + ';margin:0 -4px;align-self:center;margin-top:-18px;"></div>' : '');
      }).join('');

      // Documentos
      mdocRenderDocs(d.docs, a.status);

      // Timeline
      admRenderTimeline(d.timeline, 'mdocTimeline');
    }).catch(function(err){
      document.getElementById('mdocDocsList').innerHTML = '<p style="color:#ef4444;text-align:center;padding:20px;">Erro ao carregar dados.</p>';
    });
}

function fecharModalDocs() {
  document.getElementById('modalDocOverlay').style.display = 'none';
  document.body.style.overflow = '';
  MDOC_IMOVEL_ID = null;
}

function mdocTrocarTab(tab, btn) {
  document.querySelectorAll('.mdoc-tab').forEach(function(b) {
    b.style.borderBottomColor = 'transparent';
    b.style.color = '#64748b';
    b.style.fontWeight = '400';
  });
  if (btn) { btn.style.borderBottomColor = '#2563eb'; btn.style.color = '#2563eb'; btn.style.fontWeight = '600'; }
  document.querySelectorAll('.mdoc-tab-content').forEach(function(c) { c.style.display = 'none'; });
  var el = document.getElementById('mdoc-tab-' + tab);
  if (el) el.style.display = 'block';
}

function mdocRenderDocs(docs, arrStatus) {
  var el = document.getElementById('mdocDocsList');
  if (!el) return;

  var podeEnviar = ['aguardando_documentos','documentos_enviados','em_analise'].includes(arrStatus);
  // Mostrar ou ocultar área de upload
  var upBox = document.querySelector('#mdoc-tab-docs > div:first-child');
  if (upBox) upBox.style.display = podeEnviar ? '' : 'none';

  if (!docs || !docs.length) {
    el.innerHTML = '<p style="color:#94a3b8;text-align:center;padding:20px;">Nenhum documento enviado ainda. Use o formulário acima para enviar.</p>';
    return;
  }
  var cores = { pendente:'#f59e0b', aprovado:'#10b981', reprovado:'#ef4444' };
  el.innerHTML = '<h4 style="margin:0 0 12px;font-size:.95rem;">Documentos enviados</h4>'
    + docs.map(function(d) {
        var cor = cores[d.status] || '#64748b';
        var statusLabel = {pendente:'⏳ Pendente', aprovado:'✅ Aprovado', reprovado:'❌ Reprovado'}[d.status] || d.status;
        return '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px 14px;margin-bottom:10px;display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">'
          + '<div>'
          + '<a href="' + d.url + '" target="_blank" style="color:#2563eb;font-weight:600;font-size:.875rem;">' + escHtml(d.nome) + '</a>'
          + '<div style="font-size:.75rem;color:#94a3b8;margin-top:2px;">' + escHtml(d.tipo) + ' · ' + new Date(d.uploaded_em).toLocaleDateString('pt-BR') + '</div>'
          + (d.observacao ? '<div style="font-size:.8rem;color:#ef4444;margin-top:4px;">⚠️ ' + escHtml(d.observacao) + '</div>' : '')
          + '</div>'
          + '<span style="background:' + cor + '20;color:' + cor + ';padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:700;white-space:nowrap;">' + statusLabel + '</span>'
          + '</div>';
      }).join('');
}

function mdocEnviarDoc() {
  var imovel_id = MDOC_IMOVEL_ID;
  var fileEl = document.getElementById('mdocUpArquivo');
  var tipo   = document.getElementById('mdocUpTipo').value;
  var result = document.getElementById('mdocUpResult');
  if (!fileEl || !fileEl.files.length) {
    result.innerHTML = '<span style="color:#ef4444;">Selecione um arquivo.</span>';
    return;
  }
  var fd = new FormData();
  fd.append('action', 'upload_doc');
  fd.append('imovel_id', imovel_id);
  fd.append('tipo', tipo);
  fd.append('documento', fileEl.files[0]);

  result.innerHTML = '<span style="color:#64748b;">⏳ Enviando...</span>';

  fetch('/api/arrematacao.php', { method:'POST', credentials:'same-origin', body: fd })
    .then(function(r){ return r.json(); })
    .then(function(d) {
      if (d.ok) {
        result.innerHTML = '<span style="color:#10b981;">✅ ' + escHtml(d.mensagem) + '</span>';
        fileEl.value = '';
        setTimeout(function(){ abrirGerenciarDocs(imovel_id); }, 1000);
      } else {
        result.innerHTML = '<span style="color:#ef4444;">❌ ' + escHtml(d.erro) + '</span>';
      }
    }).catch(function(){ result.innerHTML = '<span style="color:#ef4444;">Erro de conexão.</span>'; });
}

/* ── Utilitários ── */
function escHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function admSetEl(id, val) {
  var el = document.getElementById(id);
  if (el) el.textContent = val;
}

function admSetInner(id, html) {
  var el = document.getElementById(id);
  if (el) el.innerHTML = html;
}

// Carregar dados nas abas admin quando trocar painel
document.addEventListener('click', function(e) {
  var btn = e.target.closest('[data-painel]');
  if (!btn) return;
  var painel = btn.dataset.painel;
  if (painel === 'adminUsuarios')      admCarregarUsuarios(1);
  if (painel === 'adminImoveis')       admCarregarImoveis(1);
  if (painel === 'adminArrematacoes')  admCarregarArrematacoes(1);
});

// ESC fecha modais
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    fecharModalDocs();
    admFecharDetalheArr();
    admFecharModalRole();
  }
});
"""

# Verificar se já foi appendado
if 'MODO ADMIN' not in js:
    js += JS_APPEND
    print("  [OK]   funções admin + arrematante JS adicionadas")
else:
    print("  [SKIP] JS já appendado")

with open('dashboard.js', 'w', encoding='utf-8') as f:
    f.write(js)

print("  dashboard.js salvo.")

print("\n✅ Patch concluído! Faça o upload com:")
print("""scp -o HostKeyAlgorithms=ssh-rsa -o PubkeyAcceptedAlgorithms=ssh-rsa ^
  api/arrematacao.php api/auth.php dashboard.html dashboard.js ^
  root@129.121.39.64:/var/www/sites/qatarleiloes.com.br/""")
print("  (dos respectivos subfolders)")
