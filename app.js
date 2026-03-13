/* ============================================
   QATAR LEILÕES - APP.JS
   Lógica: Chat IA, Cards, Filtros, Auth
   ============================================ */

// ─── DADOS (carregados via API do plugin leilao-caixa) ────
let IMOVEIS_DATA = [];     // dados filtrados/exibidos
let IMOVEIS_TODOS = [];    // todos os dados carregados
let VEICULOS_DATA = [];
let VEICULOS_TODOS = [];
let imoveisCarregados = false;
let veiculosCarregados = false;
let totalImoveisAPI = 0;
let totalVeiculosAPI = 0;

function carregarImoveis() {
  renderGrids();
  fetch('/wp-json/leilao/v1/imoveis?per_page=50&status=ativo')
    .then(function(res) { return res.json(); })
    .then(function(data) {
      imoveisCarregados = true;
      if (data && data.items) {
        IMOVEIS_TODOS = data.items.map(mapearImovelAPI);
        IMOVEIS_DATA = IMOVEIS_TODOS.slice();
        totalImoveisAPI = data.total || IMOVEIS_DATA.length;
      }
      renderGrids();
      carregarImagensFaltantes();
    })
    .catch(function(err) {
      console.error('[API] Erro ao carregar imóveis:', err);
      imoveisCarregados = true;
      renderGrids();
    });

  // Carregar veículos
  carregarVeiculos();

  // Carregar opções de filtro da API
  carregarFiltros();
}

function carregarVeiculos() {
  fetch('/wp-json/leilao/v1/veiculos?per_page=50')
    .then(function(res) { return res.json(); })
    .then(function(data) {
      veiculosCarregados = true;
      if (data && data.items) {
        VEICULOS_TODOS = data.items.map(mapearVeiculoAPI);
        VEICULOS_DATA = VEICULOS_TODOS.slice();
        totalVeiculosAPI = data.total || VEICULOS_DATA.length;
      }
      atualizarContagens(totalImoveisAPI, totalVeiculosAPI);
      renderGrids();
    })
    .catch(function(err) {
      console.error('[API] Erro ao carregar veículos:', err);
      veiculosCarregados = true;
      renderGrids();
    });
}

function mapearVeiculoAPI(item) {
  var avaliacao = parseFloat(item.valor_avaliacao) || 0;
  var minimo = parseFloat(item.valor_minimo) || 0;
  var desconto = avaliacao > 0 ? Math.round((1 - minimo / avaliacao) * 100) : 0;
  var dataFim = item.fim ? new Date(item.fim) : null;
  var dataFormatada = dataFim ? dataFim.toLocaleDateString('pt-BR') : '—';
  var imagem = item.thumb || '';
  if (!imagem) imagem = 'https://images.unsplash.com/photo-1549317661-bd32c8ce0afa?w=400&h=250&fit=crop';

  return {
    id: item.id,
    tipo: item.tipo || 'Carro',
    titulo: item.titulo || '',
    marca: item.marca || '',
    modelo: item.modelo || '',
    ano: item.ano || '—',
    cor: item.cor || '',
    km: item.km || 'N/I',
    combustivel: item.combustivel || '',
    cambio: item.cambio || '',
    local: [item.cidade, item.estado].filter(Boolean).join('/') || '',
    estado: item.estado || '',
    cidade: item.cidade || '',
    preco: minimo,
    precoFipe: avaliacao,
    desconto: desconto > 0 ? desconto : 0,
    imagem: imagem,
    dataLeilao: dataFormatada,
    badge: 'leilao'
  };
}

function carregarFiltros() {
  fetch('/wp-json/leilao/v1/filtros')
    .then(function(res) { return res.json(); })
    .then(function(data) {
      var sel = document.getElementById('filtroEstado');
      if (sel && data.estados) {
        sel.innerHTML = '<option value="">Estado</option>';
        data.estados.forEach(function(e) {
          var opt = document.createElement('option');
          opt.value = e.name;
          opt.textContent = e.name + ' (' + e.count + ')';
          sel.appendChild(opt);
        });
      }
      var selTipo = document.getElementById('filtroTipo');
      if (selTipo && data.tipos) {
        selTipo.innerHTML = '<option value="">Tipo</option>';
        data.tipos.forEach(function(t) {
          var opt = document.createElement('option');
          opt.value = t.name;
          opt.textContent = t.name + ' (' + t.count + ')';
          selTipo.appendChild(opt);
        });
      }
    })
    .catch(function() {});
}

function aplicarFiltros() {
  var estado = document.getElementById('filtroEstado').value;
  var ordem = document.getElementById('filtroOrdem').value;
  var precoMin = parseFloat((document.getElementById('filtroPrecoMin').value || '').replace(/[^\d]/g, '')) || 0;
  var precoMax = parseFloat((document.getElementById('filtroPrecoMax').value || '').replace(/[^\d]/g, '')) || Infinity;

  var resultado = IMOVEIS_TODOS.filter(function(item) {
    if (estado && item.estadoSigla !== estado) return false;
    if (precoMin > 0 && item.preco < precoMin) return false;
    if (precoMax < Infinity && item.preco > precoMax) return false;
    return true;
  });

  // Ordenação
  if (ordem === 'menor_preco') resultado.sort(function(a, b) { return a.preco - b.preco; });
  else if (ordem === 'maior_preco') resultado.sort(function(a, b) { return b.preco - a.preco; });
  else if (ordem === 'maior_desconto') resultado.sort(function(a, b) { return b.desconto - a.desconto; });
  else if (ordem === 'recentes') resultado.sort(function(a, b) { return b.id - a.id; });

  IMOVEIS_DATA = resultado;
  renderGrids();
}

