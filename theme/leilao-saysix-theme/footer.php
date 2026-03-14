<!-- ===== Modal de Contato ===== -->
<div class="contato-modal-overlay" id="contatoModalOverlay">
    <div class="contato-modal">
        <button class="contato-modal-close" id="contatoModalClose" aria-label="Fechar">&times;</button>
        <div class="contato-modal-header">
            <h3>📩 Fale conosco sobre este imóvel</h3>
            <p class="contato-modal-imovel-title" id="contatoImovelTitle"></p>
        </div>
        <form class="contato-modal-form" id="contatoForm">
            <input type="hidden" id="contatoImovelId" name="imovel_id" value="" />
            <div class="contato-field">
                <label for="contatoNome">Nome completo *</label>
                <input type="text" id="contatoNome" name="nome" required placeholder="Seu nome" />
            </div>
            <div class="contato-field-row">
                <div class="contato-field">
                    <label for="contatoEmail">E-mail *</label>
                    <input type="email" id="contatoEmail" name="email" required placeholder="seu@email.com" />
                </div>
                <div class="contato-field">
                    <label for="contatoTelefone">Telefone / WhatsApp</label>
                    <input type="tel" id="contatoTelefone" name="telefone" placeholder="(00) 00000-0000" />
                </div>
            </div>
            <div class="contato-field">
                <label for="contatoMensagem">Mensagem *</label>
                <textarea id="contatoMensagem" name="mensagem" rows="4" required placeholder="Escreva sua dúvida, proposta ou interesse..."></textarea>
            </div>
            <div class="contato-msg" id="contatoMsg"></div>
            <button type="submit" class="contato-btn-enviar" id="contatoBtnEnviar">
                Enviar mensagem
            </button>
        </form>
    </div>
</div>

<footer class="site-footer">
    <div class="container">
        <div class="footer-inner">
            <div class="footer-brand">
                🏠 Qatar<span class="accent">Leilões</span>
                <span class="footer-tagline">Imóveis da Caixa com os melhores descontos.</span>
            </div>

            <div class="footer-social">
                <a href="https://instagram.com/qatarleiloes" target="_blank" rel="noopener" aria-label="Instagram" title="Instagram">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
                </a>
                <a href="https://facebook.com/qatarleiloes" target="_blank" rel="noopener" aria-label="Facebook" title="Facebook">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
                </a>
                <a href="https://youtube.com/@qatarleiloes" target="_blank" rel="noopener" aria-label="YouTube" title="YouTube">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33A2.78 2.78 0 0 0 3.4 19.1c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.25 29 29 0 0 0-.46-5.33z"/><polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02"/></svg>
                </a>
                <a href="https://tiktok.com/@qatarleiloes" target="_blank" rel="noopener" aria-label="TikTok" title="TikTok">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-2.88 2.5 2.89 2.89 0 0 1 0-5.78 2.92 2.92 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 3 15.57 6.33 6.33 0 0 0 9.37 22a6.33 6.33 0 0 0 6.33-6.33V9.13a8.16 8.16 0 0 0 3.89.96V6.69z"/></svg>
                </a>
                <a href="https://wa.me/5500000000000" target="_blank" rel="noopener" aria-label="WhatsApp" title="WhatsApp">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M17.47 14.38c-.29-.15-1.71-.84-1.97-.94-.27-.1-.46-.15-.66.15s-.75.94-.92 1.13-.34.22-.63.07a7.93 7.93 0 0 1-2.34-1.44 8.76 8.76 0 0 1-1.62-2.01c-.17-.29 0-.45.13-.59.12-.13.29-.34.43-.51s.19-.29.29-.49.05-.36-.02-.51-.66-1.58-.9-2.16c-.24-.57-.48-.49-.66-.5h-.56a1.07 1.07 0 0 0-.78.37 3.29 3.29 0 0 0-1.02 2.44c0 1.44 1.05 2.83 1.2 3.02s2.07 3.17 5.02 4.44c.7.3 1.25.48 1.68.62.7.22 1.34.19 1.85.12.56-.08 1.71-.7 1.95-1.37s.24-1.26.17-1.38c-.07-.11-.27-.18-.56-.32zM12.05 21.5a9.3 9.3 0 0 1-4.74-1.3l-.34-.2-3.53.93.95-3.46-.22-.35A9.28 9.28 0 0 1 2.7 12 9.35 9.35 0 0 1 12.05 2.65 9.35 9.35 0 0 1 21.4 12 9.35 9.35 0 0 1 12.05 21.35v.15zM12.05.5A11.47 11.47 0 0 0 1.96 17.66L0 24l6.5-1.7A11.5 11.5 0 1 0 12.05.5z"/></svg>
                </a>
            </div>

            <div class="footer-copy">
                &copy; <?php echo date('Y'); ?> Qatar Leilões &middot; contato@qatarleiloes.com.br
            </div>
        </div>
    </div>
