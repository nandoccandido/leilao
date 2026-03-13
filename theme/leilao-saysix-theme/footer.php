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
</body>
</html>
