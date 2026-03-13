/* ============================================
   LEILÃO SAYSIX - DASHBOARD JS
   Lógica do painel do cliente
   ============================================ */

// ─── DADOS (inicialmente vazios — serão carregados via API) ──
let LEILOES_USUARIO = [];
let FAVORITOS = [];
let TRANSACOES = [];
let ALERTAS = [];
let ATIVIDADES = [];

// ─── TIPOS DE DOCUMENTO — PESSOA FÍSICA ────────────────────────────────────────────
const DOC_TIPOS_PF = [
  { key: 'identidade',        label: 'RG ou CNH',                desc: 'Documento de identidade oficial com foto',                           obrigatorio: true  },
  { key: 'cpf',               label: 'CPF',                      desc: 'Cartão ou comprovante do Cadastro de Pessoa Física',                  obrigatorio: true  },
  { key: 'comprovante_renda', label: 'Comprovante de Renda',     desc: 'Contracheque, extrato bancário, pró-labore ou declaração',            obrigatorio: true  },
  { key: 'comprovante_end',   label: 'Comprovante de Endereço',  desc: 'Conta de luz, água ou telefone dos últimos 3 meses',                  obrigatorio: true  },
  { key: 'certidao_civil',    label: 'Certidão de Estado Civil', desc: 'Certidão de nascimento, casamento, divórcio ou viuvez',               obrigatorio: true  },
  { key: 'declaracao_ir',     label: 'Declaração de IR',         desc: 'Última declaração de Imposto de Renda entregue à Receita Federal',    obrigatorio: false },
];

const DOC_TIPOS_PJ = [
  { key: 'contrato_social',         label: 'Contrato Social e Alterações',    desc: 'Contrato social consolidado e todas as alterações registradas',                    obrigatorio: true  },
  { key: 'cnpj',                    label: 'Cartão CNPJ',                      desc: 'Comprovante de inscrição no Cadastro Nacional da Pessoa Jurídica',                  obrigatorio: true  },
  { key: 'identidade_socio',        label: 'RG / CNH dos Sócios',              desc: 'Documento de identidade oficial com foto de todos os sócios',                      obrigatorio: true  },
  { key: 'cpf_socio',               label: 'CPF dos Sócios',                   desc: 'CPF de todos os sócios constantes no contrato social',                              obrigatorio: true  },
  { key: 'comprovante_end_empresa', label: 'Comprovante de Endereço (Empresa)', desc: 'Conta de serviço, contrato de locação ou correspondente em nome da empresa',          obrigatorio: true  },
  { key: 'balanco_patrimonial',     label: 'Balanço Patrimonial',               desc: 'Último balanço patrimonial assinado por contador habilitado (CRC)',                  obrigatorio: false },
  { key: 'certidoes_negativas',     label: 'Certidões Negativas',               desc: 'Certidões negativas de débitos municipais, estaduais e federais em nome da empresa', obrigatorio: false },
];

// ─── NAVEGAÇÃO ENTRE PAINÉIS ────────────────────────────────

function carregarUsuario() {
  fetch('/api/auth.php?action=check', {
    credentials: 'same-origin'
  })
  .then(function(res) {
    if (!res.ok) {
      // Não autenticado — redirecionar para login
      window.location.href = '/';
      return null;
    }
    return res.json();
  })
  .then(function(data) {
    if (!data || !data.ok) return;
    var u = data.usuario;
    preencherDadosUsuario(u);
  })
  .catch(function() {
    window.location.href = '/';
  });
}

function preencherDadosUsuario(u) {
  var nome = u.nome || 'Usuário';
  var primeiroNome = nome.split(' ')[0];
  var iniciais = nome.split(' ').map(function(p) { return p[0]; }).slice(0, 2).join('').toUpperCase();

  // Header
  var avatarEl = document.getElementById('headerAvatar');
  var nomeEl = document.getElementById('headerNome');
  if (avatarEl) avatarEl.textContent = iniciais;
  if (nomeEl) nomeEl.textContent = nome;

  // Saudação
  var tituloEl = document.getElementById('dashSaudacao');
  if (tituloEl) tituloEl.textContent = 'Olá, ' + primeiroNome + '! 👋';

  // Modo admin
  if (u.roles && u.roles.includes('administrator')) {
    ativarModoAdmin();
  }

  // Config - dados pessoais
  var inputNome = document.getElementById('configNome');
  if (inputNome) inputNome.value = u.nome || '';
  var inputEmail = document.getElementById('configEmail');
  if (inputEmail) inputEmail.value = u.email || '';
  var inputCPF = document.getElementById('configCPF');
  if (inputCPF) inputCPF.value = u.cpf || '';
  var inputTelefone = document.getElementById('configTelefone');
  if (inputTelefone) inputTelefone.value = u.telefone || '';

  // Config - endereço
  if (u.endereco) {
    var inputCEP = document.getElementById('configCEP');
    var inputRua = document.getElementById('configRua');
    var inputCidade = document.getElementById('configCidade');
    var inputUF = document.getElementById('configUF');
    if (inputCEP) inputCEP.value = u.endereco.cep || '';
    if (inputRua) inputRua.value = u.endereco.rua || '';
    if (inputCidade) inputCidade.value = u.endereco.cidade || '';
    if (inputUF) inputUF.value = u.endereco.uf || '';
  }
}

function trocarPainel(painel, btn) {
  // Desativar todos
  document.querySelectorAll('.dash__painel').forEach(p => p.classList.remove('ativo'));
  document.querySelectorAll('.dash__nav-item').forEach(b => b.classList.remove('ativo'));

  // Ativar selecionado
  const painelId = 'painel' + painel.charAt(0).toUpperCase() + painel.slice(1);
  const el = document.getElementById(painelId);
  if (el) el.classList.add('ativo');
  if (btn) btn.classList.add('ativo');

  // Fechar sidebar mobile
  fecharSidebarMobile();
}

// ─── RENDERIZAÇÃO ───────────────────────────

function renderLeilaoCard(item) {
  const statusTexto = { ativo: 'Ativo', aguardando: 'Aguardando', ganho: 'Ganho', perdido: 'Perdido' };
  return `
    <div class="dash__leilao-card" data-status="${item.status}">
      <img src="${item.imagem}" alt="${item.titulo}" class="dash__leilao-img" loading="lazy">
      <div class="dash__leilao-info">
        <div class="dash__leilao-header">
          <h3 class="dash__leilao-titulo">${item.titulo}</h3>
          <span class="dash__leilao-status dash__leilao-status--${item.status}">${statusTexto[item.status]}</span>
        </div>
        <span class="dash__leilao-local">📍 ${item.local}</span>
        <span class="dash__leilao-detalhes">${item.detalhes}</span>
        <div class="dash__leilao-footer">
          <div>
            <div class="dash__leilao-preco-label">Meu lance</div>
            <div class="dash__leilao-preco">R$ ${item.meuLance.toLocaleString('pt-BR')}</div>
          </div>
          <span class="dash__leilao-data">📅 ${item.dataLeilao}</span>
        </div>
        ${item.status === 'ganho' ? `<button class="btn btn--primario btn--sm" style="width:100%;margin-top:10px;" onclick="abrirGerenciarDocs(${item.imovel_id})">📄 Gerenciar Documentos</button>` : ''}
      </div>
    </div>`;
}

function renderFavoritoCard(item) {
  const badgeTipo = item.tipo === 'imovel' ? 'imovel' : 'veiculo';
  const badgeLabel = item.tipo === 'imovel' ? '🏠 Imóvel' : '🚗 Veículo';
  return `
    <div class="dash__fav-card" data-tipo="${item.tipo}">
      <div class="dash__fav-img-wrap">
        <img src="${item.imagem}" alt="${item.titulo}" loading="lazy">
        <span class="dash__fav-badge dash__fav-badge--${badgeTipo}">${badgeLabel}</span>
        <button class="dash__fav-remover" onclick="event.stopPropagation();removerFavorito(${item.id})" title="Remover dos favoritos">♥</button>
      </div>
      <div class="dash__fav-corpo">
        <h3 class="dash__fav-titulo">${item.titulo}</h3>
        <p class="dash__fav-local">📍 ${item.local}</p>
        <div class="dash__fav-footer">
          <div>
            <div class="dash__fav-preco">R$ ${item.preco.toLocaleString('pt-BR')}</div>
            <div class="dash__fav-countdown">📅 Leilão em ${item.dataLeilao}</div>
          </div>
        </div>
      </div>
    </div>`;
}

function renderTransacoes() {
  const tbody = document.getElementById('tabelaFinanceiro');
  if (!TRANSACOES.length) {
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:40px;color:var(--cor-texto-muted)">Nenhuma transação registrada</td></tr>';
    return;
  }
  tbody.innerHTML = TRANSACOES.map(t => `
    <tr>
      <td>${t.data}</td>
      <td>${t.descricao}</td>
      <td><span class="dash__tabela-tipo dash__tabela-tipo--${t.tipo}">${t.tipo.charAt(0).toUpperCase() + t.tipo.slice(1)}</span></td>
      <td><strong>${t.valor}</strong></td>
      <td><span class="dash__tabela-status dash__tabela-status--${t.status}">${t.status === 'pago' ? '✓ Pago' : '⏳ Pendente'}</span></td>
    </tr>`).join('');
}

