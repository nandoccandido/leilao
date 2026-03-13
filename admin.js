/* =====================================================
   ADMIN PANEL — admin.js
   Área administrativa do Leilão SaySix
   ===================================================== */

'use strict';

const API_ADMIN = '/api/admin.php';
const API_AUTH  = '/api/auth.php';

/* ============================================================
   STATE
   ============================================================ */
const state = {
  usuario_id     : null,
  usuario_nome   : '',
  pagUsuarios    : 1,
  pagImoveis     : 1,
  buscaUsuarios  : '',
  buscaImoveis   : '',
  modalRoleId    : null,
  debounceTimer  : null,
};

/* ============================================================
   INIT
   ============================================================ */
async function init() {
  // 1. Verifica autenticação
  let me;
  try {
    const r = await fetch(API_AUTH + '?action=me', { credentials: 'include' });
    me = await r.json();
  } catch (e) {
    window.location.href = '/';
    return;
  }

  if (!me.success || !me.data) {
    window.location.href = '/';
    return;
  }

  // 2. Verifica se é administrador
  const roles = me.data.roles || [];
  if (!roles.includes('administrator')) {
    alert('Acesso restrito a administradores.');
    window.location.href = '/';
    return;
  }

  state.usuario_id   = me.data.id;
  state.usuario_nome = me.data.nome || me.data.display_name || me.data.login;

  document.getElementById('admNome').textContent = state.usuario_nome;
  atualizarRelogio();
  setInterval(atualizarRelogio, 1000);

  // 3. Carrega painel inicial
  trocarPainel('visao-geral', null);
  carregarStats();
}

/* ============================================================
   RELÓGIO
   ============================================================ */
function atualizarRelogio() {
  const el = document.getElementById('admDataHora');
  if (el) {
    const now = new Date();
    el.textContent = now.toLocaleDateString('pt-BR', {
      weekday: 'short', year: 'numeric', month: 'short', day: 'numeric',
      hour: '2-digit', minute: '2-digit',
    });
  }
}

/* ============================================================
   NAVEGAÇÃO LATERAL
   ============================================================ */
function trocarPainel(id, elClicado) {
  // Oculta todos os painéis
  document.querySelectorAll('.adm-painel').forEach(p => p.classList.remove('ativo'));
  document.querySelectorAll('.adm-nav__item').forEach(n => n.classList.remove('ativo'));

  // Ativa o painel solicitado
  const painel = document.getElementById('painel-' + id);
  if (painel) painel.classList.add('ativo');

  // Destaca item do menu (via elemento ou via data-painel)
  if (elClicado) {
    elClicado.classList.add('ativo');
  } else {
    const item = document.querySelector('[data-painel="' + id + '"]');
    if (item) item.classList.add('ativo');
  }

  // Carrega dados ao abrir painel
  if (id === 'usuarios' && !document.getElementById('tbodyUsuarios').dataset.carregado) {
    carregarUsuarios();
  }
  if (id === 'imoveis' && !document.getElementById('tbodyImoveis').dataset.carregado) {
    carregarImoveis();
  }
}

/* ============================================================
   ESTATÍSTICAS
   ============================================================ */
async function carregarStats() {
  try {
    const r = await fetch(API_ADMIN + '?action=stats', { credentials: 'include' });
    const d = await r.json();
    if (!d.success) return;

    const s = d.data;
    setEl('stTotUsuarios', s.total_usuarios ?? '—');
    setEl('stTotImoveis',  s.total_imoveis  ?? '—');
    setEl('stTotVeiculos', s.total_veiculos ?? '—');
    setEl('stNovosHoje',   s.novos_hoje     ?? '—');

    // Atualiza badges na sidebar
    const badgeU = document.getElementById('badgeUsuarios');
    const badgeI = document.getElementById('badgeImoveis');
    if (badgeU) badgeU.textContent = s.total_usuarios ?? '';
    if (badgeI) badgeI.textContent = s.total_imoveis  ?? '';
  } catch (e) {
    console.warn('Erro ao carregar stats:', e);
  }
}

/* ============================================================
   USUÁRIOS
   ============================================================ */
