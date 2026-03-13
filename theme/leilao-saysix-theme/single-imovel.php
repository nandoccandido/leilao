<?php
/**
 * Single Imóvel - Detalhe com painel de lances
 */
get_header();

while (have_posts()): the_post();

$id              = get_the_ID();
$status          = get_post_meta($id, '_leilao_status', true) ?: 'agendado';
$inicio          = get_post_meta($id, '_leilao_inicio', true);
$fim             = get_post_meta($id, '_leilao_fim', true);
$valor_avaliacao = floatval(get_post_meta($id, '_leilao_valor_avaliacao', true));
$valor_minimo    = floatval(get_post_meta($id, '_leilao_valor_minimo', true));
$incremento      = floatval(get_post_meta($id, '_leilao_incremento', true)) ?: 500;
$endereco        = get_post_meta($id, '_imovel_endereco', true);
$bairro          = get_post_meta($id, '_imovel_bairro', true);
$area_total      = get_post_meta($id, '_imovel_area_total', true);
$area_priv       = get_post_meta($id, '_imovel_area_privativa', true);
$quartos         = get_post_meta($id, '_imovel_quartos', true);
$garagem         = get_post_meta($id, '_imovel_garagem', true);
$matricula       = get_post_meta($id, '_imovel_matricula', true);
$edital          = get_post_meta($id, '_imovel_edital', true);

$tipo       = wp_get_post_terms($id, 'tipo_imovel', ['fields' => 'names']);
$estado     = wp_get_post_terms($id, 'estado_imovel', ['fields' => 'names']);
$cidade     = wp_get_post_terms($id, 'cidade_imovel', ['fields' => 'names']);
$modalidade = wp_get_post_terms($id, 'modalidade_venda', ['fields' => 'names']);

$maior_lance = class_exists('Leilao_Bidding') ? Leilao_Bidding::get_maior_lance($id) : null;
$lance_atual = $maior_lance ? $maior_lance['valor'] : $valor_minimo;
$lance_min   = $maior_lance ? ($maior_lance['valor'] + $incremento) : $valor_minimo;

// Galeria
$gallery = get_attached_media('image', $id);
$thumb   = get_the_post_thumbnail_url($id, 'large');
?>