// ─── UPGRADE DE IMAGENS VIA PIXABAY (opcional) ──
function carregarImagensFaltantes() {
  // Tenta buscar imagens mais específicas via Pixabay API (requer chave configurada)
  // Se a API retornar erro, os fallbacks Unsplash já estão no lugar
  var porTipo = {};
  IMOVEIS_DATA.forEach(function(item, idx) {
    if (item.imagem && item.imagem.indexOf('unsplash.com') > -1) {
      var tipo = item.tipo || 'Imóvel';
      if (!porTipo[tipo]) porTipo[tipo] = [];
      porTipo[tipo].push(idx);
    }
  });

  var tipos = Object.keys(porTipo);
  if (!tipos.length) return;

  tipos.forEach(function(tipo) {
    var indices = porTipo[tipo];
    fetch('/api/imagens.php?tipo=' + encodeURIComponent(tipo) + '&n=' + indices.length)
      .then(function(res) {
        if (!res.ok) return null;
        return res.json();
      })
      .then(function(data) {
        if (data && data.imagens && data.imagens.length) {
          indices.forEach(function(idx, i) {
            var img = data.imagens[i % data.imagens.length];
            IMOVEIS_DATA[idx].imagem = img.url_sm || img.url;
          });
          renderGrids();
        }
      })
      .catch(function() { /* Silencioso — fallback Unsplash já está ativo */ });
  });
}

function mapearImovelAPI(item) {
  var avaliacao = parseFloat(item.valor_avaliacao) || 0;
  var minimo = parseFloat(item.valor_minimo) || 0;
  var desconto = avaliacao > 0 ? Math.round((1 - minimo / avaliacao) * 100) : 0;
  var dataFim = item.fim ? new Date(item.fim) : null;
  var dataFormatada = dataFim ? dataFim.toLocaleDateString('pt-BR') : '—';
  var imagem = item.thumb || '';
  if (!imagem && item.galeria && item.galeria.length) imagem = item.galeria[0].url;
  if (!imagem) imagem = imagemFallback(item.tipo, item.id);

  return {
    id: item.id,
    tipo: item.tipo || 'Imóvel',
    titulo: item.titulo || '',
    endereco: [item.cidade, item.estado].filter(Boolean).join(', ') || '',
    estadoSigla: item.estado || '',
    cidade: item.cidade || '',
    area: item.area_total ? item.area_total + 'm²' : (item.area_privativa ? item.area_privativa + 'm²' : ''),
    quartos: parseInt(item.quartos) || 0,
    vagas: parseInt(item.garagem) || 0,
    preco: minimo,
    precoAvaliacao: avaliacao,
    desconto: desconto > 0 ? desconto : 0,
    imagem: imagem,
    dataLeilao: dataFormatada,
    badge: 'leilao',
    url: item.url || '#'
  };
}

// ─── IMAGENS FALLBACK (Unsplash — sem chave) ─
var IMAGENS_FALLBACK = {
  'apartamento': [
    'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?w=400&h=250&fit=crop',
    'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?w=400&h=250&fit=crop',
    'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?w=400&h=250&fit=crop',
    'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?w=400&h=250&fit=crop',
    'https://images.unsplash.com/photo-1493809842364-78817add7ffb?w=400&h=250&fit=crop'
  ],
  'casa': [
    'https://images.unsplash.com/photo-1568605114967-8130f3a36994?w=400&h=250&fit=crop',
    'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?w=400&h=250&fit=crop',
    'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=400&h=250&fit=crop',
    'https://images.unsplash.com/photo-1583608205776-bfd35f0d9f83?w=400&h=250&fit=crop',
    'https://images.unsplash.com/photo-1572120360610-d971b9d7767c?w=400&h=250&fit=crop'
  ],
  'terreno': [
    'https://images.unsplash.com/photo-1500382017468-9049fed747ef?w=400&h=250&fit=crop',
    'https://images.unsplash.com/photo-1628624747186-a941c476b7ef?w=400&h=250&fit=crop',
    'https://images.unsplash.com/photo-1500076656116-558758c991c1?w=400&h=250&fit=crop'
  ],
  'sala': [
    'https://images.unsplash.com/photo-1497366216548-37526070297c?w=400&h=250&fit=crop',
    'https://images.unsplash.com/photo-1524758631624-e2822e304c36?w=400&h=250&fit=crop'
  ],
  'galpao': [
    'https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?w=400&h=250&fit=crop',
    'https://images.unsplash.com/photo-1553246969-7dcb4b551a63?w=400&h=250&fit=crop'
  ],
  'sitio': [
    'https://images.unsplash.com/photo-1500076656116-558758c991c1?w=400&h=250&fit=crop',
    'https://images.unsplash.com/photo-1516253593875-bd7ba052b0ae?w=400&h=250&fit=crop'
  ],
  'default': [
    'https://images.unsplash.com/photo-1560518883-ce09059eeffa?w=400&h=250&fit=crop',
    'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?w=400&h=250&fit=crop',
    'https://images.unsplash.com/photo-1582407947304-fd86f028f716?w=400&h=250&fit=crop'
  ]
};

function imagemFallback(tipo, id) {
  var tipoNorm = (tipo || '').toLowerCase()
    .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z]/g, '');
  var fotos = IMAGENS_FALLBACK[tipoNorm] || IMAGENS_FALLBACK['default'];
  return fotos[(id || 0) % fotos.length];
}

function atualizarContagens(totalImoveis, totalVeiculos) {
  var el1 = document.getElementById('statImoveis');
  var el2 = document.getElementById('statVeiculos');
  if (el1) el1.textContent = totalImoveis.toLocaleString('pt-BR');
  if (el2) el2.textContent = totalVeiculos.toLocaleString('pt-BR');
}