async function carregarUsuarios(pagina, busca) {
  if (pagina !== undefined) state.pagUsuarios = pagina;
  if (busca  !== undefined) state.buscaUsuarios = busca;

  const tbody = document.getElementById('tbodyUsuarios');
  tbody.innerHTML = '<tr><td colspan="6" class="adm-loading"></td></tr>';

  try {
    const params = new URLSearchParams({
      action : 'usuarios',
      pagina : state.pagUsuarios,
      busca  : state.buscaUsuarios,
      por_pagina: 20,
    });
    const r = await fetch(API_ADMIN + '?' + params, { credentials: 'include' });
    const d = await r.json();

    if (!d.success) { renderErroTabela(tbody, 6, d.message); return; }

    tbody.dataset.carregado = '1';
    renderTabelaUsuarios(d.data.items, tbody);
    renderPaginacao(
      document.getElementById('paginacaoUsuarios'),
      d.data.paginas,
      d.data.pagina,
      (p) => carregarUsuarios(p),
    );
  } catch (e) {
    renderErroTabela(tbody, 6, 'Erro de conexão');
  }
}

function buscarUsuarios(val) {
  clearTimeout(state.debounceTimer);
  state.debounceTimer = setTimeout(() => carregarUsuarios(1, val), 350);
}

function renderTabelaUsuarios(items, tbody) {
  if (!items || !items.length) {
    tbody.innerHTML = `<tr><td colspan="6">
      <div class="adm-empty"><div class="icon">👤</div><p>Nenhum usuário encontrado.</p></div>
    </td></tr>`;
    return;
  }

  tbody.innerHTML = items.map(u => {
    const roles = (u.roles || []).join(', ') || 'assinante';
    const tagCls = roles.includes('administrator') ? 'admin'
                 : roles.includes('editor')        ? 'editor'
                 : roles.includes('author')        ? 'autor'
                 : 'assinante';
    return `<tr>
      <td>${esc(u.ID)}</td>
      <td>${esc(u.user_login)}</td>
      <td>${esc(u.user_email)}</td>
      <td>${esc(u.display_name || '—')}</td>
      <td><span class="adm-tag ${tagCls}">${esc(roles)}</span></td>
      <td>
        <button class="adm-btn adm-btn-sm adm-btn-secondary"
          onclick="abrirModalRole(${u.ID}, '${escJs(u.user_login)}', '${escJs(roles)}')">
          Papel
        </button>
        <button class="adm-btn adm-btn-sm adm-btn-danger" style="margin-left:4px"
          onclick="deletarUsuario(${u.ID}, '${escJs(u.user_login)}')">
          Excluir
        </button>
      </td>
    </tr>`;
  }).join('');
}

/* ============================================================
   IMÓVEIS
   ============================================================ */
async function carregarImoveis(pagina, busca) {
  if (pagina !== undefined) state.pagImoveis = pagina;
  if (busca  !== undefined) state.buscaImoveis = busca;

  const tbody = document.getElementById('tbodyImoveis');
  tbody.innerHTML = '<tr><td colspan="7" class="adm-loading"></td></tr>';

  try {
    const params = new URLSearchParams({
      action : 'imoveis',
      pagina : state.pagImoveis,
      busca  : state.buscaImoveis,
      por_pagina: 20,
    });
    const r = await fetch(API_ADMIN + '?' + params, { credentials: 'include' });
    const d = await r.json();

    if (!d.success) { renderErroTabela(tbody, 7, d.message); return; }

    tbody.dataset.carregado = '1';
    renderTabelaImoveis(d.data.items, tbody);
    renderPaginacao(
      document.getElementById('paginacaoImoveis'),
      d.data.paginas,
      d.data.pagina,
      (p) => carregarImoveis(p),
    );
  } catch (e) {
    renderErroTabela(tbody, 7, 'Erro de conexão');
  }
}

function buscarImoveis(val) {
  clearTimeout(state.debounceTimer);
  state.debounceTimer = setTimeout(() => carregarImoveis(1, val), 350);
}

