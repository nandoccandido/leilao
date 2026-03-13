#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Patch: integra o painel administrativo ao dashboard.html / dashboard.js
"""

BASE = '/var/www/sites/qatarleiloes.com.br'


# ══════════════════════════════════════════════════════════════════════════════
#  1. auth.php — adicionar 'roles' ao build_user_response
# ══════════════════════════════════════════════════════════════════════════════
def patch_auth():
    path = f'{BASE}/api/auth.php'
    with open(path, 'r', encoding='utf-8') as f:
        content = f.read()

    if "'roles'" in content:
        print('auth.php: roles já existia, pulando')
        return

    old = "        'endereco' => ["
    new = "        'roles'    => array_values($user->roles),\n        'endereco' => ["

    if old not in content:
        print('auth.php: ERRO — marcador não encontrado')
        return

    content = content.replace(old, new, 1)
    with open(path, 'w', encoding='utf-8') as f:
        f.write(content)
    print('auth.php: roles adicionado ✓')


# ══════════════════════════════════════════════════════════════════════════════
#  2. dashboard.html — admin.css + nav admin + painéis admin
# ══════════════════════════════════════════════════════════════════════════════
def patch_dashboard_html():
    path = f'{BASE}/dashboard.html'
    with open(path, 'r', encoding='utf-8') as f:
        html = f.read()

    changed = False

    # ── 2a. Adicionar admin.css ──────────────────────────────────────────────
    if 'admin.css' not in html:
        html = html.replace(
            '<link rel="stylesheet" href="dashboard.css">',
            '<link rel="stylesheet" href="dashboard.css">\n  <link rel="stylesheet" href="admin.css">'
        )
        changed = True
        print('dashboard.html: admin.css adicionado ✓')

    # ── 2b. Nav items admin no sidebar ──────────────────────────────────────
    if 'dash__nav-admin' not in html:
        admin_nav = (
            '\n'
            '      <!-- ══ ADMIN ONLY (oculto para usuários comuns) ══ -->\n'
            '      <hr class="dash__nav-admin" style="display:none;border:none;border-top:1px solid #e4e8f0;margin:8px 12px;">\n'
            '      <p class="dash__nav-admin" style="display:none;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#a0aec0;padding:4px 20px 2px">Administração</p>\n'
            '      <button class="dash__nav-item dash__nav-admin" data-painel="adminUsuarios"\n'
            '        onclick="trocarPainel(\'adminUsuarios\',this);if(!this.dataset.loaded){admCarregarUsuarios();this.dataset.loaded=1}"\n'
            '        style="display:none">\n'
            '        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>\n'
            '        Usuários\n'
            '        <span class="dash__nav-badge dash__nav-admin" id="badgeAdmUsuarios" style="display:none"></span>\n'
            '      </button>\n'
            '      <button class="dash__nav-item dash__nav-admin" data-painel="adminImoveis"\n'
            '        onclick="trocarPainel(\'adminImoveis\',this);if(!this.dataset.loaded){admCarregarImoveis();this.dataset.loaded=1}"\n'
            '        style="display:none">\n'
            '        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>\n'
            '        Im\u00f3veis\n'
            '        <span class="dash__nav-badge dash__nav-admin" id="badgeAdmImoveis" style="display:none"></span>\n'
            '      </button>\n'
            '      <button class="dash__nav-item dash__nav-admin" data-painel="adminImportacao"\n'
            '        onclick="trocarPainel(\'adminImportacao\',this)"\n'
            '        style="display:none">\n'
            '        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>\n'
            '        Importa\u00e7\u00e3o\n'
            '      </button>\n'
            '      <button class="dash__nav-item dash__nav-admin" data-painel="adminWordpress"\n'
            '        onclick="trocarPainel(\'adminWordpress\',this)"\n'
            '        style="display:none">\n'
            '        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>\n'
            '        WordPress\n'
            '      </button>\n'
        )
        marker = '<div class="dash__sidebar-footer">'
        if marker in html:
            html = html.replace(marker, admin_nav + '      ' + marker, 1)
            changed = True
            print('dashboard.html: nav admin adicionado ✓')
        else:
            print('dashboard.html: ERRO — sidebar-footer não encontrado')

    # ── 2c. Painéis admin antes de </main> ───────────────────────────────────
    if 'painelAdminUsuarios' not in html:
        admin_panels = (
            '\n'
            '      <!-- ══════ PAINEL ADMIN: USUÁRIOS ══════ -->\n'
            '      <section class="dash__painel" id="painelAdminUsuarios">\n'
            '        <div class="dash__painel-header">\n'
            '          <div><h1 class="dash__titulo">Usu\u00e1rios</h1><p class="dash__subtitulo">Gerencie os usu\u00e1rios cadastrados</p></div>\n'
            '        </div>\n'
            '        <div class="adm-card">\n'
            '          <div class="adm-filter-row">\n'
            '            <input type="text" class="adm-input-search" placeholder="\U0001f50d Buscar por nome ou e-mail..." oninput="admBuscarUsuarios(this.value)">\n'
            '          </div>\n'
            '          <div class="adm-table-wrap">\n'
            '            <table class="adm-table">\n'
            '              <thead><tr><th>#</th><th>Login</th><th>E-mail</th><th>Nome</th><th>Papel</th><th>A\u00e7\u00f5es</th></tr></thead>\n'
            '              <tbody id="tbodyAdmUsuarios"><tr><td colspan="6" class="adm-loading"></td></tr></tbody>\n'
            '            </table>\n'
            '          </div>\n'
            '          <div class="adm-paginacao" id="paginacaoAdmUsuarios"></div>\n'
            '        </div>\n'
            '      </section>\n'
            '\n'
            '      <!-- ══════ PAINEL ADMIN: IMÓVEIS ══════ -->\n'
            '      <section class="dash__painel" id="painelAdminImoveis">\n'
            '        <div class="dash__painel-header">\n'
            '          <div><h1 class="dash__titulo">Im\u00f3veis</h1><p class="dash__subtitulo">Gerencie o cat\u00e1logo de im\u00f3veis</p></div>\n'
            '        </div>\n'
            '        <div class="adm-card">\n'
            '          <div class="adm-filter-row">\n'
            '            <input type="text" class="adm-input-search" placeholder="\U0001f50d Buscar im\u00f3vel..." oninput="admBuscarImoveis(this.value)">\n'
            '          </div>\n'
            '          <div class="adm-table-wrap">\n'
            '            <table class="adm-table">\n'
            '              <thead><tr><th>#</th><th>T\u00edtulo</th><th>Edital</th><th>Cidade</th><th>UF</th><th>Status</th><th>A\u00e7\u00f5es</th></tr></thead>\n'
            '              <tbody id="tbodyAdmImoveis"><tr><td colspan="7" class="adm-loading"></td></tr></tbody>\n'
            '            </table>\n'
            '          </div>\n'
            '          <div class="adm-paginacao" id="paginacaoAdmImoveis"></div>\n'
            '        </div>\n'
            '      </section>\n'
            '\n'
            '      <!-- ══════ PAINEL ADMIN: IMPORTAÇÃO ══════ -->\n'
            '      <section class="dash__painel" id="painelAdminImportacao">\n'
            '        <div class="dash__painel-header">\n'
            '          <div><h1 class="dash__titulo">Importa\u00e7\u00e3o</h1><p class="dash__subtitulo">Disparar importa\u00e7\u00e3o de dados da Caixa Econ\u00f4mica</p></div>\n'
            '        </div>\n'
            '        <div class="adm-cards-row">\n'
            '          <div class="adm-card">\n'
            '            <div class="adm-card__header"><h3>\U0001f3e0 Im\u00f3veis \u2014 Caixa</h3></div>\n'
            '            <div class="adm-card__body">\n'
            '              <p style="font-size:.875rem;color:#718096;margin-bottom:16px">Importa o CSV de im\u00f3veis da Caixa e atualiza o cat\u00e1logo.</p>\n'
            '              <button id="btnAdmImportImoveis" data-label="\u25b6 Importar Im\u00f3veis" class="adm-btn adm-btn-primary" onclick="admRodarImport(\'imoveis\')">\u25b6 Importar Im\u00f3veis</button>\n'
            '              <div class="adm-import-log" id="logAdmImoveis"></div>\n'
            '            </div>\n'
            '          </div>\n'
            '          <div class="adm-card">\n'
            '            <div class="adm-card__header"><h3>\U0001f697 Ve\u00edculos \u2014 Caixa</h3></div>\n'
            '            <div class="adm-card__body">\n'
            '              <p style="font-size:.875rem;color:#718096;margin-bottom:16px">Importa o CSV de ve\u00edculos da Caixa e atualiza o cat\u00e1logo.</p>\n'
            '              <button id="btnAdmImportVeiculos" data-label="\u25b6 Importar Ve\u00edculos" class="adm-btn adm-btn-primary" onclick="admRodarImport(\'veiculos\')">\u25b6 Importar Ve\u00edculos</button>\n'
            '              <div class="adm-import-log" id="logAdmVeiculos"></div>\n'
            '            </div>\n'
            '          </div>\n'
            '        </div>\n'
            '      </section>\n'
            '\n'
            '      <!-- ══════ PAINEL ADMIN: WORDPRESS ══════ -->\n'
            '      <section class="dash__painel" id="painelAdminWordpress">\n'
            '        <div class="dash__painel-header">\n'
            '          <div><h1 class="dash__titulo">WordPress</h1><p class="dash__subtitulo">Atalhos para o painel do WordPress</p></div>\n'
            '        </div>\n'
            '        <div class="adm-cards-row">\n'
            '          <div class="adm-card">\n'
            '            <div class="adm-card__header"><h3>\u26a1 Acesso R\u00e1pido</h3></div>\n'
            '            <div class="adm-card__body">\n'
            '              <ul class="adm-link-list">\n'
            '                <li><a href="/wp-admin/" target="_blank"><span class="icon">\U0001f3e0</span> Painel WordPress</a></li>\n'
            '                <li><a href="/wp-admin/users.php" target="_blank"><span class="icon">\U0001f465</span> Gerenciar Usu\u00e1rios</a></li>\n'
            '                <li><a href="/wp-admin/edit.php?post_type=imovel" target="_blank"><span class="icon">\U0001f3e0</span> Posts \u2014 Im\u00f3veis</a></li>\n'
            '                <li><a href="/wp-admin/plugins.php" target="_blank"><span class="icon">\U0001f50c</span> Plugins</a></li>\n'
            '                <li><a href="/wp-admin/themes.php" target="_blank"><span class="icon">\U0001f3a8</span> Temas</a></li>\n'
            '                <li><a href="/wp-admin/update-core.php" target="_blank"><span class="icon">\U0001f504</span> Atualiza\u00e7\u00f5es</a></li>\n'
            '              </ul>\n'
            '            </div>\n'
            '          </div>\n'
            '          <div class="adm-card">\n'
            '            <div class="adm-card__header"><h3>\U0001f4ca Estat\u00edsticas</h3></div>\n'
            '            <div class="adm-card__body">\n'
            '              <div class="adm-stats-grid" style="grid-template-columns:repeat(2,1fr)">\n'
            '                <div class="adm-stat-card"><div class="adm-stat-icon azul">\U0001f465</div><div class="adm-stat-info"><div class="adm-stat-label">Usu\u00e1rios</div><div class="adm-stat-valor" id="admStTotUsuarios">\u2014</div></div></div>\n'
            '                <div class="adm-stat-card"><div class="adm-stat-icon verde">\U0001f3e0</div><div class="adm-stat-info"><div class="adm-stat-label">Im\u00f3veis</div><div class="adm-stat-valor" id="admStTotImoveis">\u2014</div></div></div>\n'
            '                <div class="adm-stat-card"><div class="adm-stat-icon roxo">\U0001f697</div><div class="adm-stat-info"><div class="adm-stat-label">Ve\u00edculos</div><div class="adm-stat-valor" id="admStTotVeiculos">\u2014</div></div></div>\n'
            '                <div class="adm-stat-card"><div class="adm-stat-icon amarelo">\U0001f195</div><div class="adm-stat-info"><div class="adm-stat-label">Novos hoje</div><div class="adm-stat-valor" id="admStNovosHoje">\u2014</div></div></div>\n'
            '              </div>\n'
            '            </div>\n'
            '          </div>\n'
            '        </div>\n'
            '      </section>\n'
            '\n'
            '      <!-- MODAL ROLE (admin) -->\n'
            '      <div class="adm-modal-overlay" id="admModalRoleOverlay" onclick="if(event.target===this)admFecharModalRole()">\n'
            '        <div class="adm-modal">\n'
            '          <h3>Alterar papel do usu\u00e1rio</h3>\n'
            '          <p id="admModalRoleNome" style="color:#718096;margin-bottom:16px"></p>\n'
            '          <label>Novo papel</label>\n'
            '          <select class="adm-select" id="admModalRoleSelect" style="width:100%;margin-bottom:20px">\n'
            '            <option value="subscriber">Assinante</option>\n'
            '            <option value="customer">Cliente</option>\n'
            '            <option value="author">Autor</option>\n'
            '            <option value="editor">Editor</option>\n'
            '            <option value="administrator">Administrador</option>\n'
            '          </select>\n'
            '          <div class="adm-modal-actions">\n'
            '            <button class="adm-btn adm-btn-secondary" onclick="admFecharModalRole()">Cancelar</button>\n'
            '            <button class="adm-btn adm-btn-primary" onclick="admSalvarRole()">Salvar</button>\n'
            '          </div>\n'
            '        </div>\n'
            '      </div>\n'
        )
        marker = '    </main>'
        if marker in html:
            html = html.replace(marker, admin_panels + '    </main>', 1)
            changed = True
            print('dashboard.html: painéis admin adicionados ✓')
        else:
            print('dashboard.html: ERRO — </main> não encontrado')

    if changed:
        with open(path, 'w', encoding='utf-8') as f:
            f.write(html)
        print('dashboard.html: salvo ✓')
    else:
        print('dashboard.html: nenhuma mudança necessária')


# ══════════════════════════════════════════════════════════════════════════════
#  3. dashboard.js — detectar admin + funções admin
# ══════════════════════════════════════════════════════════════════════════════
def patch_dashboard_js():
    path = f'{BASE}/dashboard.js'
    with open(path, 'r', encoding='utf-8') as f:
        content = f.read()

    changed = False

    # ── 3a. Chamar ativarModoAdmin em preencherDadosUsuario ──────────────────
    if 'ativarModoAdmin' not in content:
        # Inserir antes do fechamento da função preencherDadosUsuario
        # O final da função tem o bloco if(u.endereco){...} e depois fecha
        old_end = "    if (inputUF) inputUF.value = u.endereco.uf || '';\n  }\n}"
        new_end = (
            "    if (inputUF) inputUF.value = u.endereco.uf || '';\n"
            "  }\n"
            "\n"
            "  // Ativar modo admin se o usuário for administrador\n"
            "  if (u.roles && u.roles.includes('administrator')) {\n"
            "    ativarModoAdmin();\n"
            "  }\n"
            "}"
        )
        if old_end in content:
            content = content.replace(old_end, new_end, 1)
            changed = True
            print('dashboard.js: detecção de admin adicionada ✓')
        else:
            print('dashboard.js: AVISO — marcador UF não encontrado, tentando alternativa')
            # Alternativa: inserir antes de "function trocarPainel"
            alt_marker = '\nfunction trocarPainel(painel, btn) {'
            if alt_marker in content:
                admin_detect = (
                    "\n"
                    "// Chamado em preencherDadosUsuario quando roles estiver disponível\n"
                    "// Nota: a detecção de admin foi inserida via evento\n"
                )
                # We'll handle this differently - patch from HTML side
                print('dashboard.js: marcador alternativo encontrado, mas não aplicado')

    # ── 3b. Acrescentar funções admin no final do arquivo ────────────────────
    if 'admCarregarStats' not in content:
        admin_js = r"""

