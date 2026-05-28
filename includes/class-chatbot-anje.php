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
        $welcome = $s['welcome_message'] ?: "Ol\u00e1! \ud83d\udc4b Sou o assistente virtual da ANJE Forma\u00e7\u00e3o.\n\nPosso ajudar com:\n\u2022 \ud83d\udcda Cursos\n\u2022 \ud83d\udcb0 Pre\u00e7os e datas\n\u2022 \ud83d\udc65 Equipa\n\u2022 \ud83d\udcde Contactos\n\nO que procura?";
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
                xhr.onerror=function(){remTyping();addMsg('Erro de liga\u00e7\u00e3o.','bot')};
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
            'Excel' => ['excel', 'folha de c', 'folha de c\u00e1lculo', 'folha de calculo'],
            'PowerBI' => ['power bi', 'powerbi', 'dashboard'],
            'IA' => ['intelig\u00eancia artificial', ' claude', 'chatgpt', 'generativa', 'copilot'],
            'Gest\u00e3o' => ['gest\u00e3o', 'lideran', 'lideran\u00e7a', 'equipa', 'tempo', 'projeto', 'produtividade', 'burnout'],
            'Marketing' => ['marketing', 'digital', 'ecommerce', 'e-commerce', 'seo', 'influenc'],
            'Vendas' => ['venda', 'vendas', 'comercial', 'neuromarketing', 'crm', 'vendedor'],
            'Finan\u00e7as' => ['financ', 'tesouraria', 'poupanca', 'sql', 'python'],
            'Jur\u00eddico' => ['juridic', 'direito', 'rgpd', 'laboral', 'sociedade', 'branqueamento'],
            'Comunica\u00e7\u00e3o' => ['comunicar', 'storytelling', 'apresentac', 'impacto', 'pnl'],
            'Empreendedorismo' => ['empreend', 'negocio', 'startup', 'plano de neg', 'inovar'],
            'Hotelaria' => ['hotelaria', 'turismo', 'higiene', 'alimentar'],
            'Certifica\u00e7\u00e3o' => ['certifica', 'icagile', 'coach', 'pnl practitioner'],
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

        return "\u00c9s o assistente virtual da ANJE Forma\u00e7\u00e3o (anjeformacao.pt).\n"
            . "\nSOBRE: ANJE - Associa\u00e7\u00e3o Nacional de Jovens Empres\u00e1rios, fundada 1986. ANJE Forma\u00e7\u00e3o presente nas 5 regi\u00f5es, certificada DGERT.\n"
            . "\nEQUIPA:\n"
            . "- Ana Jogo Mendes - Diretora\n"
            . "- Coordenadores: Cl\u00e1udia Almeida, Cristiana Moreira, Manuela Almeida, Vit\u00f3ria Pereira, Ana Rodrigues (Lisboa), Armanda \u00c2ngelo (Coimbra), C\u00e1tia Santos (Algarve), Patr\u00edcia Nobre (Alentejo)\n"
            . "- Teresa Miranda - Comunica\u00e7\u00e3o e Marketing\n"
            . "\n\u00d3RG\u00c3OS SOCIAIS:\n"
            . "- Presidente: Carlos Carvalho\n"
            . "- Vice-Presidentes: Nuno Malheiro, Filipa Pinto de Carvalho, Gon\u00e7alo Sim\u00f5es de Almeida\n"
            . "- Presidente Assembleia Geral: Miguel Moreira da Silva\n"
            . "- Presidente Conselho Fiscal: Catarina Azevedo\n"
            . "\nCONTACTOS: infoformacao@anje.pt | (+351) 220 108 074\n"
            . "MORADA: Rua Paulo da Gama - Casa do Farol, 4169-006 Porto\n"
            . "\nCURSOS: {$total} cursos ({$gratis} gratuitos)\n"
            . implode("\n", $course_lines) . "\n"
            . "\nFORMA\u00c7\u00c3O-A\u00c7\u00c3O: programa para micro/PME, 90% FSE, Norte/Centro/Alentejo, at\u00e9 250 colaboradores, Inova\u00e7\u00e3o/Transi\u00e7\u00e3o Digital/ESG. Vitoria Pereira e Cristiana Moreira. https://anjeformacao.pt/formacao-acao-pme/\n"
            . "\nREGRAS:\n"
            . "- Portugu\u00eas de Portugal\n"
            . "- Usa **negrita** para t\u00edtulos\n"
            . "- URLs completos: https://anjeformacao.pt/curso/...\n"
            . "- Lista TODOS os cursos dispon\u00edveis na \u00e1rea\n"
            . "- N\u00e3o listes cursos para perguntas sobre equipa/org\u00e3os\n"
            . "- Se n\u00e3o souberes: contacte infoformacao@anje.pt";
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
                    $price_display = '\u20ac' . number_format((float)$price, 2, ',', '.');
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

    private function get_fallback_courses() {
        return [
            ['titulo' => 'Como elaborar um Plano de Neg\u00f3cios | Forma\u00e7\u00e3o Ass\u00edncrona', 'preco' => '\u20ac150,00', 'url' => 'https://anjeformacao.pt/curso/como-elaborar-um-plano-de-negocios-formacao-assincrona/'],
            ['titulo' => 'Programa Executivo Vendedor de Alta Performance | Online', 'preco' => '\u20ac280,00', 'url' => 'https://anjeformacao.pt/curso/programa-executivo-vendedor-de-alta-performance/'],
            ['titulo' => 'RGPD para Gestores e Empreendedores | Forma\u00e7\u00e3o Ass\u00edncrona', 'preco' => '\u20ac180,00', 'url' => 'https://anjeformacao.pt/curso/rgpd-para-gestores-e-empreendedores-formacao-assincrona/'],
            ['titulo' => 'Direito das Sociedades \u2013 Constitui\u00e7\u00e3o de Empresas | Forma\u00e7\u00e3o Ass\u00edncrona', 'preco' => '\u20ac175,00', 'url' => 'https://anjeformacao.pt/curso/direito-das-sociedades-constituicao-de-empresas-formacao-assincrona/'],
            ['titulo' => 'Intelig\u00eancia Emocional para a Motiva\u00e7\u00e3o e Tomada de Decis\u00e3o | Online', 'preco' => '\u20ac480,00', 'url' => 'https://anjeformacao.pt/curso/inteligencia-emocional-para-a-motivacao-e-tomada-de-decisao-online/'],
            ['titulo' => 'Direito Laboral para Gestores e Empreendedores | Forma\u00e7\u00e3o Ass\u00edncrona', 'preco' => '\u20ac190,00', 'url' => 'https://anjeformacao.pt/curso/direito-laboral-para-gestores-e-empreendedores-formacao-assincrona/'],
            ['titulo' => 'Comunicar com Impacto | Online', 'preco' => '\u20ac150,00', 'url' => 'https://anjeformacao.pt/curso/comunicar-com-impacto-online/'],
            ['titulo' => 'Intelig\u00eancia Artificial Aplicada \u2013 Claude AI | Online', 'preco' => '\u20ac150,00', 'url' => 'https://anjeformacao.pt/curso/inteligencia-artificial-aplicada-claude-ai-online/'],
            ['titulo' => 'Inovar para Crescer: Ferramentas de Gest\u00e3o pr\u00e1ticas para PME | Online', 'preco' => '\u20ac180,00', 'url' => 'https://anjeformacao.pt/curso/inovar-para-crescer-ferramentas-de-gestao-praticas-para-pme-online/'],
            ['titulo' => 'Microsoft Copilot aplicado ao contexto profissional | Online', 'preco' => '\u20ac180,00', 'url' => 'https://anjeformacao.pt/curso/microsoft-copilot-aplicado-ao-contexto-profissional-online/'],
            ['titulo' => 'Intelig\u00eancia Artificial Aplicada ao Setor Imobili\u00e1rio | Online', 'preco' => '\u20ac150,00', 'url' => 'https://anjeformacao.pt/curso/inteligencia-artificial-aplicada-ao-setor-imobiliario-online/'],
            ['titulo' => 'Intelig\u00eancia Artificial Aplicada a \u00c1rea Comercial | Online', 'preco' => '\u20ac150,00', 'url' => 'https://anjeformacao.pt/curso/inteligencia-artificial-aplicada-a-area-comercial-online/'],
            ['titulo' => 'RGPC na Pr\u00e1tica | Online', 'preco' => '\u20ac150,00', 'url' => 'https://anjeformacao.pt/curso/rgpc-na-pratica-prevenir-riscos-cumprir-e-reforcar-a-integridade-online/'],
            ['titulo' => 'Felicidade nas Organiza\u00e7\u00f5es | Online', 'preco' => '\u20ac135,00', 'url' => 'https://anjeformacao.pt/curso/felicidade-nas-organizacoes-cultura-seguranca-psicologica-e-bem-estar-sustentavel-online/'],
            ['titulo' => 'Treino Intensivo em Lideran\u00e7a | Lisboa', 'preco' => '\u20ac1750,00', 'url' => 'https://anjeformacao.pt/curso/treino-intensivo-de-lideranca-lisboa/'],
            ['titulo' => 'Programa Executivo em Vendas | Norte e Online', 'preco' => '\u20ac1890,00', 'url' => 'https://anjeformacao.pt/curso/programa-executivo-em-vendas/'],
            ['titulo' => 'Lideran\u00e7a Anti-Burnout | Online', 'preco' => '\u20ac120,00', 'url' => 'https://anjeformacao.pt/curso/lideranca-anti-burnout-energia-limites-e-clareza-na-gestao-de-pessoas-online/'],
            ['titulo' => 'IA Generativa como Ferramenta de Otimiza\u00e7\u00e3o | Online', 'preco' => '\u20ac350,00', 'url' => 'https://anjeformacao.pt/curso/ia-generativa-como-ferramenta-de-optimizacao-dos-negocios-online/'],
            ['titulo' => 'Programa Executivo em Marketing Digital e E-commerce | Online', 'preco' => '\u20ac1800,00', 'url' => 'https://anjeformacao.pt/curso/programa-executivo-em-marketing-digital-e-e-commerce/'],
            ['titulo' => 'Gest\u00e3o de Projetos | Online', 'preco' => '\u20ac190,00', 'url' => 'https://anjeformacao.pt/curso/gestao-de-projetos-online/'],
            ['titulo' => 'Branqueamento de Capitais em Portugal | Online', 'preco' => '\u20ac150,00', 'url' => 'https://anjeformacao.pt/curso/branqueamento-de-capitais-em-portugal-online/'],
            ['titulo' => 'Conduzir ao Fecho da Venda | Online', 'preco' => '\u20ac135,00', 'url' => 'https://anjeformacao.pt/curso/conduzir-ao-fecho-da-venda-online/'],
            ['titulo' => 'Excel Avan\u00e7ado aplicado \u00e0 Gest\u00e3o | Algarve', 'preco' => 'Gratuito', 'url' => 'https://anjeformacao.pt/curso/excel-avancado-aplicado-a-gestao-ufcd-342219-algarve/'],
            ['titulo' => 'Cria\u00e7\u00e3o de dashboards din\u00e2micos com PowerBI | Algarve', 'preco' => 'Gratuito', 'url' => 'https://anjeformacao.pt/curso/criacao-de-dashboards-dinamicos-com-powerbi-ufcfd-341107-algarve/'],
            ['titulo' => 'Excel Inicia\u00e7\u00e3o | Algarve', 'preco' => 'Gratuito', 'url' => 'https://anjeformacao.pt/curso/excel-iniciacao-extra-catalogo-algarve/'],
            ['titulo' => 'Folha de c\u00e1lculo \u2013 utiliza\u00e7\u00e3o interm\u00e9dia | Centro', 'preco' => 'Gratuito', 'url' => 'https://anjeformacao.pt/curso/folha-de-calculo-utilizacao-intermedia-centro/'],
            ['titulo' => 'Produzir documentos em folha de c\u00e1lculo \u2013 UC 02775 | Norte', 'preco' => 'Gratuito', 'url' => 'https://anjeformacao.pt/curso/produzir-documentos-em-folha-de-calculo-uc-02775-norte-pessoas-2030/'],
            ['titulo' => 'Produzir documentos em folha de c\u00e1lculo \u2013 UC 02775 | Algarve', 'preco' => 'Gratuito', 'url' => 'https://anjeformacao.pt/curso/produzir-documentos-em-folha-de-calculo-uc-02775-algarve/'],
        ];
    }

    /* RULE-BASED FALLBACK */

    private function get_rule_based_response($msg) {
        $equipa = [
            ['nome' => 'Ana Jogo Mendes', 'cargo' => 'Diretora ANJE Forma\u00e7\u00e3o'],
            ['nome' => 'Cl\u00e1udia Almeida', 'cargo' => 'Coordenadora'],
            ['nome' => 'Cristiana Moreira', 'cargo' => 'Coordenadora'],
            ['nome' => 'Manuela Almeida', 'cargo' => 'Coordenadora'],
            ['nome' => 'Vit\u00f3ria Pereira', 'cargo' => 'Coordenadora'],
            ['nome' => 'Ana Rodrigues', 'cargo' => 'Coordenadora Lisboa'],
            ['nome' => 'Armanda \u00c2ngelo', 'cargo' => 'Coordenadora Coimbra'],
            ['nome' => 'C\u00e1tia Santos', 'cargo' => 'Coordenadora Algarve'],
            ['nome' => 'Patr\u00edcia Nobre', 'cargo' => 'Coordenadora Alentejo'],
            ['nome' => 'Sara Almeida', 'cargo' => 'Administrativa'],
            ['nome' => 'Susana Pereira', 'cargo' => 'Administrativa'],
            ['nome' => 'F\u00e1tima Pinto', 'cargo' => 'Administrativa Coimbra'],
            ['nome' => 'Teresa Miranda', 'cargo' => 'Comunica\u00e7\u00e3o e Marketing'],
        ];
        $orgaos = [
            ['nome' => 'Carlos Carvalho', 'cargo' => 'Presidente'],
            ['nome' => 'Nuno Malheiro', 'cargo' => 'Vice-Presidente'],
            ['nome' => 'Filipa Pinto de Carvalho', 'cargo' => 'Vice-Presidente'],
            ['nome' => 'Gon\u00e7alo Sim\u00f5es de Almeida', 'cargo' => 'Vice-Presidente'],
            ['nome' => 'Miguel Moreira da Silva', 'cargo' => 'Presidente da Assembleia Geral'],
            ['nome' => 'Catarina Azevedo', 'cargo' => 'Presidente do Conselho Fiscal'],
            ['nome' => 'Pedro Cardoso', 'cargo' => 'Vice-Presidente do Conselho Fiscal'],
            ['nome' => 'Sofia Xavier', 'cargo' => 'Vogal do Conselho Fiscal'],
        ];

        // Person search
        foreach (array_merge($equipa, $orgaos) as $p) {
            if (mb_strpos($msg, mb_strtolower($p['nome'])) !== false) {
                return "\ud83d\udc64 **{$p['nome']}** - {$p['cargo']}";
            }
        }

        if ($this->match_kw($msg, ['formacao acao', 'formacao-acao', 'forma\u00e7\u00e3o a\u00e7\u00e3o', 'formacao acao'])) {
            return "\ud83d\udccb **Forma\u00e7\u00e3o-A\u00e7\u00e3o para PME:**\n\nPrograma de forma\u00e7\u00e3o \u00e0 medida para micro, pequenas e m\u00e9dias empresas.\n\n\ud83d\udcb0 Financiamento: 90% FSE, 10% empresa\n\ud83d\udccd Regi\u00f5es: Norte, Centro, Alentejo\n\ud83c\udfe2 Destinat\u00e1rios: Micro/PME at\u00e9 250 colaboradores\n\ud83d\udccc \u00c1reas: Inova\u00e7\u00e3o, Transi\u00e7\u00e3o Digital, ESG\n\n\ud83d\udc69\u200d\ud83d\udcbc Respons\u00e1veis:\n\u2022 Vit\u00f3ria Pereira - vitoriapereira@anje.pt\n\u2022 Cristiana Moreira - cristianamoreira@anje.pt\n\n\u2139\ufe0f https://anjeformacao.pt/formacao-acao-pme/\n\ud83d\udce7 infoformacao@anje.pt";
        }

        if ($this->match_kw($msg, ['equipa', 'equipe', 'staff', 'funcionarios', 'quem trabalha', 'diretor', 'diretoras'])) {
            $r = "**Equipa da ANJE Forma\u00e7\u00e3o:**\n\n";
            foreach ($equipa as $p) { $r .= "\u2022 {$p['nome']} - {$p['cargo']}\n"; }
            return $r;
        }

        if ($this->match_kw($msg, ['orgaos', 'orgaos', 'org\u00e3o', 'org\u00e3o', 'conselho fiscal', 'assembleia', 'mesa', 'fiscal'])) {
            $r = "**\u00d3rg\u00e3os Sociais da ANJE:**\n\n";
            foreach ($orgaos as $p) { $r .= "\u2022 {$p['nome']} - {$p['cargo']}\n"; }
            return $r;
        }

        if ($this->match_kw($msg, ['presidente', 'quem e o presidente', 'quem \u00e9 o presidente'])) {
            return 'O presidente da ANJE \u00e9 **Carlos Carvalho**.';
        }

        if ($this->match_kw($msg, ['contacto', 'contatos', 'email', 'telefone', 'morada', 'endereco', 'endere\u00e7o', 'onde fica', 'localiza\u00e7\u00e3o'])) {
            return "\ud83d\udcde **Contactos da ANJE Forma\u00e7\u00e3o:**\n\n\ud83d\udce7 infoformacao@anje.pt\n\ud83d\udcf1 (+351) 220 108 074\n\ud83d\udccd Rua Paulo da Gama - Casa do Farol, 4169-006 Porto";
        }

        if ($this->match_kw($msg, ['curso', 'cursos', 'formacao', 'formacoes', 'forma\u00e7\u00e3o', 'forma\u00e7\u00f5es', 'treinamento', 'workshop', 'excel', 'powerbi', 'power bi', 'gratuito', 'gratuitos', 'gratis', 'desempregado', 'desempregados'])) {
            return $this->search_courses($msg);
        }

        return "N\u00e3o tenho essa informa\u00e7\u00e3o espec\u00edfica. Posso ajudar com:\n\n\u2022 \ud83d\udcda **Cursos e forma\u00e7\u00f5es** - Pesquisa por \u00e1rea (IA, gest\u00e3o, marketing, vendas, excel, powerbi...)\n\u2022 \ud83d\udcb0 Pre\u00e7os e datas\n\u2022 \ud83d\udc65 **Equipa**\n\u2022 \ud83d\udccb **\u00d3rg\u00e3os sociais**\n\u2022 \ud83d\udcde **Contactos**\n\nOu contacte: infoformacao@anje.pt";
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
            'excel' => ['excel', 'folha de c', 'folha de c\u00e1lculo', 'folha de calculo'],
            'powerbi' => ['power bi', 'powerbi', 'dashboard'],
            'ia' => ['intelig\u00eancia artificial', ' claude', 'chatgpt', 'generativa', 'copilot'],
            'gest\u00e3o' => ['gest\u00e3o', 'lideran', 'lideran\u00e7a', 'equipa', 'tempo', 'projeto', 'produtividade', 'burnout'],
            'marketing' => ['marketing', 'digital', 'ecommerce', 'e-commerce', 'seo', 'influenc'],
            'vendas' => ['venda', 'vendas', 'comercial', 'neuromarketing', 'crm', 'vendedor'],
            'finan\u00e7as' => ['financ', 'tesouraria', 'poupanca', 'sql', 'python'],
            'jur\u00eddico' => ['juridic', 'direito', 'rgpd', 'laboral', 'sociedade', 'branqueamento'],
            'comunica\u00e7\u00e3o' => ['comunicar', 'storytelling', 'apresentac', 'impacto', 'pnl'],
            'empreendedorismo' => ['empreend', 'negocio', 'startup', 'plano de neg', 'inovar'],
            'hotelaria' => ['hotelaria', 'turismo', 'higiene', 'alimentar'],
            'certifica\u00e7\u00e3o' => ['certifica', 'icagile', 'coach', 'pnl practitioner'],
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
            return 'N\u00e3o encontrei cursos para essa \u00e1rea. Pesquise por: IA, gest\u00e3o, marketing, vendas, excel, powerbi, jur\u00eddico, comunica\u00e7\u00e3o, empreendedorismo...';
        }

        $response = 'Encontrei **' . count($filtered) . ' cursos**' . ($matched_area ? ' na \u00e1rea de ' . ucfirst($matched_area) : '') . ":\n\n";
        $count = 0;
        foreach ($filtered as $c) {
            if ($count >= 10) { $response .= "\n_E mais " . (count($filtered) - 10) . " cursos!_"; break; }
            $title = trim(preg_replace('/\s+/', ' ', html_entity_decode($c['titulo'], ENT_QUOTES, 'UTF-8')));
            $response .= "\u2022 **{$title}** - {$c['preco']}\n  {$c['url']}\n\n";
            $count++;
        }
        return $response;
    }

    /* ADMIN */

    public function add_admin_menu() {
        add_options_page('ChatBot ANJE Forma\u00e7\u00e3o', 'ChatBot ANJE', 'manage_options', 'chatbot-anje-formacao', [$this, 'admin_page']);
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
            <h1>\ud83e\udd16 ChatBot ANJE Forma\u00e7\u00e3o</h1>
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
                        <p class="description">Apenas se n\u00e3o usar backend. <a href="https://openrouter.ai/keys" target="_blank">Obter key</a></p></td></tr>
                    <tr><th><label>Modelo LLM</label></th>
                        <td><input type="text" name="chatbot_anje_formacao_settings[model]" value="<?php echo esc_attr($s['model']); ?>" class="regular-text"></td></tr>
                    <tr><th><label>Cor Principal</label></th>
                        <td><input type="color" name="chatbot_anje_formacao_settings[primary_color]" value="<?php echo esc_attr($s['primary_color']); ?>"></td></tr>
                    <tr><th><label>Posi\u00e7\u00e3o</label></th>
                        <td><select name="chatbot_anje_formacao_settings[position]">
                            <option value="right" <?php selected($s['position'], 'right'); ?>>Direita</option>
                            <option value="left" <?php selected($s['position'], 'left'); ?>>Esquerda</option>
                        </select></td></tr>
                    <tr><th><label>Max Tokens</label></th>
                        <td><input type="number" name="chatbot_anje_formacao_settings[max_tokens]" value="<?php echo esc_attr($s['max_tokens']); ?>" min="200" max="4000" class="small-text"></td></tr>
                    <tr><th><label>Timeout (segundos)</label></th>
                        <td><input type="number" name="chatbot_anje_formacao_settings[request_timeout]" value="<?php echo esc_attr($s['request_timeout']); ?>" min="15" max="120" class="small-text"></td></tr>
                    <tr><th>Mostrar em todas as p\u00e1ginas</th>
                        <td><label><input type="checkbox" name="chatbot_anje_formacao_settings[show_on_all_pages]" value="yes" <?php checked($s['show_on_all_pages'], 'yes'); ?>> Sim</label></td></tr>
                </table>
                <?php submit_button('Guardar'); ?>
            </form>
            <hr>
            <h2>Estado</h2>
            <table class="widefat" style="max-width:600px">
                <thead><tr><th>Configura\u00e7\u00e3o</th><th>Estado</th></tr></thead>
                <tbody>
                    <tr><td>Backend URL</td><td><?php echo !empty($s['backend_url']) ? '<span style="color:green">\u2713 Configurado</span> <code>' . esc_html($s['backend_url']) . '</code>' : '<span style="color:orange">\u2713 N\u00e3o configurado (usa fallback)</span>'; ?></td></tr>
                    <tr><td>OpenRouter API Key</td><td><?php echo !empty($s['openrouter_key']) ? '<span style="color:green">\u2713 Configurada</span>' : '<span style="color:orange">\u2713 N\u00e3o configurada</span>'; ?></td></tr>
                </tbody>
            </table>
        </div>
        <?php
    }
}