function renderAlertas(filtro) {
  const container = document.getElementById('listaAlertas');
  const lista = filtro ? ALERTAS.filter(a => !a.lido) : ALERTAS;
  if (!lista.length) {
    container.innerHTML = '<div style="text-align:center;padding:40px;color:var(--cor-texto-muted)">Nenhuma notificação</div>';
    return;
  }
  container.innerHTML = lista.map(a => `
    <div class="dash__alerta ${a.lido ? '' : 'dash__alerta--nao-lido'}">
      <div class="dash__alerta-icone dash__alerta-icone--${a.tipo}">${a.icone}</div>
      <div class="dash__alerta-info">
        <div class="dash__alerta-titulo">${a.titulo}</div>
        <div class="dash__alerta-desc">${a.desc}</div>
      </div>
      <span class="dash__alerta-hora">${a.hora}</span>
    </div>`).join('');
}

function renderAtividades() {
  const container = document.getElementById('resumoAtividades');
  if (!ATIVIDADES.length) {
    container.innerHTML = '<div style="text-align:center;padding:40px;color:var(--cor-texto-muted)">Nenhuma atividade recente</div>';
    return;
  }
  container.innerHTML = ATIVIDADES.map(a => `
    <div class="dash__atividade">
      <div class="dash__atividade-icone">${a.icone}</div>
      <div class="dash__atividade-texto">${a.texto}</div>
      <span class="dash__atividade-hora">${a.hora}</span>
    </div>`).join('');
}

function renderResumoLeiloes() {
  const container = document.getElementById('resumoProximosLeiloes');
  const proximos = LEILOES_USUARIO.filter(l => l.status === 'ativo' || l.status === 'aguardando');
  if (!proximos.length) {
    container.innerHTML = '<div style="text-align:center;padding:40px;color:var(--cor-texto-muted)">Você ainda não participou de nenhum leilão.<br>Explore os leilões disponíveis na página principal!</div>';
    return;
  }
  container.innerHTML = proximos.map(renderLeilaoCard).join('');
}

function renderListaLeiloes(filtro) {
  const container = document.getElementById('listaLeiloes');
  const lista = filtro && filtro !== 'todos'
    ? LEILOES_USUARIO.filter(l => l.status === filtro)
    : LEILOES_USUARIO;
  container.innerHTML = lista.length
    ? lista.map(renderLeilaoCard).join('')
    : '<div style="text-align:center;padding:40px;color:var(--cor-texto-muted)">Nenhum leilão encontrado nesta categoria</div>';
}

function renderFavoritos(filtro) {
  const container = document.getElementById('gridFavoritos');
  let lista = FAVORITOS;
  if (filtro === 'imoveis') lista = FAVORITOS.filter(f => f.tipo === 'imovel');
  if (filtro === 'veiculos') lista = FAVORITOS.filter(f => f.tipo === 'veiculo');
  if (!lista.length) {
    container.innerHTML = '<div style="text-align:center;padding:40px;color:var(--cor-texto-muted)">Nenhum favorito salvo.<br>Marque leilões como favorito para acompanhá-los aqui.</div>';
    return;
  }
  container.innerHTML = lista.map(renderFavoritoCard).join('');
}

// ─── FILTROS ────────────────────────────────
function filtrarLeiloes(status, btn) {
  document.querySelectorAll('#painelLeiloes .dash__tab').forEach(t => t.classList.remove('ativo'));
  btn.classList.add('ativo');
  renderListaLeiloes(status);
}

function filtrarFavoritos(tipo, btn) {
  document.querySelectorAll('#painelFavoritos .dash__tab').forEach(t => t.classList.remove('ativo'));
  btn.classList.add('ativo');
  renderFavoritos(tipo);
}

// ─── AÇÕES ──────────────────────────────────
function removerFavorito(id) {
  const idx = FAVORITOS.findIndex(f => f.id === id);
  if (idx > -1) {
    FAVORITOS.splice(idx, 1);
    renderFavoritos('todos');
    mostrarToast('sucesso', 'Removido!', 'Item removido dos favoritos.');
  }
}

function marcarTodosLido() {
  ALERTAS.forEach(a => a.lido = true);
  renderAlertas();
  mostrarToast('sucesso', 'Pronto!', 'Todas notificações marcadas como lidas.');
}

// ─── CONFIG ─────────────────────────────────
function salvarDadosPessoais(e) {
  e.preventDefault();
  var nome = document.getElementById('configNome').value.trim();
  var telefone = document.getElementById('configTelefone').value.trim();
  var btn = e.target.querySelector('button[type="submit"]');
  btn.disabled = true;
  btn.textContent = 'Salvando...';

  fetch('/api/user.php?action=perfil', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',
    body: JSON.stringify({ nome: nome, telefone: telefone })
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    btn.disabled = false;
    btn.textContent = 'Salvar dados';
    if (data.ok) {
      preencherDadosUsuario(data.usuario);
      mostrarToast('sucesso', 'Salvo!', 'Dados pessoais atualizados com sucesso.');
    } else {
      mostrarToast('erro', 'Erro', data.erro || 'Não foi possível salvar');
    }
  })
  .catch(function() {
    btn.disabled = false;
    btn.textContent = 'Salvar dados';
    mostrarToast('erro', 'Erro', 'Falha na comunicação com o servidor');
  });
  return false;
}

function salvarEndereco(e) {
  e.preventDefault();
  var cep = document.getElementById('configCEP').value.trim();
  var rua = document.getElementById('configRua').value.trim();
  var cidade = document.getElementById('configCidade').value.trim();
  var uf = document.getElementById('configUF').value.trim();
  var btn = e.target.querySelector('button[type="submit"]');
  btn.disabled = true;
  btn.textContent = 'Salvando...';

  fetch('/api/user.php?action=endereco', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',
    body: JSON.stringify({ cep: cep, rua: rua, cidade: cidade, uf: uf })
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    btn.disabled = false;
    btn.textContent = 'Salvar endereço';
    if (data.ok) {
      mostrarToast('sucesso', 'Salvo!', 'Endereço atualizado com sucesso.');
    } else {
      mostrarToast('erro', 'Erro', data.erro || 'Não foi possível salvar');
    }
  })
  .catch(function() {
    btn.disabled = false;
    btn.textContent = 'Salvar endereço';
    mostrarToast('erro', 'Erro', 'Falha na comunicação com o servidor');
  });
  return false;
}

function salvarSenha(e) {
  e.preventDefault();
  var inputs = e.target.querySelectorAll('input[type="password"]');
  var senhaAtual = inputs[0].value;
  var novaSenha = inputs[1].value;
  var confirmaSenha = inputs[2].value;

  if (novaSenha !== confirmaSenha) {
    mostrarToast('erro', 'Erro', 'As senhas não coincidem');
    return false;
  }
  if (novaSenha.length < 6) {
    mostrarToast('erro', 'Erro', 'A nova senha deve ter no mínimo 6 caracteres');
    return false;
  }

  var btn = e.target.querySelector('button[type="submit"]');
  btn.disabled = true;
  btn.textContent = 'Salvando...';

  fetch('/api/user.php?action=senha', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',
    body: JSON.stringify({ senha_atual: senhaAtual, nova_senha: novaSenha })
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    btn.disabled = false;
    btn.textContent = 'Alterar senha';
    if (data.ok) {
      inputs[0].value = '';
      inputs[1].value = '';
      inputs[2].value = '';
      mostrarToast('sucesso', 'Senha alterada!', 'Sua senha foi atualizada com sucesso.');
    } else {
      mostrarToast('erro', 'Erro', data.erro || 'Não foi possível alterar a senha');
    }
  })
  .catch(function() {
    btn.disabled = false;
    btn.textContent = 'Alterar senha';
    mostrarToast('erro', 'Erro', 'Falha na comunicação com o servidor');
  });
  return false;
}

function handleLogout() {
  if (confirm('Deseja realmente sair?')) {
    fetch('/api/auth.php?action=logout', { credentials: 'same-origin' })
    .then(function() {
      window.location.href = '/';
    })
    .catch(function() {
      window.location.href = '/';
    });
  }
}

// ─── MOBILE ─────────────────────────────────
function toggleMenuMobile() {
  const sidebar = document.getElementById('dashSidebar');
  const overlay = document.getElementById('dashOverlay');
  const toggle = document.querySelector('.header__menu-toggle');

  sidebar.classList.toggle('ativo');
  overlay.classList.toggle('ativo');
  if (toggle) toggle.classList.toggle('ativo');
}

function fecharSidebarMobile() {
  const sidebar = document.getElementById('dashSidebar');
  const overlay = document.getElementById('dashOverlay');
  const toggle = document.querySelector('.header__menu-toggle');

  sidebar.classList.remove('ativo');
  overlay.classList.remove('ativo');
  if (toggle) toggle.classList.remove('ativo');
}

// ─── NOTIFICAÇÕES E MENU USUÁRIO ────────────
function toggleNotificacoes() {
  trocarPainel('alertas', document.querySelector('[data-painel="alertas"]'));
}

function toggleMenuUsuario() {
  trocarPainel('config', document.querySelector('[data-painel="config"]'));
}

// ─── TOASTS ─────────────────────────────────
function mostrarToast(tipo, titulo, mensagem) {
  const container = document.getElementById('toastContainer');
  const toast = document.createElement('div');
  toast.className = 'toast toast--' + tipo;
  toast.innerHTML =
    '<div class="toast__conteudo">' +
      '<div class="toast__titulo">' + titulo + '</div>' +
      '<div class="toast__mensagem">' + mensagem + '</div>' +
    '</div>' +
    '<button class="toast__fechar" onclick="this.parentElement.remove()">✕</button>';
  container.appendChild(toast);
  setTimeout(function() { toast.remove(); }, 4000);
}