// ═══════════════════════════════════════════════════════════════════════════
//  ADMIN — funções integradas ao dashboard
// ═══════════════════════════════════════════════════════════════════════════

var ADM_STATE = {
  pagUsuarios  : 1,
  pagImoveis   : 1,
  buscaUsuarios: '',
  buscaImoveis : '',
  debounce     : null,
  modalRoleId  : null,
};

function ativarModoAdmin() {
  // Revelar itens de menu admin
  document.querySelectorAll('.dash__nav-admin').forEach(function(el) {
    el.style.display = '';
  });
  // Ocultar abas exclusivas do usuário comum
  var itemLeiloes   = document.querySelector('[data-painel="leiloes"]');
  var itemFavoritos = document.querySelector('[data-painel="favoritos"]');
  if (itemLeiloes)   itemLeiloes.style.display   = 'none';
  if (itemFavoritos) itemFavoritos.style.display = 'none';
  // Carregar estatísticas do admin
  admCarregarStats();
}

// ── Stats ──────────────────────────────────────────────────────────────────
async function admCarregarStats() {
  try {
    var r = await fetch('/api/admin.php?action=stats', { credentials: 'include' });
    var d = await r.json();
    if (!d.success) return;
    var s = d.data;
    var mapa = {
      admStTotUsuarios : s.total_usuarios,
      admStTotImoveis  : s.total_imoveis,
      admStTotVeiculos : s.total_veiculos,
      admStNovosHoje   : s.novos_hoje,
    };
    Object.keys(mapa).forEach(function(id) {
      var el = document.getElementById(id);
      if (el) el.textContent = mapa[id] != null ? mapa[id] : '\u2014';
    });
    var bu = document.getElementById('badgeAdmUsuarios');
    var bi = document.getElementById('badgeAdmImoveis');
    if (bu && s.total_usuarios) { bu.textContent = s.total_usuarios; bu.style.display = ''; }
    if (bi && s.total_imoveis)  { bi.textContent = s.total_imoveis;  bi.style.display = ''; }
  } catch(e) {}
}

