# ChatBot ANJE Formação

Plugin WordPress para o chatbot da ANJE Formação (anjeformacao.pt).

## Características

- **Com LLM** - Suporte a backend Flask (proxy) ou OpenRouter API direta
- **Fallback rule-based** - Funciona sem LLM configurada (respostas por regras)
- **Cursos dinâmicos** - WooCommerce WP_Query com cache 1h + fallback hardcoded
- **Pesquisa por área** - Excel, PowerBI, IA, Gestão, Marketing, Vendas, Finanças, Jurídico, etc.
- **Equipa e Órgãos Sociais** - Informação completa
- **Contactos** - Email, telefone, morada
- **Formação-Ação** - Informação detalhada do programa
- **Design responsivo** - Widget de chat flutuante com imagem personalizada
- **Customizável** - Nome, cor, posição, mensagem de boas-vindas, timeout, max tokens
- **Admin completo** - Configuração de backend URL, API key, modelo LLM

## Requisitos

- WordPress 5.0+
- PHP 7.4+
- WooCommerce (para cursos dinâmicos)

## Instalação

1. Download do ZIP
2. WordPress > Plugins > Adicionar Novo > Carregar Plugin
3. Ativar
4. Definições > ChatBot ANJE > Configurar

## Configuração LLM

### Opção A: Backend Flask (Recomendado)
1. Inserir URL do backend Flask (ex: https://chat.anjeformacao.pt)
2. O chatbot proxya os pedidos para o backend

### Opção B: OpenRouter Direto
1. Inserir OpenRouter API Key
2. Configurar modelo (ex: openrouter/owl-alpha)
3. O chatbot chama a API diretamente

### Sem configuração
- O chatbot funciona com respostas rule-based (sem LLM)

## Changelog

### v3.0.0
- Adicionado suporte a LLM via backend Flask (proxy)
- Adicionado suporte a OpenRouter API direta
- Fallback rule-based quando LLM não configurada
- System prompt completo com cursos dinâmicos, equipa, orgãos, formação-ação
- Botão do chatbot com imagem Wise.png (90x90)
- CSS responsivo com max-height dinâmico (não sai do ecrã)
- Admin page com configurações de backend, API key, modelo, tokens, timeout

### v2.0.0
- Respostas diretas baseadas em regras
- Lista de cursos hardcoded (48+ cursos)
- Pesquisa por área com keywords
- Info de equipa e órgãos sociais
- BOT.jpg como imagem do botão