// ─── INICIALIZAÇÃO ──────────────────────────
document.addEventListener('DOMContentLoaded', function() {
  carregarUsuario();
  carregarLancesUsuario();
  renderAtividades();
  renderFavoritos('todos');
  renderTransacoes();
  renderAlertas();
});

function carregarLancesUsuario() {
  fetch('/api/arrematacao.php?action=meus_lances', { credentials: 'same-origin' })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (!data.ok) return;
      LEILOES_USUARIO = data.lances.map(function(l) {
        return {
          imovel_id:      l.imovel_id,
          titulo:         l.titulo,
          imagem:         l.imagem || '',
          local:          '',
          detalhes:       l.valor_final ? 'Valor final: R$ ' + parseFloat(l.valor_final).toLocaleString('pt-BR', {minimumFractionDigits:2}) : '',
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
      // Badge documentação — alerta se há docs reprovados ou aguardando
      var lanceGanhos = data.lances.filter(function(l) { return l.status === 'ganho' && l.arr_status; });
      var temAlerta   = lanceGanhos.some(function(l) { return l.arr_status === 'reprovado' || l.arr_status === 'aguardando_documentos'; });
      var badgeDoc    = document.getElementById('badgeDocumentacao');
      if (badgeDoc) badgeDoc.style.display = (temAlerta ? '' : 'none');
    })
    .catch(function() {});
}

/* ============================================================
   MODO ADMIN
   ============================================================ */
function ativarModoAdmin() {
  document.querySelectorAll('.dash__nav-admin').forEach(function(el) {
    el.style.display = '';
  });

  // Oculta abas exclusivas de usuário
  ['leiloes', 'documentacao', 'favoritos', 'financeiro', 'alertas'].forEach(function(p) {
    var btn = document.querySelector('.dash__nav-item[data-painel="' + p + '"]');
    if (btn) btn.style.display = 'none';
  });

  admCarregarArrematacoes(1);
}

/* ============================================================
   ADMIN — ARREMATAÇÕES
   ============================================================ */
var _admArrPagAtual = 1;

function admCarregarArrematacoes(page) {
  _admArrPagAtual = page || 1;
  var busca  = (document.getElementById('admArrBusca') || {}).value || '';
  var status = (document.getElementById('admArrFiltroStatus') || {}).value || '';
  var url = '/api/arrematacao.php?action=listar_admin&page=' + _admArrPagAtual +
            '&busca=' + encodeURIComponent(busca) +
            '&status=' + encodeURIComponent(status);
  fetch(url, { credentials: 'same-origin' })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (!data.ok) return;
      // KPIs
      var s = data.stats || {};
      var el = function(id) { return document.getElementById(id); };
      if (el('admArrStatTotal'))      el('admArrStatTotal').textContent      = s.total      || 0;
      if (el('admArrStatAguardando')) el('admArrStatAguardando').textContent = s.aguardando || 0;
      if (el('admArrStatAprovado'))   el('admArrStatAprovado').textContent   = s.aprovado   || 0;
      if (el('admArrStatAnalise'))    el('admArrStatAnalise').textContent    = s.em_analise || 0;
      if (el('badgeAdmArr'))          el('badgeAdmArr').textContent          = s.total || 0;
      admRenderArrematacoes(data);
    })
    .catch(function(e) { console.error('admCarregarArrematacoes', e); });
}

function admRenderArrematacoes(data) {
  var tbody = document.getElementById('tbodyAdmArr');
  if (!tbody) return;
  if (!data.arrematacoes || !data.arrematacoes.length) {
    tbody.innerHTML = '<tr><td colspan="6" style="padding:32px;text-align:center;color:#94a3b8;">Nenhum resultado encontrado.</td></tr>';
    return;
  }
  var rows = data.arrematacoes.map(function(a) {
    var prazoHtml = a.prazo_restante !== null
      ? '<span style="color:' + (a.prazo_restante <= 3 ? '#ef4444' : '#64748b') + ';font-size:.8rem;">' + (a.prazo_restante >= 0 ? a.prazo_restante + 'd' : 'vencido') + '</span>'
      : '<span style="color:#94a3b8;font-size:.8rem;">—</span>';
    return '<tr>' +
      '<td style="padding:10px 12px;font-size:.875rem;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + htmlEsc(a.titulo) + '">' + htmlEsc(a.titulo) + '</td>' +
      '<td style="padding:10px 12px;font-size:.875rem;"><div>' + htmlEsc(a.arrematante_nome) + '</div><div style="font-size:.78rem;color:#94a3b8;">' + htmlEsc(a.arrematante_email) + '</div></td>' +
      '<td style="padding:10px 12px;text-align:right;font-size:.875rem;">R$ ' + parseFloat(a.valor_arrematacao || 0).toLocaleString('pt-BR', {minimumFractionDigits:2}) + '</td>' +
      '<td style="padding:10px 12px;text-align:center;">' + badgeStatusArr(a.status) + '</td>' +
      '<td style="padding:10px 12px;text-align:center;">' + prazoHtml + '</td>' +
      '<td style="padding:10px 12px;text-align:center;"><button onclick="admAbrirDetalheArr(' + a.id + ')" style="padding:6px 12px;background:#7c3aed;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:.8rem;">Abrir</button></td>' +
      '</tr>';
  }).join('');
  tbody.innerHTML = rows;

  // Paginação
  var pag = document.getElementById('admArrPaginacao');
  if (pag && data.paginacao) {
    var p = data.paginacao;
    var btns = '';
    if (p.pagina_atual > 1) btns += '<button onclick="admCarregarArrematacoes(' + (p.pagina_atual - 1) + ')" style="padding:6px 14px;border:1px solid #e2e8f0;border-radius:6px;cursor:pointer;">← Anterior</button>';
    btns += '<span style="padding:6px 10px;font-size:.875rem;color:#64748b;">' + p.pagina_atual + ' / ' + p.total_paginas + '</span>';
    if (p.pagina_atual < p.total_paginas) btns += '<button onclick="admCarregarArrematacoes(' + (p.pagina_atual + 1) + ')" style="padding:6px 14px;border:1px solid #e2e8f0;border-radius:6px;cursor:pointer;">Próxima →</button>';
    pag.innerHTML = btns;
  }
}

function admAbrirDetalheArr(id) {
  var overlay = document.getElementById('admModalArrOverlay');
  if (!overlay) return;
  overlay.style.display = 'block';
  document.getElementById('admArrDetalheId').value = id;
  document.getElementById('admArrModalTitulo').textContent = 'Carregando...';
  document.getElementById('admArrDocsList').innerHTML = '<p style="color:#94a3b8;">Carregando...</p>';

  fetch('/api/arrematacao.php?action=detalhe_admin&id=' + id, { credentials: 'same-origin' })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (!data.ok) { mostrarToast('Erro ao carregar detalhes.', 'erro'); return; }
      var a = data.arrematacao;
      document.getElementById('admArrModalTitulo').textContent = a.titulo + ' — ' + a.arrematante_nome;

      // Resumo
      var resumo = document.getElementById('admArrModalResumo');
      resumo.innerHTML =
        '<div><div style="font-size:.75rem;color:#94a3b8;">Arrematante</div><div style="font-weight:600;">' + htmlEsc(a.arrematante_nome) + '</div><div style="font-size:.8rem;color:#64748b;">' + htmlEsc(a.arrematante_email) + '</div></div>' +
        '<div><div style="font-size:.75rem;color:#94a3b8;">Valor Arrematado</div><div style="font-weight:600;color:#16a34a;">R$ ' + parseFloat(a.valor_arrematacao || 0).toLocaleString('pt-BR', {minimumFractionDigits:2}) + '</div></div>' +
        '<div><div style="font-size:.75rem;color:#94a3b8;">Status Atual</div><div>' + badgeStatusArr(a.status) + '</div></div>' +
        (a.data_limite_docs ? '<div><div style="font-size:.75rem;color:#94a3b8;">Prazo Documentos</div><div style="font-size:.875rem;">' + new Date(a.data_limite_docs).toLocaleDateString('pt-BR') + '</div></div>' : '');

      // Docs
      var docsHtml = admRenderDocsList(data.documentos || []);
      document.getElementById('admArrDocsList').innerHTML = docsHtml;

      // Pipeline status
      var pipeEl = document.getElementById('admArrPipeline');
      if (pipeEl) pipeEl.innerHTML = renderPipelineStr(a.status);
      var novoStatusEl = document.getElementById('admArrNovoStatus');
      if (novoStatusEl) novoStatusEl.value = a.status;

      // Notif dest
      var notifDest = document.getElementById('admArrNotifDest');
      if (notifDest) notifDest.textContent = 'Para: ' + a.arrematante_nome + ' <' + a.arrematante_email + '>';

      // Timeline
      var tlEl = document.getElementById('admArrTimeline');
      if (tlEl) tlEl.innerHTML = renderTimelineStr(data.timeline || []);

      // Ativar aba inicial
      admArrTrocarTab('docs', document.querySelector('.admArr-tab'));
    })
    .catch(function(e) { mostrarToast('Erro de conexão.', 'erro'); });
}