function renderTabelaImoveis(items, tbody) {
  if (!items || !items.length) {
    tbody.innerHTML = `<tr><td colspan="7">
      <div class="adm-empty"><div class="icon">🏠</div><p>Nenhum imóvel encontrado.</p></div>
    </td></tr>`;
    return;
  }

  tbody.innerHTML = items.map(im => {
    const status = im.status === 'publish' ? 'ativo' : 'inativo';
    const statusLabel = im.status === 'publish' ? 'Publicado' : (im.status || '—');
    return `<tr>
      <td>${esc(im.ID)}</td>
      <td style="max-width:220px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis">
        <a href="/imovel/?p=${im.ID}" target="_blank" style="color:#e05a1e; text-decoration:none">
          ${esc(im.post_title || '—')}
        </a>
      </td>
      <td>${esc(im.numero_edital || '—')}</td>
      <td>${esc(im.cidade || '—')}</td>
      <td>${esc(im.estado || '—')}</td>
      <td><span class="adm-tag ${status}">${esc(statusLabel)}</span></td>
      <td>
        <button class="adm-btn adm-btn-sm adm-btn-danger"
          onclick="deletarImovel(${im.ID}, '${escJs(im.post_title || 'Imóvel')}')">
          Excluir
        </button>
      </td>
    </tr>`;
  }).join('');
}

/* ============================================================
   PAGINAÇÃO
   ============================================================ */
function renderPaginacao(el, totalPaginas, paginaAtual, onClickFn) {
  if (!el) return;
  if (totalPaginas <= 1) { el.innerHTML = ''; return; }

  const max   = 7; // máx botões visíveis
  const delta = 2;

  let pages = [];
  pages.push(1);
  if (paginaAtual - delta > 2)  pages.push('...');
  for (let i = Math.max(2, paginaAtual - delta); i <= Math.min(totalPaginas - 1, paginaAtual + delta); i++) {
    pages.push(i);
  }
  if (paginaAtual + delta < totalPaginas - 1) pages.push('...');
  if (totalPaginas > 1) pages.push(totalPaginas);

  el.innerHTML = pages.map(p => {
    if (p === '...') return `<button disabled style="cursor:default; color:#ccc">…</button>`;
    const ativa = p === paginaAtual ? 'ativa' : '';
    return `<button class="${ativa}" onclick="(${onClickFn})(${p})">${p}</button>`;
  }).join('');
}

/* ============================================================
   DELETAR USUÁRIO
   ============================================================ */
async function deletarUsuario(id, login) {
  if (!confirm(`Tem certeza que deseja excluir o usuário "${login}"? Esta ação não pode ser desfeita.`)) return;

  try {
    const r = await fetch(API_ADMIN, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'usuario_delete', id }),
    });
    const d = await r.json();
    if (d.success) {
      mostrarToast('Usuário excluído com sucesso.', 'ok');
      carregarUsuarios();
      carregarStats();
    } else {
      mostrarToast('Erro: ' + d.message, 'erro');
    }
  } catch (e) {
    mostrarToast('Erro de conexão.', 'erro');
  }
}

/* ============================================================
   MODAL — ALTERAR PAPEL
   ============================================================ */
function abrirModalRole(id, nome, roleAtual) {
  state.modalRoleId = id;
  document.getElementById('modalRoleNome').textContent   = nome;
  document.getElementById('modalRoleSelect').value = roleAtual.includes('administrator') ? 'administrator'
    : roleAtual.includes('editor')    ? 'editor'
    : roleAtual.includes('author')    ? 'author'
    : 'subscriber';
  document.getElementById('modalRoleOverlay').classList.add('ativo');
}

function fecharModalRole() {
  document.getElementById('modalRoleOverlay').classList.remove('ativo');
  state.modalRoleId = null;
}

async function salvarRole() {
  const role = document.getElementById('modalRoleSelect').value;
  if (!state.modalRoleId || !role) return;

  try {
    const r = await fetch(API_ADMIN, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'usuario_role', id: state.modalRoleId, role }),
    });
    const d = await r.json();
    if (d.success) {
      mostrarToast('Papel atualizado com sucesso.', 'ok');
      fecharModalRole();
      carregarUsuarios();
    } else {
      mostrarToast('Erro: ' + d.message, 'erro');
    }
  } catch (e) {
    mostrarToast('Erro de conexão.', 'erro');
  }
}

/* ============================================================
   DELETAR IMÓVEL
   ============================================================ */
