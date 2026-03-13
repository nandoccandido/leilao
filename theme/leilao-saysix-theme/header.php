<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Leilão de imóveis da Caixa Econômica Federal. Arremate imóveis com descontos de até 50%.">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏠</text></svg>">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="site-header">
    <div class="header-inner">
        <!-- Logo -->
        <a href="<?php echo home_url(); ?>" class="site-logo">
            🏠 Qatar<span class="accent">Leilões</span>
        </a>

        <!-- Busca -->
        <form class="header-search" action="<?php echo home_url('/catalogo/'); ?>" method="get">
            <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" name="busca" placeholder="Buscar imóveis por cidade, estado ou tipo..." autocomplete="off" />
        </form>

        <!-- Login / Cadastro -->
        <nav class="header-nav">
            <?php if (is_user_logged_in()): ?>
                <a href="<?php echo home_url('/painel-arrematante/'); ?>" class="btn-header-outline">Meu Painel</a>
            <?php else: ?>
                <a href="<?php echo home_url('/login-leilao/'); ?>" class="btn-header-outline">Entrar</a>
                <a href="<?php echo home_url('/registro-leilao/'); ?>" class="btn-header">Criar Conta</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