function admFecharDetalheArr() {
  var overlay = document.getElementById('admModalArrOverlay');
  if (overlay) overlay.style.display = 'none';
  admCarregarArrematacoes(_admArrPagAtual);
}

function admArrTrocarTab(tab, btn) {
  document.querySelectorAll('.admArr-tab').forEach(function(b) {
    b.style.borderBottomColor = 'transparent';
    b.style.color = '#64748b';
    b.style.fontWeight = '500';
  });
  document.querySelectorAll('.admArr-tab-content').forEach(function(c) {
    c.style.display = 'none';
  });
  if (btn) {
    btn.style.borderBottomColor = '#7c3aed';
    btn.style.color = '#7c3aed';
    btn.style.fontWeight = '600';
  }
  var content = document.getElementById('admArr-tab-' + tab);
  if (content) content.style.display = '';
}

function admRenderDocsList(docs) {
  if (!docs.length) return '<p style="color:#94a3b8;text-align:center;padding:24px;">Nenhum documento enviado ainda.</p>';
  return docs.map(function(d) {
    var statusBadge = d.status === 'aprovado'
      ? '<span style="background:#dcfce7;color:#16a34a;padding:2px 8px;border-radius:20px;font-size:.75rem;">✅ Aprovado</span>'
      : d.status === 'reprovado'
      ? '<span style="background:#fee2e2;color:#dc2626;padding:2px 8px;border-radius:20px;font-size:.75rem;">❌ Reprovado</span>'
      : '<span style="background:#fef9c3;color:#92400e;padding:2px 8px;border-radius:20px;font-size:.75rem;">⏳ Pendente</span>';
    var acoes = d.status === 'pendente'
      ? '<button onclick="admRevisarDoc(' + d.id + ',\'aprovado\')" style="padding:5px 12px;background:#16a34a;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:.8rem;margin-right:6px;">✅ Aprovar</button>' +
        '<button onclick="admRevisarDoc(' + d.id + ',\'reprovado\')" style="padding:5px 12px;background:#ef4444;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:.8rem;">❌ Reprovar</button>'
      : '<a href="' + htmlEsc(d.url_arquivo) + '" target="_blank" style="padding:5px 12px;background:#f1f5f9;color:#1e3a5f;border:none;border-radius:6px;cursor:pointer;font-size:.8rem;text-decoration:none;">📥 Ver</a>';
    return '<div style="display:flex;align-items:center;gap:12px;padding:12px;background:#f8fafc;border-radius:8px;margin-bottom:8px;flex-wrap:wrap;">' +
      '<div style="flex:1;min-width:140px;"><div style="font-weight:600;font-size:.875rem;">' + htmlEsc(d.tipo_doc_label || d.tipo_doc) + '</div>' +
      (d.nome_arquivo ? '<div style="font-size:.78rem;color:#64748b;">' + htmlEsc(d.nome_arquivo) + '</div>' : '') +
      '<div style="font-size:.75rem;color:#94a3b8;">' + new Date(d.criado_em).toLocaleDateString('pt-BR') + '</div></div>' +
      '<div>' + statusBadge + '</div>' +
      '<div style="display:flex;gap:6px;">' + acoes + '</div>' +
      (d.obs_revisao ? '<div style="width:100%;font-size:.8rem;color:#64748b;border-top:1px solid #e2e8f0;padding-top:8px;margin-top:4px;">Obs: ' + htmlEsc(d.obs_revisao) + '</div>' : '') +
      '</div>';
  }).join('');
}

function admRevisarDoc(docId, decisao) {
  var obs = '';
  if (decisao === 'reprovado') {
    obs = prompt('Motivo da reprovação (opcional):') || '';
  }
  var arrId = document.getElementById('admArrDetalheId').value;
  fetch('/api/arrematacao.php', {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=revisar_doc&doc_id=' + docId + '&decisao=' + encodeURIComponent(decisao) + '&obs=' + encodeURIComponent(obs),
  }).then(function(r) { return r.json(); })
    .then(function(data) {
      mostrarToast(data.ok ? (decisao === 'aprovado' ? '✅ Documento aprovado!' : '❌ Documento reprovado.') : (data.erro || 'Erro'), data.ok ? 'sucesso' : 'erro');
      if (data.ok && arrId) admAbrirDetalheArr(parseInt(arrId));
    });
}

function admAlterarStatus() {
  var id = document.getElementById('admArrDetalheId').value;
  var novoStatus = document.getElementById('admArrNovoStatus').value;
  var resEl = document.getElementById('admArrStatusResult');
  if (resEl) resEl.textContent = 'Salvando...';
  fetch('/api/arrematacao.php', {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=alterar_status&arrematacao_id=' + id + '&status=' + encodeURIComponent(novoStatus),
  }).then(function(r) { return r.json(); })
    .then(function(data) {
      if (resEl) resEl.textContent = data.ok ? '✅ Status atualizado!' : (data.erro || 'Erro');
      if (data.ok) {
        mostrarToast('Status atualizado com sucesso!', 'sucesso');
        admAbrirDetalheArr(parseInt(id));
      }
    });
}

var _admArrNotifTemplates = {
  docs_pendentes: { assunto: 'Lembrete: envio de documentos pendente', msg: 'Prezado(a) arrematante,\n\nGostaríamos de lembrá-lo(a) que a documentação referente ao imóvel arrematado ainda não foi enviada. Por favor, acesse o portal e realize o envio dentro do prazo.\n\nAtenciosamente,\nEquipe Qatar Leilões' },
  docs_reprovados: { assunto: 'Atenção: documentos reprovados', msg: 'Prezado(a) arrematante,\n\nInformamos que um ou mais documentos enviados foram reprovados. Por favor, acesse o portal para verificar os motivos e reenviar os documentos corrigidos.\n\nAtenciosamente,\nEquipe Qatar Leilões' },
  aprovacao: { assunto: 'Parabéns! Documentação aprovada', msg: 'Prezado(a) arrematante,\n\nTemos o prazer de informar que toda a documentação foi analisada e aprovada. Em breve nossa equipe entrará em contato para os próximos passos.\n\nAtenciosamente,\nEquipe Qatar Leilões' },
  prazo_expirado: { assunto: 'URGENTE: prazo de envio de documentos expirado', msg: 'Prezado(a) arrematante,\n\nO prazo para envio dos documentos expirou. Entre em contato conosco imediatamente para evitar a perda do direito de arrematação.\n\nAtenciosamente,\nEquipe Qatar Leilões' },
  concluido: { assunto: 'Processo de arrematação concluído', msg: 'Prezado(a) arrematante,\n\nInformamos que o processo de arrematação foi concluído com sucesso. Agradecemos sua confiança.\n\nAtenciosamente,\nEquipe Qatar Leilões' },
};

function admArrAplicarTemplate() {
  var key = document.getElementById('admArrNotifTemplate').value;
  if (!key || !_admArrNotifTemplates[key]) return;
  var t = _admArrNotifTemplates[key];
  document.getElementById('admArrNotifAssunto').value = t.assunto;
  document.getElementById('admArrNotifMensagem').value = t.msg;
}

function admEnviarNotif() {
  var id      = document.getElementById('admArrDetalheId').value;
  var assunto = document.getElementById('admArrNotifAssunto').value.trim();
  var mensagem = document.getElementById('admArrNotifMensagem').value.trim();
  var resEl   = document.getElementById('admArrNotifResult');
  if (!assunto || !mensagem) { mostrarToast('Preencha assunto e mensagem.', 'aviso'); return; }
  if (resEl) resEl.textContent = 'Enviando...';
  fetch('/api/arrematacao.php', {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=enviar_notif&arrematacao_id=' + id + '&assunto=' + encodeURIComponent(assunto) + '&mensagem=' + encodeURIComponent(mensagem),
  }).then(function(r) { return r.json(); })
    .then(function(data) {
      if (resEl) resEl.textContent = data.ok ? '✅ E-mail enviado!' : (data.erro || 'Erro');
      if (data.ok) mostrarToast('E-mail enviado com sucesso!', 'sucesso');
    });
}

/* ============================================================
   ADMIN — USUÁRIOS
   ============================================================ */
function admBuscarUsuarios() {
  var busca = (document.getElementById('admUserBusca') || {}).value || '';
  var tbody = document.getElementById('tbodyAdmUsuarios');
  if (tbody) tbody.innerHTML = '<tr><td colspan="4" style="padding:20px;text-align:center;color:#94a3b8;">Carregando...</td></tr>';
  fetch('/api/user.php?action=listar&busca=' + encodeURIComponent(busca), { credentials: 'same-origin' })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (!tbody) return;
      if (!data.ok || !data.usuarios || !data.usuarios.length) {
        tbody.innerHTML = '<tr><td colspan="4" style="padding:20px;text-align:center;color:#94a3b8;">Nenhum usuário encontrado.</td></tr>';
        return;
      }
      tbody.innerHTML = data.usuarios.map(function(u) {
        return '<tr>' +
          '<td style="padding:10px 12px;font-size:.875rem;">' + htmlEsc(u.nome || u.display_name) + '</td>' +
          '<td style="padding:10px 12px;font-size:.875rem;">' + htmlEsc(u.email || u.user_email) + '</td>' +
          '<td style="padding:10px 12px;text-align:center;font-size:.8rem;">' + htmlEsc((u.roles || ['subscriber']).join(', ')) + '</td>' +
          '<td style="padding:10px 12px;text-align:center;"><button onclick="admAbrirModalRole(' + u.id + ',\'' + htmlEsc(u.nome || u.display_name) + '\',\'' + ((u.roles || ['subscriber'])[0]) + '\')" style="padding:5px 12px;background:#2563eb;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:.8rem;">Editar Perfil</button></td>' +
          '</tr>';
      }).join('');
    })
    .catch(function() {
      if (tbody) tbody.innerHTML = '<tr><td colspan="4" style="padding:20px;text-align:center;color:#ef4444;">Erro ao carregar usuários.</td></tr>';
    });
}

