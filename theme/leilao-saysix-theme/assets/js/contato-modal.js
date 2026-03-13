/**
 * Modal de Contato — Leilão SaySix
 * Abre/fecha o modal e envia via AJAX para leilao_contato
 */
(function () {
    'use strict';

    const overlay  = document.getElementById('contatoModalOverlay');
    const closeBtn = document.getElementById('contatoModalClose');
    const form     = document.getElementById('contatoForm');
    const msgBox   = document.getElementById('contatoMsg');
    const btnEnviar = document.getElementById('contatoBtnEnviar');

    if (!overlay || !form) return;

    /* ===== Abrir modal ===== */
    window.abrirContatoModal = function (el) {
        const id    = el.getAttribute('data-imovel-id');
        const title = el.getAttribute('data-imovel-title');

        document.getElementById('contatoImovelId').value = id;
        document.getElementById('contatoImovelTitle').textContent = title;

        // Limpar form anterior
        form.reset();
        msgBox.textContent = '';
        msgBox.className = 'contato-msg';
        btnEnviar.disabled = false;
        btnEnviar.textContent = 'Enviar mensagem';

        // Preencher mensagem padrão
        document.getElementById('contatoMensagem').value =
            'Olá! Tenho interesse no imóvel "' + title + '". Gostaria de mais informações.';

        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';

        // Focus no nome
        setTimeout(function () {
            document.getElementById('contatoNome').focus();
        }, 200);
    };

    /* ===== Fechar modal ===== */
    function fecharModal() {
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    closeBtn.addEventListener('click', fecharModal);
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) fecharModal();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && overlay.classList.contains('active')) fecharModal();
    });

    /* ===== Enviar formulário ===== */
    form.addEventListener('submit', function (e) {
        e.preventDefault();

        btnEnviar.disabled = true;
        btnEnviar.textContent = 'Enviando...';
        msgBox.textContent = '';
        msgBox.className = 'contato-msg';

        var fd = new FormData(form);
        fd.append('action', 'leilao_contato');

        fetch(leilaoContato.ajaxUrl, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success) {
                    msgBox.textContent = res.data.msg;
                    msgBox.className = 'contato-msg success';
                    btnEnviar.textContent = '✅ Enviada!';

                    // Fechar após 3s
                    setTimeout(fecharModal, 3000);
                } else {
                    msgBox.textContent = res.data.msg || 'Erro ao enviar. Tente novamente.';
                    msgBox.className = 'contato-msg error';
                    btnEnviar.disabled = false;
                    btnEnviar.textContent = 'Enviar mensagem';
                }
            })
            .catch(function () {
                msgBox.textContent = 'Erro de conexão. Verifique sua internet e tente novamente.';
                msgBox.className = 'contato-msg error';
                btnEnviar.disabled = false;
                btnEnviar.textContent = 'Enviar mensagem';
            });
    });
})();