// ─── RENDERIZAÇÃO DOS CARDS ─────────────────
function renderCardImovel(item) {
  const badgeClass = `imovel-card__badge--${item.badge}`;
  const badgeTexto = { leilao: '🔨 Leilão', novo: '✨ Novo', destaque: '⭐ Destaque' };
  return `
    <article class="imovel-card" data-id="${item.id}" onclick="window.location.href='imovel.html?id=${item.id}'" style="cursor:pointer">
      <div class="imovel-card__imagem">
        <img src="${item.imagem}" alt="${item.titulo}" loading="lazy" onerror="this.onerror=null;this.src=imagemFallback('${item.tipo}',${item.id})">
        <span class="imovel-card__badge ${badgeClass}">${badgeTexto[item.badge] || 'Leilão'}</span>
        <button class="imovel-card__favorito" onclick="event.stopPropagation();this.classList.toggle('ativo')">♥</button>
      </div>
      <div class="imovel-card__corpo">
        <div class="imovel-card__tipo">${item.tipo}</div>
        <h3 class="imovel-card__titulo">${item.titulo}</h3>
        <p class="imovel-card__endereco">📍 ${item.endereco}</p>
        <div class="imovel-card__detalhes">
          ${item.area ? `<span class="imovel-card__detalhe">📐 ${item.area}</span>` : ''}
          ${item.quartos ? `<span class="imovel-card__detalhe">🛏️ ${item.quartos} qts</span>` : ''}
          ${item.vagas ? `<span class="imovel-card__detalhe">🚗 ${item.vagas} vaga${item.vagas > 1 ? 's' : ''}</span>` : ''}
        </div>
        <div class="imovel-card__footer">
          <div>
            <span class="imovel-card__preco-label">Lance mínimo</span>
            <span class="imovel-card__preco">R$ ${item.preco.toLocaleString('pt-BR')}</span>
            ${item.desconto > 0 ? `<span class="imovel-card__preco-desconto">↓ -${item.desconto}% abaixo</span>` : ''}
          </div>
          <div class="imovel-card__leilao-info">
            <div class="imovel-card__leilao-data">📅 ${item.dataLeilao}</div>
          </div>
        </div>
      </div>
    </article>`;
}

function renderCardVeiculo(item) {
  const badgeClass = `veiculo-card__badge--${item.badge}`;
  const badgeTexto = { leilao: '🔨 Leilão', novo: '✨ Novo', destaque: '⭐ Destaque' };
  return `
    <article class="veiculo-card" data-id="${item.id}" onclick="window.location.href='veiculo.html?id=${item.id}'" style="cursor:pointer">
      <div class="veiculo-card__imagem">
        <img src="${item.imagem}" alt="${item.titulo}" loading="lazy">
        <span class="veiculo-card__badge ${badgeClass}">${badgeTexto[item.badge] || 'Leilão'}</span>
        <button class="veiculo-card__favorito" onclick="event.stopPropagation();this.classList.toggle('ativo')">♥</button>
      </div>
      <div class="veiculo-card__corpo">
        <div class="veiculo-card__tipo">${item.tipo}</div>
        <h3 class="veiculo-card__titulo">${item.titulo}</h3>
        <div class="veiculo-card__specs">
          <div class="veiculo-card__spec">
            <div class="veiculo-card__spec-icone">📅</div>
            <span class="veiculo-card__spec-valor">${item.ano}</span>
            <span class="veiculo-card__spec-label">Ano</span>
          </div>
          <div class="veiculo-card__spec">
            <div class="veiculo-card__spec-icone">🛣️</div>
            <span class="veiculo-card__spec-valor">${item.km}</span>
            <span class="veiculo-card__spec-label">KM</span>
          </div>
          <div class="veiculo-card__spec">
            <div class="veiculo-card__spec-icone">⚙️</div>
            <span class="veiculo-card__spec-valor">${item.cambio || '—'}</span>
            <span class="veiculo-card__spec-label">Câmbio</span>
          </div>
          <div class="veiculo-card__spec">
            <div class="veiculo-card__spec-icone">⛽</div>
            <span class="veiculo-card__spec-valor">${item.combustivel || '—'}</span>
            <span class="veiculo-card__spec-label">Comb.</span>
          </div>
        </div>
        <p class="veiculo-card__local">📍 ${item.local}</p>
        <div class="veiculo-card__footer">
          <div>
            <span class="veiculo-card__preco-label">Lance mínimo</span>
            <span class="veiculo-card__preco">R$ ${item.preco.toLocaleString('pt-BR')}</span>
            ${item.precoFipe > 0 ? `<div class="veiculo-card__preco-fipe">Avaliação: <span>R$ ${item.precoFipe.toLocaleString('pt-BR')}</span></div>` : ''}
            ${item.desconto > 0 ? `<span class="veiculo-card__desconto">↓ ${item.desconto}% abaixo</span>` : ''}
          </div>
          <div class="veiculo-card__leilao-info">
            <div class="veiculo-card__leilao-data">📅 ${item.dataLeilao}</div>
          </div>
        </div>
      </div>
    </article>`;
}

function renderGrids() {
  var gridImoveis = document.getElementById('gridImoveis');
  var gridVeiculos = document.getElementById('gridVeiculos');
  var MAX_HOME = 10;

  if (IMOVEIS_DATA.length) {
    gridImoveis.innerHTML = IMOVEIS_DATA.slice(0, MAX_HOME).map(renderCardImovel).join('');
  } else {
    gridImoveis.innerHTML = imoveisCarregados
      ? '<div style="text-align:center;padding:40px;color:var(--cor-texto-muted)">Nenhum imóvel disponível no momento</div>'
      : '<div style="text-align:center;padding:40px;color:var(--cor-texto-muted)">Carregando imóveis...</div>';
  }

  gridVeiculos.innerHTML = VEICULOS_DATA.length
    ? VEICULOS_DATA.slice(0, MAX_HOME).map(renderCardVeiculo).join('')
    : (veiculosCarregados
      ? '<div style="text-align:center;padding:40px;color:var(--cor-texto-muted)">Nenhum veículo disponível no momento</div>'
      : '<div style="text-align:center;padding:40px;color:var(--cor-texto-muted)">Carregando veículos...</div>');

  document.getElementById('countImoveis').textContent = IMOVEIS_DATA.length;
  document.getElementById('countVeiculos').textContent = VEICULOS_DATA.length;
  document.getElementById('countTodos').textContent = IMOVEIS_DATA.length + VEICULOS_DATA.length;
}