// ── Usuários ───────────────────────────────────────────────────────────────
async function admCarregarUsuarios(pag, busca) {
  if (pag   !== undefined) ADM_STATE.pagUsuarios   = pag;
  if (busca !== undefined) ADM_STATE.buscaUsuarios = busca;
  var tbody = document.getElementById('tbodyAdmUsuarios');
  if (!tbody) return;
  tbody.innerHTML = '<tr><td colspan="6" class="adm-loading"></td></tr>';
  try {
    var p = new URLSearchParams({
      action    : 'usuarios',
      pagina    : ADM_STATE.pagUsuarios,
      busca     : ADM_STATE.buscaUsuarios,
      por_pagina: 20,
    });
    var r = await fetch('/api/admin.php?' + p, { credentials: 'include' });
    var d = await r.json();
    if (!d.success) {
      tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:28px;color:#e53e3e">\u26a0\ufe0f ' + admEsc(d.message) + '</td></tr>';
      return;
    }
    admRenderUsuarios(d.data.items, tbody);
    admRenderPaginacao(
      document.getElementById('paginacaoAdmUsuarios'),
      d.data.paginas, d.data.pagina, 'admCarregarUsuarios'
    );
  } catch(e) {
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:28px;color:#e53e3e">\u26a0\ufe0f Erro de conex\u00e3o</td></tr>';
  }
}

