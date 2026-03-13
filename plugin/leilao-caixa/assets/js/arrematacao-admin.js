/**
 * Leilão Caixa - Arrematação Admin JS
 * Gerenciamento completo: Abas, Documentos, Status, Notificações, Timeline
 */
(function($) {
    'use strict';

    /* ==================================================================
       Utilidades
       ================================================================== */

    function showMsg($el, msg, type) {
        var cls = type === 'ok' ? 'color:#5cb85c' : 'color:#d9534f';
        $el.html('<span style="' + cls + ';font-weight:bold;">' + msg + '</span>');
    }

    function getArrId() {
        return $('.arr-metabox-wrap').data('arr-id');
    }

    /* ==================================================================
       Abas
       ================================================================== */

    $(document).on('click', '.arr-tab', function() {
        var tab = $(this).data('tab');
        $('.arr-tab').removeClass('active');
        $(this).addClass('active');
        $('.arr-tab-content').removeClass('active');
        $('#arr-tab-' + tab).addClass('active');
    });

    /* ==================================================================
       Confirmar arrematante
       ================================================================== */

    $(document).on('click', '#btn-confirmar-arrematante', function() {
        var $btn = $(this);
        var imovelId = $btn.data('imovel');
        var $result = $('#arrematacao-result');

        if (!confirm('Confirmar arrematante e iniciar fluxo de documentação?')) return;

        $btn.prop('disabled', true).text('Processando...');

        $.post(leilaoAdmin.ajaxUrl, {
            action: 'leilao_confirmar_arrematante',
            nonce: leilaoAdmin.nonce,
            imovel_id: imovelId,
            tipo_pessoa: $('input[name="arr_tipo_pessoa"]:checked').val(),
            prazo_dias: $('input[name="arr_prazo_dias"]').val(),
            observacoes: $('textarea[name="arr_observacoes"]').val()
        }, function(resp) {
            if (resp.success) {
                $result.html('<div class="notice notice-success"><p>' + resp.data.message + '</p></div>');
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                $result.html('<div class="notice notice-error"><p>' + resp.data.message + '</p></div>');
                $btn.prop('disabled', false).text('✅ Confirmar Arrematante e Iniciar Fluxo');
            }
        }).fail(function() {
            $result.html('<div class="notice notice-error"><p>Erro de conexão.</p></div>');
            $btn.prop('disabled', false).text('✅ Confirmar Arrematante e Iniciar Fluxo');
        });
    });

    /* ==================================================================
       Atualizar status
       ================================================================== */

    $(document).on('click', '#btn-atualizar-arrematacao', function() {
        var $btn = $(this);
        var arrId = $btn.data('id');
        var novoStatus = $('#arr-novo-status').val();
        var $result = $('#arr-update-result');

        $btn.prop('disabled', true);

        $.post(leilaoAdmin.ajaxUrl, {
            action: 'leilao_atualizar_arrematacao',
            nonce: leilaoAdmin.nonce,
            arrematacao_id: arrId,
            status: novoStatus
        }, function(resp) {
            if (resp.success) {
                showMsg($result, '✓ ' + resp.data.message, 'ok');
                $('.leilao-arr-badge').first()
                    .removeClass().addClass('leilao-arr-badge status-' + resp.data.status)
                    .text(resp.data.label);
            } else {
                showMsg($result, resp.data.message, 'err');
            }
            $btn.prop('disabled', false);
        }).fail(function() {
            showMsg($result, 'Erro de conexão.', 'err');
            $btn.prop('disabled', false);
        });
    });

    /* ==================================================================
       Upload de documento
       ================================================================== */

    $(document).on('click', '#btn-upload-doc', function() {
        var $btn = $(this);
        var arrId = getArrId();
        var $result = $('#arr-upload-result');
        var fileInput = $('#arr-doc-file')[0];

        if (!fileInput.files.length) {
            showMsg($result, 'Selecione um arquivo.', 'err');
            return;
        }

        var formData = new FormData();
        formData.append('action', 'leilao_upload_documento');
        formData.append('nonce', leilaoAdmin.nonce);
        formData.append('arrematacao_id', arrId);
        formData.append('tipo', $('#arr-doc-tipo').val());
        formData.append('documento', fileInput.files[0]);

        $btn.prop('disabled', true).text('Enviando...');

        $.ajax({
            url: leilaoAdmin.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(resp) {
                if (resp.success) {
                    showMsg($result, '✓ ' + resp.data.message, 'ok');
                    setTimeout(function() { location.reload(); }, 1200);
                } else {
                    showMsg($result, resp.data.message, 'err');
                    $btn.prop('disabled', false).text('📤 Enviar');
                }
            },
            error: function() {
                showMsg($result, 'Erro de conexão.', 'err');
                $btn.prop('disabled', false).text('📤 Enviar');
            }
        });
    });

    /* ==================================================================
       Revisar documento (aprovar / reprovar)
       ================================================================== */

    $(document).on('click', '.btn-doc-review', function() {
        var $btn = $(this);
        var docId = $btn.data('doc');
        var acao = $btn.data('acao');
        var observacao = '';

        if (acao === 'reprovado') {
            observacao = prompt('Motivo da reprovação (opcional):') || '';
        }

        var actionText = acao === 'aprovado' ? 'aprovar' : 'reprovar';
        if (!confirm('Deseja ' + actionText + ' este documento?')) return;

        $btn.prop('disabled', true);

        $.post(leilaoAdmin.ajaxUrl, {
            action: 'leilao_revisar_documento',
            nonce: leilaoAdmin.nonce,
            doc_id: docId,
            acao: acao,
            observacao: observacao
        }, function(resp) {
            if (resp.success) {
                location.reload();
            } else {
                alert(resp.data.message);
                $btn.prop('disabled', false);
            }
        }).fail(function() {
            alert('Erro de conexão.');
            $btn.prop('disabled', false);
        });
    });

    /* ==================================================================
       Excluir documento
       ================================================================== */

    $(document).on('click', '.btn-doc-delete', function() {
        var $btn = $(this);
        var docId = $btn.data('doc');

        if (!confirm('Tem certeza que deseja excluir este documento? Esta ação é irreversível.')) return;

        $btn.prop('disabled', true);

        $.post(leilaoAdmin.ajaxUrl, {
            action: 'leilao_excluir_documento',
            nonce: leilaoAdmin.nonce,
            doc_id: docId
        }, function(resp) {
            if (resp.success) {
                $('#doc-row-' + docId).fadeOut(300, function() { $(this).remove(); });
            } else {
                alert(resp.data.message);
                $btn.prop('disabled', false);
            }
        }).fail(function() {
            alert('Erro de conexão.');
            $btn.prop('disabled', false);
        });
    });

    /* ==================================================================
       Templates de notificação
       ================================================================== */

    var templates = {
        docs_pendentes: "Olá {nome},\n\nGostaríamos de lembrá-lo(a) que existem documentos pendentes de envio referentes à sua arrematação do imóvel \"{imovel}\".\n\nO prazo para envio se encerra em {prazo}. Por favor, providencie o envio o mais breve possível.\n\nAtenciosamente,",
        docs_reprovados: "Olá {nome},\n\nInformamos que alguns documentos enviados foram reprovados na análise. Por favor, acesse o sistema para verificar os motivos e reenviar os documentos corrigidos.\n\nImóvel: {imovel}\n\nAtenciosamente,",
        aprovacao: "Olá {nome},\n\nTemos o prazer de informar que toda a documentação referente à sua arrematação do imóvel \"{imovel}\" foi aprovada!\n\nEm breve entraremos em contato com os próximos passos para conclusão do processo.\n\nParabéns e obrigado,",
        prazo_expirado: "Olá {nome},\n\nInformamos que o prazo para envio de documentação referente à arrematação do imóvel \"{imovel}\" expirou.\n\nPor favor, entre em contato conosco urgentemente para regularizar a situação.\n\nAtenciosamente,",
        concluido: "Olá {nome},\n\nInformamos que o processo de arrematação do imóvel \"{imovel}\" foi concluído com sucesso!\n\nTodos os documentos foram verificados e aprovados. Parabéns pela aquisição!\n\nAtenciosamente,"
    };

    $(document).on('change', '#arr-notif-template', function() {
        var key = $(this).val();
        if (key && templates[key]) {
            $('#arr-notif-mensagem').val(templates[key]);
        }
    });

    /* ==================================================================
       Enviar notificação
       ================================================================== */

    $(document).on('click', '#btn-enviar-notificacao', function() {
        var $btn = $(this);
        var $result = $('#arr-notif-result');
        var arrId = getArrId();
        var assunto = $('#arr-notif-assunto').val();
        var mensagem = $('#arr-notif-mensagem').val();

        if (!assunto || !mensagem) {
            showMsg($result, 'Preencha assunto e mensagem.', 'err');
            return;
        }

        if (!confirm('Enviar e-mail ao arrematante?')) return;

        $btn.prop('disabled', true).text('Enviando...');

        $.post(leilaoAdmin.ajaxUrl, {
            action: 'leilao_enviar_notificacao',
            nonce: leilaoAdmin.nonce,
            arrematacao_id: arrId,
            assunto: assunto,
            mensagem: mensagem
        }, function(resp) {
            if (resp.success) {
                showMsg($result, '✓ ' + resp.data.message, 'ok');
            } else {
                showMsg($result, resp.data.message, 'err');
            }
            $btn.prop('disabled', false).text('📧 Enviar E-mail');
        }).fail(function() {
            showMsg($result, 'Erro de conexão.', 'err');
            $btn.prop('disabled', false).text('📧 Enviar E-mail');
        });
    });

})(jQuery);