function admAbrirModalRole(userId, nome, roleAtual) {
  var overlay = document.getElementById('admModalRoleOverlay');
  if (!overlay) return;
  overlay.style.display = 'flex';
  document.getElementById('admRoleUserId').value = userId;
  document.getElementById('admRoleUserNome').textContent = 'Usuário: ' + nome;
  document.getElementById('admRoleSelect').value = roleAtual || 'subscriber';
}

function admFecharModalRole() {
  var overlay = document.getElementById('admModalRoleOverlay');
  if (overlay) overlay.style.display = 'none';
}

function admSalvarRole() {
  var userId = document.getElementById('admRoleUserId').value;
  var role   = document.getElementById('admRoleSelect').value;
  fetch('/api/user.php', {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=alterar_role&user_id=' + userId + '&role=' + encodeURIComponent(role),
  }).then(function(r) { return r.json(); })
    .then(function(data) {
      mostrarToast(data.ok ? '✅ Perfil atualizado!' : (data.erro || 'Erro'), data.ok ? 'sucesso' : 'erro');
      if (data.ok) { admFecharModalRole(); admBuscarUsuarios(); }
    });
}

/* ============================================================
   ADMIN — IMÓVEIS (básico)
   ============================================================ */
function admBuscarImoveis() {
  var busca = (document.getElementById('admImovBusca') || {}).value || '';
  var tbody = document.getElementById('tbodyAdmImoveis');
  if (tbody) tbody.innerHTML = '<tr><td colspan="4" style="padding:20px;text-align:center;color:#94a3b8;">Carregando...</td></tr>';
  fetch('/api/admin.php?action=listar_imoveis&busca=' + encodeURIComponent(busca), { credentials: 'same-origin' })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (!tbody) return;
      if (!data.ok || !data.imoveis || !data.imoveis.length) {
        tbody.innerHTML = '<tr><td colspan="4" style="padding:20px;text-align:center;color:#94a3b8;">Nenhum imóvel encontrado.</td></tr>';
        return;
      }
      tbody.innerHTML = data.imoveis.map(function(im) {
        return '<tr>' +
          '<td style="padding:10px 12px;font-size:.875rem;max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + htmlEsc(im.titulo) + '">' + htmlEsc(im.titulo) + '</td>' +
          '<td style="padding:10px 12px;text-align:right;font-size:.875rem;">R$ ' + parseFloat(im.preco || 0).toLocaleString('pt-BR', {minimumFractionDigits:2}) + '</td>' +
          '<td style="padding:10px 12px;text-align:center;font-size:.8rem;">' + htmlEsc(im.status_leilao || '—') + '</td>' +
          '<td style="padding:10px 12px;text-align:center;"><a href="/wp-admin/post.php?post=' + im.id + '&action=edit" target="_blank" style="padding:5px 12px;background:#f1f5f9;color:#1e3a5f;border-radius:6px;font-size:.8rem;text-decoration:none;">✏️ Editar</a></td>' +
          '</tr>';
      }).join('');
    })
    .catch(function() {
      if (tbody) tbody.innerHTML = '<tr><td colspan="4" style="padding:20px;text-align:center;color:#ef4444;">Erro ao carregar imóveis.</td></tr>';
    });
}

/* ============================================================
   ADMIN — VEÍCULOS
   ============================================================ */
function admBuscarVeiculos() {
  var busca  = (document.getElementById('admVeicBusca') || {}).value || '';
  var tbody  = document.getElementById('tbodyAdmVeiculos');
  if (tbody) tbody.innerHTML = '<tr><td colspan="4" style="padding:20px;text-align:center;color:#94a3b8;">Carregando...</td></tr>';
  fetch('/api/admin.php?action=listar_veiculos&busca=' + encodeURIComponent(busca), { credentials: 'same-origin' })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (!tbody) return;
      if (!data.ok || !data.veiculos || !data.veiculos.length) {
        tbody.innerHTML = '<tr><td colspan="4" style="padding:20px;text-align:center;color:#94a3b8;">Nenhum veículo encontrado.</td></tr>';
        return;
      }
      tbody.innerHTML = data.veiculos.map(function(v) {
        return '<tr>' +
          '<td style="padding:10px 12px;font-size:.875rem;max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + htmlEsc(v.titulo) + '">' + htmlEsc(v.titulo) + '</td>' +
          '<td style="padding:10px 12px;text-align:right;font-size:.875rem;">R$ ' + parseFloat(v.preco || 0).toLocaleString('pt-BR', {minimumFractionDigits:2}) + '</td>' +
          '<td style="padding:10px 12px;text-align:center;font-size:.8rem;">' + htmlEsc(v.status_leilao || '—') + '</td>' +
          '<td style="padding:10px 12px;text-align:center;"><a href="/wp-admin/post.php?post=' + v.id + '&action=edit" target="_blank" style="padding:5px 12px;background:#f1f5f9;color:#1e3a5f;border-radius:6px;font-size:.8rem;text-decoration:none;">✏️ Editar</a></td>' +
          '</tr>';
      }).join('');
    })
    .catch(function() {
      if (tbody) tbody.innerHTML = '<tr><td colspan="4" style="padding:20px;text-align:center;color:#ef4444;">Erro ao carregar veículos.</td></tr>';
    });
}

/* ============================================================
   ADMIN — IMPORTAÇÃO
   ============================================================ */
function admRodarImport(tipo) {
  var btnId  = tipo === 'imoveis' ? 'btnAdmImportImoveis' : 'btnAdmImportVeiculos';
  var logId  = tipo === 'imoveis' ? 'logAdmImoveis' : 'logAdmVeiculos';
  var apiUrl = tipo === 'imoveis' ? '/api/importar-caixa.php' : '/api/importar-veiculos.php';
  var btn = document.getElementById(btnId);
  var log = document.getElementById(logId);
  if (btn) { btn.disabled = true; btn.textContent = '⏳ Importando...'; }
  if (log) log.innerHTML = '<em>Iniciando importação...</em>';
  fetch(apiUrl, { credentials: 'same-origin' })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (log) log.innerHTML = '<strong>' + (data.ok ? '✅ Concluído' : '❌ Erro') + '</strong><br>' + (data.mensagem || JSON.stringify(data));
      mostrarToast(data.ok ? 'Importação concluída!' : 'Erro na importação.', data.ok ? 'sucesso' : 'erro');
    })
    .catch(function(e) {
      if (log) log.innerHTML = '<span style="color:#ef4444;">Erro de conexão: ' + e.message + '</span>';
    })
    .finally(function() {
      if (btn) { btn.disabled = false; btn.textContent = '▶ Iniciar Importação'; }
    });
}

/* ============================================================
   ARREMATANTE — PAINEL DOCUMENTAÇÃO (aba dedicada)
   ============================================================ */

function carregarPainelDocumentacao(imovelIdOpt) {
  var content = document.getElementById('docPainelContent');
  if (content) content.innerHTML = '<p style="text-align:center;color:#94a3b8;padding:60px;">⏳ Carregando...</p>';

  fetch('/api/arrematacao.php?action=meus_processos', { credentials: 'same-origin' })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (!data.ok || !(data.processos || []).length) {
        if (content) content.innerHTML = docPainelVazio();
        return;
      }
      var processos = data.processos;
      var alvo = null;
      if (imovelIdOpt) {
        alvo = processos.find(function(p) { return p.imovel_id == imovelIdOpt; });
      }
      if (alvo || processos.length === 1) {
        carregarDetalheDoc((alvo || processos[0]).id, processos);
      } else {
        if (content) content.innerHTML = docRenderLista(processos);
      }
    })
    .catch(function() {
      if (content) content.innerHTML = '<p style="text-align:center;color:#ef4444;padding:40px;">Erro ao carregar processos.</p>';
    });
}

function docPainelVazio() {
  return '<div style="text-align:center;padding:60px 20px;">' +
    '<div style="font-size:4rem;margin-bottom:16px;">📁</div>' +
    '<h3 style="margin:0 0 8px;color:#1e3a5f;font-size:1.1rem;">Nenhum processo de documentação</h3>' +
    '<p style="color:#64748b;max-width:400px;margin:0 auto;line-height:1.6;">Quando você arrematar um imóvel, o processo de documentação aparecerá aqui para que você possa enviar os documentos necessários.</p>' +
    '</div>';
}