function admBuscarUsuarios(val) {
  clearTimeout(ADM_STATE.debounce);
  ADM_STATE.debounce = setTimeout(function() { admCarregarUsuarios(1, val); }, 350);
}

function admRenderUsuarios(items, tbody) {
  if (!items || !items.length) {
    tbody.innerHTML = '<tr><td colspan="6"><div class="adm-empty"><div class="icon">\U0001f464</div><p>Nenhum usu\u00e1rio encontrado.</p></div></td></tr>';
    return;
  }
  tbody.innerHTML = items.map(function(u) {
    var roles  = (u.roles || []).join(', ') || 'assinante';
    var tagCls = roles.includes('administrator') ? 'admin'
               : roles.includes('editor')        ? 'editor'
               : roles.includes('author')         ? 'autor'
               : 'assinante';
    return '<tr>' +
      '<td>' + admEsc(u.ID) + '</td>' +
      '<td>' + admEsc(u.user_login) + '</td>' +
      '<td>' + admEsc(u.user_email) + '</td>' +
      '<td>' + admEsc(u.display_name || '\u2014') + '</td>' +
      '<td><span class="adm-tag ' + tagCls + '">' + admEsc(roles) + '</span></td>' +
      '<td>' +
        '<button class="adm-btn adm-btn-sm adm-btn-secondary adm-role-btn"' +
          ' data-id="' + u.ID + '"' +
          ' data-nome="' + admEsc(u.user_login) + '"' +
          ' data-role="' + admEsc(roles) + '">Papel</button> ' +
        '<button class="adm-btn adm-btn-sm adm-btn-danger adm-del-user-btn"' +
          ' data-id="' + u.ID + '"' +
          ' data-nome="' + admEsc(u.user_login) + '">Excluir</button>' +
      '</td>' +
    '</tr>';
  }).join('');
}