// ─── TOGGLE CATEGORIA (mostrar/esconder colunas) ──
function toggleCategoria(cat, btn) {
  // Atualizar botões
  document.querySelectorAll('.categoria-toggle__btn').forEach(b => {
    b.className = 'categoria-toggle__btn';
  });
  if (cat === 'todos') btn.classList.add('ativo--todos');
  else if (cat === 'imoveis') btn.classList.add('ativo--imovel');
  else if (cat === 'veiculos') btn.classList.add('ativo--veiculo');

  const colImoveis = document.getElementById('colunaImoveis');
  const colVeiculos = document.getElementById('colunaVeiculos');
  const dual = document.getElementById('dualColunas');

  if (cat === 'todos') {
    colImoveis.style.display = '';
    colVeiculos.style.display = '';
    dual.style.gridTemplateColumns = '1fr';
  } else if (cat === 'imoveis') {
    colImoveis.style.display = '';
    colVeiculos.style.display = 'none';
    dual.style.gridTemplateColumns = '1fr';
  } else {
    colImoveis.style.display = 'none';
    colVeiculos.style.display = '';
    dual.style.gridTemplateColumns = '1fr';
  }
}

function filtrarTipo(tipo, btn) {
  document.querySelectorAll('.filtros__tipo-toggle-btn').forEach(b => {
    b.className = 'filtros__tipo-toggle-btn';
  });
  btn.classList.add('ativo');
  if (tipo === 'imoveis') btn.classList.add('ativo--imovel');
  if (tipo === 'veiculos') btn.classList.add('ativo--veiculo');
  // Sincronizar com as colunas
  const catBtns = document.querySelectorAll('.categoria-toggle__btn');
  catBtns.forEach(b => { if (b.dataset.cat === tipo) toggleCategoria(tipo, b); });
}

// ─── CHAT IA ─────────────────────────────────
let chatAberto = false;
let chatCategoriaEscolhida = null; // 'imovel' ou 'veiculo'
let etapaChat = 0;
let respostasUsuario = {};

// ─── IA INLINE NO HERO ──────────────────────
let heroIAAtiva = false;
let heroIACat = null;
let heroIAEtapa = 0;
let heroIARespostas = {};
let heroTermoBusca = '';

const PALAVRAS_IMOVEL = ['apartamento','casa','sobrado','terreno','imóvel','imovel','kitnet','cobertura','sala','galpão','galpao','chácara','chacara','sítio','sitio','fazenda','lote','comercial','prédio','predio','flat','studio','duplex','triplex','loft','edícula','edicula','quarto','quartos','m²','metros'];
const PALAVRAS_VEICULO = ['carro','veículo','veiculo','moto','caminhão','caminhao','pickup','suv','sedan','hatch','hb20','onix','polo','gol','civic','corolla','hilux','s10','ranger','toro','renegade','compass','tracker','creta','kicks','t-cross','nivus','argo','mobi','kwid','uno','palio','punto','siena','toyota','honda','volkswagen','fiat','chevrolet','hyundai','jeep','ford','renault','peugeot','citroen','bmw','mercedes','audi','volvo','nissan','mitsubishi','kia','ram','dodge','automático','automatico','manual','flex','km','quilometragem','motor','cilindrada','turbo'];

const FLUXO_IMOVEL = [
  { pergunta: 'Qual o valor médio que você pretende investir?', sugestoes: ['Até R$ 100 mil', 'R$ 100-200 mil', 'R$ 200-400 mil', 'Acima de R$ 400 mil'] },
  { pergunta: 'Em qual estado você busca o imóvel?', sugestoes: ['São Paulo', 'Rio de Janeiro', 'Minas Gerais', 'Paraná', 'Qualquer estado'] },
  { pergunta: 'Que tipo de imóvel prefere?', sugestoes: ['Apartamento', 'Casa', 'Terreno', 'Comercial', 'Qualquer'] },
  { pergunta: 'Quantos quartos precisa?', sugestoes: ['1 quarto', '2 quartos', '3 quartos', '4+ quartos', 'Não importa'] },
  { pergunta: 'Precisa de vaga de garagem?', sugestoes: ['Sim, 1 vaga', 'Sim, 2+ vagas', 'Não precisa'] }
];

const FLUXO_VEICULO = [
  { pergunta: 'Qual o valor máximo que pretende investir no veículo?', sugestoes: ['Até R$ 30 mil', 'R$ 30-60 mil', 'R$ 60-100 mil', 'Acima de R$ 100 mil'] },
  { pergunta: 'Que tipo de veículo prefere?', sugestoes: ['Hatch', 'Sedan', 'SUV', 'Pickup', 'Qualquer'] },
  { pergunta: 'Prefere câmbio automático ou manual?', sugestoes: ['Automático', 'Manual', 'Tanto faz'] },
  { pergunta: 'Qual ano mínimo do veículo?', sugestoes: ['2024+', '2022+', '2020+', '2018+', 'Qualquer'] },
  { pergunta: 'Qual a quilometragem máxima aceitável?', sugestoes: ['Até 20 mil km', 'Até 50 mil km', 'Até 80 mil km', 'Qualquer'] }
];

// ─── DETECÇÃO DE CATEGORIA POR PALAVRAS-CHAVE ──
function detectarCategoria(texto) {
  const lower = texto.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  let scoreImovel = 0;
  let scoreVeiculo = 0;

  PALAVRAS_IMOVEL.forEach(p => {
    const norm = p.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    if (lower.includes(norm)) scoreImovel++;
  });
  PALAVRAS_VEICULO.forEach(p => {
    const norm = p.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    if (lower.includes(norm)) scoreVeiculo++;
  });

  if (scoreImovel > 0 && scoreImovel > scoreVeiculo) return 'imovel';
  if (scoreVeiculo > 0 && scoreVeiculo > scoreImovel) return 'veiculo';
  return null; // ambíguo
}

