<?php
/**
 * Plugin Name: ChatBot ANJE Formação
 * Description: Chatbot inteligente para anjeformacao.pt - com LLM via backend ou OpenRouter direto
 * Version: 3.0.0
 * Author: Pedro Silva
 * Text Domain: chatbot-anje-formacao
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

define('CHATBOT_ANJE_VERSION', '3.0.0');
define('CHATBOT_ANJE_PATH', plugin_dir_path(__FILE__));
define('CHATBOT_ANJE_URL', plugin_dir_url(__FILE__));

require_once CHATBOT_ANJE_PATH . 'includes/class-chatbot-anje.php';

add_action('plugins_loaded', function() {
    ChatBot_ANJE_Formacao::instance();
});

register_activation_hook(__FILE__, function() {
    $defaults = [
        'chatbot_name' => 'ChatBot ANJE',
        'backend_url' => '',
        'openrouter_key' => '',
        'model' => 'openrouter/owl-alpha',
        'welcome_message' => "Olá! Sou o assistente virtual da ANJE Formação.\n\nPosso ajudar com:\n\n- Cursos disponíveis\n- Preços e datas\n- Links para inscrição\n- Equipa e órgãos sociais\n\nO que procura?",
        'primary_color' => '#007bff',
        'position' => 'right',
        'max_tokens' => 800,
        'request_timeout' => 60,
        'show_on_all_pages' => 'yes',
    ];
    if (!get_option('chatbot_anje_formacao_settings')) {
        add_option('chatbot_anje_formacao_settings', $defaults);
    }
});