// ── Imóveis ────────────────────────────────────────────────────────────────
async function admCarregarImoveis(pag, busca) {
  if (pag   !== undefined) ADM_STATE.pagImoveis   = pag;
  if (busca !== undefined) ADM_STATE.buscaImoveis = busca;
  var tbody = document.getElementById('tbodyAdmImoveis');
  if (!tbody) return;
  tbody.innerHTML = '<tr><td colspan="7" class="adm-loading"></td></tr>';
  try {
    var p = new URLSearchParams({
      action    : 'imoveis',
      pagina    : ADM_STATE.pagImoveis,
      busca     : ADM_STATE.buscaImoveis,
      por_pagina: 20,
    });
    var r = await fetch('/api/admin.php?' + p, { credentials: 'include' });
    var d = await r.json();
    if (!d.success) {
      tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:28px;color:#e53e3e">\u26a0\ufe0f ' + admEsc(d.message) + '</td></tr>';
      return;
    }
    admRenderImoveis(d.data.items, tbody);
    admRenderPaginacao(
      document.getElementById('paginacaoAdmImoveis'),
      d.data.paginas, d.data.pagina, 'admCarregarImoveis'
    );
  } catch(e) {
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:28px;color:#e53e3e">\u26a0\ufe0f Erro de conex\u00e3o</td></tr>';
  }
}