async function deletarImovel(id, titulo) {
  if (!confirm(`Excluir o imóvel "${titulo}" (ID ${id})? Esta ação não pode ser desfeita.`)) return;

  try {
    const r = await fetch(API_ADMIN, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'imovel_delete', id }),
    });
    const d = await r.json();
    if (d.success) {
      mostrarToast('Imóvel excluído.', 'ok');
      carregarImoveis();
      carregarStats();
    } else {
      mostrarToast('Erro: ' + d.message, 'erro');
    }
  } catch (e) {
    mostrarToast('Erro de conexão.', 'erro');
  }
}

/* ============================================================
   IMPORTAÇÃO
   ============================================================ */
async function rodarImport(tipo) {
  const btn = document.getElementById('btnImport' + tipo.charAt(0).toUpperCase() + tipo.slice(1));
  const log = document.getElementById('log' + tipo.charAt(0).toUpperCase() + tipo.slice(1));

  if (btn) { btn.disabled = true; btn.textContent = '⏳ Importando...'; }
  if (log) log.textContent = '> Iniciando importação de ' + tipo + '...\n';

  try {
    const r = await fetch(API_ADMIN, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'importar', tipo }),
    });
    const d = await r.json();
    if (log) {
      if (d.success) {
        log.textContent += '> ' + (d.message || 'Importação concluída.') + '\n';
        if (d.data && d.data.log) log.textContent += d.data.log;
        log.textContent += '> ✅ Concluído!\n';
      } else {
        log.textContent += '> ❌ Erro: ' + (d.message || 'Falha desconhecida') + '\n';
      }
    }
    if (d.success) carregarStats();
  } catch (e) {
    if (log) log.textContent += '> ❌ Erro de conexão: ' + e.message + '\n';
  } finally {
    if (btn) { btn.disabled = false; btn.textContent = btn.dataset.label || 'Importar'; }
  }
}

/* ============================================================
   LOGOUT
   ============================================================ */
async function fazerLogout() {
  try {
    await fetch(API_AUTH + '?action=logout', { method: 'POST', credentials: 'include' });
  } catch (_) {}
  window.location.href = '/';
}

/* ============================================================
   TOAST
   ============================================================ */
function mostrarToast(msg, tipo) {
  const id = 'adm-toast-' + Date.now();
  const toast = document.createElement('div');
  toast.id = id;
  toast.style.cssText = `
    position:fixed; bottom:28px; right:28px; z-index:9999;
    background:${tipo === 'ok' ? '#1a8a3a' : '#e53e3e'};
    color:#fff; padding:12px 22px; border-radius:10px;
    font-size:.9rem; font-weight:600; box-shadow:0 4px 16px rgba(0,0,0,.25);
    animation:slideInRight .25s ease; max-width:360px;
  `;
  toast.textContent = msg;
  document.body.appendChild(toast);

  // Injeta keyframe se necessário
  if (!document.getElementById('adm-toast-style')) {
    const s = document.createElement('style');
    s.id = 'adm-toast-style';
    s.textContent = '@keyframes slideInRight{from{transform:translateX(120%);opacity:0}}';
    document.head.appendChild(s);
  }

  setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity .3s'; }, 3200);
  setTimeout(() => toast.remove(), 3600);
}

/* ============================================================
   HELPERS
   ============================================================ */
function setEl(id, val) {
  const el = document.getElementById(id);
  if (el) el.textContent = val;
}

function renderErroTabela(tbody, cols, msg) {
  tbody.innerHTML = `<tr><td colspan="${cols}" style="text-align:center; padding:28px; color:#e53e3e;">
    ⚠️ ${esc(msg || 'Erro ao carregar dados')}
  </td></tr>`;
}

function esc(str) {
  if (str === null || str === undefined) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function escJs(str) {
  if (str === null || str === undefined) return '';
  return String(str).replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"');
}

/* ============================================================
   FECHAR MODAL AO CLICAR NO BACKDROP
   ============================================================ */
document.addEventListener('click', function(e) {
  if (e.target.id === 'modalRoleOverlay') fecharModalRole();
});

/* ============================================================
   INICIALIZAR
   ============================================================ */
document.addEventListener('DOMContentLoaded', init);
