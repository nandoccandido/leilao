<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Leilão de imóveis da Caixa Econômica Federal. Arremate imóveis com descontos de até 50%.">
    <link rel="icon" type="image/svg+xml" href="<?php echo get_template_directory_uri(); ?>/assets/img/favicon.svg">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="header">
    <div class="container header__inner">
        <!-- Logo -->
        <a href="<?php echo home_url('/'); ?>" class="header__logo" aria-label="Qatar Leilões – Página inicial">
            <img src="<?php echo get_template_directory_uri(); ?>/assets/img/logo-white.svg"
                 alt="Qatar Leilões"
                 height="36" loading="eager">
        </a>

        <!-- Busca -->
        <div class="header__busca">
            <svg class="header__busca-icone" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="text" class="header__busca-input" placeholder="Buscar imóveis por cidade, estado ou tipo...">
        </div>

        <!-- Navegação -->
        <nav class="header__nav">
            <div class="dropdown">
                <button class="header__nav-link" onclick="this.parentElement.classList.toggle('ativo')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    Consultas
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:12px;height:12px"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="dropdown__menu">
                    <a href="<?php echo home_url('/consultas/#certidao'); ?>" class="dropdown__item">📜 Certidão de Matrícula</a>
                    <a href="<?php echo home_url('/consultas/#pesquisa_bens'); ?>" class="dropdown__item">🔍 Pesquisa de Bens</a>
                    <a href="<?php echo home_url('/consultas/#matricula_online'); ?>" class="dropdown__item">🏛️ Matrícula Online</a>
                    <a href="<?php echo home_url('/consultas/#certidao_onus'); ?>" class="dropdown__item">⚖️ Certidão de Ônus Reais</a>
                </div>
            </div>

            <div class="header__auth-btns" id="headerAuthBtns">
                <button class="btn btn--secundario" onclick="abrirAuth('login')">Entrar</button>
                <button class="btn btn--primario" onclick="abrirAuth('cadastro')">Criar Conta</button>
            </div>
            <div class="header__perfil" id="headerPerfil" style="display:none">
                <button class="header__perfil-btn" onclick="this.parentElement.classList.toggle('aberto')">
                    <span class="header__perfil-avatar" id="headerAvatar">U</span>
                    <span class="header__perfil-nome" id="headerNome">Usuário</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="header__perfil-dropdown">
                    <a href="<?php echo home_url('/painel-arrematante/'); ?>" class="header__perfil-item">📊 Meu Painel</a>
                    <a href="<?php echo home_url('/painel-arrematante/'); ?>" class="header__perfil-item">👤 Meu Perfil</a>
                    <a href="#" class="header__perfil-item" onclick="fazerLogout(event)">🚪 Sair</a>
                </div>
            </div>
        </nav>

        <button class="header__menu-toggle" onclick="this.classList.toggle('ativo');document.getElementById('navMobileWP').classList.toggle('ativo')">
            <span></span>
        </button>
    </div>
</header>

<!-- Nav Mobile -->
<div class="header__nav-mobile" id="navMobileWP">
    <div class="header__busca">
        <svg class="header__busca-icone" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        <input type="text" class="header__busca-input" placeholder="Buscar imóveis ou veículos...">
    </div>
    <a href="<?php echo home_url('/consultas/'); ?>" class="header__nav-link">🔍 Consultas</a>
    <div id="mobileAuthBtns">
        <a href="#" class="header__nav-link" onclick="abrirAuth('login')">Entrar</a>
        <a href="#" class="header__nav-link" onclick="abrirAuth('cadastro')">Criar Conta</a>
    </div>
    <div id="mobilePerfil" style="display:none">
        <a href="<?php echo home_url('/painel-arrematante/'); ?>" class="header__nav-link">📊 Meu Painel</a>
        <a href="#" class="header__nav-link" onclick="fazerLogout(event)">🚪 Sair</a>
    </div>
</div>