// ─── INICIAR IA PELO HERO ───────────────────
function iniciarIAHero() {
  const input = document.getElementById('heroBuscaInput');
  const termo = input.value.trim();
  if (!termo) {
    // Se nada digitado, mostrar escolha de categoria
    mostrarEscolhaHero();
    return;
  }

  heroTermoBusca = termo;
  const cat = detectarCategoria(termo);

  if (cat) {
    // Detectou automaticamente
    ativarIAHero(cat, termo);
  } else {
    // Ambíguo — pedir para o usuário escolher
    mostrarEscolhaHero(termo);
  }
}

function mostrarEscolhaHero(termo) {
  heroTermoBusca = termo || '';
  document.querySelector('.hero__busca').style.display = 'none';
  document.getElementById('heroIAEscolha').style.display = 'block';
  document.getElementById('heroIA').style.display = 'none';
  document.getElementById('heroStats').style.display = 'none';

  // Scroll suave para a escolha
  document.getElementById('heroIAEscolha').scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function heroEscolherCategoria(cat) {
  document.getElementById('heroIAEscolha').style.display = 'none';
  ativarIAHero(cat, heroTermoBusca);
}

function ativarIAHero(cat, termo) {
  heroIAAtiva = true;
  heroIACat = cat;
  heroIAEtapa = 0;
  heroIARespostas = { categoria: cat, busca_original: termo };

  // Esconder busca e stats, mostrar painel IA
  document.querySelector('.hero__busca').style.display = 'none';
  document.getElementById('heroStats').style.display = 'none';
  document.getElementById('heroIAEscolha').style.display = 'none';
  const painel = document.getElementById('heroIA');
  painel.style.display = 'flex';
  document.getElementById('heroIACorpo').innerHTML = '';

  // Mensagem de boas-vindas
  const nome = cat === 'imovel' ? 'imóvel' : 'veículo';
  const emoji = cat === 'imovel' ? '🏠' : '🚗';

  let msgInicial;
  if (termo) {
    msgInicial = `Entendi! Você busca ${emoji} <strong>${nome}</strong>: "<em>${termo}</em>". Vou fazer algumas perguntas rápidas para refinar ainda mais a busca! 🎯`;
  } else {
    msgInicial = `Ótimo! Você escolheu buscar ${emoji} <strong>${nome}</strong>. Vou te fazer algumas perguntas para encontrar as melhores oportunidades!`;
  }

  heroAddMsgIA(msgInicial);

  // Primeira pergunta
  setTimeout(() => {
    const fluxo = cat === 'imovel' ? FLUXO_IMOVEL : FLUXO_VEICULO;
    heroAddMsgIA(fluxo[0].pergunta);
    heroMostrarSugestoes(fluxo[0].sugestoes);
  }, 800);

  // Scroll para o painel
  setTimeout(() => {
    painel.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }, 200);
}

function fecharIAHero() {
  heroIAAtiva = false;
  document.getElementById('heroIA').style.display = 'none';
  document.getElementById('heroIAEscolha').style.display = 'none';
  document.querySelector('.hero__busca').style.display = '';
  document.getElementById('heroStats').style.display = '';
}

function heroAddMsgIA(texto) {
  const corpo = document.getElementById('heroIACorpo');
  const div = document.createElement('div');
  div.className = 'hero-ia__msg hero-ia__msg--ia';
  div.innerHTML = `<div class="hero-ia__msg-avatar">🤖</div><div class="hero-ia__msg-bolha">${texto}</div>`;
  corpo.appendChild(div);
  corpo.scrollTop = corpo.scrollHeight;
}

function heroAddMsgUser(texto) {
  const corpo = document.getElementById('heroIACorpo');
  const div = document.createElement('div');
  div.className = 'hero-ia__msg hero-ia__msg--user';
  div.innerHTML = `<div class="hero-ia__msg-bolha">${texto}</div>`;
  corpo.appendChild(div);
  corpo.scrollTop = corpo.scrollHeight;
}

function heroMostrarSugestoes(lista) {
  const container = document.getElementById('heroIASugestoes');
  container.style.display = 'flex';
  const classeExtra = heroIACat === 'veiculo' ? ' hero-ia__sug--veiculo' : '';
  container.innerHTML = lista.map(s =>
    `<button class="hero-ia__sug${classeExtra}" onclick="heroSelecionarSugestao('${s}')">${s}</button>`
  ).join('');
}

function heroEsconderSugestoes() {
  document.getElementById('heroIASugestoes').style.display = 'none';
}

function heroSelecionarSugestao(texto) {
  heroEsconderSugestoes();
  heroAddMsgUser(texto);
  heroProcessarResposta(texto);
}

function enviarMsgHero() {
  const input = document.getElementById('heroIAInput');
  const texto = input.value.trim();
  if (!texto) return;
  input.value = '';
  heroEsconderSugestoes();
  heroAddMsgUser(texto);
  heroProcessarResposta(texto);
}

function heroProcessarResposta(texto) {
  const fluxo = heroIACat === 'imovel' ? FLUXO_IMOVEL : FLUXO_VEICULO;
  heroIARespostas[`etapa_${heroIAEtapa}`] = texto;
  heroIAEtapa++;

  if (heroIAEtapa < fluxo.length) {
    heroMostrarDigitando();
    setTimeout(() => {
      heroEsconderDigitando();
      heroAddMsgIA(fluxo[heroIAEtapa].pergunta);
      heroMostrarSugestoes(fluxo[heroIAEtapa].sugestoes);
    }, 800);
  } else {
    heroMostrarDigitando();
    setTimeout(() => {
      heroEsconderDigitando();
      heroAddMsgIA('Perfeito! 🎯 Com base nas suas respostas, encontrei estas oportunidades:');
      setTimeout(() => heroMostrarResultados(), 600);
    }, 1200);
  }
}

function heroMostrarDigitando() {
  const corpo = document.getElementById('heroIACorpo');
  const div = document.createElement('div');
  div.className = 'hero-ia__digitando';
  div.id = 'heroDigitando';
  div.innerHTML = '<span></span><span></span><span></span>';
  corpo.appendChild(div);
  corpo.scrollTop = corpo.scrollHeight;
}

function heroEsconderDigitando() {
  const el = document.getElementById('heroDigitando');
  if (el) el.remove();
}

function heroMostrarResultados() {
  const corpo = document.getElementById('heroIACorpo');
  const dados = heroIACat === 'imovel' ? IMOVEIS_DATA.slice(0, 3) : VEICULOS_DATA.slice(0, 3);

  dados.forEach(item => {
    const div = document.createElement('div');
    div.className = 'hero-ia__msg hero-ia__msg--ia';

    if (heroIACat === 'imovel') {
      div.innerHTML = `
        <div class="hero-ia__msg-avatar">🤖</div>
        <div class="hero-ia__msg-bolha">
          <div class="hero-ia__resultado">
            <img src="${item.imagem}" alt="${item.titulo}" class="hero-ia__resultado-img">
            <div class="hero-ia__resultado-info">
              <div class="hero-ia__resultado-titulo">${item.titulo}</div>
              <div class="hero-ia__resultado-local">📍 ${item.endereco}</div>
              <div class="hero-ia__resultado-detalhes">${item.area} · ${item.quartos} qts · ${item.vagas} vaga${item.vagas > 1 ? 's' : ''}</div>
              <div class="hero-ia__resultado-preco">R$ ${item.preco.toLocaleString('pt-BR')} <span class="hero-ia__resultado-desconto">↓${item.desconto}%</span></div>
            </div>
          </div>
        </div>`;
    } else {
      div.innerHTML = `
        <div class="hero-ia__msg-avatar">🤖</div>
        <div class="hero-ia__msg-bolha">
          <div class="hero-ia__resultado hero-ia__resultado--veiculo">
            <img src="${item.imagem}" alt="${item.titulo}" class="hero-ia__resultado-img">
            <div class="hero-ia__resultado-info">
              <div class="hero-ia__resultado-titulo">${item.titulo}</div>
              <div class="hero-ia__resultado-local">📍 ${item.local}</div>
              <div class="hero-ia__resultado-detalhes">${item.ano} · ${item.km} · ${item.cambio}</div>
              <div class="hero-ia__resultado-preco">R$ ${item.preco.toLocaleString('pt-BR')} <span class="hero-ia__resultado-desconto">↓${item.desconto}% FIPE</span></div>
            </div>
          </div>
        </div>`;
    }
    corpo.appendChild(div);
  });

  corpo.scrollTop = corpo.scrollHeight;

  setTimeout(() => {
    heroAddMsgIA('Quer ver mais opções ou refinar a busca? Posso te ajudar com outra pesquisa! 😊');
    // Adicionar botão para rolar até os cards
    const corpo2 = document.getElementById('heroIACorpo');
    const btnDiv = document.createElement('div');
    btnDiv.className = 'hero-ia__acoes';
    btnDiv.innerHTML = `
      <button class="hero-ia__acao-btn" onclick="scrollParaCards()">📋 Ver todos os resultados</button>
      <button class="hero-ia__acao-btn hero-ia__acao-btn--novo" onclick="fecharIAHero()">🔄 Nova busca</button>
    `;
    corpo2.appendChild(btnDiv);
    corpo2.scrollTop = corpo2.scrollHeight;
  }, 500);
}

function scrollParaCards() {
  const tipo = heroIACat === 'imovel' ? 'imoveis' : 'veiculos';
  // Filtrar e rolar
  const btn = document.querySelector(`.filtros__tipo-toggle-btn[data-tipo="${tipo}"]`);
  if (btn) filtrarTipo(tipo, btn);
  document.querySelector('.leilao-dual').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function toggleChat() {
  chatAberto = !chatAberto;
  document.getElementById('chatJanela').classList.toggle('aberto', chatAberto);
  document.getElementById('chatBadge').style.display = 'none';
}

function escolherCategoria(cat, btn) {
  chatCategoriaEscolhida = cat;
  etapaChat = 0;
  respostasUsuario = { categoria: cat };

  // Atualizar visual dos botões
  document.querySelectorAll('.chat-ia__categoria-btn').forEach(b => {
    b.className = 'chat-ia__categoria-btn';
  });
  btn.classList.add(cat === 'imovel' ? 'ativo--imovel' : 'ativo--veiculo');

  // Habilitar input
  const input = document.getElementById('chatInput');
  input.disabled = false;
  input.placeholder = 'Digite sua resposta...';
  document.getElementById('chatEnviar').disabled = false;

  // Limpar mensagens anteriores
  document.getElementById('chatCorpo').innerHTML = '';

  // Mensagem inicial da IA
  const nome = cat === 'imovel' ? 'imóvel' : 'veículo';
  const emoji = cat === 'imovel' ? '🏠' : '🚗';
  adicionarMsgIA(`Ótimo! Você escolheu buscar ${emoji} <strong>${nome}</strong>. Vou te fazer algumas perguntas para encontrar as melhores oportunidades pra você!`);

  // Primeira pergunta após delay
  setTimeout(() => {
    const fluxo = cat === 'imovel' ? FLUXO_IMOVEL : FLUXO_VEICULO;
    adicionarMsgIA(fluxo[0].pergunta);
    mostrarSugestoes(fluxo[0].sugestoes);
  }, 800);
}

function adicionarMsgIA(texto) {
  const corpo = document.getElementById('chatCorpo');
  const div = document.createElement('div');
  div.className = 'chat-msg chat-msg--ia';
  div.innerHTML = `
    <div class="chat-msg__avatar">🤖</div>
    <div class="chat-msg__bolha">${texto}</div>`;
  corpo.appendChild(div);
  corpo.scrollTop = corpo.scrollHeight;
}

function adicionarMsgUsuario(texto) {
  const corpo = document.getElementById('chatCorpo');
  const div = document.createElement('div');
  div.className = 'chat-msg chat-msg--usuario';
  div.innerHTML = `
    <div class="chat-msg__avatar">👤</div>
    <div class="chat-msg__bolha">${texto}</div>`;
  corpo.appendChild(div);
  corpo.scrollTop = corpo.scrollHeight;
}

function mostrarSugestoes(lista) {
  const container = document.getElementById('chatSugestoes');
  container.style.display = 'flex';
  const classeExtra = chatCategoriaEscolhida === 'veiculo' ? ' chat-ia__sugestao--veiculo' : '';
  container.innerHTML = lista.map(s =>
    `<button class="chat-ia__sugestao${classeExtra}" onclick="selecionarSugestao('${s}')">${s}</button>`
  ).join('');
}

function esconderSugestoes() {
  document.getElementById('chatSugestoes').style.display = 'none';
}

function selecionarSugestao(texto) {
  esconderSugestoes();
  adicionarMsgUsuario(texto);
  processarResposta(texto);
}

function enviarMsg() {
  const input = document.getElementById('chatInput');
  const texto = input.value.trim();
  if (!texto) return;
  input.value = '';
  esconderSugestoes();
  adicionarMsgUsuario(texto);
  processarResposta(texto);
}

function processarResposta(texto) {
  const fluxo = chatCategoriaEscolhida === 'imovel' ? FLUXO_IMOVEL : FLUXO_VEICULO;
  respostasUsuario[`etapa_${etapaChat}`] = texto;
  etapaChat++;

  if (etapaChat < fluxo.length) {
    // Próxima pergunta com delay para parecer natural
    mostrarDigitando();
    setTimeout(() => {
      esconderDigitando();
      adicionarMsgIA(fluxo[etapaChat].pergunta);
      mostrarSugestoes(fluxo[etapaChat].sugestoes);
    }, 1000);
  } else {
    // Finalizar e recomendar
    mostrarDigitando();
    setTimeout(() => {
      esconderDigitando();
      adicionarMsgIA('Perfeito! 🎯 Com base nas suas respostas, encontrei estas oportunidades:');
      setTimeout(() => mostrarResultadosChat(), 600);
    }, 1500);
  }
}

function mostrarDigitando() {
  const corpo = document.getElementById('chatCorpo');
  const div = document.createElement('div');
  div.className = 'chat-ia__digitando';
  div.id = 'chatDigitando';
  div.innerHTML = '<span></span><span></span><span></span>';
  corpo.appendChild(div);
  corpo.scrollTop = corpo.scrollHeight;
}

function esconderDigitando() {
  const el = document.getElementById('chatDigitando');
  if (el) el.remove();
}

function mostrarResultadosChat() {
  const corpo = document.getElementById('chatCorpo');

  if (chatCategoriaEscolhida === 'imovel') {
    // Mostrar 2 imóveis como mini-cards
    IMOVEIS_DATA.slice(0, 2).forEach(item => {
      const div = document.createElement('div');
      div.className = 'chat-msg chat-msg--ia';
      div.innerHTML = `
        <div class="chat-msg__avatar">🤖</div>
        <div class="chat-msg__bolha">
          <div class="chat-imovel-mini">
            <div class="chat-imovel-mini__img"><img src="${item.imagem}" alt="${item.titulo}"></div>
            <div class="chat-imovel-mini__info">
              <div class="chat-imovel-mini__titulo">${item.titulo}</div>
              <div class="chat-imovel-mini__local">📍 ${item.endereco}</div>
              <div class="chat-imovel-mini__preco">R$ ${item.preco.toLocaleString('pt-BR')}</div>
            </div>
          </div>
        </div>`;
      corpo.appendChild(div);
    });
  } else {
    // Mostrar 2 veículos como mini-cards
    VEICULOS_DATA.slice(0, 2).forEach(item => {
      const div = document.createElement('div');
      div.className = 'chat-msg chat-msg--ia';
      div.innerHTML = `
        <div class="chat-msg__avatar">🤖</div>
        <div class="chat-msg__bolha">
          <div class="chat-veiculo-mini">
            <div class="chat-veiculo-mini__img"><img src="${item.imagem}" alt="${item.titulo}"></div>
            <div class="chat-veiculo-mini__info">
              <div class="chat-veiculo-mini__titulo">${item.titulo}</div>
              <div class="chat-veiculo-mini__specs">
                <span>${item.ano}</span> · <span>${item.km}</span> · <span>${item.cambio}</span>
              </div>
              <div class="chat-veiculo-mini__preco">R$ ${item.preco.toLocaleString('pt-BR')}</div>
            </div>
          </div>
        </div>`;
      corpo.appendChild(div);
    });
  }

  corpo.scrollTop = corpo.scrollHeight;

  // Mensagem final
  setTimeout(() => {
    adicionarMsgIA('Quer refinar a busca ou ver mais opções? Você pode trocar entre 🏠 Imóvel e 🚗 Veículo a qualquer momento! 😊');
  }, 500);
}

// ─── AUTH (LOGIN / CADASTRO) ─────────────────
function abrirAuth(tipo) {
  document.getElementById('authOverlay').classList.add('ativo');
  trocarAuthTab(tipo);
}

function fecharAuth() {
  document.getElementById('authOverlay').classList.remove('ativo');
}

function trocarAuthTab(tipo, btn) {
  const tabs = document.querySelectorAll('.auth-tab');
  tabs.forEach(t => t.classList.remove('ativo'));

  if (btn) {
    btn.classList.add('ativo');
  } else {
    tabs.forEach(t => {
      if ((tipo === 'login' && t.textContent === 'Entrar') ||
          (tipo === 'cadastro' && t.textContent === 'Criar Conta')) {
        t.classList.add('ativo');
      }
    });
  }

  document.getElementById('formLogin').style.display = tipo === 'login' ? 'block' : 'none';
  document.getElementById('formCadastro').style.display = tipo === 'cadastro' ? 'block' : 'none';
  document.getElementById('authTitulo').textContent = tipo === 'login' ? 'Entrar' : 'Criar Conta';
}

function handleLogin(e) {
  e.preventDefault();
  var email = document.getElementById('loginEmail').value.trim();
  var senha = document.getElementById('loginSenha').value;
  var btnSubmit = e.target.querySelector('button[type="submit"]');
  var textoOriginal = btnSubmit.textContent;
  btnSubmit.disabled = true;
  btnSubmit.textContent = 'Entrando...';

  console.log('[AUTH] Enviando login para API...', email);

  fetch('/api/auth.php?action=login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',
    body: JSON.stringify({ email: email, senha: senha })
  })
  .then(function(res) {
    console.log('[AUTH] Resposta HTTP:', res.status);
    return res.text();
  })
  .then(function(text) {
    console.log('[AUTH] Body:', text);
    var data;
    try { data = JSON.parse(text); } catch(err) {
      console.error('[AUTH] JSON inválido:', err);
      throw new Error('Resposta inválida do servidor');
    }
    btnSubmit.disabled = false;
    btnSubmit.textContent = textoOriginal;
    if (data.ok) {
      mostrarToast('sucesso', 'Login realizado!', 'Bem-vindo de volta!');
      fecharAuth();
      verificarSessao();
    } else {
      mostrarToast('erro', 'Erro no login', data.erro || 'E-mail ou senha incorretos');
    }
  })
  .catch(function(err) {
    console.error('[AUTH] Erro:', err);
    btnSubmit.disabled = false;
    btnSubmit.textContent = textoOriginal;
    mostrarToast('erro', 'Erro', 'Falha na comunicação com o servidor');
  });
  return false;
}

function handleCadastro(e) {
  e.preventDefault();
  var nome = document.getElementById('cadNome').value.trim();
  var email = document.getElementById('cadEmail').value.trim();
  var cpf = document.getElementById('cadCPF').value.trim();
  var senha = document.getElementById('cadSenha').value;
  var btnSubmit = e.target.querySelector('button[type="submit"]');
  var textoOriginal = btnSubmit.textContent;
  btnSubmit.disabled = true;
  btnSubmit.textContent = 'Criando conta...';

  fetch('/api/auth.php?action=cadastro', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',
    body: JSON.stringify({ nome: nome, email: email, cpf: cpf, senha: senha })
  })
  .then(function(res) { return res.json().then(function(data) { return { status: res.status, data: data }; }); })
  .then(function(result) {
    btnSubmit.disabled = false;
    btnSubmit.textContent = textoOriginal;
    if (result.data.ok) {
      mostrarToast('sucesso', 'Conta criada!', 'Bem-vindo ao Qatar Leilões!');
      fecharAuth();
      verificarSessao();
    } else {
      mostrarToast('erro', 'Erro no cadastro', result.data.erro || 'Não foi possível criar a conta');
    }
  })
  .catch(function() {
    btnSubmit.disabled = false;
    btnSubmit.textContent = textoOriginal;
    mostrarToast('erro', 'Erro', 'Falha na comunicação com o servidor');
  });
  return false;
}