function docRenderLista(processos) {
  var cards = processos.map(function(p) {
    var prazoHtml = '';
    if (p.data_limite_docs) {
      var diff = Math.ceil((new Date(p.data_limite_docs) - new Date()) / 86400000);
      var cor = diff <= 0 ? '#ef4444' : (diff <= 3 ? '#f59e0b' : '#64748b');
      prazoHtml = '<div style="font-size:.78rem;color:' + cor + ';font-weight:' + (diff <= 3 ? '600' : '400') + ';">\u23f1 Prazo: ' + new Date(p.data_limite_docs).toLocaleDateString('pt-BR') + (diff >= 0 ? ' (' + diff + 'd)' : ' — Vencido') + '</div>';
    }
    return '<div onclick="carregarDetalheDoc(' + p.id + ')" ' +
      'style="background:#fff;border:2px solid #e2e8f0;border-radius:12px;padding:20px;cursor:pointer;transition:all .2s;" ' +
      'onmouseover="this.style.borderColor=\'#2563eb\';this.style.boxShadow=\'0 4px 12px rgba(37,99,235,.1)\'" ' +
      'onmouseout="this.style.borderColor=\'#e2e8f0\';this.style.boxShadow=\'none\'">' +
      '<div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:12px;">' +
      '<div style="font-size:2rem;">🏠</div>' +
      '<div style="flex:1;"><div style="font-weight:700;font-size:.95rem;margin-bottom:3px;">' + htmlEsc(p.titulo) + '</div>' + prazoHtml + '</div>' +
      '<div>' + badgeStatusArr(p.status) + '</div></div>' +
      '<div style="display:flex;gap:14px;flex-wrap:wrap;">' +
      '<span style="font-size:.8rem;color:#64748b;">📄 ' + p.total_docs + ' enviado(s)</span>' +
      (p.docs_aprovados ? '<span style="font-size:.8rem;color:#16a34a;">✅ ' + p.docs_aprovados + ' aprovado(s)</span>' : '') +
      (p.docs_reprovados ? '<span style="font-size:.8rem;color:#ef4444;">❌ ' + p.docs_reprovados + ' reprovado(s)</span>' : '') +
      (p.docs_pendentes ? '<span style="font-size:.8rem;color:#92400e;">⏳ ' + p.docs_pendentes + ' em análise</span>' : '') +
      '</div></div>';
  }).join('');
  return '<h2 style="margin:0 0 20px;font-size:.85rem;color:#64748b;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">Selecione o processo:</h2>' +
    '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px;">' + cards + '</div>';
}

var _docProcessosCache = [];

function carregarDetalheDoc(arrId, processosCache) {
  if (processosCache) _docProcessosCache = processosCache;
  var content = document.getElementById('docPainelContent');
  if (content) content.innerHTML = '<p style="text-align:center;color:#94a3b8;padding:60px;">⏳ Carregando...</p>';

  fetch('/api/arrematacao.php?action=minha_arrematacao&arr_id=' + arrId, { credentials: 'same-origin' })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (!data.ok) {
        mostrarToast('erro', 'Erro', data.erro || 'Erro ao carregar processo.');
        return;
      }
      if (content) {
        content.innerHTML = renderDetalheDoc(data.arr, data.docs || [], data.timeline || [], _docProcessosCache.length > 1);
      }
    })
    .catch(function() {
      mostrarToast('erro', 'Erro', 'Erro de conexão ao carregar processo.');
    });
}

function renderDetalheDoc(arr, docs, timeline, showBack) {
  // Agrupar: pega o doc mais recente de cada tipo
  var docsPorTipo = {};
  docs.forEach(function(d) {
    var k = d.tipo_key || d.tipo;
    if (!docsPorTipo[k] || d.id > docsPorTipo[k].id) docsPorTipo[k] = d;
  });

  var docTipos   = (arr.tipo_pessoa === 'juridica') ? DOC_TIPOS_PJ : DOC_TIPOS_PF;
  var obrigatorios = docTipos.filter(function(t) { return t.obrigatorio; });
  var enviados  = obrigatorios.filter(function(t) { return !!docsPorTipo[t.key]; }).length;
  var aprovados = obrigatorios.filter(function(t) { return docsPorTipo[t.key] && docsPorTipo[t.key].status === 'aprovado'; }).length;
  var pct       = Math.round((enviados  / obrigatorios.length) * 100);
  var pctAprov  = Math.round((aprovados / obrigatorios.length) * 100);
  var canUpload = !['aprovado', 'pagamento_pendente', 'concluido'].includes(arr.status);

  var html = '';

  if (showBack) {
    html += '<button onclick="carregarPainelDocumentacao()" style="display:inline-flex;align-items:center;gap:6px;background:none;border:none;color:#2563eb;cursor:pointer;font-size:.875rem;padding:0 0 20px;font-weight:500;">← Todos os processos</button>';
  }

  // Header
  var prazoTxt = '';
  if (arr.data_limite_docs) {
    var diffH = Math.ceil((new Date(arr.data_limite_docs) - new Date()) / 86400000);
    var prazoColor = diffH <= 0 ? '#fca5a5' : (diffH <= 3 ? '#fde68a' : '#bbf7d0');
    prazoTxt = '<span style="background:rgba(255,255,255,.15);padding:4px 12px;border-radius:20px;font-size:.82rem;color:' + prazoColor + ';font-weight:600;">' +
      (diffH <= 0 ? '⚠️ Prazo vencido' : '⏱ Prazo: ' + new Date(arr.data_limite_docs).toLocaleDateString('pt-BR') + ' · ' + diffH + 'd restante' + (diffH !== 1 ? 's' : '')) + '</span>';
  }
  html += '<div style="background:linear-gradient(135deg,#1e3a5f 0%,#2563eb 100%);color:#fff;border-radius:16px;padding:22px 24px;margin-bottom:20px;">' +
    '<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;">' +
    '<div><h2 style="margin:0 0 4px;font-size:1.05rem;font-weight:700;">' + htmlEsc(arr.titulo || 'Imóvel') + '</h2>' +
    '<div style="font-size:.82rem;opacity:.8;">Valor arrematado: <strong>R$ ' + parseFloat(arr.valor_final || 0).toLocaleString('pt-BR', {minimumFractionDigits:2}) + '</strong></div></div>' +
    '<div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;">' + badgeStatusArr(arr.status) + prazoTxt + '</div></div></div>';

  // Barra de progresso
  var barColor = aprovados === obrigatorios.length ? '#22c55e' : (enviados === obrigatorios.length ? '#3b82f6' : '#f59e0b');
  html += '<div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:16px 20px;margin-bottom:16px;">' +
    '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">' +
    '<span style="font-size:.875rem;font-weight:600;color:#1e3a5f;">Progresso — documentos obrigatórios</span>' +
    '<span style="font-size:.9rem;font-weight:700;color:' + barColor + ';">' + enviados + ' / ' + obrigatorios.length + ' enviados</span></div>' +
    '<div style="height:10px;background:#f1f5f9;border-radius:5px;overflow:hidden;">' +
    '<div style="height:100%;background:' + barColor + ';width:' + pct + '%;border-radius:5px;transition:width .4s;"></div></div>' +
    (aprovados > 0 ? '<div style="font-size:.78rem;color:#16a34a;margin-top:6px;">✅ ' + aprovados + ' de ' + obrigatorios.length + ' aprovado(s) pelo gestor</div>' : '') +
    '</div>';

  // Pipeline de status
  html += '<div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:14px 20px;margin-bottom:16px;overflow-x:auto;">' +
    renderPipelineStr(arr.status) + '</div>';

  // Alerta reprovado
  if (arr.status === 'reprovado') {
    html += '<div style="background:#fff5f5;border:1.5px solid #fca5a5;border-radius:10px;padding:14px 18px;margin-bottom:16px;color:#dc2626;font-size:.875rem;display:flex;align-items:center;gap:10px;">' +
      '<span style="font-size:1.3rem;">❌</span>' +
      '<div><strong>Documentação reprovada</strong><br>Revise os itens marcados como reprovados abaixo e reenvie os arquivos corrigidos.</div></div>';
  }

  // Título checklist
  var tipoPessoaLabel = arr.tipo_pessoa === 'juridica' ? 'Pessoa Jurídica 🏢' : 'Pessoa Física 👤';
  html += '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">' +
    '<h3 style="margin:0;font-size:.95rem;font-weight:700;color:#1e3a5f;">📋 Checklist — ' + tipoPessoaLabel + '</h3>' +
    '<span style="font-size:.75rem;color:#94a3b8;background:#f8fafc;padding:3px 10px;border-radius:20px;">PDF, JPG ou PNG · máx 10MB</span></div>';

  // Checklist por tipo de documento
  html += '<div style="display:flex;flex-direction:column;gap:8px;margin-bottom:24px;">';
  docTipos.forEach(function(tipo) {
    var doc = docsPorTipo[tipo.key] || null;
    var s   = doc ? doc.status : null;
    var borderColor = !doc ? '#e2e8f0' : (s === 'aprovado' ? '#86efac' : (s === 'reprovado' ? '#fca5a5' : '#fde68a'));
    var bg          = !doc ? '#fff'    : (s === 'aprovado' ? '#f0fdf4' : (s === 'reprovado' ? '#fff5f5' : '#fffbeb'));
    var icone       = !doc ? '⬜'       : (s === 'aprovado' ? '✅' : (s === 'reprovado' ? '❌' : '⏳'));

    var statusBadge = !doc
      ? '<span style="font-size:.72rem;background:#f1f5f9;color:#94a3b8;padding:2px 9px;border-radius:20px;">Não enviado</span>'
      : (s === 'aprovado'
        ? '<span style="font-size:.72rem;background:#dcfce7;color:#15803d;padding:2px 9px;border-radius:20px;font-weight:600;">✅ Aprovado</span>'
        : (s === 'reprovado'
          ? '<span style="font-size:.72rem;background:#fee2e2;color:#dc2626;padding:2px 9px;border-radius:20px;font-weight:600;">❌ Reprovado</span>'
          : '<span style="font-size:.72rem;background:#fef9c3;color:#92400e;padding:2px 9px;border-radius:20px;">⏳ Em análise</span>'));

    var acoes = '';
    if (doc && doc.url) {
      acoes += '<a href="' + htmlEsc(doc.url) + '" target="_blank" rel="noopener" ' +
        'style="padding:6px 12px;background:#f8fafc;color:#475569;border:1px solid #e2e8f0;border-radius:7px;font-size:.8rem;text-decoration:none;white-space:nowrap;">📥 Ver</a> ';
    }
    if (canUpload && s !== 'aprovado') {
      var btnLabel = !doc ? '📤 Enviar' : (s === 'reprovado' ? '📤 Reenviar' : '🔄 Substituir');
      var btnBg    = !doc ? '#2563eb'  : (s === 'reprovado' ? '#ef4444'  : '#f59e0b');
      acoes += '<label style="padding:6px 13px;background:' + btnBg + ';color:#fff;border-radius:7px;cursor:pointer;font-size:.8rem;font-weight:600;white-space:nowrap;display:inline-block;">' +
        btnLabel +
        '<input type="file" accept=".pdf,.jpg,.jpeg,.png" style="display:none;" ' +
        'onchange="docUploadPorTipo(' + arr.id + ',\'' + tipo.key + '\',this)">' +
        '</label>';
    }

    html += '<div style="background:' + bg + ';border:1.5px solid ' + borderColor + ';border-radius:10px;padding:14px 16px;">' +
      '<div style="display:flex;align-items:flex-start;gap:10px;">' +
      '<span style="font-size:1.3rem;margin-top:1px;flex-shrink:0;">' + icone + '</span>' +
      '<div style="flex:1;min-width:0;">' +
      '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:3px;">' +
      '<span style="font-weight:700;font-size:.9rem;">' + htmlEsc(tipo.label) + '</span>' +
      '<span style="font-size:.7rem;padding:2px 8px;border-radius:20px;font-weight:600;' + (tipo.obrigatorio ? 'background:#fee2e2;color:#dc2626;' : 'background:#dcfce7;color:#15803d;') + '">' + (tipo.obrigatorio ? 'Obrigatório' : 'Opcional') + '</span>' +
      statusBadge + '</div>' +
      '<div style="font-size:.78rem;color:#64748b;margin-bottom:' + (doc ? '6' : '0') + 'px;">' + htmlEsc(tipo.desc) + '</div>' +
      (doc && doc.nome ? '<div style="font-size:.74rem;color:#94a3b8;">📎 ' + htmlEsc(doc.nome) + (doc.uploaded_em ? ' · ' + new Date(doc.uploaded_em).toLocaleDateString('pt-BR') : '') + '</div>' : '') +
      (doc && s === 'reprovado' && doc.observacao ? '<div style="font-size:.8rem;background:#fff3cd;border:1px solid #fde68a;color:#92400e;padding:7px 10px;border-radius:7px;margin-top:8px;">💬 <strong>Motivo:</strong> ' + htmlEsc(doc.observacao) + '</div>' : '') +
      '</div>' +
      '<div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;flex-shrink:0;">' + acoes + '</div></div></div>';
  });
  html += '</div>';

  // Indicador de upload
  html += '<div id="docUploadProgress" style="display:none;background:#dbeafe;border:1px solid #93c5fd;border-radius:8px;padding:10px 16px;margin-bottom:16px;font-size:.875rem;color:#1e40af;align-items:center;gap:8px;">⏳ Enviando documento, aguarde...</div>';

  // Histórico
  if (timeline && timeline.length) {
    html += '<div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px 24px;">' +
      '<h3 style="margin:0 0 16px;font-size:.95rem;font-weight:700;color:#1e3a5f;">📅 Histórico do Processo</h3>' +
      renderTimelineStr(timeline) + '</div>';
  }
  return html;
}

