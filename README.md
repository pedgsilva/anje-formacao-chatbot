# ChatBot ANJE Formação

Plugin WordPress para o chatbot da ANJE Formação (anjeformacao.pt).

## Características

- **Sem LLM** - Respostas diretas baseadas em regras, sem custos de API
- **Cursos hardcoded** - Lista de cursos do WooCommerce embutida no plugin
- **Pesquisa por área** - IA, Gestão, Marketing, Vendas, Finanças, Jurídico, etc.
- **Equipa e Órgãos Sociais** - Informação direta
- **Contactos** - Email, telefone, morada
- **Design responsivo** - Widget de chat flutuante
- **Customizável** - Nome, cor, posição, mensagem de boas-vindas

## Instalação

1. Download do ZIP
2. WordPress > Plugins > Adicionar Novo > Carregar Plugin
3. Ativar
4. Definições > ChatBot ANJE > Configurar

## Actualização de Cursos

Para actualizar a lista de cursos, editar o array `$courses` no método `get_cursos()`.

## Changelog

### v2.0.0
- Removido LLM (OpenRouter)
- Respostas diretas baseadas em regras
- Lista de cursos hardcoded (48+ cursos)
- Pesquisa por área com keywords
- Info de equipa e órgãos sociais