function admBuscarImoveis(val) {
  clearTimeout(ADM_STATE.debounce);
  ADM_STATE.debounce = setTimeout(function() { admCarregarImoveis(1, val); }, 350);
}

function admRenderImoveis(items, tbody) {
  if (!items || !items.length) {
    tbody.innerHTML = '<tr><td colspan="7"><div class="adm-empty"><div class="icon">\U0001f3e0</div><p>Nenhum im\u00f3vel encontrado.</p></div></td></tr>';
    return;
  }
  tbody.innerHTML = items.map(function(im) {
    var status      = im.status === 'publish' ? 'ativo' : 'inativo';
    var statusLabel = im.status === 'publish' ? 'Publicado' : (im.status || '\u2014');
    return '<tr>' +
      '<td>' + admEsc(im.ID) + '</td>' +
      '<td style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' +
        '<a href="/imovel/?p=' + im.ID + '" target="_blank" style="color:#e05a1e;text-decoration:none">' +
          admEsc(im.post_title || '\u2014') +
        '</a>' +
      '</td>' +
      '<td>' + admEsc(im.numero_edital || '\u2014') + '</td>' +
      '<td>' + admEsc(im.cidade || '\u2014') + '</td>' +
      '<td>' + admEsc(im.estado || '\u2014') + '</td>' +
      '<td><span class="adm-tag ' + status + '">' + admEsc(statusLabel) + '</span></td>' +
      '<td>' +
        '<button class="adm-btn adm-btn-sm adm-btn-danger adm-del-imovel-btn"' +
          ' data-id="' + im.ID + '"' +
          ' data-titulo="' + admEsc(im.post_title || 'Im\u00f3vel') + '">Excluir</button>' +
      '</td>' +
    '</tr>';
  }).join('');
}