</footer>

<?php wp_footer(); ?>

<!-- Toast Container -->
<div id="toastContainer" style="position:fixed;top:20px;right:20px;z-index:99999;display:flex;flex-direction:column;gap:10px"></div>

<!-- ===== Modal de Autenticação ===== -->
<div class="auth-overlay" id="authOverlay" style="display:none" onclick="if(event.target===this)fecharAuth()">
    <div class="auth-modal">
        <div class="auth-modal__header">
            <h2 class="auth-modal__titulo" id="authTitulo">Entrar</h2>
            <button class="auth-modal__fechar" onclick="fecharAuth()">✕</button>
        </div>
        <div class="auth-modal__corpo">
            <div class="auth-tabs">
                <button class="auth-tab ativo" onclick="trocarAuthTab('login', this)">Entrar</button>
                <button class="auth-tab" onclick="trocarAuthTab('cadastro', this)">Criar Conta</button>
            </div>

            <!-- LOGIN -->
            <form id="formLogin" onsubmit="return handleLogin(event)">
                <div class="auth-form__grupo">
                    <label class="auth-form__label">E-mail</label>
                    <input type="email" id="loginEmail" class="auth-form__input" placeholder="seu@email.com" required>
                </div>
                <div class="auth-form__grupo">
                    <label class="auth-form__label">Senha</label>
                    <input type="password" id="loginSenha" class="auth-form__input" placeholder="••••••••" required>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
                    <div class="auth-form__checkbox">
                        <input type="checkbox" id="lembrar"><label for="lembrar">Lembrar-me</label>
                    </div>
                    <a href="#" class="auth-form__link">Esqueci a senha</a>
                </div>
                <button type="submit" class="btn btn--primario btn--full btn--lg">Entrar</button>
            </form>

            <!-- CADASTRO -->
            <form id="formCadastro" style="display:none" onsubmit="return handleCadastro(event)">
                <div class="auth-form__grupo">
                    <label class="auth-form__label">Nome completo</label>
                    <input type="text" id="cadNome" class="auth-form__input" placeholder="Seu nome" required>
                </div>
                <div class="auth-form__grupo">
                    <label class="auth-form__label">CPF</label>
                    <input type="text" id="cadCPF" class="auth-form__input" placeholder="000.000.000-00" required>
                </div>
                <div class="auth-form__grupo">
                    <label class="auth-form__label">E-mail</label>
                    <input type="email" id="cadEmail" class="auth-form__input" placeholder="seu@email.com" required>
                </div>
                <div class="auth-form__grupo">
                    <label class="auth-form__label">Senha</label>
                    <input type="password" id="cadSenha" class="auth-form__input" placeholder="Mínimo 8 caracteres" required>
                </div>
                <div class="auth-form__checkbox" style="margin-bottom:16px">
                    <input type="checkbox" id="termos" required><label for="termos">Aceito os <a href="#" class="auth-form__link">Termos de Uso</a></label>
                </div>
                <button type="submit" class="btn btn--primario btn--full btn--lg">Criar Conta</button>
            </form>
        </div>
    </div>
</div>

<script>
/* ── Auth Modal ── */
function abrirAuth(tipo){
    var ov=document.getElementById('authOverlay');
    ov.style.display='flex';
    ov.classList.add('ativo');
    trocarAuthTab(tipo);
}
function fecharAuth(){
    var ov=document.getElementById('authOverlay');
    ov.classList.remove('ativo');
    ov.style.display='none';
}
function trocarAuthTab(tipo,btn){
    var tabs=document.querySelectorAll('.auth-tab');
    tabs.forEach(function(t){t.classList.remove('ativo')});
    if(btn){btn.classList.add('ativo')}else{
        tabs.forEach(function(t){
            if((tipo==='login'&&t.textContent==='Entrar')||(tipo==='cadastro'&&t.textContent==='Criar Conta'))t.classList.add('ativo');
        });
    }
    document.getElementById('formLogin').style.display=tipo==='login'?'block':'none';
    document.getElementById('formCadastro').style.display=tipo==='cadastro'?'block':'none';
    document.getElementById('authTitulo').textContent=tipo==='login'?'Entrar':'Criar Conta';
}