function docUploadPorTipo(arrId, tipoKey, inputEl) {
  var arquivo = inputEl.files[0];
  if (!arquivo) return;
  if (arquivo.size > 10 * 1024 * 1024) {
    mostrarToast('aviso', 'Arquivo muito grande', 'O tamanho máximo permitido é 10MB.');
    return;
  }
  var allowed = ['application/pdf', 'image/jpeg', 'image/png'];
  if (!allowed.includes(arquivo.type)) {
    mostrarToast('aviso', 'Tipo não permitido', 'Use PDF, JPG ou PNG.');
    return;
  }
  var progressEl = document.getElementById('docUploadProgress');
  if (progressEl) progressEl.style.display = 'flex';
  document.querySelectorAll('#docPainelContent input[type=file]').forEach(function(i) { i.disabled = true; });

  var fd = new FormData();
  fd.append('action',   'upload_doc');
  fd.append('arr_id',   arrId);
  fd.append('tipo_doc', tipoKey);
  fd.append('arquivo',  arquivo);

  fetch('/api/arrematacao.php', { method: 'POST', credentials: 'same-origin', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (progressEl) progressEl.style.display = 'none';
      if (data.ok) {
        mostrarToast('sucesso', 'Documento enviado!', 'Seu documento foi recebido e está em análise.');
        carregarDetalheDoc(arrId);
      } else {
        mostrarToast('erro', 'Erro ao enviar', data.erro || 'Tente novamente.');
        document.querySelectorAll('#docPainelContent input[type=file]').forEach(function(i) { i.disabled = false; });
      }
    })
    .catch(function() {
      if (progressEl) progressEl.style.display = 'none';
      mostrarToast('erro', 'Erro de conexão', 'Verifique sua conexão e tente novamente.');
      document.querySelectorAll('#docPainelContent input[type=file]').forEach(function(i) { i.disabled = false; });
    });
}

/* ============================================================
   ARREMATANTE — GERENCIAR DOCUMENTOS (legado — redirecionado)
   ============================================================ */
var _mdocImovelId = null;

function abrirGerenciarDocs(imovelId) {
  // Navegar para o painel dedicado de Documentação
  var navBtn = document.querySelector('[data-painel=documentacao]');
  trocarPainel('documentacao', navBtn);
  carregarPainelDocumentacao(imovelId);
}

function _legadoModalDocs(imovelId) {
  _mdocImovelId = imovelId;
  var overlay = document.getElementById('modalDocOverlay');
  if (!overlay) return;
  overlay.style.display = 'block';
  document.getElementById('mdocTitulo').textContent = 'Carregando...';
  document.getElementById('mdocDocsList').innerHTML = '<p style="color:#94a3b8;">Carregando...</p>';
  document.getElementById('mdocUpResult').textContent = '';

  fetch('/api/arrematacao.php?action=minha_arrematacao&imovel_id=' + imovelId, { credentials: 'same-origin' })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (!data.ok) { mostrarToast(data.erro || 'Acesso negado.', 'erro'); fecharModalDocs(); return; }
      var a = data.arrematacao;
      document.getElementById('mdocTitulo').textContent = a.titulo;

      // Pipeline
      var pipeEl = document.getElementById('mdocPipeline');
      if (pipeEl) pipeEl.innerHTML = renderPipelineStr(a.status);

      // Status label
      var statusMap = { aguardando_documentos: 'Envio de Documentos', documentos_enviados: 'Docs Enviados', em_analise: 'Em Análise', aprovado: 'Aprovado', reprovado: 'Reprovado', concluido: 'Concluído' };
      document.getElementById('mdocStatusLabel').textContent = 'Status: ' + (statusMap[a.status] || a.status);

      var prazo = '';
      if (a.data_limite_docs) {
        var diff = Math.ceil((new Date(a.data_limite_docs) - new Date()) / 86400000);
        prazo = '  |  Prazo: ' + new Date(a.data_limite_docs).toLocaleDateString('pt-BR') + (diff <= 0 ? ' ⚠️ Vencido' : (diff <= 3 ? ' ⚠️ ' + diff + 'd restante(s)' : ' (' + diff + 'd restante(s))'));
      }
      document.getElementById('mdocPrazo').textContent = prazo;

      // Upload: ocultar se status não permite
      var uploadArea = document.getElementById('mdocUploadArea');
      var canUpload = ['aguardando_documentos', 'documentos_enviados', 'reprovado'].includes(a.status);
      if (uploadArea) uploadArea.style.display = canUpload ? '' : 'none';

      // Docs
      document.getElementById('mdocDocsList').innerHTML = mdocRenderDocsList(data.documentos || []);

      // Timeline
      var tlEl = document.getElementById('mdocTimeline');
      if (tlEl) tlEl.innerHTML = renderTimelineStr(data.timeline || []);

      // Sync arrId on hidden input if we later need it
      var arrIdInp = document.getElementById('admArrDetalheId');
      if (!arrIdInp) {
        var inp = document.createElement('input');
        inp.type = 'hidden'; inp.id = 'mdocArrId'; inp.value = a.id;
        overlay.appendChild(inp);
      }
    })
    .catch(function(e) { mostrarToast('Erro de conexão.', 'erro'); });
}

function fecharModalDocs() {
  var overlay = document.getElementById('modalDocOverlay');
  if (overlay) overlay.style.display = 'none';
}

