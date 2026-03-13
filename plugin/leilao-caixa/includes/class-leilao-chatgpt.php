<?php
/**
 * Integração com ChatGPT para triagem de imóveis
 */

defined('ABSPATH') || exit;

class Leilao_ChatGPT {

    private static string $option_key = 'leilao_caixa_openai_key';

    public static function init(): void {
        add_action('wp_ajax_leilao_ai_chat', [__CLASS__, 'ajax_chat']);
        add_action('wp_ajax_nopriv_leilao_ai_chat', [__CLASS__, 'ajax_chat']);
    }

    /**
     * Retorna a API key salva
     */
    public static function get_api_key(): string {
        return get_option(self::$option_key, '');
    }

    /**
     * Salva a API key
     */
    public static function save_api_key(string $key): void {
        update_option(self::$option_key, sanitize_text_field($key));
    }

    /**
     * AJAX handler para o chat de triagem
     */
    public static function ajax_chat(): void {
        $mensagem = sanitize_text_field($_POST['mensagem'] ?? '');
        if (empty($mensagem)) {
            wp_send_json_error('Mensagem vazia.');
        }

        $api_key = self::get_api_key();
        if (empty($api_key)) {
            // Fallback: busca por filtros simples sem IA
            wp_send_json_success([
                'resposta' => 'A IA está sendo configurada. Por enquanto, use o catálogo para filtrar imóveis.',
                'imoveis'  => [],
            ]);
        }

        // Buscar imóveis disponíveis para contexto
        $imoveis_context = self::get_imoveis_context();

        // Montar o prompt do sistema
        $system_prompt = self::build_system_prompt($imoveis_context);

        // Chamar a API do ChatGPT
        $resposta = self::call_openai($api_key, $system_prompt, $mensagem);

        if (is_wp_error($resposta)) {
            wp_send_json_error($resposta->get_error_message());
        }

        wp_send_json_success($resposta);
    }

    /**
     * Busca imóveis disponíveis e formata como contexto para o GPT
     */
    private static function get_imoveis_context(): string {
        $imoveis = get_posts([
            'post_type'      => 'imovel',
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'meta_query'     => [
                [
                    'key'   => '_leilao_status',
                    'value' => ['ativo', 'agendado'],
                    'compare' => 'IN',
                ],
            ],
        ]);

        if (empty($imoveis)) {
            return "Nenhum imóvel disponível no momento.";
        }

        $linhas = [];
        foreach ($imoveis as $imovel) {
            $id             = $imovel->ID;
            $tipo           = wp_get_post_terms($id, 'tipo_imovel', ['fields' => 'names']);
            $estado         = wp_get_post_terms($id, 'estado_imovel', ['fields' => 'names']);
            $cidade         = wp_get_post_terms($id, 'cidade_imovel', ['fields' => 'names']);
            $modalidade     = wp_get_post_terms($id, 'modalidade_venda', ['fields' => 'names']);
            $valor_min      = get_post_meta($id, '_leilao_valor_minimo', true);
            $valor_aval     = get_post_meta($id, '_leilao_valor_avaliacao', true);
            $status         = get_post_meta($id, '_leilao_status', true);
            $quartos        = get_post_meta($id, '_imovel_quartos', true);
            $area           = get_post_meta($id, '_imovel_area_total', true);
            $bairro         = get_post_meta($id, '_imovel_bairro', true);
            $fim            = get_post_meta($id, '_leilao_fim', true);
            $url            = get_permalink($id);

            $linha = "ID:{$id} | {$imovel->post_title} | Tipo:" . ($tipo[0] ?? '-') .
                     " | Cidade:" . ($cidade[0] ?? '-') . "/" . ($estado[0] ?? '-') .
                     " | Bairro:{$bairro}" .
                     " | Área:{$area}m²" .
                     " | Quartos:{$quartos}" .
                     " | Modalidade:" . ($modalidade[0] ?? '-') .
                     " | Avaliação:R$" . number_format(floatval($valor_aval), 0, ',', '.') .
                     " | Mínimo:R$" . number_format(floatval($valor_min), 0, ',', '.') .
                     " | Status:{$status}" .
                     " | Encerra:{$fim}" .
                     " | URL:{$url}";

            $linhas[] = $linha;
        }

        return implode("\n", $linhas);
    }