function handleLogin(e){
    e.preventDefault();
    var email=document.getElementById('loginEmail').value.trim();
    var senha=document.getElementById('loginSenha').value;
    var btn=e.target.querySelector('button[type="submit"]');
    var txt=btn.textContent;
    btn.disabled=true;btn.textContent='Entrando...';
    fetch('/api/auth.php?action=login',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({email:email,senha:senha})})
    .then(function(r){return r.json()})
    .then(function(d){
        btn.disabled=false;btn.textContent=txt;
        if(d.ok){mostrarToast('sucesso','Login realizado!','Bem-vindo de volta!');fecharAuth();if(window.location.pathname.indexOf('/consultas')===0){setTimeout(function(){window.location.reload()},600)}else{verificarSessao()}}
        else{mostrarToast('erro','Erro no login',d.erro||'E-mail ou senha incorretos')}
    }).catch(function(){btn.disabled=false;btn.textContent=txt;mostrarToast('erro','Erro','Falha na comunicação')});
    return false;
}

function handleCadastro(e){
    e.preventDefault();
    var nome=document.getElementById('cadNome').value.trim();
    var email=document.getElementById('cadEmail').value.trim();
    var cpf=document.getElementById('cadCPF').value.trim();
    var senha=document.getElementById('cadSenha').value;
    var btn=e.target.querySelector('button[type="submit"]');
    var txt=btn.textContent;
    btn.disabled=true;btn.textContent='Criando conta...';
    fetch('/api/auth.php?action=cadastro',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({nome:nome,email:email,cpf:cpf,senha:senha})})
    .then(function(r){return r.json()})
    .then(function(d){
        btn.disabled=false;btn.textContent=txt;
        if(d.ok){mostrarToast('sucesso','Conta criada!','Bem-vindo ao Qatar Leilões!');fecharAuth();if(window.location.pathname.indexOf('/consultas')===0){setTimeout(function(){window.location.reload()},600)}else{verificarSessao()}}
        else{mostrarToast('erro','Erro no cadastro',d.erro||'Não foi possível criar a conta')}
    }).catch(function(){btn.disabled=false;btn.textContent=txt;mostrarToast('erro','Erro','Falha na comunicação')});
    return false;
}

/* ── Toast ── */
function mostrarToast(tipo,titulo,mensagem){
    var c=document.getElementById('toastContainer');
    var t=document.createElement('div');
    t.className='toast toast--'+tipo;
    t.innerHTML='<div class="toast__conteudo"><div class="toast__titulo">'+titulo+'</div><div class="toast__mensagem">'+mensagem+'</div></div><button class="toast__fechar" onclick="this.parentElement.remove()">✕</button>';
    c.appendChild(t);setTimeout(function(){t.remove()},4000);
}

/* ── Sessão ── */
function verificarSessao(){
    fetch('/api/auth.php?action=check',{credentials:'same-origin'})
    .then(function(r){return r.json()})
    .then(function(d){if(d.ok&&d.usuario)mostrarPerfilHeader(d.usuario)})
    .catch(function(){});
}
function mostrarPerfilHeader(u){
    var nome=u.nome||u.email||'Usuário';
    var iniciais=nome.split(' ').map(function(p){return p[0]}).join('').substring(0,2).toUpperCase();
    var ab=document.getElementById('headerAuthBtns');
    var pf=document.getElementById('headerPerfil');
    if(ab)ab.style.display='none';
    if(pf){pf.style.display='flex';document.getElementById('headerAvatar').textContent=iniciais;document.getElementById('headerNome').textContent=nome.split(' ')[0]}
    var ma=document.getElementById('mobileAuthBtns');
    var mp=document.getElementById('mobilePerfil');
    if(ma)ma.style.display='none';
    if(mp)mp.style.display='block';
}
function fazerLogout(e){
    if(e)e.preventDefault();
    fetch('/api/auth.php?action=logout',{credentials:'same-origin'})
    .then(function(){window.location.reload()}).catch(function(){window.location.reload()});
}

document.addEventListener('DOMContentLoaded',function(){
    verificarSessao();
    var params=new URLSearchParams(window.location.search);
    var authParam=params.get('auth');
    if(authParam==='login'||authParam==='cadastro')abrirAuth(authParam);
    document.addEventListener('click',function(e){
        var p=document.getElementById('headerPerfil');
        if(p&&!p.contains(e.target))p.classList.remove('aberto');
        var d=document.querySelector('.dropdown');
        if(d&&!d.contains(e.target))d.classList.remove('ativo');
    });
});
</script>
</body>
</html>
