/**
 * Leilão Caixa - Frontend JavaScript
 * Bidding, polling, auto-lance, catálogo
 */
(function ($) {
    'use strict';

    const LC = window.leilaoCaixa || {};
    const { ajaxUrl, restUrl, nonce, restNonce, userId, i18n } = LC;

    /* ==============================
       UTILS
    ============================== */
    function formatBRL(val) {
        return 'R$ ' + parseFloat(val).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
    }

    function showMsg(selector, msg, type) {
        const $box = $(selector);
        $box.removeClass('success error').addClass(type).text(msg).show();
        if (type === 'success') {
            setTimeout(() => $box.fadeOut(), 4000);
        }
    }

    function timeLeft(endDate) {
        const diff = new Date(endDate) - new Date();
        if (diff <= 0) return null;

        const d = Math.floor(diff / 86400000);
        const h = Math.floor((diff % 86400000) / 3600000);
        const m = Math.floor((diff % 3600000) / 60000);
        const s = Math.floor((diff % 60000) / 1000);

        if (d > 0) return `${d}d ${h}h ${m}m`;
        if (h > 0) return `${h}h ${m}m ${s}s`;
        return `${m}m ${s}s`;
    }

    /* ==============================
       PÁGINA DO IMÓVEL (SINGLE)
    ============================== */
    const $bidPanel = $('.leilao-bid-panel');
    if ($bidPanel.length) {
        const imovelId = $bidPanel.data('imovel-id');
        let pollingInterval;

        // Polling de lances a cada 3 segundos
        function pollLances() {
            $.ajax({
                url: ajaxUrl,
                data: {
                    action: 'leilao_get_lances',
                    imovel_id: imovelId,
                },
                success(res) {
                    if (!res.success) return;
                    updateBidPanel(res.data);
                },
            });
        }

        function updateBidPanel(data) {
            // Atualizar maior lance
            if (data.maior_lance) {
                $('.leilao-bid-value').text(formatBRL(data.maior_lance.valor));
                $('.leilao-bid-user').text(data.maior_lance.nome_anonimo || '');
            }

            // Atualizar timer
            if (data.fim) {
                const tl = timeLeft(data.fim);
                const $timer = $('.leilao-bid-timer');
                if (tl) {
                    $timer.text(tl);
                    // Animação se < 3min
                    const diff = new Date(data.fim) - new Date();
                    $timer.toggleClass('ending', diff < 180000);
                } else {
                    $timer.text('ENCERRADO');
                    $timer.addClass('ending');
                    clearInterval(pollingInterval);
                    disableBidForm();
                }
            }

            // Atualizar status
            if (data.status === 'encerrado') {
                clearInterval(pollingInterval);
                disableBidForm();
            }

            // Atualizar total de lances
            $('.leilao-total-lances').text(data.total_lances || 0);

            // Atualizar histórico
            renderLances(data.lances || []);

            // Atualizar mínimo no input
            if (data.maior_lance) {
                const incremento = parseFloat($bidPanel.data('incremento')) || 500;
                const minimo = parseFloat(data.maior_lance.valor) + incremento;
                $('#lance-valor').attr('min', minimo).attr('placeholder', formatBRL(minimo));
                if (!$('#lance-valor').val()) {
                    $('#lance-valor').val(minimo);
                }
            }
        }

        function renderLances(lances) {
            const $list = $('.leilao-lances-history');
            if (!$list.length) return;

            let html = '';
            lances.forEach((l, i) => {
                const isMe = parseInt(l.user_id) === parseInt(userId);
                const tipoClass = l.tipo === 'automatico' ? 'auto' : '';
                html += `
                    <div class="leilao-lance-item ${i === 0 ? 'top' : ''}">
                        <span class="lance-user">${l.nome_anonimo}${isMe ? ' (Você)' : ''}</span>
                        <span class="lance-valor">${formatBRL(l.valor)}</span>
                        <span class="lance-tipo ${tipoClass}">${l.tipo === 'automatico' ? 'Auto' : 'Manual'}</span>
                        <span class="lance-time">${new Date(l.criado_em).toLocaleTimeString('pt-BR')}</span>
                    </div>
                `;
            });
            $list.html(html);
        }

        function disableBidForm() {
            $('#form-lance input, #form-lance button').prop('disabled', true);
            $('#form-lance').append(`<p style="color:red;font-weight:700;text-align:center;margin-top:12px">${i18n.leilao_encerrado}</p>`);
        }

        // Enviar lance
        $(document).on('submit', '#form-lance', function (e) {
            e.preventDefault();

            if (!userId) {
                showMsg('#lance-msg', i18n.login_required, 'error');
                return;
            }

            const valor = parseFloat($('#lance-valor').val());
            if (!valor) return;

            if (!confirm(`${i18n.confirmar_lance} ${formatBRL(valor)}?`)) return;

            const $btn = $(this).find('button[type="submit"]');
            $btn.prop('disabled', true).text('Enviando...');

            $.post(ajaxUrl, {
                action: 'leilao_dar_lance',
                nonce: nonce,
                imovel_id: imovelId,
                valor: valor,
            }, function (res) {
                $btn.prop('disabled', false).text('Dar Lance');

                if (res.success) {
                    showMsg('#lance-msg', res.data.message, 'success');
                    $('#lance-valor').val('');
                    pollLances(); // Refresh imediato
                } else {
                    showMsg('#lance-msg', res.data.message, 'error');
                }
            });
        });

        // Botões de lance rápido
        $(document).on('click', '.leilao-bid-quick button', function () {
            const increment = parseFloat($(this).data('increment'));
            const current = parseFloat($('#lance-valor').attr('min')) || 0;
            $('#lance-valor').val(current + increment);
        });

        // Auto lance
        $(document).on('submit', '#form-auto-lance', function (e) {
            e.preventDefault();

            if (!userId) {
                showMsg('#auto-lance-msg', i18n.login_required, 'error');
                return;
            }

            $.post(ajaxUrl, {
                action: 'leilao_auto_lance',
                nonce: nonce,
                imovel_id: imovelId,
                valor_maximo: $('#auto-lance-max').val(),
                incremento: $('#auto-lance-inc').val(),
            }, function (res) {
                if (res.success) {
                    showMsg('#auto-lance-msg', res.data.message, 'success');
                    $('.leilao-auto-lance-status').text('Ativo').addClass('active');
                } else {
                    showMsg('#auto-lance-msg', res.data.message, 'error');
                }
            });
        });

        // Cancelar auto lance
        $(document).on('click', '#btn-cancelar-auto', function () {
            $.post(ajaxUrl, {
                action: 'leilao_cancelar_auto_lance',
                nonce: nonce,
                imovel_id: imovelId,
            }, function (res) {
                if (res.success) {
                    showMsg('#auto-lance-msg', res.data.message, 'success');
                    $('.leilao-auto-lance-status').text('Inativo').removeClass('active');
                }
            });
        });

        // Gallery thumbs
        $(document).on('click', '.leilao-gallery-thumbs img', function () {
            const src = $(this).data('full');
            $('.leilao-gallery-main img').attr('src', src);
            $('.leilao-gallery-thumbs img').removeClass('active');
            $(this).addClass('active');
        });

        // Start polling
        pollLances();
        pollingInterval = setInterval(pollLances, 3000);

        // Update timer every second
        setInterval(function () {
            const $timer = $('.leilao-bid-timer');
            const endDate = $timer.data('end');
            if (endDate) {
                const tl = timeLeft(endDate);
                if (tl) {
                    $timer.text(tl);
                } else {
                    $timer.text('ENCERRADO');
                }
            }
        }, 1000);
    }

    /* ==============================
       CATÁLOGO
    ============================== */
    const $catalogo = $('#leilao-catalogo');
    if ($catalogo.length) {
        let currentPage = 1;
        let filters = {};

        // Carregar filtros
        $.get(restUrl + 'filtros', function (data) {
            populateSelect('#filtro-estado', data.estados);
            populateSelect('#filtro-cidade', data.cidades);
            populateSelect('#filtro-tipo', data.tipos);
            populateSelect('#filtro-modalidade', data.modalidades);
        });

        function populateSelect(selector, items) {
            const $sel = $(selector);
            const firstOption = $sel.find('option:first').text();
            $sel.html(`<option value="">${firstOption}</option>`);
            (items || []).forEach(item => {
                $sel.append(`<option value="${item.slug}">${item.name} (${item.count})</option>`);
            });
        }

        function loadImoveis() {
            const $grid = $('#leilao-grid');
            $grid.html('<div class="leilao-loading">Carregando imóveis...</div>');

            const params = new URLSearchParams({
                page: currentPage,
                per_page: 12,
                status: 'ativo',
                ...filters,
            });

            $.get(restUrl + 'imoveis?' + params.toString(), {
                beforeSend(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', restNonce);
                },
            }, function (data) {
                renderGrid(data.items);
                renderPagination(data.total_pages);
            }).fail(function () {
                $grid.html('<p class="leilao-empty">Erro ao carregar imóveis.</p>');
            });
        }

        function renderGrid(items) {
            const $grid = $('#leilao-grid');

            if (!items || items.length === 0) {
                $grid.html('<p class="leilao-empty">Nenhum imóvel em leilão no momento.</p>');
                return;
            }

            let html = '';
            items.forEach(item => {
                const valor = item.maior_lance > 0 ? item.maior_lance : item.valor_minimo;
                const tl = item.fim ? timeLeft(item.fim) : '';

                html += `
                    <a href="${item.url}" class="leilao-card" style="text-decoration:none;color:inherit">
                        <div class="leilao-card-img">
                            ${item.thumb ? `<img src="${item.thumb}" alt="${item.titulo}" loading="lazy" />` : '<div style="height:220px;background:#eee"></div>'}
                            <span class="leilao-badge ${item.status}">${item.status}</span>
                            ${item.modalidade ? `<span class="leilao-badge modalidade">${item.modalidade}</span>` : ''}
                        </div>
                        <div class="leilao-card-body">
                            <h4>${item.titulo}</h4>
                            <div class="leilao-card-location">
                                📍 ${item.cidade || ''}${item.cidade && item.estado ? ' - ' : ''}${item.estado || ''}
                            </div>
                            <div class="leilao-card-details">
                                ${item.area_total ? `<span>📐 ${item.area_total}m²</span>` : ''}
                                ${item.quartos ? `<span>🛏 ${item.quartos} qts</span>` : ''}
                                ${item.garagem ? `<span>🚗 ${item.garagem} vaga(s)</span>` : ''}
                            </div>
                            <div class="leilao-card-footer">
                                <div class="leilao-card-price">
                                    ${item.valor_avaliacao ? `<span class="leilao-valor-avaliacao">${formatBRL(item.valor_avaliacao)}</span>` : ''}
                                    <small>${item.maior_lance > 0 ? 'Maior lance' : 'Lance mínimo'}</small>
                                    <span class="leilao-valor">${formatBRL(valor)}</span>
                                </div>
                                <div>
                                    ${tl ? `<span class="leilao-card-timer">⏱ ${tl}</span>` : ''}
                                    <br><small>${item.total_lances} lance(s)</small>
                                </div>
                            </div>
                        </div>
                    </a>
                `;
            });

            $grid.html(html);
        }

        function renderPagination(totalPages) {
            const $pag = $('#leilao-pagination');
            if (totalPages <= 1) {
                $pag.html('');
                return;
            }

            let html = '';
            for (let i = 1; i <= totalPages; i++) {
                html += `<button class="${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</button>`;
            }
            $pag.html(html);
        }

        // Eventos
        $('#btn-filtrar').on('click', function () {
            filters = {};
            if ($('#filtro-estado').val()) filters.estado = $('#filtro-estado').val();
            if ($('#filtro-cidade').val()) filters.cidade = $('#filtro-cidade').val();
            if ($('#filtro-tipo').val()) filters.tipo = $('#filtro-tipo').val();
            if ($('#filtro-modalidade').val()) filters.modalidade = $('#filtro-modalidade').val();
            currentPage = 1;
            loadImoveis();
        });

        $('#btn-limpar').on('click', function () {
            filters = {};
            currentPage = 1;
            $('.leilao-select').val('');
            loadImoveis();
        });

        $(document).on('click', '.leilao-pagination button', function () {
            currentPage = parseInt($(this).data('page'));
            loadImoveis();
            $('html, body').animate({ scrollTop: $catalogo.offset().top - 100 }, 300);
        });

        // Load inicial
        loadImoveis();
    }

    /* ==============================
       AUTH FORMS
    ============================== */
    $(document).on('submit', '#leilao-login-form', function (e) {
        e.preventDefault();
        const $form = $(this);
        const $btn = $form.find('button[type="submit"]');
        $btn.prop('disabled', true).text('Entrando...');

        $.post(ajaxUrl, $form.serialize(), function (res) {
            $btn.prop('disabled', false).text('Entrar');
            if (res.success) {
                showMsg('#login-msg', res.data.message, 'success');
                window.location.href = res.data.redirect;
            } else {
                showMsg('#login-msg', res.data.message, 'error');
            }
        });
    });

    $(document).on('submit', '#leilao-register-form', function (e) {
        e.preventDefault();
        const $form = $(this);
        const $btn = $form.find('button[type="submit"]');
        $btn.prop('disabled', true).text('Criando...');

        $.post(ajaxUrl, $form.serialize(), function (res) {
            $btn.prop('disabled', false).text('Criar Conta');
            if (res.success) {
                showMsg('#register-msg', res.data.message, 'success');
                window.location.href = res.data.redirect;
            } else {
                showMsg('#register-msg', res.data.message, 'error');
            }
        });
    });

    /* ==============================
       PAINEL ARREMATANTE
    ============================== */
    const $painel = $('#painel-arrematante');
    if ($painel.length && userId) {
        // Tabs
        $(document).on('click', '.leilao-tab', function () {
            const tab = $(this).data('tab');
            $('.leilao-tab').removeClass('active');
            $(this).addClass('active');
            $('.leilao-tab-content').removeClass('active');
            $(`#tab-${tab}`).addClass('active');
        });

        // Carregar meus lances
        $.ajax({
            url: restUrl + 'meus-lances',
            headers: { 'X-WP-Nonce': restNonce },
            success(lances) {
                const $list = $('#lista-meus-lances');

                if (!lances || lances.length === 0) {
                    $list.html('<p class="leilao-empty">Você ainda não deu nenhum lance.</p>');
                    return;
                }

                let html = '<div class="leilao-grid">';
                lances.forEach(l => {
                    const statusClass = l.esta_ganhando ? 'success' : (l.status === 'encerrado' ? 'danger' : '');
                    const statusText = l.status === 'encerrado'
                        ? (l.esta_ganhando ? '🏆 Arrematado!' : 'Encerrado')
                        : (l.esta_ganhando ? '✅ Ganhando' : '⚠️ Superado');

                    html += `
                        <div class="leilao-card">
                            <div class="leilao-card-img">
                                ${l.thumb ? `<img src="${l.thumb}" />` : '<div style="height:160px;background:#eee"></div>'}
                            </div>
                            <div class="leilao-card-body">
                                <h4><a href="${l.url}">${l.titulo}</a></h4>
                                <p>Meu maior lance: <strong>${formatBRL(l.meu_maior_lance)}</strong></p>
                                <p>Maior lance atual: <strong>${formatBRL(l.maior_lance)}</strong></p>
                                <p>Total de lances meus: ${l.total_lances}</p>
                                <p style="color:${l.esta_ganhando ? '#27ae60' : '#c0392b'};font-weight:700">${statusText}</p>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
                $list.html(html);
            },
            error() {
                $('#lista-meus-lances').html('<p class="leilao-empty">Erro ao carregar seus lances.</p>');
            },
        });
    }

    /* ==============================
       CPF MASK
    ============================== */
    $(document).on('input', 'input[name="cpf"]', function () {
        let v = $(this).val().replace(/\D/g, '');
        if (v.length > 3) v = v.replace(/^(\d{3})/, '$1.');
        if (v.length > 7) v = v.replace(/^(\d{3})\.(\d{3})/, '$1.$2.');
        if (v.length > 11) v = v.replace(/^(\d{3})\.(\d{3})\.(\d{3})/, '$1.$2.$3-');
        $(this).val(v.substring(0, 14));
    });

    // Phone mask
    $(document).on('input', 'input[name="telefone"]', function () {
        let v = $(this).val().replace(/\D/g, '');
        if (v.length > 2) v = '(' + v.substring(0, 2) + ') ' + v.substring(2);
        if (v.length > 10) v = v.substring(0, 10) + '-' + v.substring(10);
        $(this).val(v.substring(0, 15));
    });

    /* ==============================
       ASSESSORIA Q4
    ============================== */

    // Contratar assessoria
    $(document).on('click', '#btn-contratar-assessoria', function () {
        const arrematacaoId = $(this).data('arrematacao');
        const tipoPessoa = $('#tipo_pessoa_assessoria').val();

        if (!confirm('Confirma a contratação da assessoria jurídica Q4?')) {
            return;
        }

        const $btn = $(this);
        $btn.prop('disabled', true).text('Processando...');

        $.post(ajaxUrl, {
            action: 'leilao_contratar_assessoria',
            nonce: nonce,
            arrematacao_id: arrematacaoId,
            tipo_pessoa: tipoPessoa
        }, function (res) {
            if (res.success) {
                alert(res.data.message);
                if (res.data.redirect) {
                    window.location.href = res.data.redirect;
                }
            } else {
                alert(res.data.message || 'Erro ao contratar assessoria.');
                $btn.prop('disabled', false).text('Contratar Assessoria Jurídica');
            }
        }).fail(function () {
            alert('Erro de conexão.');
            $btn.prop('disabled', false).text('Contratar Assessoria Jurídica');
        });
    });

    // Assinar contrato
    $(document).on('click', '#btn-assinar-contrato', function () {
        const assessoriaId = $(this).data('assessoria');
        const aceite = $('#aceite_termos_assessoria').is(':checked');

        if (!aceite) {
            alert('Você precisa aceitar os termos para prosseguir.');
            return;
        }

        if (!confirm('Confirma a assinatura digital do contrato e da procuração?')) {
            return;
        }

        const $btn = $(this);
        $btn.prop('disabled', true).text('Assinando...');

        $.post(ajaxUrl, {
            action: 'leilao_assinar_contrato',
            nonce: nonce,
            assessoria_id: assessoriaId,
            aceite_termos: true
        }, function (res) {
            if (res.success) {
                alert(res.data.message);
                if (res.data.redirect) {
                    window.location.href = res.data.redirect;
                } else {
                    window.location.reload();
                }
            } else {
                alert(res.data.message || 'Erro ao assinar.');
                $btn.prop('disabled', false).text('✍️ Assinar Digitalmente');
            }
        }).fail(function () {
            alert('Erro de conexão.');
            $btn.prop('disabled', false).text('✍️ Assinar Digitalmente');
        });
    });

    // Upload documento assessoria
    $(document).on('submit', '.leilao-upload-form-assessoria', function (e) {
        e.preventDefault();

        const $form = $(this);
        const formData = new FormData(this);
        formData.append('action', 'leilao_upload_documento_assessoria');
        formData.append('nonce', nonce);

        const $btn = $form.find('button[type="submit"]');
        $btn.prop('disabled', true).text('Enviando...');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (res) {
                if (res.success) {
                    alert(res.data.message);
                    window.location.reload();
                } else {
                    alert(res.data.message || 'Erro ao enviar documento.');
                    $btn.prop('disabled', false).text('Enviar');
                }
            },
            error: function () {
                alert('Erro de conexão.');
                $btn.prop('disabled', false).text('Enviar');
            }
        });
    });

})(jQuery);
