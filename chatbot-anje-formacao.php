<?php
/**
 * Plugin Name: ChatBot ANJE Formação
 * Description: Chatbot inteligente para anje.formacao.pt - Sem LLM, respostas diretas
 * Version: 2.0.0
 * Author: Pedro Silva
 * Text Domain: chatbot-anje-formacao
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
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_chatbot_anje_chat', [$this, 'handle_chat']);
        add_action('wp_ajax_nopriv_chatbot_anje_chat', [$this, 'handle_chat']);
    }

    /* ================================================================
     * SETTINGS
     * ================================================================ */

    private function get_settings() {
        $defaults = [
            'chatbot_name' => 'ChatBot ANJE',
            'welcome_message' => '',
            'primary_color' => '#007bff',
            'position' => 'right',
            'show_on_all_pages' => 'yes',
        ];
        $settings = get_option($this->option_key, []);
        return wp_parse_args($settings, $defaults);
    }

    /* ================================================================
     * ASSETS
     * ================================================================ */

    public function enqueue_assets() {
        $s = $this->get_settings();

        wp_register_style('chatbot-af-css', false);
        wp_enqueue_style('chatbot-af-css');
        wp_add_inline_style('chatbot-af-css', $this->get_css($s));

        // Add chatbot HTML and JS via wp_enqueue_scripts
        add_action('wp_enqueue_scripts', [$this, 'inject_chatbot_html'], 999);
    }

    public function inject_chatbot_html() {
        $s = $this->get_settings();
        $name = esc_html($s['chatbot_name']);
        $welcome = $s['welcome_message'] ?: "Olá! 👋 Sou o assistente virtual da ANJE Formação.\n\nPosso ajudar com:\n• 📚 Cursos e formações\n• 💰 Preços e datas\n• 👥 Equipa\n• 📞 Contactos\n\nO que procura?";
        $welcome = html_entity_decode($welcome, ENT_QUOTES, 'UTF-8');
        $ajax = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('chatbot_af_nonce');

        $html = '
        <div id="chatbot-af-widget">
<button id="chatbot-af-toggle" aria-label="' . $name . '" style="width:90px;height:90px;border-radius:50%;border:none;padding:0;overflow:hidden;background:none;cursor:pointer;box-shadow:0 4px 16px rgba(0,0,0,.2);"><img src="' . plugin_dir_url(__FILE__) . 'assets/BOT.jpg" alt="' . $name . '" style="width:90px;height:90px;object-fit:contain;display:block;"></button>
            <div id="chatbot-af-window" style="display:none;">
                <div id="chatbot-af-header">
                    <div id="chatbot-af-header-text">
                        <strong>' . $name . '</strong>
                        <small>Online</small>
                    </div>
                    <button id="chatbot-af-close" aria-label="Fechar">&#10005;</button>
                </div>
                <div id="chatbot-af-messages"></div>
                <div id="chatbot-af-input-area">
                    <input type="text" id="chatbot-af-input" placeholder="Escreva a sua pergunta..." maxlength="500">
                    <button id="chatbot-af-send" aria-label="Enviar">&#10148;</button>
                </div>
            </div>
        </div>';

        $js = '
        (function(){
            var ajaxUrl=' . json_encode($ajax) . ';
            var nonce=' . json_encode($nonce) . ';
            var welcome=' . json_encode($welcome) . ';
            var busy=false,shown=false;
            var toggle=document.getElementById("chatbot-af-toggle");
            var win=document.getElementById("chatbot-af-window");
            var input=document.getElementById("chatbot-af-input");
            var sendBtn=document.getElementById("chatbot-af-send");
            var msgs=document.getElementById("chatbot-af-messages");
            if(!toggle)return;

            toggle.addEventListener("click",function(){
                if(win.style.display==="flex"){win.style.display="none";}
                else{win.style.display="flex";input.focus();if(!shown&&welcome){addMsg(welcome,"bot");shown=true;}}
            });
            document.getElementById("chatbot-af-close").addEventListener("click",function(){win.style.display="none";});
            sendBtn.addEventListener("click",sendMsg);
            input.addEventListener("keypress",function(e){if(e.key==="Enter")sendMsg();});
            document.addEventListener("keydown",function(e){if(e.key==="Escape"&&win.style.display==="flex")win.style.display="none";});

            function sendMsg(){
                var msg=input.value.trim();
                if(!msg||busy)return;busy=true;sendBtn.disabled=true;
                addMsg(msg,"user");input.value="";addTyping();
                var xhr=new XMLHttpRequest();
                xhr.open("POST",ajaxUrl);
                xhr.setRequestHeader("Content-Type","application/x-www-form-urlencoded");
                xhr.timeout=15000;
                xhr.onload=function(){
                    removeTyping();
                    try{var r=JSON.parse(xhr.responseText);
                        if(r.success){addMsg(r.data.response||"Erro.","bot");}
                        else{addMsg("Erro: "+(r.data||"Desconhecido"),"bot");}
                    }catch(e){addMsg("Erro ao processar.","bot");}
                };
                xhr.onerror=function(){removeTyping();addMsg("Erro de ligação.","bot");};
                xhr.ontimeout=function(){removeTyping();addMsg("Timeout. Tente novamente.","bot");};
                xhr.onreadystatechange=function(){if(xhr.readyState===4){busy=false;sendBtn.disabled=false;input.focus();}};
                xhr.send("action=chatbot_anje_chat&message="+encodeURIComponent(msg)+"&nonce="+nonce);
            }
            function addMsg(text,type){
                var d=document.createElement("div");
                d.className="caf-msg caf-"+type;
                var html=text
                    .replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;")
                    .replace(/\*\*([^*]+)\*\*/g,"<strong>$1</strong>")
                    .replace(/\[([^\]]+)\]\((https?:\/\/[^)]+)\)/g,\'<a href="$2" target="_blank" rel="noopener">$1</a>\')
                    .replace(/(https?:\/\/[^<\s\)]+)/g,\'<a href="$1" target="_blank" rel="noopener">$1</a>\')
                    .replace(/\n/g,"<br>");
                d.innerHTML=html;msgs.appendChild(d);d.scrollIntoView({behavior:"smooth"});
            }
            function addTyping(){
                var d=document.createElement("div");d.id="chatbot-af-typing";
                d.className="caf-msg caf-bot";d.textContent="A escrever...";msgs.appendChild(d);
            }
            function removeTyping(){var t=document.getElementById("chatbot-af-typing");if(t)t.remove();}
        })();';

        echo $html;
        wp_add_inline_script('jquery', $js);
    }

    private function get_css($s) {
        $c = esc_attr($s['primary_color']);
        $pos = esc_attr($s['position']);
        $side = ($pos === 'left') ? 'left:20px;' : 'right:20px;';
        $winSide = ($pos === 'left') ? 'left:0' : 'right:0';
        return "
        #chatbot-af-widget{position:fixed;bottom:20px;{$side}z-index:999999;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}
        #chatbot-af-toggle{padding:0;border:none;background:none;cursor:pointer;transition:transform .2s}
        #chatbot-af-toggle:hover{transform:scale(1.08)}
        #chatbot-af-window{position:absolute;bottom:105px;{$winSide};width:400px;height:580px;background:#fff;border-radius:16px;box-shadow:0 12px 48px rgba(0,0,0,.18);display:none;flex-direction:column;overflow:hidden;animation:cafSlide .25s ease}
        @keyframes cafSlide{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
        #chatbot-af-header{background:{$c};color:#fff;padding:14px 16px;display:flex;align-items:center;gap:10px}
        #chatbot-af-header-text{flex:1}
        #chatbot-af-header strong{display:block;font-size:14px;font-weight:600}
        #chatbot-af-header small{font-size:11px;opacity:.85}
        #chatbot-af-close{background:none;border:none;color:#fff;font-size:22px;cursor:pointer;opacity:.7;padding:4px;line-height:1}
        #chatbot-af-close:hover{opacity:1}
        #chatbot-af-messages{flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:10px;background:#f0f2f5}
        .caf-msg{max-width:88%;padding:10px 14px;border-radius:14px;font-size:13.5px;line-height:1.5;word-wrap:break-word;box-shadow:0 1px 2px rgba(0,0,0,.06)}
        .caf-bot{background:#fff;color:#222;align-self:flex-start;border-bottom-left-radius:4px}
        .caf-user{background:{$c};color:#fff;align-self:flex-end;border-bottom-right-radius:4px}
        .caf-msg a{color:#0066ee!important;text-decoration:underline!important;font-weight:600!important}
        .caf-msg strong{color:#1a1a2e}
        .caf-msg ul{margin:6px 0;padding-left:18px}
        .caf-msg li{margin-bottom:4px}
        #chatbot-af-input-area{display:flex;padding:10px 12px;background:#fff;border-top:1px solid #e8e8e8;gap:8px}
        #chatbot-af-input{flex:1;padding:10px 14px;border:1px solid #ddd;border-radius:20px;outline:none;font-size:13.5px;font-family:inherit}
        #chatbot-af-input:focus{border-color:{$c}}
        #chatbot-af-send{width:42px;height:42px;border-radius:50%;border:none;background:{$c};color:#fff;cursor:pointer;font-size:18px;display:flex;align-items:center;justify-content:center}
        #chatbot-af-send:disabled{background:#ccc;cursor:not-allowed}
        @media(max-width:480px){chatbot-af-window{width:calc(100vw - 16px);height:calc(100vh - 120px)}}
        ";
    }

    /* ================================================================
     * HANDLE CHAT - Respostas diretas sem LLM
     * ================================================================ */

    public function handle_chat() {
        if (!check_ajax_referer('chatbot_af_nonce', 'nonce', false)) {
            wp_send_json_error('Token inválido', 403);
        }
        $msg = sanitize_text_field($_POST['message'] ?? '');
        if (empty($msg)) wp_send_json_error('Vazio', 400);

        $response = $this->get_response(strtolower(trim($msg)));
        wp_send_json_success(['response' => $response]);
    }

    /* ================================================================
     * COURSES DATA - Hardcoded from site scraping
     * ================================================================ */

    private function get_courses() {
        // Try to get from WooCommerce via WP_Query (no auth needed)
        $cached = get_transient('chatbot_af_courses_cache');
        if ($cached !== false) {
            return $cached;
        }

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

                $courses[] = [
                    'titulo' => $name,
                    'preco' => $price_display,
                    'data' => '',
                    'url' => $url,
                ];
            }
            wp_reset_postdata();
        }

        // If WP_Query returned courses, cache them
        if (!empty($courses)) {
            set_transient('chatbot_af_courses_cache', $courses, HOUR_IN_SECONDS);
            return $courses;
        }

        // Fallback to hardcoded courses
        return $this->get_fallback_courses();
    }

    private function get_fallback_courses() {
        return [
            ['titulo' => 'Como elaborar um Plano de Negócios | Formação Assíncrona', 'preco' => '€150,00', 'data' => '25-05-2026', 'url' => 'https://anjeformacao.pt/curso/como-elaborar-um-plano-de-negocios-formacao-assincrona/'],
            ['titulo' => 'Programa Executivo Vendedor de Alta Performance | Online', 'preco' => '€280,00', 'data' => '25-05-2026', 'url' => 'https://anjeformacao.pt/curso/programa-executivo-vendedor-de-alta-performance/'],
            ['titulo' => 'RGPD para Gestores e Empreendedores | Formação Assíncrona', 'preco' => '€180,00', 'data' => '26-05-2026', 'url' => 'https://anjeformacao.pt/curso/rgpd-para-gestores-e-empreendedores-formacao-assincrona/'],
            ['titulo' => 'Direito das Sociedades – Constituição de Empresas | Formação Assíncrona', 'preco' => '€175,00', 'data' => '26-05-2026', 'url' => 'https://anjeformacao.pt/curso/direito-das-sociedades-constituicao-de-empresas-formacao-assincrona/'],
            ['titulo' => 'Gestão de correio eletrónico e pesquisa de informação na web – UFCD 0693 | Norte', 'preco' => 'Gratuito', 'data' => '27-05-2026', 'url' => 'https://anjeformacao.pt/curso/gestao-de-correio-eletronico-e-pesquisa-de-informacao-na-web-ufcd-0693-norte-pessoas-2030/'],
            ['titulo' => 'Inteligência Emocional para a Motivação e Tomada de Decisão | Online', 'preco' => '€480,00', 'data' => '28-05-2026', 'url' => 'https://anjeformacao.pt/curso/inteligencia-emocional-para-a-motivacao-e-tomada-de-decisao-online/'],
            ['titulo' => 'Executar operações de controlo de tesouraria – UC 02777 | Norte', 'preco' => 'Gratuito', 'data' => '28-05-2026', 'url' => 'https://anjeformacao.pt/curso/executar-operacoes-de-controlo-de-tesouraria-uc-02777-norte-pessoas-2030/'],
            ['titulo' => 'Implementar práticas de gestão do tempo e organização do trabalho – UC00458 | Norte', 'preco' => 'Gratuito', 'data' => '01-06-2026', 'url' => 'https://anjeformacao.pt/curso/implementar-praticas-de-gestao-do-tempo-e-organizacao-do-trabalho-uc00458-norte-pessoas-2030/'],
            ['titulo' => 'Direito Laboral para Gestores e Empreendedores | Formação Assíncrona', 'preco' => '€190,00', 'data' => '02-06-2026', 'url' => 'https://anjeformacao.pt/curso/direito-laboral-para-gestores-e-empreendedores-formacao-assincrona/'],
            ['titulo' => 'Comunicar com Impacto | Online', 'preco' => '€150,00', 'data' => '02-06-2026', 'url' => 'https://anjeformacao.pt/curso/comunicar-com-impacto-online/'],
            ['titulo' => 'Inteligência Artificial Aplicada – Claude AI | Online', 'preco' => '€150,00', 'data' => '16-06-2026', 'url' => 'https://anjeformacao.pt/curso/inteligencia-artificial-aplicada-claude-ai-online/'],
            ['titulo' => 'Plano de negócio – criação de micronegócios – UFCD 7854 | Norte', 'preco' => 'Gratuito', 'data' => '25-06-2026', 'url' => 'https://anjeformacao.pt/curso/plano-de-negocio-criacao-de-micronegocios-ufcd-7854-norte-pessoas-2030/'],
            ['titulo' => 'Gerir plataformas de CRM – UC 02760 | Norte', 'preco' => 'Gratuito', 'data' => '25-06-2026', 'url' => 'https://anjeformacao.pt/curso/gerir-plataformas-de-crm-uc-02760-norte-pessoas-2030/'],
            ['titulo' => 'Inovar para Crescer: Ferramentas de Gestão práticas para PME | Online', 'preco' => '€180,00', 'data' => '29-06-2026', 'url' => 'https://anjeformacao.pt/curso/inovar-para-crescer-ferramentas-de-gestao-praticas-para-pme-online/'],
            ['titulo' => 'Microsoft Copilot aplicado ao contexto profissional | Online', 'preco' => '€180,00', 'data' => '30-06-2026', 'url' => 'https://anjeformacao.pt/curso/microsoft-copilot-aplicado-ao-contexto-profissional-online/'],
            ['titulo' => 'Realizar prospeção comercial – UC 01159 | Norte', 'preco' => 'Gratuito', 'data' => '01-07-2026', 'url' => 'https://anjeformacao.pt/curso/realizar-prospeção-comercial-e-planear-a-venda-atraves-de-meios-fisicos-e-digitais-uc-01159-norte-pessoas-2030/'],
            ['titulo' => 'Produzir documentos em folha de cálculo – UC 02775 | Norte', 'preco' => 'Gratuito', 'data' => '07-07-2026', 'url' => 'https://anjeformacao.pt/curso/produzir-documentos-em-folha-de-calculo-uc-02775-norte-pessoas-2030/'],
            ['titulo' => 'Inteligência Artificial Aplicada ao Setor Imobiliário | Online', 'preco' => '€150,00', 'data' => '13-07-2026', 'url' => 'https://anjeformacao.pt/curso/inteligencia-artificial-aplicada-ao-setor-imobiliario-online/'],
            ['titulo' => 'Processar a venda através de meios interativos – UC02681 | Norte', 'preco' => 'Gratuito', 'data' => '14-07-2026', 'url' => 'https://anjeformacao.pt/curso/processar-a-venda-atraves-de-meios-interativos-e-ou-digitais-uc02681-norte-pessoas-2030/'],
            ['titulo' => 'Inteligência Artificial Aplicada a Área Comercial | Online', 'preco' => '€150,00', 'data' => '14-09-2026', 'url' => 'https://anjeformacao.pt/curso/inteligencia-artificial-aplicada-a-area-comercial-online/'],
            ['titulo' => 'RGPC na Prática | Online', 'preco' => '€150,00', 'data' => '21-09-2026', 'url' => 'https://anjeformacao.pt/curso/rgpc-na-pratica-prevenir-riscos-cumprir-e-reforcar-a-integridade-online/'],
            ['titulo' => 'Felicidade nas Organizações | Online', 'preco' => '€135,00', 'data' => '22-09-2026', 'url' => 'https://anjeformacao.pt/curso/felicidade-nas-organizacoes-cultura-seguranca-psicologica-e-bem-estar-sustentavel-online/'],
            ['titulo' => 'Utilizar aplicações digitais – UC00294 | Norte', 'preco' => 'Gratuito', 'data' => '22-09-2026', 'url' => 'https://anjeformacao.pt/curso/utilizar-aplicacoes-digitais-de-produtividade-colaboracao-e-comunicacao-uc00294-norte-pessoas-2030/'],
            ['titulo' => 'Treino Intensivo em Liderança | Lisboa', 'preco' => '€1750,00', 'data' => '', 'url' => 'https://anjeformacao.pt/curso/treino-intensivo-de-lideranca-lisboa/'],
            ['titulo' => 'Programa Executivo em Vendas | Norte e Online', 'preco' => '€1890,00', 'data' => '', 'url' => 'https://anjeformacao.pt/curso/programa-executivo-em-vendas/'],
            ['titulo' => 'Liderança Anti-Burnout | Online', 'preco' => '€120,00', 'data' => '', 'url' => 'https://anjeformacao.pt/curso/lideranca-anti-burnout-energia-limites-e-clareza-na-gestao-de-pessoas-online/'],
            ['titulo' => 'IA Generativa como Ferramenta de Otimização | Online', 'preco' => '€350,00', 'data' => '', 'url' => 'https://anjeformacao.pt/curso/ia-generativa-como-ferramenta-de-optimizacao-dos-negocios-online/'],
            ['titulo' => 'Liderança e motivação de equipas – UFCD 5436', 'preco' => 'Gratuito', 'data' => '', 'url' => 'https://anjeformacao.pt/curso/lideranca-e-motivacao-de-equipas-ufcd-5436-alentejo/'],
            ['titulo' => 'Gestão do Tempo e Organização do Trabalho – UFCD 0382', 'preco' => 'Gratuito', 'data' => '', 'url' => 'https://anjeformacao.pt/curso/gestao-do-tempo-e-organizacao-do-trabalho-ufcd-0382-norte-pessoas-2030/'],
            ['titulo' => 'Programa Executivo em Marketing Digital e E-commerce | Online', 'preco' => '€1800,00', 'data' => '', 'url' => 'https://anjeformacao.pt/curso/programa-executivo-em-marketing-digital-e-e-commerce/'],
            ['titulo' => 'Gestão de Projetos | Online', 'preco' => '€190,00', 'data' => '', 'url' => 'https://anjeformacao.pt/curso/gestao-de-projetos-online/'],
            ['titulo' => 'Programa Executivo em Marketing Digital e E-business | Norte', 'preco' => '€3625,00', 'data' => '', 'url' => 'https://anjeformacao.pt/curso/programa-executivo-em-marketing-digital-e-e-business-norte/'],
            ['titulo' => 'Conduzir ao Fecho da Venda | Online', 'preco' => '€135,00', 'data' => '', 'url' => 'https://anjeformacao.pt/curso/conduzir-ao-fecho-da-venda-online/'],
            ['titulo' => 'Gestão de equipas – UFCD 7844', 'preco' => 'Gratuito', 'data' => '', 'url' => 'https://anjeformacao.pt/curso/gestao-de-equipas-ufcd-7844-alentejo-pessoas-2030/'],
            ['titulo' => 'Liderança e Ferramentas para a Transformação Digital | Norte', 'preco' => 'Gratuito', 'data' => '', 'url' => 'https://anjeformacao.pt/curso/lideranca-e-ferramentas-para-a-transformacao-digital-norte/'],
            ['titulo' => 'Liderança e Ferramentas para a Transformação Digital | Alentejo', 'preco' => 'Gratuito', 'data' => '', 'url' => 'https://anjeformacao.pt/curso/lideranca-e-ferramentas-para-a-transformacao-digital-alentejo/'],
            ['titulo' => 'Liderança e Ferramentas para a Transformação Digital | Lisboa', 'preco' => 'Gratuito', 'data' => '', 'url' => 'https://anjeformacao.pt/curso/lideranca-e-ferramentas-para-a-transformacao-digital-lisboa/'],
            ['titulo' => 'Liderança e Ferramentas para a Transformação Digital | Algarve', 'preco' => 'Gratuito', 'data' => '', 'url' => 'https://anjeformacao.pt/curso/lideranca-e-ferramentas-para-a-transformacao-digital-algarve/'],
            ['titulo' => 'Branqueamento de Capitais em Portugal | Online', 'preco' => '€150,00', 'data' => '', 'url' => 'https://anjeformacao.pt/curso/branqueamento-de-capitais-em-portugal-online/'],
            ['titulo' => 'PWIT & ANJE Leadership Development Program', 'preco' => '€1050,00', 'data' => '', 'url' => 'https://anjeformacao.pt/curso/pwit-anje-leadership-development-program-norte/'],
            ['titulo' => 'Excel Avançado aplicado à Gestão | Algarve', 'preco' => 'Gratuito', 'data' => '', 'url' => 'https://anjeformacao.pt/curso/excel-avancado-aplicado-a-gestao-ufcd-342219-algarve/'],
            ['titulo' => 'Criação de dashboards dinâmicos com PowerBI | Algarve', 'preco' => 'Gratuito', 'data' => '', 'url' => 'https://anjeformacao.pt/curso/criacao-de-dashboards-dinamicos-com-powerbi-ufcfd-341107-algarve/'],
            ['titulo' => 'Excel Iniciação | Algarve', 'preco' => 'Gratuito', 'data' => '', 'url' => 'https://anjeformacao.pt/curso/excel-iniciacao-extra-catalogo-algarve/'],
            ['titulo' => 'Folha de cálculo – utilização intermédia | Centro', 'preco' => 'Gratuito', 'data' => '', 'url' => 'https://anjeformacao.pt/curso/folha-de-calculo-utilizacao-intermedia-centro/'],
            ['titulo' => 'Produzir documentos em folha de cálculo – UC 02775 | Algarve', 'preco' => 'Gratuito', 'data' => '', 'url' => 'https://anjeformacao.pt/curso/produzir-documentos-em-folha-de-calculo-uc-02775-algarve/'],
            ['titulo' => 'Gestão de correio eletrónico e pesquisa de informação na web – UFCD 0693 | Algarve', 'preco' => 'Gratuito', 'data' => '', 'url' => 'https://anjeformacao.pt/curso/gestao-de-correio-eletronico-e-pesquisa-de-informacao-na-web/'],
            ['titulo' => 'Produzir documentos em folha de cálculo – UC 02775 | Norte (extra)', 'preco' => 'Gratuito', 'data' => '', 'url' => 'https://anjeformacao.pt/curso/produzir-documentos-em-folha-de-calculo-uc-02775-norte/'],
        ];
    }

    /* ================================================================
     * TEAM DATA
     * ================================================================ */

    private function get_equipa() {
        return [
            'diretoras' => [
                ['nome' => 'Ana Jogo Mendes', 'cargo' => 'Diretora ANJE Formação'],
            ],
            'coordenadores' => [
                ['nome' => 'Cláudia Almeida', 'cargo' => 'Coordenadora'],
                ['nome' => 'Cristiana Moreira', 'cargo' => 'Coordenadora'],
                ['nome' => 'Manuela Almeida', 'cargo' => 'Coordenadora'],
                ['nome' => 'Vitória Pereira', 'cargo' => 'Coordenadora'],
                ['nome' => 'Ana Rodrigues', 'cargo' => 'Coordenadora Lisboa'],
                ['nome' => 'Armanda Ângelo', 'cargo' => 'Coordenadora Coimbra'],
                ['nome' => 'Cátia Santos', 'cargo' => 'Coordenadora Algarve'],
                ['nome' => 'Patrícia Nobre', 'cargo' => 'Coordenadora Alentejo'],
            ],
            'administrativos' => [
                ['nome' => 'Sara Almeida', 'cargo' => 'Administrativa'],
                ['nome' => 'Susana Pereira', 'cargo' => 'Administrativa'],
                ['nome' => 'Fátima Pinto', 'cargo' => 'Administrativa Coimbra'],
            ],
            'comunicacao' => [
                ['nome' => 'Teresa Miranda', 'cargo' => 'Comunicação e Marketing'],
            ],
        ];
    }

    /* ================================================================
     * ÓRGÃOS SOCIAIS
     * ================================================================ */

    private function get_orgaos() {
        return [
            'presidente' => ['nome' => 'Carlos Carvalho', 'cargo' => 'Presidente'],
            'vice_presidentes' => [
                ['nome' => 'Nuno Malheiro', 'cargo' => 'Vice-Presidente'],
                ['nome' => 'Filipa Pinto de Carvalho', 'cargo' => 'Vice-Presidente'],
                ['nome' => 'Gonçalo Simões de Almeida', 'cargo' => 'Vice-Presidente'],
            ],
            'assembleia' => [
                ['nome' => 'Miguel Moreira da Silva', 'cargo' => 'Presidente da Assembleia Geral'],
            ],
            'conselho_fiscal' => [
                ['nome' => 'Catarina Azevedo', 'cargo' => 'Presidente do Conselho Fiscal'],
                ['nome' => 'Pedro Cardoso', 'cargo' => 'Vice-Presidente'],
                ['nome' => 'Sofia Xavier', 'cargo' => 'Vogal'],
                ['nome' => 'Vítor Almeida', 'cargo' => 'Vogal'],
                ['nome' => 'Gonçalo Abreu', 'cargo' => 'Vogal'],
            ],
        ];
    }

    /* ================================================================
     * RESPONSE LOGIC
     * ================================================================ */

    private function get_response($msg) {
        $courses = $this->get_courses();
        $equipa = $this->get_equipa();
        $orgaos = $this->get_orgaos();

        // Search for person names (Teresa, Ana, Carlos, etc.)
        $all_names = [];
        foreach ($equipa as $group) {
            foreach ($group as $m) {
                $all_names[] = mb_strtolower($m['nome']);
            }
        }
        foreach ($orgaos as $group) {
            if (is_array($group) && isset($group['nome'])) {
                $all_names[] = mb_strtolower($group['nome']);
            } elseif (is_array($group)) {
                foreach ($group as $m) {
                    if (is_array($m) && isset($m['nome'])) {
                        $all_names[] = mb_strtolower($m['nome']);
                    }
                }
            }
        }
        foreach ($all_names as $name) {
            if (strpos($msg, $name) !== false) {
                return $this->find_person_info($name, $equipa, $orgaos);
            }
        }

        // Search for formação-ação specific queries
        if ($this->matches($msg, ['formação ação', 'formacao acao', 'formação-ação', 'formacao-acao'])) {
            return "📋 **Formação-Ação para PME:**\n\n"
                . "Programa de formação à medida para micro, pequenas e médias empresas.\n\n"
                . "Como funciona:\n"
                . "• Diagnóstico de necessidades da empresa\n"
                . "• Plano de ação integrado (formação + consultoria especializada)\n"
                . "• Focado em reorganização, inovação e melhoria de gestão\n\n"
                . "💰 **Investimento:** 90% financiado pelo FSE, 10% pela empresa\n\n"
                . "📍 **Regiões:** Norte, Centro e Alentejo\n"
                . "🏢 **Destinatários:** Micro, PME até 250 colaboradores\n\n"
                . "📌 **Áreas temáticas:** Inovação, Transição Digital, ESG\n\n"
                . "👩‍💼 **Responsáveis:**\n"
                . "• Vitória Pereira - vitoriapereira@anje.pt | (+351) 965 390 959\n"
                . "• Cristiana Moreira - cristianamoreira@anje.pt | (+351) 965 390 959\n\n"
                . "ℹ️ Mais info: https://anjeformacao.pt/formacao-acao-pme/\n"
                . "📧 Contacto geral: infoformacao@anje.pt\n"
                . "📍 Rua Paulo da Gama - Casa do Farol, 4169-006 Porto";
        }

        // Search for team/orgaos queries
        if ($this->matches($msg, ['equipa', 'equipe', 'staff', 'funcionarios', 'quem trabalha', 'diretor', 'diretores', 'diretoras', 'coordenador'])) {
            return $this->format_equipa($equipa);
        }

        if ($this->matches($msg, ['orgaos', 'órgãos', 'orgão', 'órgão', 'conselho fiscal', 'assembleia', 'mesa', 'fiscal'])) {
            return $this->format_orgaos($orgaos);
        }

        if ($this->matches($msg, ['presidente', 'quem é o presidente', 'quem e o presidente'])) {
            return 'O presidente da ANJE é **' . $orgaos['presidente']['nome'] . '**.';
        }

        // Search for course queries
        if ($this->matches($msg, ['curso', 'cursos', 'formacao', 'formações', 'treinamento', 'workshop', 'capacitação', 'certificação', 'powerbi', 'excel', 'gratuito', 'gratuitos', 'gratis', 'desempregado', 'desempregados', 'formação ação', 'formacao acao', 'formação-ação', 'formacao-acao'])) {
            return $this->search_courses($courses, $msg);
        }

        // Search for contact queries
        if ($this->matches($msg, ['contacto', 'contatos', 'email', 'telefone', 'morada', 'endereço', 'onde fica', 'localização'])) {
            return "📞 **Contactos da ANJE Formação:**\\n\\n"
                . "📧 Email: infoformacao@anje.pt\\n"
                . "📱 Telefone: (+351) 220 108 074\\n"
                . "📍 Rua Paulo da Gama - Casa do Farol, 4169-006 Porto";
        }

        // Default response
        return "Não tenho essa informação específica. Posso ajudar com:\n\n"
            . "• 📚 **Cursos e formações** - Pesquisa por área (ex: IA, gestão, marketing, vendas, excel, powerbi...)\n"
            . "• 💰 Preços e datas de cursos\n"
            . "• 👥 **Equipa** - Quem faz parte da ANJE Formação\n"
            . "• 📋 **Órgãos sociais** - Direção e conselhos\n"
            . "• 📞 **Contactos** - Email, telefone, morada\n\n"
            . "Ou contacte diretamente: infoformacao@anje.pt";
    }

    private function find_person_info($name, $equipa, $orgaos) {
        // Search in equipa
        foreach ($equipa as $group) {
            foreach ($group as $m) {
                if (mb_strtolower($m['nome']) === $name) {
                    return "👤 **{$m['nome']}** - {$m['cargo']}";
                }
            }
        }
        // Search in orgaos
        foreach ($orgaos as $group) {
            if (is_array($group) && isset($group['nome']) && mb_strtolower($group['nome']) === $name) {
                return "🏛️ **{$group['nome']}** - {$group['cargo']}";
            } elseif (is_array($group)) {
                foreach ($group as $m) {
                    if (is_array($m) && isset($m['nome']) && mb_strtolower($m['nome']) === $name) {
                        return "🏛️ **{$m['nome']}** - {$m['cargo']}";
                    }
                }
            }
        }
        return "Encontrei esse nome mas não tenho detalhes. Contacte infoformacao@anje.pt";
    }

    private function matches($msg, $keywords) {
        foreach ($keywords as $kw) {
            if (strpos($msg, $kw) !== false) return true;
        }
        return false;
    }

    private function search_courses($courses, $query) {
        // Detect area keywords
        $area_keywords = [
            'excel' => ['excel', 'folha de cálculo', 'folha de calculo', 'spreadsheet'],
            'powerbi' => ['powerbi', 'power bi', 'dashboard', 'dashboards', 'criação de dashboards'],
            'ia' => ['ia', 'inteligência artificial', 'artificial intelligence', 'claude', 'chatgpt', 'machine learning', 'generativa', 'copilot'],
            'gestao' => ['gestao', 'gestão', 'lideran', 'liderança', 'equipa', 'tempo', 'projeto', 'produtividade', 'burnout'],
            'marketing' => ['marketing', 'digital', 'seo', 'influenc', 'instagram', 'linkedin', 'marca', 'ecommerce', 'e-commerce'],
            'vendas' => ['venda', 'vendas', 'comercial', 'neuromarketing', 'crm', 'vendedor', 'prospe', 'fecho'],
            'finanças' => ['financ', 'tesouraria', 'poupanca', 'sql', 'python', 'controlo'],
            'juridico' => ['juridic', 'direito', 'rgpd', 'laboral', 'sociedade', 'rgpc', 'branqueamento'],
            'comunicação' => ['comunicar', 'storytelling', 'apresentac', 'impacto', 'pnl', 'falar'],
            'hotelaria' => ['hotel', 'turismo', 'higiene', 'alimentar'],
            'empreendedorismo' => ['empreend', 'negocio', 'startup', 'plano de neg', 'inovar', 'crescer', 'pme'],
            'certificação' => ['certifica', 'icagile', 'coach', 'pnl practitioner'],
            'gratuito' => ['gratuito', 'gratis', 'sem custo', 'free', 'desempregado', 'desempregados'],
        ];

        $matched_area = null;
        foreach ($area_keywords as $area => $keywords) {
            foreach ($keywords as $kw) {
                if (strpos($query, $kw) !== false) {
                    $matched_area = $area;
                    break 2;
                }
            }
        }

        // Filter courses
        $filtered = [];
        foreach ($courses as $c) {
            $titulo_lower = mb_strtolower($c['titulo']);
            $preco_lower = mb_strtolower($c['preco']);
            if ($matched_area) {
                $keywords = $area_keywords[$matched_area];
                foreach ($keywords as $kw) {
                    if (strpos($titulo_lower, $kw) !== false || strpos($preco_lower, $kw) !== false) {
                        $filtered[] = $c;
                        break;
                    }
                }
            } else {
                $filtered[] = $c;
            }
        }

        if (empty($filtered)) {
            return 'Não encontrei cursos para essa área. Temos cursos em:\n\n'
                . '• IA e Inteligência Artificial\n'
                . '• Gestão e Liderança\n'
                . '• Marketing Digital\n'
                . '• Vendas e Comercial\n'
                . '• Finanças e Excel\n'
                . '• Jurídico (RGPD, Trabalho)\n'
                . '• Comunicação e Storytelling\n'
                . '• Hotelaria e Turismo\n'
                . '• Empreendedorismo\n'
                . '• Certificações\n\n'
                . 'Pesquise por uma área específica!';
        }

        // Format response
        $response = 'Encontrei **' . count($filtered) . ' cursos**' . ($matched_area ? ' na área de ' . ucfirst($matched_area) : '') . ":\n\n";
        $count = 0;
        foreach ($filtered as $c) {
            if ($count >= 10) {
                $remaining = count($filtered) - 10;
                $response .= "\n_E mais {$remaining} cursos. Refina a tua pesquisa!_";
                break;
            }
            $response .= '• **' . $this->clean_title($c['titulo']) . "** - {$c['preco']}";
            if (!empty($c['data'])) $response .= " ({$c['data']})";
            $response .= "\n  {$c['url']}\n\n";
            $count++;
        }

        return $response;
    }

    private function clean_title($title) {
        $title = trim($title);
        $title = preg_replace('/\s+/', ' ', $title);
        return $title;
    }

    private function format_equipa($equipa) {
        $response = "**Equipa da ANJE Formação:**\n\n";
        foreach ($equipa['diretoras'] as $m) {
            $response .= "👩‍💼 **{$m['cargo']}:** {$m['nome']}\n";
        }
        $response .= "\n**Coordenadores:**\n";
        foreach ($equipa['coordenadores'] as $m) {
            $response .= "• {$m['nome']} - {$m['cargo']}\n";
        }
        $response .= "\n**Comunicação:**\n";
        foreach ($equipa['comunicacao'] as $m) {
            $response .= "• {$m['nome']} - {$m['cargo']}\n";
        }
        return $response;
    }

    private function format_orgaos($orgaos) {
        $response = "**Órgãos Sociais da ANJE:**\n\n";
        $response .= "🏛️ **Presidente:** {$orgaos['presidente']['nome']}\n\n";
        $response .= "**Vice-Presidentes:**\n";
        foreach ($orgaos['vice_presidentes'] as $m) {
            $response .= "• {$m['nome']}\n";
        }
        $response .= "\n🏛️ **Assembleia Geral:** {$orgaos['assembleia'][0]['nome']}\n\n";
        $response .= "**Conselho Fiscal:**\n";
        foreach ($orgaos['conselho_fiscal'] as $m) {
            $response .= "• {$m['nome']} - {$m['cargo']}\n";
        }
        return $response;
    }

    /* ================================================================
     * ADMIN
     * ================================================================ */

    public function add_admin_menu() {
        add_options_page('ChatBot ANJE Formação', 'ChatBot ANJE', 'manage_options', 'chatbot-anje-formacao', [$this, 'admin_page']);
    }

    public function register_settings() {
        register_setting('chatbot_af_grp', $this->option_key, [$this, 'sanitize']);
    }

    public function sanitize($input) {
        $out = [];
        $out['chatbot_name'] = sanitize_text_field($input['chatbot_name'] ?? 'ChatBot ANJE');
        $out['welcome_message'] = sanitize_textarea_field($input['welcome_message'] ?? '');
        $out['primary_color'] = sanitize_hex_color($input['primary_color'] ?? '#007bff');
        $out['position'] = in_array($input['position'] ?? '', ['left','right']) ? $input['position'] : 'right';
        $out['show_on_all_pages'] = ($input['show_on_all_pages'] ?? '') === 'yes' ? 'yes' : 'no';
        return $out;
    }

    public function admin_page() {
        $s = $this->get_settings();
        ?>
        <div class="wrap">
            <h1>🤖 ChatBot ANJE Formação</h1>
            <form method="post" action="options.php">
                <?php settings_fields('chatbot_af_grp'); ?>
                <table class="form-table">
                    <tr>
                        <th><label>Nome do ChatBot</label></th>
                        <td><input type="text" name="chatbot_anje_formacao_settings[chatbot_name]" value="<?php echo esc_attr($s['chatbot_name']); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label>Mensagem de Boas-vindas</label></th>
                        <td><textarea name="chatbot_anje_formacao_settings[welcome_message]" rows="4" class="large-text"><?php echo esc_textarea($s['welcome_message']); ?></textarea></td>
                    </tr>
                    <tr>
                        <th><label>Cor Principal</label></th>
                        <td><input type="color" name="chatbot_anje_formacao_settings[primary_color]" value="<?php echo esc_attr($s['primary_color']); ?>"></td>
                    </tr>
                    <tr>
                        <th><label>Posição</label></th>
                        <td>
                            <select name="chatbot_anje_formacao_settings[position]">
                                <option value="right" <?php selected($s['position'],'right'); ?>>Direita</option>
                                <option value="left" <?php selected($s['position'],'left'); ?>>Esquerda</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Mostrar em todas as páginas</th>
                        <td><label><input type="checkbox" name="chatbot_anje_formacao_settings[show_on_all_pages]" value="yes" <?php checked($s['show_on_all_pages'],'yes'); ?>> Sim</label></td>
                    </tr>
                </table>
                <?php submit_button('Guardar'); ?>
            </form>
        </div>
        <?php
    }
}

add_action('plugins_loaded', function() {
    ChatBot_ANJE_Formacao::instance();
});
