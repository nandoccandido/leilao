/**
 * Leilão Caixa - Admin JavaScript
 */
(function ($) {
    'use strict';

    const { ajaxUrl, nonce } = window.leilaoAdmin || {};

    // Importar da Caixa
    $(document).on('submit', '#form-importar-caixa', function (e) {
        e.preventDefault();

        const estado = $('#import-estado').val();
        if (!estado) {
            alert('Selecione um estado.');
            return;
        }

        const $btn = $('#btn-importar');
        const $result = $('#import-result');

        $btn.prop('disabled', true).text('Importando...');
        $result.html('<p>Buscando imóveis no site da Caixa para ' + estado + '... Aguarde.</p>');

        $.post(ajaxUrl, {
            action: 'leilao_importar_caixa',
            nonce: nonce,
            estado: estado,
        }, function (res) {
            $btn.prop('disabled', false).text('Importar');

            if (res.success) {
                $result.html(
                    '<div class="notice notice-success"><p>' +
                    res.data.message +
                    '</p></div>'
                );
            } else {
                $result.html(
                    '<div class="notice notice-error"><p>' +
                    (res.data.message || 'Erro desconhecido.') +
                    '</p></div>'
                );
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Importar');
            $result.html('<div class="notice notice-error"><p>Erro de comunicação.</p></div>');
        });
    });

    // Cadastrar manualmente
    $(document).on('submit', '#form-cadastrar-manual', function (e) {
        e.preventDefault();

        const $form = $(this);
        const $btn = $form.find('button[type="submit"]');
        const $result = $('#cadastro-result');

        $btn.prop('disabled', true).text('Cadastrando...');

        const data = $form.serializeArray().reduce((obj, item) => {
            obj[item.name] = item.value;
            return obj;
        }, {});

        data.action = 'leilao_importar_manual';
        data.nonce = nonce;

        // Convert datetime-local to mysql format
        if (data.inicio) data.inicio = data.inicio.replace('T', ' ');
        if (data.fim) data.fim = data.fim.replace('T', ' ');

        $.post(ajaxUrl, data, function (res) {
            $btn.prop('disabled', false).text('Cadastrar Imóvel');

            if (res.success) {
                $result.html(
                    '<div class="notice notice-success"><p>' +
                    res.data.message +
                    ' <a href="' + res.data.edit_url + '" class="button">Editar</a></p></div>'
                );
                $form[0].reset();
            } else {
                $result.html(
                    '<div class="notice notice-error"><p>' +
                    (res.data.message || 'Erro.') +
                    '</p></div>'
                );
            }
        });
    });

    // ================================================
    // ARREMATAÇÃO - Fluxo de Documentação
    // ================================================

    // Confirmar arrematante
    $(document).on('click', '#btn-confirmar-arrematante', function () {
        const $btn = $(this);
        const imovelId = $btn.data('imovel');
        const userId = $btn.data('user');
        const valor = $btn.data('valor');
        const tipoPessoa = $('#tipo_pessoa_arrematante').val();
        const prazoDias = $('#prazo_dias_documentos').val();
        const observacoes = $('#obs_arrematacao').val();

        if (!confirm('Confirmar este arrematante e iniciar o fluxo de documentação?\n\nEsta ação enviará um e-mail ao arrematante solicitando os documentos.')) {
            return;
        }

        $btn.prop('disabled', true).text('Processando...');

        $.post(ajaxUrl, {
            action: 'leilao_confirmar_arrematante',
            nonce: nonce,
            imovel_id: imovelId,
            user_id: userId,
            valor: valor,
            tipo_pessoa: tipoPessoa,
            prazo_dias: prazoDias,
            observacoes: observacoes,
        }, function (res) {
            if (res.success) {
                alert(res.data.message);
                location.reload();
            } else {
                alert(res.data.message || 'Erro ao processar.');
                $btn.prop('disabled', false).text('✅ Confirmar Arrematante e Iniciar Fluxo');
            }
        }).fail(function () {
            alert('Erro de comunicação.');
            $btn.prop('disabled', false).text('✅ Confirmar Arrematante e Iniciar Fluxo');
        });
    });

    // Atualizar status da arrematação
    $(document).on('click', '#btn-atualizar-status', function () {
        const $btn = $(this);
        const arrematacaoId = $btn.data('id');
        const novoStatus = $('#novo_status_arrematacao').val();

        if (!confirm('Atualizar o status desta arrematação?')) {
            return;
        }

        $btn.prop('disabled', true).text('Atualizando...');

        $.post(ajaxUrl, {
            action: 'leilao_atualizar_status_arrematacao',
            nonce: nonce,
            arrematacao_id: arrematacaoId,
            status: novoStatus,
        }, function (res) {
            if (res.success) {
                alert(res.data.message);
                location.reload();
            } else {
                alert(res.data.message || 'Erro.');
                $btn.prop('disabled', false).text('Atualizar Status');
            }
        });
    });

    // Enviar notificação
    $(document).on('click', '#btn-enviar-notificacao', function () {
        const $btn = $(this);
        const arrematacaoId = $btn.data('id');

        if (!confirm('Enviar notificação ao arrematante?')) {
            return;
        }

        $btn.prop('disabled', true).text('Enviando...');

        $.post(ajaxUrl, {
            action: 'leilao_enviar_notificacao',
            nonce: nonce,
            arrematacao_id: arrematacaoId,
            tipo: 'lembrete',
        }, function (res) {
            alert(res.success ? res.data.message : (res.data.message || 'Erro.'));
            $btn.prop('disabled', false).text('📧 Enviar Notificação');
        });
    });

    // Aprovar documento
    $(document).on('click', '.btn-aprovar-doc', function () {
        const $btn = $(this);
        const docId = $btn.data('id');

        if (!confirm('Aprovar este documento?')) {
            return;
        }

        $btn.prop('disabled', true).text('...');

        $.post(ajaxUrl, {
            action: 'leilao_aprovar_documento',
            nonce: nonce,
            doc_id: docId,
        }, function (res) {
            if (res.success) {
                alert(res.data.message);
                location.reload();
            } else {
                alert(res.data.message || 'Erro.');
                $btn.prop('disabled', false).text('✓ Aprovar');
            }
        });
    });

    // Reprovar documento
    $(document).on('click', '.btn-reprovar-doc', function () {
        const $btn = $(this);
        const docId = $btn.data('id');

        const motivo = prompt('Informe o motivo da reprovação:');
        if (motivo === null) return;
        if (!motivo.trim()) {
            alert('Por favor, informe o motivo.');
            return;
        }

        $btn.prop('disabled', true).text('...');

        $.post(ajaxUrl, {
            action: 'leilao_reprovar_documento',
            nonce: nonce,
            doc_id: docId,
            motivo: motivo,
        }, function (res) {
            if (res.success) {
                alert(res.data.message);
                location.reload();
            } else {
                alert(res.data.message || 'Erro.');
                $btn.prop('disabled', false).text('✗ Reprovar');
            }
        });
    });

    /* ==============================
       ASSESSORIA Q4 - ADMIN
    ============================== */

    // Aprovar documentação e iniciar processo
    $(document).on('click', '.btn-aprovar-assessoria', function () {
        const $btn = $(this);
        const assessoriaId = $btn.data('id');

        if (!confirm('Aprovar documentação e iniciar o processo de regularização?')) {
            return;
        }

        $btn.prop('disabled', true).text('Processando...');

        $.post(ajaxUrl, {
            action: 'leilao_assessoria_aprovar_contrato',
            nonce: nonce,
            assessoria_id: assessoriaId,
        }, function (res) {
            if (res.success) {
                alert(res.data.message);
                location.reload();
            } else {
                alert(res.data.message || 'Erro.');
                $btn.prop('disabled', false).text('✅ Aprovar Documentação e Iniciar Processo');
            }
        });
    });

    // Aprovar documento de assessoria
    $(document).on('click', '.btn-aprovar-doc-assessoria', function () {
        const $btn = $(this);
        const docId = $btn.data('id');

        if (!confirm('Aprovar este documento?')) {
            return;
        }

        $btn.prop('disabled', true).text('...');

        $.post(ajaxUrl, {
            action: 'leilao_assessoria_aprovar_doc',
            nonce: nonce,
            doc_id: docId,
        }, function (res) {
            if (res.success) {
                alert('Documento aprovado!');
                location.reload();
            } else {
                alert(res.data.message || 'Erro.');
                $btn.prop('disabled', false).text('✓ Aprovar');
            }
        });
    });

    // Reprovar documento de assessoria
    $(document).on('click', '.btn-reprovar-doc-assessoria', function () {
        const $btn = $(this);
        const docId = $btn.data('id');

        const motivo = prompt('Informe o motivo da reprovação:');
        if (motivo === null) return;
        if (!motivo.trim()) {
            alert('Por favor, informe o motivo.');
            return;
        }

        $btn.prop('disabled', true).text('...');

        $.post(ajaxUrl, {
            action: 'leilao_assessoria_reprovar_doc',
            nonce: nonce,
            doc_id: docId,
            motivo: motivo,
        }, function (res) {
            if (res.success) {
                alert('Documento reprovado!');
                location.reload();
            } else {
                alert(res.data.message || 'Erro.');
                $btn.prop('disabled', false).text('✗ Reprovar');
            }
        });
    });

    // Atualizar etapa do processo
    $(document).on('click', '.btn-atualizar-etapa', function () {
        const $btn = $(this);
        const assessoriaId = $btn.data('assessoria');
        const etapaKey = $btn.data('etapa');
        const novoStatus = $btn.data('status') || 'concluido';

        const observacao = prompt('Observação (opcional):');
        if (observacao === null) return;

        $btn.prop('disabled', true).text('...');

        $.post(ajaxUrl, {
            action: 'leilao_assessoria_atualizar_etapa',
            nonce: nonce,
            assessoria_id: assessoriaId,
            etapa_key: etapaKey,
            status: novoStatus,
            observacao: observacao,
        }, function (res) {
            if (res.success) {
                alert(res.data.message);
                location.reload();
            } else {
                alert(res.data.message || 'Erro.');
                $btn.prop('disabled', false).text('Atualizar');
            }
        });
    });

    // Salvar análise IA
    $(document).on('click', '#btn-salvar-analise-ia', function () {
        const $btn = $(this);
        const assessoriaId = $btn.data('id');
        const analiseData = $('#analise_ia_data').val();
        const custasEstimadas = $('#custas_estimadas').val();
        const itbiEstimado = $('#itbi_estimado').val();

        $btn.prop('disabled', true).text('Salvando...');

        $.post(ajaxUrl, {
            action: 'leilao_assessoria_salvar_analise_ia',
            nonce: nonce,
            assessoria_id: assessoriaId,
            analise_data: analiseData,
            custas_estimadas: custasEstimadas,
            itbi_estimado: itbiEstimado,
        }, function (res) {
            if (res.success) {
                alert(res.data.message);
            } else {
                alert(res.data.message || 'Erro.');
            }
            $btn.prop('disabled', false).text('💾 Salvar Análise');
        });
    });

})(jQuery);
