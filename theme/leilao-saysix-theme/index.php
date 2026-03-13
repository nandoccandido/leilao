<?php get_header(); ?>

<div class="site-content">
    <?php if (is_front_page()): ?>

        <!-- ===== Seção IA de Triagem ===== -->
        <section class="ai-triage-section">
            <div class="ai-triage-inner">
                <div class="ai-triage-header">
                    <span class="ai-badge">✨ IA Assistente</span>
                    <h2>Encontre o imóvel ideal com ajuda da nossa inteligência artificial</h2>
                    <p>Diga o que procura — cidade, faixa de preço, tipo de imóvel — e nossa IA filtra os melhores resultados para você.</p>
                </div>

                <div class="ai-chat-box" id="ai-triage-chat">
                    <div class="ai-messages" id="ai-messages">
                        <div class="ai-message ai-bot">
                            <div class="ai-avatar">🤖</div>
                            <div class="ai-bubble">
                                Olá! Sou a IA do Qatar Leilões. Me diga o que você procura.<br>
                                <em>Ex: "Apartamento em São Paulo até 200 mil" ou "Casa com 3 quartos no Rio de Janeiro"</em>
                            </div>
                        </div>
                    </div>
                    <form class="ai-input-form" id="ai-triage-form">
                        <input type="text" id="ai-input" placeholder="Descreva o imóvel que você procura..." autocomplete="off" />
                        <button type="submit">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                        </button>
                    </form>
                </div>
            </div>
        </section>

        <!-- ===== Grid de Imóveis Aleatórios ===== -->
        <section class="imoveis-destaque">
            <div class="container">
                <div class="section-header">
                    <h2>Imóveis em Destaque</h2>
                    <a href="<?php echo home_url('/catalogo/'); ?>" class="ver-todos">
                        Ver todos →
                    </a>
                </div>

                <div class="imoveis-grid">
                    <?php
                    $imoveis = get_posts([
                        'post_type'      => 'imovel',
                        'posts_per_page' => 12,
                        'orderby'        => 'rand',
                        'post_status'    => 'publish',
                    ]);

                    if (!empty($imoveis)):
                        foreach ($imoveis as $imovel):
                            $iid            = $imovel->ID;
                            $thumb_url      = get_the_post_thumbnail_url($iid, 'medium') ?: '';
                            $tipo           = wp_get_post_terms($iid, 'tipo_imovel', ['fields' => 'names']);
                            $cidade         = wp_get_post_terms($iid, 'cidade_imovel', ['fields' => 'names']);
                            $estado         = wp_get_post_terms($iid, 'estado_imovel', ['fields' => 'names']);
                            $modalidade     = wp_get_post_terms($iid, 'modalidade_venda', ['fields' => 'names']);
                            $valor_min      = floatval(get_post_meta($iid, '_leilao_valor_minimo', true));
                            $valor_aval     = floatval(get_post_meta($iid, '_leilao_valor_avaliacao', true));
                            $status         = get_post_meta($iid, '_leilao_status', true) ?: 'agendado';
                            $fim            = get_post_meta($iid, '_leilao_fim', true);
                            $quartos        = get_post_meta($iid, '_imovel_quartos', true);
                            $area           = get_post_meta($iid, '_imovel_area_total', true);
                            $desconto       = $valor_aval > 0 ? round((1 - $valor_min / $valor_aval) * 100) : 0;
                    ?>
                        <a href="<?php echo get_permalink($iid); ?>" class="imovel-card">
                            <div class="imovel-card-img">
                                <?php if ($thumb_url): ?>
                                    <img src="<?php echo esc_url($thumb_url); ?>" alt="<?php echo esc_attr($imovel->post_title); ?>" loading="lazy" />
                                <?php else: ?>
                                    <div class="imovel-card-placeholder">🏠</div>
                                <?php endif; ?>

                                <?php if ($desconto > 0): ?>
                                    <span class="imovel-desconto">-<?php echo $desconto; ?>%</span>
                                <?php endif; ?>

                                <span class="imovel-status <?php echo $status; ?>">
                                    <?php echo $status === 'ativo' ? '🔴 Ao vivo' : ($status === 'agendado' ? '🕐 Em breve' : '✅ Encerrado'); ?>
                                </span>
                            </div>

                            <div class="imovel-card-body">
                                <div class="imovel-card-meta">
                                    <?php if (!empty($tipo)): ?>
                                        <span class="meta-tag"><?php echo esc_html($tipo[0]); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($modalidade)): ?>
                                        <span class="meta-tag modalidade"><?php echo esc_html($modalidade[0]); ?></span>
                                    <?php endif; ?>
                                </div>

                                <h3 class="imovel-card-title"><?php echo esc_html($imovel->post_title); ?></h3>

                                <div class="imovel-card-location">
                                    📍 <?php echo !empty($cidade) ? esc_html($cidade[0]) : ''; ?><?php echo !empty($estado) ? ' - ' . esc_html($estado[0]) : ''; ?>
                                </div>

                                <div class="imovel-card-features">
                                    <?php if ($quartos): ?>
                                        <span>🛏 <?php echo $quartos; ?> quartos</span>
                                    <?php endif; ?>
                                    <?php if ($area): ?>
                                        <span>📐 <?php echo $area; ?> m²</span>
                                    <?php endif; ?>
                                </div>

                                <div class="imovel-card-price">
                                    <?php if ($valor_aval > 0 && $valor_aval != $valor_min): ?>
                                        <span class="price-old">R$ <?php echo number_format($valor_aval, 2, ',', '.'); ?></span>
                                    <?php endif; ?>
                                    <span class="price-current">R$ <?php echo number_format($valor_min, 2, ',', '.'); ?></span>
                                </div>

                                <button type="button" class="btn-contatar"
                                        data-imovel-id="<?php echo $iid; ?>"
                                        data-imovel-title="<?php echo esc_attr($imovel->post_title); ?>"
                                        onclick="event.preventDefault(); event.stopPropagation(); abrirContatoModal(this);">
                                    💬 CONTATAR
                                </button>
                            </div>
                        </a>
                    <?php
                        endforeach;
                    else:
                    ?>
                        <div class="imoveis-empty">
                            <p>🏗️ Nenhum imóvel cadastrado ainda. Em breve teremos oportunidades incríveis!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

    <?php else: ?>
        <div class="container section">
            <?php
            while (have_posts()):
                the_post();
                the_content();
            endwhile;
            ?>
        </div>
    <?php endif; ?>
</div>

<?php get_footer(); ?>