    /**
     * Monta o prompt do sistema
     */
    private static function build_system_prompt(string $imoveis_context): string {
        return <<<PROMPT
Você é o assistente de leilões do Qatar Leilões, uma plataforma de leilões de imóveis da Caixa Econômica Federal.

Seu papel é ajudar o usuário a encontrar imóveis que correspondam ao que ele procura. Seja simpático, objetivo e use linguagem acessível.

REGRAS:
1. Sempre responda em português do Brasil.
2. Quando o usuário descrever o que procura, filtre os imóveis da lista abaixo e sugira os mais relevantes.
3. Retorne sua resposta EXATAMENTE no seguinte formato JSON (sem markdown, sem code fence):
{
  "texto": "Sua resposta amigável ao usuário",
  "imoveis_ids": [1, 2, 3]
}
4. O campo "texto" deve conter uma resposta conversacional e amigável.
5. O campo "imoveis_ids" deve conter os IDs dos imóveis mais relevantes (máximo 6).
6. Se não encontrar nenhum imóvel adequado, retorne imoveis_ids vazio e sugira que o usuário tente outros critérios.
7. Se o usuário fizer uma pergunta genérica (ex: como funciona, como dar lance), explique brevemente e sugira imóveis populares.
8. Destaque descontos atrativos quando relevante.
9. NUNCA invente imóveis. Use somente os da lista.

IMÓVEIS DISPONÍVEIS:
{$imoveis_context}
PROMPT;
    }

    /**
     * Chama a API do OpenAI
     */
    private static function call_openai(string $api_key, string $system_prompt, string $user_message): array|\WP_Error {
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 30,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body' => wp_json_encode([
                'model'       => 'gpt-4o-mini',
                'messages'    => [
                    ['role' => 'system', 'content' => $system_prompt],
                    ['role' => 'user', 'content' => $user_message],
                ],
                'temperature' => 0.7,
                'max_tokens'  => 600,
            ]),
        ]);

        if (is_wp_error($response)) {
            return new \WP_Error('api_error', 'Erro de conexão com a IA: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code !== 200) {
            $error_msg = $data['error']['message'] ?? 'Erro desconhecido da API (HTTP ' . $code . ')';
            return new \WP_Error('api_error', $error_msg);
        }

        $content = $data['choices'][0]['message']['content'] ?? '';

        // Tenta parsear o JSON da resposta
        $parsed = json_decode($content, true);

        if (!$parsed || !isset($parsed['texto'])) {
            // Se não veio JSON válido, retorna o texto puro
            return [
                'resposta' => $content,
                'imoveis'  => [],
            ];
        }

        // Buscar dados dos imóveis recomendados
        $imoveis_retorno = [];
        $ids = $parsed['imoveis_ids'] ?? [];

        foreach ($ids as $imovel_id) {
            $imovel_id = intval($imovel_id);
            $imovel    = get_post($imovel_id);
            if (!$imovel || $imovel->post_type !== 'imovel') continue;

            $cidade  = wp_get_post_terms($imovel_id, 'cidade_imovel', ['fields' => 'names']);
            $estado  = wp_get_post_terms($imovel_id, 'estado_imovel', ['fields' => 'names']);
            $tipo    = wp_get_post_terms($imovel_id, 'tipo_imovel', ['fields' => 'names']);

            $imoveis_retorno[] = [
                'id'            => $imovel_id,
                'titulo'        => $imovel->post_title,
                'link'          => get_permalink($imovel_id),
                'cidade'        => $cidade[0] ?? '',
                'estado'        => $estado[0] ?? '',
                'tipo'          => $tipo[0] ?? '',
                'valor_minimo'  => floatval(get_post_meta($imovel_id, '_leilao_valor_minimo', true)),
                'valor_avaliacao' => floatval(get_post_meta($imovel_id, '_leilao_valor_avaliacao', true)),
                'status'        => get_post_meta($imovel_id, '_leilao_status', true),
                'quartos'       => get_post_meta($imovel_id, '_imovel_quartos', true),
                'area'          => get_post_meta($imovel_id, '_imovel_area_total', true),
            ];
        }

        return [
            'resposta' => $parsed['texto'],
            'imoveis'  => $imoveis_retorno,
        ];
    }
}