// ─── MENU MOBILE ─────────────────────────────
function toggleMenuMobile() {
  document.getElementById('navMobile').classList.toggle('ativo');
}

// ─── BUSCA GERAL (agora redireciona para IA no hero) ─
function buscarGeral() {
  iniciarIAHero();
}

// ─── TOASTS ──────────────────────────────────
function mostrarToast(tipo, titulo, mensagem) {
  const container = document.getElementById('toastContainer');
  const toast = document.createElement('div');
  toast.className = `toast toast--${tipo}`;
  toast.innerHTML = `
    <div class="toast__conteudo">
      <div class="toast__titulo">${titulo}</div>
      <div class="toast__mensagem">${mensagem}</div>
    </div>
    <button class="toast__fechar" onclick="this.parentElement.remove()">✕</button>`;
  container.appendChild(toast);
  setTimeout(() => toast.remove(), 4000);
}

// ─── SESSÃO / HEADER PERFIL ──────────────────
function verificarSessao() {
  fetch('/api/auth.php?action=check', { credentials: 'same-origin' })
    .then(function(res) { return res.json(); })
    .then(function(data) {
      if (data.ok && data.usuario) {
        mostrarPerfilHeader(data.usuario);
      }
    })
    .catch(function() { /* não logado, manter botões */ });
}

