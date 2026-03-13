/**
 * IA de Triagem — Chat com ChatGPT
 * Envia pergunta ao backend que consulta o GPT e retorna imóveis
 */
(function () {
    'use strict';

    const form     = document.getElementById('ai-triage-form');
    const input    = document.getElementById('ai-input');
    const messages = document.getElementById('ai-messages');

    if (!form || !input || !messages) return;

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const text = input.value.trim();
        if (!text) return;

        addMessage(text, 'user');
        input.value = '';
        input.disabled = true;

        const typingEl = addMessage('Pensando...', 'bot', true);

        fetchAI(text).then(function (data) {
            typingEl.remove();
            input.disabled = false;
            input.focus();

            if (!data || data.error) {
                addMessage(data?.error || 'Desculpe, houve um erro. Tente novamente.', 'bot');
                return;
            }

            let html = data.resposta || 'Aqui estão os resultados:';

            if (data.imoveis && data.imoveis.length > 0) {
                html += '<div class="ai-results">';
                data.imoveis.forEach(function (r) {
                    const desconto = r.valor_avaliacao > 0
                        ? Math.round((1 - r.valor_minimo / r.valor_avaliacao) * 100)
                        : 0;

                    html += '<a href="' + r.link + '" class="ai-result-card">';
                    html += '<strong>' + r.titulo + '</strong>';
                    if (r.cidade) html += '<span>📍 ' + r.cidade + (r.estado ? '/' + r.estado : '') + '</span>';
                    if (r.quartos) html += '<span>🛏 ' + r.quartos + ' quartos</span>';
                    if (r.area) html += '<span>📐 ' + r.area + 'm²</span>';
                    html += '<span class="ai-result-price">R$ ' + formatMoney(r.valor_minimo) + '</span>';
                    if (desconto > 0) html += '<span class="ai-result-desconto">-' + desconto + '% off</span>';
                    html += '</a>';
                });
                html += '</div>';
            }

            addMessage(html, 'bot');
        }).catch(function (err) {
            typingEl.remove();
            input.disabled = false;
            input.focus();
            addMessage('Desculpe, ocorreu um erro ao consultar a IA. Tente novamente.', 'bot');
        });
    });

    function addMessage(content, type, isTyping) {
        const wrap = document.createElement('div');
        wrap.className = 'ai-message ai-' + type + (isTyping ? ' ai-typing' : '');

        const avatar = document.createElement('div');
        avatar.className = 'ai-avatar';
        avatar.textContent = type === 'bot' ? '🤖' : '👤';

        const bubble = document.createElement('div');
        bubble.className = 'ai-bubble';
        bubble.innerHTML = content;

        wrap.appendChild(avatar);
        wrap.appendChild(bubble);
        messages.appendChild(wrap);
        messages.scrollTop = messages.scrollHeight;

        return wrap;
    }

    function fetchAI(mensagem) {
        const body = new FormData();
        body.append('action', 'leilao_ai_chat');
        body.append('mensagem', mensagem);

        return fetch(leilaoAI.ajaxUrl, {
            method: 'POST',
            body: body,
        })
        .then(function (r) { return r.json(); })
        .then(function (json) {
            if (json.success) return json.data;
            return { error: json.data || 'Erro desconhecido.' };
        });
    }

    function formatMoney(val) {
        return Number(val).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
})();