function mdocTrocarTab(tab, btn) {
  document.querySelectorAll('.mdoc-tab').forEach(function(b) {
    b.style.borderBottomColor = 'transparent';
    b.style.color = '#64748b';
    b.style.fontWeight = '500';
  });
  document.querySelectorAll('.mdoc-tab-content').forEach(function(c) {
    c.style.display = 'none';
  });
  if (btn) {
    btn.style.borderBottomColor = '#2563eb';
    btn.style.color = '#2563eb';
    btn.style.fontWeight = '600';
  }
  var content = document.getElementById('mdoc-tab-' + tab);
  if (content) content.style.display = '';
}

function mdocEnviarDoc() {
  var tipo    = document.getElementById('mdocUpTipo').value;
  var arquivo = document.getElementById('mdocUpArquivo').files[0];
  var resEl   = document.getElementById('mdocUpResult');
  if (!arquivo) { resEl.textContent = '⚠️ Selecione um arquivo.'; return; }
  if (arquivo.size > 10 * 1024 * 1024) { resEl.textContent = '⚠️ Arquivo muito grande (máx 10MB).'; return; }
  var allowed = ['application/pdf', 'image/jpeg', 'image/png', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
  if (!allowed.includes(arquivo.type)) { resEl.textContent = '⚠️ Tipo de arquivo não permitido.'; return; }
  resEl.textContent = '⏳ Enviando...';
  var fd = new FormData();
  fd.append('action', 'upload_doc');
  fd.append('imovel_id', _mdocImovelId);
  fd.append('tipo_doc', tipo);
  fd.append('arquivo', arquivo);
  fetch('/api/arrematacao.php', { method: 'POST', credentials: 'same-origin', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      resEl.textContent = data.ok ? '✅ Documento enviado com sucesso!' : ('❌ ' + (data.erro || 'Erro ao enviar'));
      if (data.ok) {
        document.getElementById('mdocUpArquivo').value = '';
        abrirGerenciarDocs(_mdocImovelId);
      }
    })
    .catch(function() { resEl.textContent = '❌ Erro de conexão.'; });
}

function mdocRenderDocsList(docs) {
  if (!docs.length) return '<p style="color:#94a3b8;text-align:center;padding:24px;">Nenhum documento enviado. Use o formulário acima para enviar.</p>';
  var tiposLabel = { identidade: 'RG / CNH', cpf: 'CPF', comprovante_renda: 'Comprovante de Renda', comprovante_end: 'Comprovante de Endereço', contrato_social: 'Contrato Social', outros: 'Outros' };
  return '<h4 style="margin:0 0 12px;font-size:.95rem;">Documentos Enviados</h4>' +
    docs.map(function(d) {
      var statusBadge = d.status === 'aprovado'
        ? '<span style="background:#dcfce7;color:#16a34a;padding:3px 10px;border-radius:20px;font-size:.75rem;">✅ Aprovado</span>'
        : d.status === 'reprovado'
        ? '<span style="background:#fee2e2;color:#dc2626;padding:3px 10px;border-radius:20px;font-size:.75rem;">❌ Reprovado</span>'
        : '<span style="background:#fef9c3;color:#92400e;padding:3px 10px;border-radius:20px;font-size:.75rem;">⏳ Em análise</span>';
      return '<div style="display:flex;align-items:center;gap:12px;padding:12px 14px;background:#f8fafc;border-radius:8px;margin-bottom:8px;flex-wrap:wrap;border:1px solid #e2e8f0;">' +
        '<div style="font-size:1.5rem;">' + (d.status === 'aprovado' ? '✅' : d.status === 'reprovado' ? '❌' : '📄') + '</div>' +
        '<div style="flex:1;min-width:120px;"><div style="font-weight:600;font-size:.875rem;">' + htmlEsc(tiposLabel[d.tipo_doc] || d.tipo_doc) + '</div>' +
        (d.nome_arquivo ? '<div style="font-size:.78rem;color:#64748b;">' + htmlEsc(d.nome_arquivo) + '</div>' : '') +
        '<div style="font-size:.75rem;color:#94a3b8;">' + new Date(d.criado_em).toLocaleDateString('pt-BR') + '</div></div>' +
        '<div>' + statusBadge + '</div>' +
        (d.url_arquivo ? '<a href="' + htmlEsc(d.url_arquivo) + '" target="_blank" style="padding:5px 12px;background:#e0e7ff;color:#1e40af;border-radius:6px;font-size:.8rem;text-decoration:none;">📥 Ver arquivo</a>' : '') +
        (d.obs_revisao ? '<div style="width:100%;font-size:.82rem;background:#fff3cd;color:#92400e;padding:8px 10px;border-radius:6px;margin-top:6px;">💬 Observação: ' + htmlEsc(d.obs_revisao) + '</div>' : '') +
        '</div>';
    }).join('');
}

/* ============================================================
   HELPERS COMPARTILHADOS
   ============================================================ */
function htmlEsc(str) {
  return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

function badgeStatusArr(status) {
  var map = {
    aguardando_confirmacao: { bg: '#f1f5f9', c: '#475569',  label: '⏳ Aguard. Confirmação' },
    aguardando_documentos:  { bg: '#fff3cd', c: '#92400e',  label: '📤 Aguard. Documentos' },
    documentos_enviados:    { bg: '#dbeafe', c: '#1e40af',  label: '📋 Docs Enviados'       },
    em_analise:             { bg: '#d1ecf1', c: '#0c5460',  label: '🔍 Em Análise'          },
    aprovado:               { bg: '#dcfce7', c: '#16a34a',  label: '✅ Docs Aprovados'      },
    reprovado:              { bg: '#fee2e2', c: '#dc2626',  label: '❌ Docs Reprovados'     },
    pagamento_pendente:     { bg: '#fef3c7', c: '#b45309',  label: '💳 Pagamento Pendente'  },
    concluido:              { bg: '#e9d5ff', c: '#6d28d9',  label: '🏆 Concluído'           },
  };
  var s = map[status] || { bg: '#f1f5f9', c: '#64748b', label: status };
  return '<span style="background:' + s.bg + ';color:' + s.c + ';padding:3px 10px;border-radius:20px;font-size:.78rem;font-weight:600;">' + s.label + '</span>';
}

function renderPipelineStr(statusAtual) {
  var etapas = [
    { key: 'aguardando_documentos', label: 'Aguard. Docs'       },
    { key: 'documentos_enviados',   label: 'Docs Enviados'      },
    { key: 'em_analise',            label: 'Em Análise'         },
    { key: 'aprovado',              label: 'Docs Aprovados'     },
    { key: 'pagamento_pendente',    label: 'Pag. Pendente'      },
    { key: 'concluido',             label: 'Concluído'          },
  ];
  if (statusAtual === 'reprovado') {
    return '<span style="background:#fee2e2;color:#dc2626;padding:4px 12px;border-radius:20px;font-size:.82rem;font-weight:600;">❌ Documentação Reprovada — Reenvie os documentos corrigidos</span>';
  }
  if (statusAtual === 'aguardando_confirmacao') {
    return '<span style="background:#f1f5f9;color:#475569;padding:4px 12px;border-radius:20px;font-size:.82rem;font-weight:600;">⏳ Aguardando confirmação do processo pela equipe</span>';
  }
  var curIdx = etapas.findIndex(function(e) { return e.key === statusAtual; });
  return etapas.map(function(e, i) {
    var isActive = i === curIdx;
    var isPast   = i < curIdx;
    var colBg    = isPast ? '#16a34a' : (isActive ? '#2563eb' : '#e2e8f0');
    var colC     = (isPast || isActive) ? '#fff' : '#94a3b8';
    var arrow    = i < etapas.length - 1 ? '<span style="color:#cbd5e1;font-size:.9rem;margin:0 2px;">›</span>' : '';
    return '<span style="background:' + colBg + ';color:' + colC + ';padding:4px 10px;border-radius:20px;font-size:.75rem;font-weight:' + (isActive ? '700' : '500') + ';margin-right:2px;white-space:nowrap;">' +
           (isPast ? '✓ ' : '') + e.label + '</span>' + arrow;
  }).join('');
}

function renderTimelineStr(entries) {
  if (!entries || !entries.length) return '<p style="color:#94a3b8;text-align:center;padding:24px;">Nenhum evento registrado.</p>';
  return '<div style="position:relative;padding-left:24px;">' +
    entries.map(function(e, i) {
      var isFirst = i === 0;
      return '<div style="position:relative;margin-bottom:16px;">' +
        '<div style="position:absolute;left:-24px;top:4px;width:12px;height:12px;border-radius:50%;background:' + (isFirst ? '#2563eb' : '#e2e8f0') + ';border:2px solid ' + (isFirst ? '#2563eb' : '#cbd5e1') + ';"></div>' +
        (i < entries.length - 1 ? '<div style="position:absolute;left:-18px;top:16px;width:2px;height:calc(100% + 16px);background:#e2e8f0;"></div>' : '') +
        '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px 14px;">' +
        '<div style="font-weight:600;font-size:.875rem;">' + htmlEsc(e.descricao || e.acao) + '</div>' +
        (e.usuario_nome ? '<div style="font-size:.78rem;color:#64748b;">Por: ' + htmlEsc(e.usuario_nome) + '</div>' : '') +
        '<div style="font-size:.75rem;color:#94a3b8;">' + new Date(e.criado_em).toLocaleString('pt-BR') + '</div>' +
        (e.dados ? '<div style="font-size:.78rem;color:#475569;margin-top:4px;">' + htmlEsc(e.dados) + '</div>' : '') +
        '</div></div>';
    }).join('') + '</div>';
}