<div class="container section leilao-single">
    <!-- Breadcrumb -->
    <nav style="margin-bottom:20px;font-size:13px;color:#999">
        <a href="<?php echo home_url(); ?>">Início</a> ›
        <a href="<?php echo home_url('/catalogo/'); ?>">Catálogo</a> ›
        <?php the_title(); ?>
    </nav>

    <div class="leilao-single-header">
        <!-- Galeria -->
        <div class="leilao-gallery">
            <div class="leilao-gallery-main">
                <?php if ($thumb): ?>
                    <img src="<?php echo esc_url($thumb); ?>" alt="<?php the_title_attribute(); ?>" />
                <?php else: ?>
                    <div style="height:400px;background:#eee;display:flex;align-items:center;justify-content:center;color:#999;font-size:48px">🏠</div>
                <?php endif; ?>
            </div>
            <?php if (!empty($gallery)): ?>
                <div class="leilao-gallery-thumbs">
                    <?php foreach ($gallery as $img): ?>
                        <img src="<?php echo wp_get_attachment_image_url($img->ID, 'thumbnail'); ?>"
                             data-full="<?php echo wp_get_attachment_url($img->ID); ?>"
                             alt="" />
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Painel de Lances -->
        <div class="leilao-bid-panel" data-imovel-id="<?php echo $id; ?>" data-incremento="<?php echo $incremento; ?>">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
                <h3 style="margin:0">Leilão</h3>
                <span class="leilao-badge <?php echo $status; ?>"><?php echo ucfirst($status); ?></span>
            </div>

            <!-- Timer -->
            <?php if ($fim && $status === 'ativo'): ?>
                <div class="leilao-bid-timer" data-end="<?php echo $fim; ?>">
                    Calculando...
                </div>
            <?php endif; ?>

            <!-- Maior Lance -->
            <div class="leilao-bid-current">
                <small><?php echo $maior_lance ? 'Maior Lance' : 'Lance Mínimo'; ?></small>
                <span class="big-value leilao-bid-value">
                    R$ <?php echo number_format($lance_atual, 2, ',', '.'); ?>
                </span>
                <?php if ($maior_lance): ?>
                    <small class="leilao-bid-user"><?php echo esc_html(substr($maior_lance['nome_usuario'], 0, 3) . '***'); ?></small>
                <?php endif; ?>
            </div>

            <!-- Info -->
            <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
                <div class="leilao-bid-status" style="flex:1">
                    <span class="status-label">Avaliação</span>
                    <span class="status-value">R$ <?php echo number_format($valor_avaliacao, 2, ',', '.'); ?></span>
                </div>
                <div class="leilao-bid-status" style="flex:1">
                    <span class="status-label">Lances</span>
                    <span class="status-value leilao-total-lances"><?php echo get_post_meta($id, '_leilao_total_lances', true) ?: 0; ?></span>
                </div>
            </div>

            <?php if ($status === 'ativo'): ?>
                <!-- Form de Lance -->
                <form id="form-lance" class="leilao-bid-form">
                    <label>Seu lance (mínimo: R$ <?php echo number_format($lance_min, 2, ',', '.'); ?>)</label>
                    <input type="number" id="lance-valor" step="0.01"
                           min="<?php echo $lance_min; ?>"
                           value="<?php echo $lance_min; ?>"
                           placeholder="R$ <?php echo number_format($lance_min, 2, ',', '.'); ?>" />

                    <div class="leilao-bid-quick">
                        <button type="button" data-increment="0">Mínimo</button>
                        <button type="button" data-increment="<?php echo $incremento; ?>">+<?php echo number_format($incremento, 0, ',', '.'); ?></button>
                        <button type="button" data-increment="<?php echo $incremento * 2; ?>">+<?php echo number_format($incremento * 2, 0, ',', '.'); ?></button>
                        <button type="button" data-increment="<?php echo $incremento * 5; ?>">+<?php echo number_format($incremento * 5, 0, ',', '.'); ?></button>
                    </div>

                    <div class="leilao-msg-box" id="lance-msg"></div>

                    <?php if (is_user_logged_in()): ?>
                        <button type="submit" class="leilao-btn leilao-btn-accent leilao-btn-full" style="font-size:18px;padding:16px">
                            🔨 Dar Lance
                        </button>
                    <?php else: ?>
                        <a href="<?php echo home_url('/login-leilao/'); ?>" class="leilao-btn leilao-btn-full" style="text-align:center">
                            Entrar para dar lance
                        </a>
                    <?php endif; ?>
                </form>

                <!-- Auto Lance -->
                <?php if (is_user_logged_in()): ?>
                    <div class="leilao-auto-lance">
                        <h4>⚡ Lance Automático</h4>
                        <form id="form-auto-lance">
                            <input type="number" id="auto-lance-max" step="0.01"
                                   placeholder="Valor máximo (ex: 200000)" />
                            <input type="number" id="auto-lance-inc" step="0.01"
                                   value="<?php echo $incremento; ?>"
                                   placeholder="Incremento" />
                            <div class="leilao-msg-box" id="auto-lance-msg"></div>
                            <div style="display:flex;gap:8px">
                                <button type="submit" class="leilao-btn leilao-btn-sm leilao-btn-success" style="flex:1">Ativar</button>
                                <button type="button" id="btn-cancelar-auto" class="leilao-btn leilao-btn-sm leilao-btn-outline" style="flex:1">Cancelar</button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            <?php elseif ($status === 'encerrado'): ?>
                <div style="text-align:center;padding:20px;background:#f8f9fa;border-radius:8px">
                    <p style="font-size:18px;font-weight:700;color:#c0392b">Leilão Encerrado</p>
                    <?php if ($maior_lance): ?>
                        <p>Arrematado por R$ <?php echo number_format($maior_lance['valor'], 2, ',', '.'); ?></p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div style="text-align:center;padding:20px;background:#fff3e0;border-radius:8px">
                    <p style="font-size:18px;font-weight:700;color:#e67e22">Leilão Agendado</p>
                    <?php if ($inicio): ?>
                        <p>Início: <?php echo date('d/m/Y H:i', strtotime($inicio)); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Histórico -->
            <div class="leilao-lances-history" style="margin-top:16px">
                <!-- Preenchido via JS polling -->
            </div>
        </div>
    </div>

    <!-- Detalhes do imóvel -->
    <div style="margin-top:32px">
        <h2 style="margin-bottom:16px"><?php the_title(); ?></h2>

        <div style="margin-bottom:16px;color:#666;font-size:14px">
            <?php if (!empty($cidade)): ?>📍 <?php echo esc_html($cidade[0]); ?><?php endif; ?>
            <?php if (!empty($estado)): ?> - <?php echo esc_html($estado[0]); ?><?php endif; ?>
            <?php if ($endereco): ?> | <?php echo esc_html($endereco); ?><?php endif; ?>
            <?php if ($bairro): ?>, <?php echo esc_html($bairro); ?><?php endif; ?>
        </div>

        <?php if (!empty($tipo) || !empty($modalidade)): ?>
            <div style="display:flex;gap:8px;margin-bottom:24px;flex-wrap:wrap">
                <?php if (!empty($tipo)): ?>
                    <span class="leilao-badge" style="background:#2980b9;position:static"><?php echo esc_html($tipo[0]); ?></span>
                <?php endif; ?>
                <?php if (!empty($modalidade)): ?>
                    <span class="leilao-badge" style="background:#e67e22;position:static"><?php echo esc_html($modalidade[0]); ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="leilao-details-grid">
            <?php if ($area_total): ?>
                <div class="leilao-detail-item">
                    <div>
                        <strong>Área Total</strong>
                        <span><?php echo esc_html($area_total); ?> m²</span>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($area_priv): ?>
                <div class="leilao-detail-item">
                    <div>
                        <strong>Área Privativa</strong>
                        <span><?php echo esc_html($area_priv); ?> m²</span>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($quartos): ?>
                <div class="leilao-detail-item">
                    <div>
                        <strong>Quartos</strong>
                        <span><?php echo esc_html($quartos); ?></span>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($garagem): ?>
                <div class="leilao-detail-item">
                    <div>
                        <strong>Garagem</strong>
                        <span><?php echo esc_html($garagem); ?> vaga(s)</span>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($matricula): ?>
                <div class="leilao-detail-item">
                    <div>
                        <strong>Matrícula</strong>
                        <span><?php echo esc_html($matricula); ?></span>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($valor_avaliacao): ?>
                <div class="leilao-detail-item">
                    <div>
                        <strong>Valor de Avaliação</strong>
                        <span>R$ <?php echo number_format($valor_avaliacao, 2, ',', '.'); ?></span>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div style="display:flex;gap:12px;margin:24px 0;flex-wrap:wrap">
            <?php if ($edital): ?>
                <a href="<?php echo esc_url($edital); ?>" target="_blank" class="leilao-btn leilao-btn-outline">
                    📄 Ver Edital Completo
                </a>
            <?php endif; ?>

            <button type="button" class="leilao-btn leilao-btn-accent btn-contatar-single"
                    data-imovel-id="<?php echo $id; ?>"
                    data-imovel-title="<?php echo esc_attr(get_the_title()); ?>"
                    onclick="abrirContatoModal(this);">
                💬 CONTATAR
            </button>
        </div>

        <!-- Conteúdo livre -->
        <div style="margin-top:32px;line-height:1.8;color:#444">
            <?php the_content(); ?>
        </div>
    </div>
</div>

<?php endwhile; ?>

<?php get_footer(); ?>
