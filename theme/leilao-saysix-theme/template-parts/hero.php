<?php
/**
 * Template: Hero section da home
 */
$stats = function_exists('leilao_get_stats') ? leilao_get_stats() : ['imoveis' => 0, 'ativos' => 0, 'users' => 0];
?>

<section class="hero-section">
    <div class="container">
        <h1>Imóveis da Caixa<br>com até <span style="color:#e67e22">50% de desconto</span></h1>
        <p>Arremate imóveis da Caixa Econômica Federal com segurança e transparência. Lance ao vivo, acompanhe em tempo real.</p>
        <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
            <a href="<?php echo home_url('/catalogo/'); ?>" class="leilao-btn leilao-btn-accent" style="font-size:18px;padding:16px 40px">
                Ver Imóveis em Leilão
            </a>
            <a href="<?php echo home_url('/registro-leilao/'); ?>" class="leilao-btn leilao-btn-outline" style="font-size:18px;padding:16px 40px;border-color:#fff;color:#fff">
                Criar Conta Grátis
            </a>
        </div>
        <div class="hero-stats">
            <div class="hero-stat">
                <span class="number"><?php echo $stats['imoveis']; ?></span>
                <span class="label">Imóveis Cadastrados</span>
            </div>
            <div class="hero-stat">
                <span class="number"><?php echo $stats['ativos']; ?></span>
                <span class="label">Leilões Ativos</span>
            </div>
            <div class="hero-stat">
                <span class="number"><?php echo $stats['users']; ?></span>
                <span class="label">Arrematantes</span>
            </div>
        </div>
    </div>
</section>