// ── Paginação ──────────────────────────────────────────────────────────────
function admRenderPaginacao(el, total, atual, fnName) {
  if (!el || total <= 1) { if (el) el.innerHTML = ''; return; }
  var pages = [1];
  if (atual - 2 > 2) pages.push('...');
  for (var i = Math.max(2, atual - 2); i <= Math.min(total - 1, atual + 2); i++) pages.push(i);
  if (atual + 2 < total - 1) pages.push('...');
  if (total > 1) pages.push(total);
  el.innerHTML = pages.map(function(p) {
    if (p === '...') return '<button disabled style="cursor:default;color:#ccc">\u2026</button>';
    return '<button class="' + (p === atual ? 'ativa' : '') + '" onclick="' + fnName + '(' + p + ')">' + p + '</button>';
  }).join('');
}

// ── Deletar usuário ────────────────────────────────────────────────────────
async function admDeletarUsuario(id, login) {
  if (!confirm('Excluir usu\u00e1rio "' + login + '"? Esta a\u00e7\u00e3o n\u00e3o pode ser desfeita.')) return;
  try {
    var r = await fetch('/api/admin.php', {
      method     : 'POST',
      credentials: 'include',
      headers    : { 'Content-Type': 'application/json' },
      body       : JSON.stringify({ action: 'usuario_delete', id: id }),
    });
    var d = await r.json();
    if (d.success) {
      mostrarToast('sucesso', 'Exclu\u00eddo!', 'Usu\u00e1rio removido.');
      admCarregarUsuarios();
      admCarregarStats();
    } else {
      mostrarToast('erro', 'Erro', d.message);
    }
  } catch(e) { mostrarToast('erro', 'Erro', 'Falha na conex\u00e3o'); }
}

// ── Modal papel ────────────────────────────────────────────────────────────
function admAbrirModalRole(id, nome, roleAtual) {
  ADM_STATE.modalRoleId = id;
  document.getElementById('admModalRoleNome').textContent = nome;
  var sel = document.getElementById('admModalRoleSelect');
  sel.value = roleAtual.includes('administrator') ? 'administrator'
            : roleAtual.includes('editor')        ? 'editor'
            : roleAtual.includes('author')         ? 'author'
            : 'subscriber';
  document.getElementById('admModalRoleOverlay').classList.add('ativo');
}

function admFecharModalRole() {
  document.getElementById('admModalRoleOverlay').classList.remove('ativo');
  ADM_STATE.modalRoleId = null;
}

