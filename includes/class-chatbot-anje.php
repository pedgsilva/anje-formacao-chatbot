<?php
/**
 * Main ChatBot ANJE Formacao class
 * v3.0 - LLM support via backend proxy or direct OpenRouter
 * Rule-based fallback when no LLM configured
 */

if (!defined('ABSPATH')) exit;

class ChatBot_ANJE_Formacao {

    private static $instance = null;
    private $option_key = 'chatbot_anje_formacao_settings';

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_footer', [$this, 'render_chatbot'], 100);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_chatbot_anje_chat', [$this, 'handle_chat']);
        add_action('wp_ajax_nopriv_chatbot_anje_chat', [$this, 'handle_chat']);
    }

    private function get_settings() {
        $defaults = [
            'chatbot_name' => 'ChatBot ANJE',
            'backend_url' => '',
            'openrouter_key' => '',
            'model' => 'openrouter/owl-alpha',
            'welcome_message' => '',
            'primary_color' => '#007bff',
            'position' => 'right',
            'max_tokens' => 800,
            'request_timeout' => 60,
            'show_on_all_pages' => 'yes',
        ];
        $settings = get_option($this->option_key, []);
        return wp_parse_args($settings, $defaults);
    }

    /* CSS */

    public function enqueue_assets() {
        $s = $this->get_settings();

        if ($s['show_on_all_pages'] !== 'yes') {
            if (!is_front_page() && !is_page()) return;
        }

        wp_register_style('chatbot-anje-css', false);
        wp_enqueue_style('chatbot-anje-css');
        wp_add_inline_style('chatbot-anje-css', $this->get_css($s));
    }

    private function get_css($settings) {
        $color = esc_attr($settings['primary_color']);
        $pos = esc_attr($settings['position']);
        $side = ($pos === 'left') ? 'left:20px;' : 'right:20px;';

        return "
            #chatbot-anje-widget{position:fixed;bottom:20px;{$side}z-index:999999;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}
            #chatbot-anje-toggle{width:90px;height:90px;border-radius:50%;border:none;padding:0;overflow:hidden;background:none;cursor:pointer;box-shadow:0 4px 16px rgba(0,0,0,.2);transition:transform .2s}
            #chatbot-anje-toggle:hover{transform:scale(1.08)}
            #chatbot-anje-toggle img{width:90px;height:90px;object-fit:contain;display:block}
            #chatbot-anje-window{position:fixed;bottom:120px;" . ($pos === 'left' ? 'left:20px' : 'right:20px') . ";width:min(400px,calc(100vw - 40px));height:min(580px,calc(100vh - 140px));background:#fff;border-radius:16px;box-shadow:0 12px 48px rgba(0,0,0,.18);display:none;flex-direction:column;overflow:hidden;animation:chatbotSlideUp .25s ease}
            @keyframes chatbotSlideUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
            #chatbot-anje-header{background:{$color};color:#fff;padding:14px 16px;display:flex;align-items:center;gap:10px;flex-shrink:0}
            #chatbot-anje-header-text{flex:1}
            #chatbot-anje-header strong{display:block;font-size:14px;font-weight:600}
            #chatbot-anje-header small{font-size:11px;opacity:.85}
            #chatbot-anje-close{background:none;border:none;color:#fff;font-size:22px;cursor:pointer;opacity:.7;padding:4px;line-height:1}
            #chatbot-anje-close:hover{opacity:1}
            #chatbot-anje-messages{flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:10px;background:#f0f2f5}
            .chatbot-msg{max-width:85%;padding:10px 14px;border-radius:12px;font-size:13.5px;line-height:1.55;word-wrap:break-word;box-shadow:0 1px 2px rgba(0,0,0,.06)}
            .chatbot-msg-bot{background:#fff;color:#222;align-self:flex-start;border-bottom-left-radius:4px}
            .chatbot-msg-user{background:{$color};color:#fff;align-self:flex-end;border-bottom-right-radius:4px}
            .chatbot-msg-bot a{color:#0066ee!important;text-decoration:underline!important;font-weight:600!important}
            .chatbot-msg-bot strong{color:#1a1a2e}
            #chatbot-anje-typing{background:#fff;color:#888;align-self:flex-start;font-size:12px;font-style:italic;padding:6px 12px;border-radius:12px;box-shadow:0 1px 2px rgba(0,0,0,.06)}
            #chatbot-anje-input-area{display:flex;padding:10px 12px;background:#fff;border-top:1px solid #e8e8e8;gap:8px;flex-shrink:0}
            #chatbot-anje-input{flex:1;padding:10px 14px;border:1px solid #ddd;border-radius:20px;outline:none;font-size:13.5px;font-family:inherit}
            #chatbot-anje-input:focus{border-color:{$color}}
            #chatbot-anje-send{width:42px;height:42px;min-width:42px;min-height:42px;max-width:42px;max-height:42px;border-radius:50%;border:none;outline:none;background:{$color};color:#fff;cursor:pointer;font-size:18px;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-sizing:border-box;padding:0;margin:0}
            #chatbot-anje-send:disabled{background:#ccc;cursor:not-allowed}
        ";
    }

    /* HTML + JS */

    public function render_chatbot() {
        $s = $this->get_settings();
        $name = esc_html($s['chatbot_name']);
        $welcome = $s['welcome_message'] ?: "Olá! 👋 Sou o assistente virtual da ANJE Formação.\n\nPosso ajudar com:\n• 📚 Cursos\n• 💰 Preços e datas\n• 👥 Equipa\n• 📞 Contactos\n\nO que procura?";
        $ajax = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('chatbot_anje_nonce');
        $timeout = intval($s['request_timeout']) * 1000;
        $plugin_url = plugin_dir_url(__FILE__);
        ?>
        <div id="chatbot-anje-widget">
            <button id="chatbot-anje-toggle" aria-label="<?php echo $name; ?>"><img src="<?php echo $plugin_url; ?>../assets/Wise.png" alt="<?php echo $name; ?>"></button>
            <div id="chatbot-anje-window">
                <div id="chatbot-anje-header">
                    <div id="chatbot-anje-header-text"><strong><?php echo $name; ?></strong><small>Online</small></div>
                    <button id="chatbot-anje-close" aria-label="Fechar">&#10005;</button>
                </div>
                <div id="chatbot-anje-messages"></div>
                <div id="chatbot-anje-input-area">
                    <input type="text" id="chatbot-anje-input" placeholder="Escreva a sua pergunta..." maxlength="500">
                    <button id="chatbot-anje-send" aria-label="Enviar">&#10148;</button>
                </div>
            </div>
        </div>
        <script>
        (function(){
            var ajaxUrl=<?php echo json_encode($ajax); ?>;
            var nonce=<?php echo json_encode($nonce); ?>;
            var welcome=<?php echo json_encode($welcome); ?>;
            var timeout=<?php echo $timeout; ?>;
            var busy=false,shown=false;
            var T=document.getElementById('chatbot-anje-toggle');
            var W=document.getElementById('chatbot-anje-window');
            var I=document.getElementById('chatbot-anje-input');
            var B=document.getElementById('chatbot-anje-send');
            var M=document.getElementById('chatbot-anje-messages');
            if(!T)return;
            T.addEventListener('click',function(){
                if(W.style.display==='flex'){W.style.display='none'}
                else{W.style.display='flex';I.focus();if(!shown&&welcome){addMsg(welcome,'bot');shown=true}}
            });
            document.getElementById('chatbot-anje-close').addEventListener('click',function(){W.style.display='none'});
            B.addEventListener('click',send);
            I.addEventListener('keypress',function(e){if(e.key==='Enter')send()});
            document.addEventListener('keydown',function(e){if(e.key==='Escape'&&W.style.display==='flex')W.style.display='none'});
            function send(){
                var msg=I.value.trim();
                if(!msg||busy)return;busy=true;B.disabled=true;
                addMsg(msg,'user');I.value='';addTyping();
                var xhr=new XMLHttpRequest();
                xhr.open('POST',ajaxUrl);
                xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
                xhr.timeout=timeout;
                xhr.onload=function(){
                    remTyping();
                    try{var r=JSON.parse(xhr.responseText);
                        if(r.success){addMsg(r.data.response||'Erro.','bot')}
                        else{addMsg('Erro: '+(r.data||'Desconhecido'),'bot')}
                    }catch(e){addMsg('Erro ao processar.','bot')}
                };
                xhr.onerror=function(){remTyping();addMsg('Erro de ligação.','bot')};
                xhr.ontimeout=function(){remTyping();addMsg('Timeout. Tente novamente.','bot')};
                xhr.onreadystatechange=function(){if(xhr.readyState===4){busy=false;B.disabled=false;I.focus()}};
                xhr.send('action=chatbot_anje_chat&message='+encodeURIComponent(msg)+'&nonce='+nonce)
            }
            function addMsg(text,type){
                var d=document.createElement('div');
                d.className='chatbot-msg chatbot-msg-'+type;
                d.innerHTML=text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>').replace(/(https?:\/\/[^\s<>"']+)/g,'<a href="$1" target="_blank" rel="noopener">$1</a>').replace(/\n/g,'<br>');
                M.appendChild(d);d.scrollIntoView({behavior:'smooth'})
            }
            function addTyping(){var d=document.createElement('div');d.id='chatbot-anje-typing';d.className='chatbot-msg';d.textContent='A escrever...';M.appendChild(d)}
            function remTyping(){var t=document.getElementById('chatbot-anje-typing');if(t)t.remove()}
        })();
        </script>
        <?php
    }

    /* AJAX handler */

    public function handle_chat() {
        if (!check_ajax_referer('chatbot_anje_nonce', 'nonce', false)) {
            wp_send_json_error('Token invalido', 403);
        }
        $msg = sanitize_text_field($_POST['message'] ?? '');
        if (empty($msg)) wp_send_json_error('Vazio', 400);

        $settings = $this->get_settings();
        $backend_url = esc_url_raw($settings['backend_url']);
        $openrouter_key = $settings['openrouter_key'];

        if (empty($backend_url) && empty($openrouter_key)) {
            $response = $this->get_rule_based_response(strtolower(trim($msg)));
            wp_send_json_success(['response' => $response]);
            return;
        }

        if (!empty($backend_url)) {
            $response = $this->proxy_to_backend($backend_url, $msg, $settings);
            wp_send_json_success(['response' => $response]);
            return;
        }

        $response = $this->call_openrouter($msg, $settings);
        wp_send_json_success(['response' => $response]);
    }

    private function proxy_to_backend($url, $msg, $settings) {
        $response = wp_remote_post(trailingslashit($url) . 'chat', [
            'timeout' => intval($settings['request_timeout']),
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode(['message' => $msg]),
        ]);
        if (is_wp_error($response)) {
            return $this->get_rule_based_response(strtolower(trim($msg)));
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['response'] ?? $this->get_rule_based_response(strtolower(trim($msg)));
    }

    private function call_openrouter($msg, $settings) {
        $key = $settings['openrouter_key'];
        $model = $settings['model'] ?: 'openrouter/owl-alpha';
        $max_tokens = intval($settings['max_tokens']) ?: 800;

        $system_prompt = $this->build_system_prompt($settings);

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user', 'content' => 'Pergunta: ' . $msg],
            ],
            'temperature' => 0.3,
            'max_tokens' => $max_tokens,
        ];

        $json_body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json_body === false) {
            return $this->get_rule_based_response(strtolower(trim($msg)));
        }

        $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', [
            'timeout' => intval($settings['request_timeout']),
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'Content-Type' => 'application/json',
            ],
            'body' => $json_body,
        ]);

        if (is_wp_error($response)) {
            return $this->get_rule_based_response(strtolower(trim($msg)));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            error_log('ChatBot ANJE: HTTP ' . $response_code);
            return $this->get_rule_based_response(strtolower(trim($msg)));
        }

        $data = json_decode($response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['choices'][0]['message']['content'])) {
            return $this->get_rule_based_response(strtolower(trim($msg)));
        }

        return $data['choices'][0]['message']['content'];
    }

    /* SYSTEM PROMPT for LLM */

    private function build_system_prompt($settings) {
        $courses = $this->fetch_courses_from_woocommerce();

        $area_keywords = [
            'Excel' => ['excel', 'folha de c', 'folha de cálculo', 'folha de calculo'],
            'PowerBI' => ['power bi', 'powerbi', 'dashboard'],
            'IA' => ['inteligência artificial', ' claude', 'chatgpt', 'generativa', 'copilot'],
            'Gestão' => ['gestão', 'lideran', 'liderança', 'equipa', 'tempo', 'projeto', 'produtividade', 'burnout'],
            'Marketing' => ['marketing', 'digital', 'ecommerce', 'e-commerce', 'seo', 'influenc'],
            'Vendas' => ['venda', 'vendas', 'comercial', 'neuromarketing', 'crm', 'vendedor'],
            'Finanças' => ['financ', 'tesouraria', 'poupanca', 'sql', 'python'],
            'Jurídico' => ['juridic', 'direito', 'rgpd', 'laboral', 'sociedade', 'branqueamento'],
            'Comunicação' => ['comunicar', 'storytelling', 'apresentac', 'impacto', 'pnl'],
            'Empreendedorismo' => ['empreend', 'negocio', 'startup', 'plano de neg', 'inovar'],
            'Hotelaria' => ['hotelaria', 'turismo', 'higiene', 'alimentar'],
            'Certificação' => ['certifica', 'icagile', 'coach', 'pnl practitioner'],
        ];

        $areas = [];
        foreach ($courses as $c) {
            $titulo = mb_strtolower(html_entity_decode($c['titulo'], ENT_QUOTES, 'UTF-8'));
            foreach ($area_keywords as $area => $kws) {
                foreach ($kws as $kw) {
                    if (mb_strpos($titulo, $kw) !== false) {
                        if (!isset($areas[$area])) $areas[$area] = [];
                        if (count($areas[$area]) < 5) {
                            $areas[$area][] = $c;
                        }
                        break 2;
                    }
                }
            }
        }

        $course_lines = [];
        foreach ($areas as $area => $cs) {
            $names = [];
            foreach ($cs as $c) {
                $title = mb_strlen($c['titulo']) > 45 ? mb_substr($c['titulo'], 0, 42) . '...' : $c['titulo'];
                $names[] = $title . ' (' . $c['preco'] . ') - ' . $c['url'];
            }
            $course_lines[] = $area . ': ' . implode('; ', $names);
        }

        $total = count($courses);
        $gratis = 0;
        foreach ($courses as $c) { if ($c['preco'] === 'Gratuito') $gratis++; }

        return "És o assistente virtual da ANJE Formação (anjeformacao.pt).\n"
            . "\nSOBRE: ANJE - Associação Nacional de Jovens Empresários, fundada 1986. ANJE Formação presente nas 5 regiões, certificada DGERT.\n"
            . "\nEQUIPA:\n"
            . "- Ana Jogo Mendes - Diretora\n"
            . "- Coordenadores: Cláudia Almeida, Cristiana Moreira, Manuela Almeida, Vitória Pereira, Ana Rodrigues (Lisboa), Armanda Ângelo (Coimbra), Cátia Santos (Algarve), Patrícia Nobre (Alentejo)\n"
            . "- Teresa Miranda - Comunicação e Marketing\n"
            . "- Sara Almeida - Administrativa\n"
            . "- Susana Pereira - Administrativa\n"
            . "- Fátima Pinto - Administrativa Coimbra\n"
            . "\nÓRGÃOS SOCIAIS:\n"
            . "- Presidente: Carlos Carvalho\n"
            . "- Vice-Presidentes: Nuno Malheiro, Filipa Pinto de Carvalho, Gonçalo Simões de Almeida\n"
            . "- Presidente Assembleia Geral: Miguel Moreira da Silva\n"
            . "- Presidente Conselho Fiscal: Catarina Azevedo\n"
            . "\nCONTACTOS: infoformacao@anje.pt | (+351) 220 108 074\n"
            . "MORADA: Rua Paulo da Gama - Casa do Farol, 4169-006 Porto\n"
            . "\nCURSOS: {$total} cursos ({$gratis} gratuitos)\n"
            . implode("\n", $course_lines) . "\n"
            . "\nFORMAÇÃO-AÇÃO: programa para micro/PME, 90% FSE, Norte/Centro/Alentejo, até 250 colaboradores, Inovação/Transição Digital/ESG. Vitoria Pereira e Cristiana Moreira. https://anjeformacao.pt/formacao-acao-pme/\n"
            . "\nREGRAS:\n"
            . "- Português de Portugal\n"
            . "- Usa **negrita** para títulos\n"
            . "- URLs completos: https://anjeformacao.pt/curso/...\n"
            . "- Lista TODOS os cursos disponíveis na área\n"
            . "- Cursos com datas (agendados/marcados): mostra APENAS produtos variáveis (não simples). Usa get_attribute('data') nas variações. Se não houver produtos variáveis, diz 'não há cursos com datas agendadas'\n"
            . "- Não listes cursos para perguntas sobre equipa/orgãos\n"
            . "- Se não souberes: contacte infoformacao@anje.pt";
    }

    /* WOOCOMMERCE */

    private function fetch_courses_from_woocommerce() {
        $cached = get_transient('chatbot_anje_courses_cache');
        if ($cached !== false) return $cached;

        $courses = [];
        $query = new WP_Query([
            'post_type' => 'product',
            'posts_per_page' => 100,
            'post_status' => 'publish',
            'post_parent' => 0,
        ]);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $product = wc_get_product(get_the_ID());
                if (!$product) continue;
                $url = get_permalink(get_the_ID());
                if (strpos($url, '/curso/') === false) continue;
                $name = $product->get_name();
                if (empty(trim($name))) continue;
                $price = $product->get_price();
                $price_display = 'Sob consulta';
                if ($price === '0' || $price === 0 || $price === '') {
                    $price_display = 'Gratuito';
                } elseif (is_numeric($price)) {
                    $price_display = '€' . number_format((float)$price, 2, ',', '.');
                }
                $courses[] = ['titulo' => $name, 'preco' => $price_display, 'url' => $url];
            }
            wp_reset_postdata();
        }

        if (!empty($courses)) {
            set_transient('chatbot_anje_courses_cache', $courses, HOUR_IN_SECONDS);
            return $courses;
        }

        return $this->get_fallback_courses();
    }

    private function fetch_variable_courses_from_woocommerce() {
        $cached = get_transient('chatbot_anje_variable_courses_cache');
        if ($cached !== false) return $cached;

        $courses = [];
        $query = new WP_Query([
            'post_type' => 'product',
            'posts_per_page' => 100,
            'post_status' => 'publish',
            'post_parent' => 0,
        ]);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $product = wc_get_product(get_the_ID());
                if (!$product) continue;
                if (!$product->is_type('variable')) continue;
                $url = get_permalink(get_the_ID());
                if (strpos($url, '/curso/') === false) continue;
                $name = $product->get_name();
                if (empty(trim($name))) continue;
                $children = $product->get_children();
                if (empty($children)) continue;
                $dates = [];
                $prices = [];
                foreach ($children as $child_id) {
                    $variation = wc_get_product($child_id);
                    if (!$variation) continue;
                    $v_price = $variation->get_price();
                    if ($v_price === '0' || $v_price === 0 || $v_price === '') {
                        $prices[] = 'Gratuito';
                    } elseif (is_numeric($v_price)) {
                        $prices[] = '€' . number_format((float)$v_price, 2, ',', '.');
                    }
                    $v_date = $variation->get_attribute('data');
                    if (empty($v_date)) {
                        $v_date = $variation->get_attribute('date');
                    }
                    if (empty($v_date)) {
                        $v_date = $variation->get_attribute('pa_data');
                    }
                    if (!empty($v_date)) {
                        $dates[] = $v_date;
                    }
                }
                $price_display = 'Sob consulta';
                if (!empty($prices)) {
                    $unique_prices = array_unique($prices);
                    $price_display = count($unique_prices) === 1 ? reset($unique_prices) : implode(' / ', array_slice($unique_prices, 0, 3));
                }
                $dates_display = '';
                if (!empty($dates)) {
                    $unique_dates = array_unique($dates);
                    sort($unique_dates);
                    $dates_display = implode(', ', $unique_dates);
                }
                $courses[] = [
                    'titulo' => $name,
                    'preco' => $price_display,
                    'url' => $url,
                    'dates' => $dates_display,
                    'is_variable' => true,
                ];
            }
            wp_reset_postdata();
        }

        if (!empty($courses)) {
            set_transient('chatbot_anje_variable_courses_cache', $courses, HOUR_IN_SECONDS);
            return $courses;
        }

        return [];
    }

    private function get_fallback_courses() {
        return [
            ['titulo' => 'Como elaborar um Plano de Negócios | Formação Assíncrona', 'preco' => '€150,00', 'url' => 'https://anjeformacao.pt/curso/como-elaborar-um-plano-de-negocios-formacao-assincrona/'],
            ['titulo' => 'Programa Executivo Vendedor de Alta Performance | Online', 'preco' => '€280,00', 'url' => 'https://anjeformacao.pt/curso/programa-executivo-vendedor-de-alta-performance/'],
            ['titulo' => 'RGPD para Gestores e Empreendedores | Formação Assíncrona', 'preco' => '€180,00', 'url' => 'https://anjeformacao.pt/curso/rgpd-para-gestores-e-empreendedores-formacao-assincrona/'],
            ['titulo' => 'Direito das Sociedades – Constituição de Empresas | Formação Assíncrona', 'preco' => '€175,00', 'url' => 'https://anjeformacao.pt/curso/direito-das-sociedades-constituicao-de-empresas-formacao-assincrona/'],
            ['titulo' => 'Inteligência Emocional para a Motivação e Tomada de Decisão | Online', 'preco' => '€480,00', 'url' => 'https://anjeformacao.pt/curso/inteligencia-emocional-para-a-motivacao-e-tomada-de-decisao-online/'],
            ['titulo' => 'Direito Laboral para Gestores e Empreendedores | Formação Assíncrona', 'preco' => '€190,00', 'url' => 'https://anjeformacao.pt/curso/direito-laboral-para-gestores-e-empreendedores-formacao-assincrona/'],
            ['titulo' => 'Comunicar com Impacto | Online', 'preco' => '€150,00', 'url' => 'https://anjeformacao.pt/curso/comunicar-com-impacto-online/'],
            ['titulo' => 'Inteligência Artificial Aplicada – Claude AI | Online', 'preco' => '€150,00', 'url' => 'https://anjeformacao.pt/curso/inteligencia-artificial-aplicada-claude-ai-online/'],
            ['titulo' => 'Inovar para Crescer: Ferramentas de Gestão práticas para PME | Online', 'preco' => '€180,00', 'url' => 'https://anjeformacao.pt/curso/inovar-para-crescer-ferramentas-de-gestao-praticas-para-pme-online/'],
            ['titulo' => 'Microsoft Copilot aplicado ao contexto profissional | Online', 'preco' => '€180,00', 'url' => 'https://anjeformacao.pt/curso/microsoft-copilot-aplicado-ao-contexto-profissional-online/'],
            ['titulo' => 'Inteligência Artificial Aplicada ao Setor Imobiliário | Online', 'preco' => '€150,00', 'url' => 'https://anjeformacao.pt/curso/inteligencia-artificial-aplicada-ao-setor-imobiliario-online/'],
            ['titulo' => 'Inteligência Artificial Aplicada a Área Comercial | Online', 'preco' => '€150,00', 'url' => 'https://anjeformacao.pt/curso/inteligencia-artificial-aplicada-a-area-comercial-online/'],
            ['titulo' => 'RGPC na Prática | Online', 'preco' => '€150,00', 'url' => 'https://anjeformacao.pt/curso/rgpc-na-pratica-prevenir-riscos-cumprir-e-reforcar-a-integridade-online/'],
            ['titulo' => 'Felicidade nas Organizações | Online', 'preco' => '€135,00', 'url' => 'https://anjeformacao.pt/curso/felicidade-nas-organizacoes-cultura-seguranca-psicologica-e-bem-estar-sustentavel-online/'],
            ['titulo' => 'Treino Intensivo em Liderança | Lisboa', 'preco' => '€1750,00', 'url' => 'https://anjeformacao.pt/curso/treino-intensivo-de-lideranca-lisboa/'],
            ['titulo' => 'Programa Executivo em Vendas | Norte e Online', 'preco' => '€1890,00', 'url' => 'https://anjeformacao.pt/curso/programa-executivo-em-vendas/'],
            ['titulo' => 'Liderança Anti-Burnout | Online', 'preco' => '€120,00', 'url' => 'https://anjeformacao.pt/curso/lideranca-anti-burnout-energia-limites-e-clareza-na-gestao-de-pessoas-online/'],
            ['titulo' => 'IA Generativa como Ferramenta de Otimização | Online', 'preco' => '€350,00', 'url' => 'https://anjeformacao.pt/curso/ia-generativa-como-ferramenta-de-optimizacao-dos-negocios-online/'],
            ['titulo' => 'Programa Executivo em Marketing Digital e E-commerce | Online', 'preco' => '€1800,00', 'url' => 'https://anjeformacao.pt/curso/programa-executivo-em-marketing-digital-e-e-commerce/'],
            ['titulo' => 'Gestão de Projetos | Online', 'preco' => '€190,00', 'url' => 'https://anjeformacao.pt/curso/gestao-de-projetos-online/'],
            ['titulo' => 'Branqueamento de Capitais em Portugal | Online', 'preco' => '€150,00', 'url' => 'https://anjeformacao.pt/curso/branqueamento-de-capitais-em-portugal-online/'],
            ['titulo' => 'Conduzir ao Fecho da Venda | Online', 'preco' => '€135,00', 'url' => 'https://anjeformacao.pt/curso/conduzir-ao-fecho-da-venda-online/'],
            ['titulo' => 'Excel Avançado aplicado à Gestão | Algarve', 'preco' => 'Gratuito', 'url' => 'https://anjeformacao.pt/curso/excel-avancado-aplicado-a-gestao-ufcd-342219-algarve/'],
            ['titulo' => 'Criação de dashboards dinâmicos com PowerBI | Algarve', 'preco' => 'Gratuito', 'url' => 'https://anjeformacao.pt/curso/criacao-de-dashboards-dinamicos-com-powerbi-ufcfd-341107-algarve/'],
            ['titulo' => 'Excel Iniciação | Algarve', 'preco' => 'Gratuito', 'url' => 'https://anjeformacao.pt/curso/excel-iniciacao-extra-catalogo-algarve/'],
            ['titulo' => 'Folha de cálculo – utilização intermédia | Centro', 'preco' => 'Gratuito', 'url' => 'https://anjeformacao.pt/curso/folha-de-calculo-utilizacao-intermedia-centro/'],
            ['titulo' => 'Produzir documentos em folha de cálculo – UC 02775 | Norte', 'preco' => 'Gratuito', 'url' => 'https://anjeformacao.pt/curso/produzir-documentos-em-folha-de-calculo-uc-02775-norte-pessoas-2030/'],
            ['titulo' => 'Produzir documentos em folha de cálculo – UC 02775 | Algarve', 'preco' => 'Gratuito', 'url' => 'https://anjeformacao.pt/curso/produzir-documentos-em-folha-de-calculo-uc-02775-algarve/'],
        ];
    }

    /* RULE-BASED FALLBACK */

    private function get_rule_based_response($msg) {
        $equipa = [
            ['nome' => 'Ana Jogo Mendes', 'cargo' => 'Diretora ANJE Formação'],
            ['nome' => 'Cláudia Almeida', 'cargo' => 'Coordenadora'],
            ['nome' => 'Cristiana Moreira', 'cargo' => 'Coordenadora'],
            ['nome' => 'Manuela Almeida', 'cargo' => 'Coordenadora'],
            ['nome' => 'Vitória Pereira', 'cargo' => 'Coordenadora'],
            ['nome' => 'Ana Rodrigues', 'cargo' => 'Coordenadora Lisboa'],
            ['nome' => 'Armanda Ângelo', 'cargo' => 'Coordenadora Coimbra'],
            ['nome' => 'Cátia Santos', 'cargo' => 'Coordenadora Algarve'],
            ['nome' => 'Patrícia Nobre', 'cargo' => 'Coordenadora Alentejo'],
            ['nome' => 'Sara Almeida', 'cargo' => 'Administrativa'],
            ['nome' => 'Susana Pereira', 'cargo' => 'Administrativa'],
            ['nome' => 'Fátima Pinto', 'cargo' => 'Administrativa Coimbra'],
            ['nome' => 'Teresa Miranda', 'cargo' => 'Comunicação e Marketing'],
        ];
        $orgaos = [
            ['nome' => 'Carlos Carvalho', 'cargo' => 'Presidente'],
            ['nome' => 'Nuno Malheiro', 'cargo' => 'Vice-Presidente'],
            ['nome' => 'Filipa Pinto de Carvalho', 'cargo' => 'Vice-Presidente'],
            ['nome' => 'Gonçalo Simões de Almeida', 'cargo' => 'Vice-Presidente'],
            ['nome' => 'Miguel Moreira da Silva', 'cargo' => 'Presidente da Assembleia Geral'],
            ['nome' => 'Catarina Azevedo', 'cargo' => 'Presidente do Conselho Fiscal'],
            ['nome' => 'Pedro Cardoso', 'cargo' => 'Vice-Presidente do Conselho Fiscal'],
            ['nome' => 'Sofia Xavier', 'cargo' => 'Vogal do Conselho Fiscal'],
        ];

        // Person search
        foreach (array_merge($equipa, $orgaos) as $p) {
            if (mb_strpos($msg, mb_strtolower($p['nome'])) !== false) {
                return "👤 **{$p['nome']}** - {$p['cargo']}";
            }
        }

        if ($this->match_kw($msg, ['formacao acao', 'formacao-acao', 'formação ação', 'formacao acao'])) {
            return "📋 **Formação-Ação para PME:**\n\nPrograma de formação à medida para micro, pequenas e médias empresas.\n\n💰 Financiamento: 90% FSE, 10% empresa\n📍 Regiões: Norte, Centro, Alentejo\n🏢 Destinatários: Micro/PME até 250 colaboradores\n📌 Áreas: Inovação, Transição Digital, ESG\n\n👩‍💼 Responsáveis:\n• Vitória Pereira - vitoriapereira@anje.pt\n• Cristiana Moreira - cristianamoreira@anje.pt\n\nℹ️ https://anjeformacao.pt/formacao-acao-pme/\n📧 infoformacao@anje.pt";
        }

        if ($this->match_kw($msg, ['equipa', 'equipe', 'staff', 'funcionarios', 'quem trabalha', 'diretor', 'diretoras'])) {
            $r = "**Equipa da ANJE Formação:**\n\n";
            foreach ($equipa as $p) { $r .= "• {$p['nome']} - {$p['cargo']}\n"; }
            return $r;
        }

        if ($this->match_kw($msg, ['orgaos', 'orgaos', 'orgão', 'orgão', 'conselho fiscal', 'assembleia', 'mesa', 'fiscal'])) {
            $r = "**Órgãos Sociais da ANJE:**\n\n";
            foreach ($orgaos as $p) { $r .= "• {$p['nome']} - {$p['cargo']}\n"; }
            return $r;
        }

        if ($this->match_kw($msg, ['presidente', 'quem e o presidente', 'quem é o presidente'])) {
            return 'O presidente da ANJE é **Carlos Carvalho**.';
        }

        if ($this->match_kw($msg, ['contacto', 'contatos', 'email', 'telefone', 'morada', 'endereco', 'endereço', 'onde fica', 'localização'])) {
            return "📞 **Contactos da ANJE Formação:**\n\n📧 infoformacao@anje.pt\n📱 (+351) 220 108 074\n📍 Rua Paulo da Gama - Casa do Farol, 4169-006 Porto";
        }

        if ($this->is_date_query($msg)) {
            return $this->search_variable_courses($msg);
        }

        if ($this->match_kw($msg, ['curso', 'cursos', 'formacao', 'formacoes', 'formação', 'formações', 'treinamento', 'workshop', 'excel', 'powerbi', 'power bi', 'gratuito', 'gratuitos', 'gratis', 'desempregado', 'desempregados'])) {
            return $this->search_courses($msg);
        }

        return "Não tenho essa informação específica. Posso ajudar com:\n\n• 📚 **Cursos e formações** - Pesquisa por área (IA, gestão, marketing, vendas, excel, powerbi...)\n• 💰 Preços e datas\n• 👥 **Equipa**\n• 📋 **Órgãos sociais**\n• 📞 **Contactos**\n\nOu contacte: infoformacao@anje.pt";
    }

    private function match_kw($msg, $keywords) {
        foreach ($keywords as $kw) {
            if (mb_strpos($msg, $kw) !== false) return true;
        }
        return false;
    }

    private function search_courses($query) {
        $courses = $this->fetch_courses_from_woocommerce();

        $area_map = [
            'excel' => ['excel', 'folha de c', 'folha de cálculo', 'folha de calculo'],
            'powerbi' => ['power bi', 'powerbi', 'dashboard'],
            'ia' => ['inteligência artificial', ' claude', 'chatgpt', 'generativa', 'copilot'],
            'gestão' => ['gestão', 'lideran', 'liderança', 'equipa', 'tempo', 'projeto', 'produtividade', 'burnout'],
            'marketing' => ['marketing', 'digital', 'ecommerce', 'e-commerce', 'seo', 'influenc'],
            'vendas' => ['venda', 'vendas', 'comercial', 'neuromarketing', 'crm', 'vendedor'],
            'finanças' => ['financ', 'tesouraria', 'poupanca', 'sql', 'python'],
            'jurídico' => ['juridic', 'direito', 'rgpd', 'laboral', 'sociedade', 'branqueamento'],
            'comunicação' => ['comunicar', 'storytelling', 'apresentac', 'impacto', 'pnl'],
            'empreendedorismo' => ['empreend', 'negocio', 'startup', 'plano de neg', 'inovar'],
            'hotelaria' => ['hotelaria', 'turismo', 'higiene', 'alimentar'],
            'certificação' => ['certifica', 'icagile', 'coach', 'pnl practitioner'],
            'gratuito' => ['gratuito', 'gratis', 'desempregado'],
        ];

        $matched_area = null;
        foreach ($area_map as $area => $kws) {
            foreach ($kws as $kw) {
                if (mb_strpos($query, $kw) !== false) { $matched_area = $area; break 2; }
            }
        }

        $filtered = [];
        foreach ($courses as $c) {
            $titulo = mb_strtolower(html_entity_decode($c['titulo'], ENT_QUOTES, 'UTF-8'));
            $preco = mb_strtolower($c['preco']);
            if ($matched_area) {
                foreach ($area_map[$matched_area] as $kw) {
                    if (mb_strpos($titulo, $kw) !== false || mb_strpos($preco, $kw) !== false) {
                        $filtered[] = $c; break;
                    }
                }
            } else {
                $filtered[] = $c;
            }
        }

        if (empty($filtered)) {
            return 'Não encontrei cursos para essa área. Pesquise por: IA, gestão, marketing, vendas, excel, powerbi, jurídico, comunicação, empreendedorismo...';
        }

        $response = 'Encontrei **' . count($filtered) . ' cursos**' . ($matched_area ? ' na área de ' . ucfirst($matched_area) : '') . ":\n\n";
        $count = 0;
        foreach ($filtered as $c) {
            if ($count >= 10) { $response .= "\n_E mais " . (count($filtered) - 10) . " cursos!_"; break; }
            $title = trim(preg_replace('/\s+/', ' ', html_entity_decode($c['titulo'], ENT_QUOTES, 'UTF-8')));
            $response .= "• **{$title}** - {$c['preco']}\n  {$c['url']}\n\n";
            $count++;
        }
        return $response;
    }

    private function is_date_query($msg) {
        $date_keywords = [
            'data', 'datas', 'agendado', 'agendados', 'agendada', 'agendadas',
            'marcado', 'marcados', 'marcada', 'marcadas',
            'proxima', 'próxima', 'proximas', 'próximas',
            'proximo', 'próximo', 'proximos', 'próximos',
            'edicao', 'edição', 'edicoes', 'edições',
            'calendario', 'calendário', 'agenda',
            'quando', 'programacao', 'programação',
            'inicio', 'início', 'começa', 'comeca',
            'inscricao', 'inscrição', 'inscricoes', 'inscrições',
        ];
        return $this->match_kw($msg, $date_keywords);
    }

    private function search_variable_courses($query) {
        $courses = $this->fetch_variable_courses_from_woocommerce();

        if (empty($courses)) {
            return 'Neste momento não há cursos com datas agendadas. Consulte todos os cursos em https://anjeformacao.pt ou contacte infoformacao@anje.pt';
        }

        $area_map = [
            'excel' => ['excel', 'folha de c', 'folha de cálculo', 'folha de calculo'],
            'powerbi' => ['power bi', 'powerbi', 'dashboard'],
            'ia' => ['inteligência artificial', ' claude', 'chatgpt', 'generativa', 'copilot'],
            'gestão' => ['gestão', 'lideran', 'liderança', 'equipa', 'tempo', 'projeto', 'produtividade', 'burnout'],
            'marketing' => ['marketing', 'digital', 'ecommerce', 'e-commerce', 'seo', 'influenc'],
            'vendas' => ['venda', 'vendas', 'comercial', 'neuromarketing', 'crm', 'vendedor'],
            'finanças' => ['financ', 'tesouraria', 'poupanca', 'sql', 'python'],
            'jurídico' => ['juridic', 'direito', 'rgpd', 'laboral', 'sociedade', 'branqueamento'],
            'comunicação' => ['comunicar', 'storytelling', 'apresentac', 'impacto', 'pnl'],
            'empreendedorismo' => ['empreend', 'negocio', 'startup', 'plano de neg', 'inovar'],
            'hotelaria' => ['hotelaria', 'turismo', 'higiene', 'alimentar'],
            'certificação' => ['certifica', 'icagile', 'coach', 'pnl practitioner'],
        ];

        $matched_area = null;
        foreach ($area_map as $area => $kws) {
            foreach ($kws as $kw) {
                if (mb_strpos($query, $kw) !== false) { $matched_area = $area; break 2; }
            }
        }

        $filtered = [];
        foreach ($courses as $c) {
            $titulo = mb_strtolower(html_entity_decode($c['titulo'], ENT_QUOTES, 'UTF-8'));
            if ($matched_area) {
                foreach ($area_map[$matched_area] as $kw) {
                    if (mb_strpos($titulo, $kw) !== false) {
                        $filtered[] = $c; break;
                    }
                }
            } else {
                $filtered[] = $c;
            }
        }

        if (empty($filtered)) {
            return 'Não encontrei cursos com datas para essa área. Pesquise por: IA, gestão, marketing, vendas, excel, powerbi...';
        }

        $response = '📅 **Cursos com datas agendadas**' . ($matched_area ? ' na área de ' . ucfirst($matched_area) : '') . " (" . count($filtered) . "):\n\n";
        $count = 0;
        foreach ($filtered as $c) {
            if ($count >= 10) { $response .= "\n_E mais " . (count($filtered) - 10) . " cursos com datas!_"; break; }
            $title = trim(preg_replace('/\\s+/', ' ', html_entity_decode($c['titulo'], ENT_QUOTES, 'UTF-8')));
            $response .= "• **{$title}** - {$c['preco']}";
            if (!empty($c['dates'])) {
                $response .= "\n  📌 Datas: {$c['dates']}";
            }
            $response .= "\n  {$c['url']}\n\n";
            $count++;
        }
        return $response;
    }

    /* ADMIN */

    public function add_admin_menu() {
        add_options_page('ChatBot ANJE Formação', 'ChatBot ANJE', 'manage_options', 'chatbot-anje-formacao', [$this, 'admin_page']);
    }

    public function register_settings() {
        register_setting('chatbot_anje_grp', $this->option_key, [$this, 'sanitize_settings']);
    }

    public function sanitize_settings($input) {
        $out = [];
        $out['chatbot_name'] = sanitize_text_field($input['chatbot_name'] ?? 'ChatBot ANJE');
        $out['backend_url'] = esc_url_raw($input['backend_url'] ?? '');
        $out['openrouter_key'] = sanitize_text_field($input['openrouter_key'] ?? '');
        $out['model'] = sanitize_text_field($input['model'] ?? 'openrouter/owl-alpha');
        $out['welcome_message'] = sanitize_textarea_field($input['welcome_message'] ?? '');
        $out['primary_color'] = sanitize_hex_color($input['primary_color'] ?? '#007bff');
        $out['position'] = in_array($input['position'] ?? '', ['left', 'right']) ? $input['position'] : 'right';
        $out['max_tokens'] = absint($input['max_tokens'] ?? 800);
        $out['request_timeout'] = absint($input['request_timeout'] ?? 60);
        $out['show_on_all_pages'] = ($input['show_on_all_pages'] ?? '') === 'yes' ? 'yes' : 'no';
        return $out;
    }

    public function admin_page() {
        $s = $this->get_settings();
        ?>
        <div class="wrap">
            <h1>🤖 ChatBot ANJE Formação</h1>
            <form method="post" action="options.php">
                <?php settings_fields('chatbot_anje_grp'); ?>
                <table class="form-table">
                    <tr><th><label>Nome do ChatBot</label></th>
                        <td><input type="text" name="chatbot_anje_formacao_settings[chatbot_name]" value="<?php echo esc_attr($s['chatbot_name']); ?>" class="regular-text"></td></tr>
                    <tr><th><label>Mensagem de Boas-vindas</label></th>
                        <td><textarea name="chatbot_anje_formacao_settings[welcome_message]" rows="4" class="large-text"><?php echo esc_textarea($s['welcome_message']); ?></textarea></td></tr>
                    <tr><th><label>URL do Backend Flask</label></th>
                        <td><input type="url" name="chatbot_anje_formacao_settings[backend_url]" value="<?php echo esc_attr($s['backend_url']); ?>" class="regular-text" placeholder="https://exemplo.com:5000">
                        <p class="description">URL do backend Flask (recomendado). Se vazio, usa API key direta ou fallback rule-based.</p></td></tr>
                    <tr><th><label>OpenRouter API Key</label></th>
                        <td><input type="password" name="chatbot_anje_formacao_settings[openrouter_key]" value="<?php echo esc_attr($s['openrouter_key']); ?>" class="regular-text" placeholder="sk-or-...">
                        <p class="description">Apenas se não usar backend. <a href="https://openrouter.ai/keys" target="_blank">Obter key</a></p></td></tr>
                    <tr><th><label>Modelo LLM</label></th>
                        <td><input type="text" name="chatbot_anje_formacao_settings[model]" value="<?php echo esc_attr($s['model']); ?>" class="regular-text"></td></tr>
                    <tr><th><label>Cor Principal</label></th>
                        <td><input type="color" name="chatbot_anje_formacao_settings[primary_color]" value="<?php echo esc_attr($s['primary_color']); ?>"></td></tr>
                    <tr><th><label>Posição</label></th>
                        <td><select name="chatbot_anje_formacao_settings[position]">
                            <option value="right" <?php selected($s['position'], 'right'); ?>>Direita</option>
                            <option value="left" <?php selected($s['position'], 'left'); ?>>Esquerda</option>
                        </select></td></tr>
                    <tr><th><label>Max Tokens</label></th>
                        <td><input type="number" name="chatbot_anje_formacao_settings[max_tokens]" value="<?php echo esc_attr($s['max_tokens']); ?>" min="200" max="4000" class="small-text"></td></tr>
                    <tr><th><label>Timeout (segundos)</label></th>
                        <td><input type="number" name="chatbot_anje_formacao_settings[request_timeout]" value="<?php echo esc_attr($s['request_timeout']); ?>" min="15" max="120" class="small-text"></td></tr>
                    <tr><th>Mostrar em todas as páginas</th>
                        <td><label><input type="checkbox" name="chatbot_anje_formacao_settings[show_on_all_pages]" value="yes" <?php checked($s['show_on_all_pages'], 'yes'); ?>> Sim</label></td></tr>
                </table>
                <?php submit_button('Guardar'); ?>
            </form>
            <hr>
            <h2>Estado</h2>
            <table class="widefat" style="max-width:600px">
                <thead><tr><th>Configuração</th><th>Estado</th></tr></thead>
                <tbody>
                    <tr><td>Backend URL</td><td><?php echo !empty($s['backend_url']) ? '<span style="color:green">✓ Configurado</span> <code>' . esc_html($s['backend_url']) . '</code>' : '<span style="color:orange">✓ Não configurado (usa fallback)</span>'; ?></td></tr>
                    <tr><td>OpenRouter API Key</td><td><?php echo !empty($s['openrouter_key']) ? '<span style="color:green">✓ Configurada</span>' : '<span style="color:orange">✓ Não configurada</span>'; ?></td></tr>
                </tbody>
            </table>
        </div>
        <?php
    }
}