function mostrarPerfilHeader(usuario) {
  var nome = usuario.nome || usuario.email || 'Usuário';
  var iniciais = nome.split(' ').map(function(p){ return p[0]; }).join('').substring(0, 2).toUpperCase();

  var authBtns = document.getElementById('headerAuthBtns');
  var perfil = document.getElementById('headerPerfil');
  if (authBtns) authBtns.style.display = 'none';
  if (perfil) {
    perfil.style.display = 'flex';
    document.getElementById('headerAvatar').textContent = iniciais;
    document.getElementById('headerNome').textContent = nome.split(' ')[0];
  }

  var mobileAuth = document.getElementById('mobileAuthBtns');
  var mobilePerfil = document.getElementById('mobilePerfil');
  if (mobileAuth) mobileAuth.style.display = 'none';
  if (mobilePerfil) mobilePerfil.style.display = 'block';
}

function fazerLogout(e) {
  if (e) e.preventDefault();
  fetch('/api/auth.php?action=logout', { credentials: 'same-origin' })
    .then(function() { window.location.reload(); })
    .catch(function() { window.location.reload(); });
}

// ─── INICIALIZAÇÃO ───────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  carregarImoveis();
  verificarSessao();

  // Mostrar badge do chat após 3 segundos
  setTimeout(() => {
    if (!chatAberto) {
      const badge = document.getElementById('chatBadge');
      badge.style.display = 'flex';
      badge.textContent = '1';
    }
  }, 3000);

  // Fechar dropdown perfil ao clicar fora
  document.addEventListener('click', function(e) {
    var perfil = document.getElementById('headerPerfil');
    if (perfil && !perfil.contains(e.target)) {
      perfil.classList.remove('aberto');
    }
  });
});