async function admSalvarRole() {
  var role = document.getElementById('admModalRoleSelect').value;
  if (!ADM_STATE.modalRoleId || !role) return;
  try {
    var r = await fetch('/api/admin.php', {
      method     : 'POST',
      credentials: 'include',
      headers    : { 'Content-Type': 'application/json' },
      body       : JSON.stringify({ action: 'usuario_role', id: ADM_STATE.modalRoleId, role: role }),
    });
    var d = await r.json();
    if (d.success) {
      mostrarToast('sucesso', 'Salvo!', 'Papel atualizado.');
      admFecharModalRole();
      admCarregarUsuarios();
    } else {
      mostrarToast('erro', 'Erro', d.message);
    }
  } catch(e) { mostrarToast('erro', 'Erro', 'Falha na conex\u00e3o'); }
}

// ── Deletar imóvel ─────────────────────────────────────────────────────────
async function admDeletarImovel(id, titulo) {
  if (!confirm('Excluir o im\u00f3vel "' + titulo + '" (ID ' + id + ')?')) return;
  try {
    var r = await fetch('/api/admin.php', {
      method     : 'POST',
      credentials: 'include',
      headers    : { 'Content-Type': 'application/json' },
      body       : JSON.stringify({ action: 'imovel_delete', id: id }),
    });
    var d = await r.json();
    if (d.success) {
      mostrarToast('sucesso', 'Exclu\u00eddo!', 'Im\u00f3vel removido.');
      admCarregarImoveis();
      admCarregarStats();
    } else {
      mostrarToast('erro', 'Erro', d.message);
    }
  } catch(e) { mostrarToast('erro', 'Erro', 'Falha na conex\u00e3o'); }
}

// ── Importação ─────────────────────────────────────────────────────────────
async function admRodarImport(tipo) {
  var cap = tipo.charAt(0).toUpperCase() + tipo.slice(1);
  var btn = document.getElementById('btnAdmImport' + cap);
  var log = document.getElementById('logAdm' + cap);
  if (btn) { btn.disabled = true; btn.textContent = '\u23f3 Importando...'; }
  if (log) log.textContent = '> Iniciando importa\u00e7\u00e3o de ' + tipo + '...\n';
  try {
    var r = await fetch('/api/admin.php', {
      method     : 'POST',
      credentials: 'include',
      headers    : { 'Content-Type': 'application/json' },
      body       : JSON.stringify({ action: 'importar', tipo: tipo }),
    });
    var d = await r.json();
    if (log) {
      log.textContent += d.success
        ? '> ' + (d.message || 'Conclu\u00eddo.') + '\n> \u2705 Feito!\n'
        : '> \u274c Erro: ' + (d.message || 'Falha') + '\n';
    }
    if (d.success) admCarregarStats();
  } catch(e) {
    if (log) log.textContent += '> \u274c ' + e.message + '\n';
  } finally {
    if (btn) { btn.disabled = false; btn.textContent = btn.dataset.label || 'Importar'; }
  }
}

// ── Helpers ────────────────────────────────────────────────────────────────
function admEsc(s) {
  if (s == null) return '';
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

// ── Event delegation para botões das tabelas ───────────────────────────────
document.addEventListener('click', function(e) {
  var btn = e.target.closest('button');
  if (!btn) return;
  var id = parseInt(btn.dataset.id, 10);
  if (btn.classList.contains('adm-role-btn')) {
    admAbrirModalRole(id, btn.dataset.nome, btn.dataset.role);
  } else if (btn.classList.contains('adm-del-user-btn')) {
    admDeletarUsuario(id, btn.dataset.nome);
  } else if (btn.classList.contains('adm-del-imovel-btn')) {
    admDeletarImovel(id, btn.dataset.titulo);
  }
});
"""
        content = content + admin_js
        changed = True
        print('dashboard.js: funções admin acrescentadas ✓')

    if changed:
        with open(path, 'w', encoding='utf-8') as f:
            f.write(content)
        print('dashboard.js: salvo ✓')
    else:
        print('dashboard.js: nenhuma mudança necessária')


# ══════════════════════════════════════════════════════════════════════════════
if __name__ == '__main__':
    print('=== Iniciando patch admin ===')
    patch_auth()
    patch_dashboard_html()
    patch_dashboard_js()
    print('\n=== Concluído! ===')
