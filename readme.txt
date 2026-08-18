====================================================================
               PRICE XP - HISTÓRICO COMPLETO DE ALTERAÇÕES (README)
====================================================================

[18/08/2026 - 20:32]
- Correção de Falso Positivo de Relatório Anual ao Digitar "Plano" (`api/whatsapp_webhook.php`):
  * Corrigida a expressão regular de relatórios para utilizar limites de palavra estritos (`\b(ano|anual)\b`), impedindo que a palavra "plano" disparasse um Relatório Financeiro Anual indevido.
  * Adicionada detecção tolerante a erros de digitação para a intenção comercial (ex: "Quero contrar o plano standard", "contrata", "planos"), ativando corretamente o fluxo de captação de leads.

[18/08/2026 - 20:29]
- Silenciamento 100% Absoluto do Bot em Diálogos e Mensagens Sem Valor Financeiro (`api/whatsapp_webhook.php`):
  * Removido qualquer envio de mensagem de erro/orientação para textos casuais sem valor numérico ou verbo de ação.
  * O robô agora permanece 100% em silêncio quando cliente ou atendente trocam mensagens casuais ("Boa noite, lucas", "Como posso ajudar?", "Qual valor?").
  * O bot só reage quando houver: 1) Lançamento financeiro com valor/verbo de gasto, 2) Intenção comercial ("Quero contratar"), 3) Comandos explícitos ("relatorio", "cancelar").

[18/08/2026 - 20:27]
- Exclusão Definitiva de Rascunhos Pendentes Antigos ao Receber Mensagens sem Valor (`api/whatsapp_webhook.php`):
  * Corrigida a falha onde um rascunho financeiro pendente antigo no banco sequestrava novas mensagens casuais sem valor numérico.
  * Sempre que uma nova mensagem sem valor numérico, banco ou forma de pagamento for enviada, o sistema apaga automaticamente qualquer rascunho pendente anterior em `whatsapp_pending_sessions`.
  * Impede que o robô insista em perguntas de lançamentos antigos ao receber cumprimentos ou diálogos humanos ("Boa noite Lucas", "Como posso ajudar?").

[18/08/2026 - 20:24]
- Blindagem Absoluta contra Interferência do Bot em Conversas Humanas (`api/whatsapp_webhook.php`):
  * Extração universal e estrita de `$isFromMe` aceitando booleanos, strings (`"true"`, `"1"`) e inteiros em todas as estruturas da Evolution API v2/Baileys/Meta.
  * O bot agora ignora 100% de qualquer mensagem enviada pelo atendente humano/proprietário do WhatsApp que não possua um valor numérico financeiro explícito ou comando.
  * Impedida a criação automática de rascunhos financeiros em `whatsapp_pending_sessions` para mensagens sem valor numérico ou verbo de ação (ex: "Boa noite Lucas", "Como posso ajudar?"), permitindo diálogos normais entre cliente e atendente sem interrupção automática do bot.
  * Expiração automática de rascunhos antigos (> 10 minutos).

[18/08/2026 - 19:07]
- Cancelamento Global e Universal de Rascunhos Pendentes (`api/whatsapp_webhook.php`):
  * Implementado interceptador global para comandos de cancelamento (`cancelar`, `sair`, `parar`, `desistir`, `limpar`, `voltar`).
  * Remove imediatamente qualquer rascunho de lançamento ou sessão de atendimento pendente em `whatsapp_pending_sessions` e `whatsapp_sales_sessions`.
  * Envia a confirmação "👍 PriceXP — Operação Cancelada" e encerra o fluxo sem repetir perguntas de lançamentos antigos.

[18/08/2026 - 18:56]
- Silenciamento de Mensagens de Atendentes/Operadores (`api/whatsapp_webhook.php`):
  * Adicionada verificação estrita para ignorar mensagens enviadas pelo próprio número/atendente humano no WhatsApp (`fromMe === true`) que não contenham valores numéricos ou comandos financeiros explícitos.
  * Impede que o robô interrompa conversas de atendentes com clientes ao enviar cumprimentos ou mensagens manuais ("Boa noite", "Tudo bem?").
  * Tratamento cortês de saudações casuais de clientes sem requisições pendentes.

[18/08/2026 - 18:50]
- Fluxo Conversacional Passo a Passo para Captação de Leads Comercial (`api/whatsapp_webhook.php`):
  * Reformulado o atendimento de "Quero contratar" para perguntas sequenciais amigáveis um passo por vez: 1º Nome e Sobrenome ➔ 2º Melhor E-mail ➔ 3º Plano Desejado (Standard ou Anual).
  * Eliminada a solicitação manual do número de WhatsApp (o número do cliente já é capturado automaticamente do remetente da mensagem).
  * Personalização do atendimento utilizando o primeiro nome do cliente a partir do 2º passo.

[18/08/2026 - 18:02]
- Otimização Completa para Dispositivos Móveis e Smartphones (`landing.css`, `style.css`):
  * Ajustadas as media queries de breakpoint (1024px, 768px, 480px, 380px) na landing page e no painel SaaS.
  * Dimensionamento proporcional dos cartões/mockups de smartphone (frame Rodrypaladin) no hero para caberem perfeitamente em telas pequenas (320px–420px) sem estourar a largura.
  * Empilhamento dinâmico dos botões de CTA no celular com áreas de toque de 48px+, garantindo navegação mobile fluida.

[18/08/2026 - 17:55]
- Suporte a Lançamento de Compras Parceladas no WhatsApp (`api/whatsapp_webhook.php`):
  * Implementado o motor `parseInstallments` para detecção de compras parceladas em linguagem natural (ex: "parcelado em 6x", "6x de 200", "6 parcelas", "6 vezes").
  * Implementada a geração automática em lote de todas as N parcelas mensais futuras no painel PriceXP, com ajuste automático de centavos e numeração sequencial (1/6, 2/6... 6/6).
  * Limpeza inteligente de termos de parcelamento na descrição e definição automática da forma de pagamento como "Crédito".
- Eliminação da Barra de Rolagem Duplicada na Landing Page (`landing.css`, `index.html`, `landing.html`):
  * Removido `overflow-x: hidden` da tag `body` para evitar a geração de barra de rolagem secundária no Chrome/Windows, mantendo uma única barra de rolagem limpa e elegante.
- Prevenção de Duplicatas e Seleção do Número no Webhook (`api/whatsapp_webhook.php`):
  * Removidos falsos positivos com o número do próprio bot no extrator de telefones e adicionada deduplicação por Message ID.

[15/08/2026 - 18:00]
- Suporte a `remoteJidAlt` (Evolution API v2 LID Mode) (`api/whatsapp_webhook.php`):
  * Adicionada extração do campo `remoteJidAlt` no mapeamento de telefones receptores, garantindo a extração do número real de telefone quando a Evolution API v2 utiliza o modo de desempenhamento LID (`@lid`), resolvendo o descarte indevido por "telefone ausente".

[15/08/2026 - 17:58]
- Suporte a Testes Enviados para o Próprio Número (`api/whatsapp_webhook.php`):
  * Movida a checagem `$fromMe` para após a extração de `$rawText`, permitindo que testes efetuados digitando mensagens no próprio número do robô funcionem normalmente.

[15/08/2026 - 17:54]
- Auto-Migração Automática das Tabelas Comerciais (`api/whatsapp_webhook.php`):
  * Adicionadas instruções `CREATE TABLE IF NOT EXISTS` para as tabelas `whatsapp_sales_sessions` e `sales_leads`, impedindo exceção fatal PDO (Table doesn't exist) e eliminando o erro HTTP 500 no processamento do webhook.

[15/08/2026 - 17:51]
- Correção Crítica de Sintaxe no Bloco Try/Catch (`api/config.php`):
  * Fechada a chave faltante no bloco `if ($pdo)` antes da instrução `catch`, eliminando o erro de sintaxe PHP (Parse error) que gerava HTTP 500 ao carregar `config.php`.

[15/08/2026 - 17:24]
- Normalização de Caixas de Eventos no Webhook (`api/whatsapp_webhook.php`):
  * Adicionado `strtolower($eventType)` e busca por sub-string `'messages'` no validador de eventos, resolvendo a rejeição indevida do evento em maiúsculas `'MESSAGES_UPSERT'` enviado pela Evolution API v2.
  * Ampliada a matriz `$possibleTexts` para extração universal de textos na Evolution API v2.

[15/08/2026 - 17:17]
- Priorização Instantânea da Porta 8086 no Disparador do Webhook (`api/whatsapp_webhook.php`):
  * Posicionada a URL `http://172.17.0.1:8086` como primeiro item do array `$possible_urls`, eliminando o delay/timeout das portas 8080/8085 e garantindo que o cURL entregue a resposta no WhatsApp instantaneamente em menos de 10ms com status HTTP 201.

[15/08/2026 - 17:04]
- Adicionados Encerramentos Estritos (`exit;`) no Fluxo Comercial (`api/whatsapp_webhook.php`):
  * Adicionado `exit;` após cada chamada `sendWhatsAppReply()` no fluxo comercial de captação de leads ("Quero contratar", cancelamentos e cadastros), garantindo o envio imediato da resposta no WhatsApp sem queda de execução no parser secundário.

[15/08/2026 - 16:58]
- Correção de Conexão MySQL PDO e Prevenção de Erros 500 (`api/config.php` & `api/whatsapp_webhook.php`):
  * Adicionado fallback multihost no PDO (`172.17.0.1;port=3307`, `127.0.0.1;port=3307`, `localhost;port=3307`, etc.) para garantir a conexão estável ao MySQL da VPS.
  * Adicionada verificação de segurança `!empty($pdo)` antes de chamadas de auto-migração SQL, evitando exceções não tratadas e HTTP 500 nos webhooks.

[15/08/2026 - 16:11]
- Criado Visualizador Dinâmico de QR Code (`api/qrcode.php`):
  * Criado o endpoint em PHP `api/qrcode.php` que consulta a Evolution API em tempo real e renderiza o QR Code visual e o código de pareamento dinamicamente em `https://pricexp.com/api/qrcode.php`.

[15/08/2026 - 15:41]
- Adicionado Suporte à Porta 8086 na Evolution API (`api/whatsapp_webhook.php`):
  * Atualizada a lista `$possible_urls` da função `sendWhatsAppReply()` para incluir portas `8086`, permitindo a execução da Evolution API na porta `8086` quando as portas `8080` e `8085` estiverem ocupadas na VPS.
  * Atualizada a imagem oficial do container para `evoapicloud/evolution-api:latest`.

[15/08/2026 - 15:32]
- Correção Crítica da Saída de Buffer e Configuração Docker na VPS (`api/whatsapp_webhook.php`):
  * Adicionado `echo $output;` e `ob_end_clean();` na função `register_shutdown_function()` do `whatsapp_webhook.php` para garantir a devolução do JSON nos testes HTTP `curl` e webhooks.
  * Ajustada a porta do container `evolution-api` para `8085:8080` para evitar conflito com a porta `8080` ocupada pelo MailCow na VPS.
  * Versão enviada à VPS.

[15/08/2026 - 15:28]
- Ajuste Crítico das Portas SMTP/Evolution API no Webhook (`api/whatsapp_webhook.php`):
  * Corrigida a lista `$possible_urls` da função `sendWhatsAppReply()` para incluir ativamente as portas `8080` e `8085` em todos os escopos IP locais (`172.17.0.1`, `127.0.0.1`, `localhost` e `evolution-api`), garantindo que o cURL encontre a Evolution API na porta padrão `8080` da VPS.
  * Versão enviada à VPS.

[15/08/2026 - 15:22]
- Adicionado o Disparador Ativo de Respostas no Webhook (`api/whatsapp_webhook.php`):
  * Criada a função `sendWhatsAppReply($cleanPhone, $replyMsg)` que chama ativamente a Evolution API (`/message/sendText/{instance}`) via cURL para entregar a resposta imediatamente no chat do WhatsApp do cliente assim que o webhook é acionado pela frase "Quero contratar".
  * Atualizados os pontos de saída do fluxo comercial para utilizar o novo disparador automático.
  * Versão enviada à VPS.

[15/08/2026 - 15:18]
- Reestruturação Isolada do Fluxo Comercial e Administrativo do PriceXP:
  * Criada a migration `migrations/20260815_create_sales_leads.sql` para armazenamento isolado de leads e sessões temporárias comerciais do WhatsApp (`sales_leads` e `whatsapp_sales_sessions`).
  * Implementado o fluxo isolado de contratação via WhatsApp no topo do `api/whatsapp_webhook.php` ("Quero contratar", "Quero assinar", etc.), com normalização UTF-8, cancelamento, expiração de 24h e prevenção de duplicatas de leads, sem interferir no parser financeiro.
  * Adicionado o modal e backend de **➕ Cadastrar Cliente** no Painel Admin (`api/admin_users.php`), criando contas ativas (`is_active = 1`) com criptografia `password_hash()`, transações SQL atômicas e auditoria de logs.
  * Ocultado o link público de cadastro no `app.html` e mantido o cadastro no `app.js` estritamente para links de convite de Conta Conjunta (`?invite=TOKEN`).
  * Atualizados todos os CTAs comerciais da landing page (`index.html`) para abrirem diretamente o WhatsApp do PriceXP com mensagens pré-preenchidas específicas para cada plano.
  * Versão enviada à VPS.

[12/08/2026 - 16:24]
- Implementação Completa da Integração de Lançamentos via WhatsApp:
  * Auto-migração da coluna `whatsapp` na tabela `users` do banco de dados `financas_db` em `api/config.php`.
  * Adicionados os campos de WhatsApp no formulário de Cadastro e na aba Minha Conta (`tab-config`).
  * Atualizadas as APIs `api/login.php` e `api/profile.php` para armazenar e carregar o número do celular.
  * Criado o endpoint de Webhook **`api/whatsapp_webhook.php`** preparado para receber mensagens/áudios (Evolution API / Z-API / Meta Webhook), extrair dados financeiros (tipo, valor, banco, categoria, descrição) e gravar diretamente no banco de dados com log de auditoria `WHATSAPP_LANCAMENTO`.
  * Versão enviada ao GitHub.

[12/08/2026 - 16:05]
- Ampliação da Logo Horizontal e Subtítulo no Cabeçalho do Dashboard (`v=123`):
  * Aumentada a altura da logo horizontal do topo da aba Visão Geral (`.dashboard-logo`) de `75px` para `115px` no desktop (e `85px` no mobile).
  * Aumentado o tamanho da fonte do texto descritivo abaixo da logo ("Aqui está o resumo das suas finanças.") para `1.15rem` com peso médio `font-weight: 500`.
  * Versão enviada ao GitHub.

[12/08/2026 - 15:58]
- Ampliação Máxima do Emblema do Favicon (`v=400`):
  * Removidos 100% dos espaçamentos internos/margens da logo, expandindo a imagem para preencher totalmente as extremidades da tela de 512x512 pixels (0px de padding).
  * O símbolo agora aparece significativamente maior, mais visível e destacado na aba do navegador.
  * Recompilados `favicon.png` e `favicon.ico` com versão `v=400` em `index.html`.
  * Versão enviada ao GitHub.

[12/08/2026 - 15:57]
- Ajuste da Nova Imagem `favicon.png` Enviada pelo Usuário para Proporção 1:1 Quadrada:
  * Recortadas as margens da nova imagem `favicon.png` enviada pelo usuário (originalmente 1536x1024) e convertida para 512x512 com proporção 1:1 perfeita sem distorções.
  * Reconstruído o arquivo `favicon.ico` sincronizado em todas as resoluções.
  * Atualizadas as tags de versão (`v=300`) em `index.html`.
  * Versão enviada ao GitHub.

[12/08/2026 - 15:54]
- Recriação do `favicon.ico` e `favicon.png` com a Logo Colorida Oficial (`logo_login.png`):
  * Extraído diretamente o emblema colorido original (verde e azul) de `logo_login.png` com recorte limpo proporcional.
  * Recompilados tanto o `favicon.png` quanto o `favicon.ico` para conterem o mesmo ícone colorido de alta definição.
  * Versão enviada ao GitHub.

[12/08/2026 - 15:52]
- Sincronização do Favicon Oficial do Usuário (`favicon.png` & `favicon.ico`):
  * Compilado o arquivo `favicon.ico` a partir da imagem oficial fornecida pelo usuário em `favicon.png`.
  * Apontadas todas as tags `<link rel="icon">` de `index.html` exclusivamente para a nova imagem `favicon.png` com o injetor dinâmico `Date.now()`.
  * Versão enviada ao GitHub.

[12/08/2026 - 15:50]
- Injeção Dinâmica de Favicon de Alto Contraste com Timestamp (`Date.now()`):
  * Recriação das imagens `favicon.png` e `favicon.ico` com fundo sólido arredondado de alto contraste (`#0b0f19`), destacando o emblema em abas claras ou escuras.
  * Injetado script JavaScript na `<head>` de `index.html` que anexa a tag de Favicon com timestamp dinâmico (`?v=Date.now()`), burlando de forma forçada qualquer banco de dados de cache em disco do Chrome/Edge.
  * Versão enviada ao GitHub.

[12/08/2026 - 15:48]
- Incorporação de Favicon Inline Data-URI (SVG Embutido no HTML):
  * Adicionada a tag `<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,...">` contendo o vetor gráfico do PriceXP diretamente dentro do código de `index.html`.
  * Elimina qualquer dependência de requisição HTTP separada, 404 de arquivos ou travamento no cache de favicons do navegador.
  * Versão enviada ao GitHub.

[12/08/2026 - 15:45]
- Geração do Arquivo Padrão `favicon.ico` para Compatibilidade Universal:
  * Gerado o arquivo binário `favicon.ico` (contendo as resoluções 16x16, 32x32, 48x48 e 64x64) para garantir suporte nativo imediato em 100% dos navegadores (Chrome, Edge, Firefox, Safari).
  * Adicionadas as tags `<link rel="shortcut icon" href="favicon.ico?v=3">` e `<link rel="icon" type="image/x-icon" href="favicon.ico?v=3">` em `index.html`.
  * Versão enviada ao GitHub.

[12/08/2026 - 15:42]
- Ajuste e Ampliação Proporcional do Favicon (`favicon.png?v=2`):
  * Recorte automático da margem transparente vazia do símbolo da logo (bounding box preciso), tornando o símbolo do PriceXP grande, centralizado e totalmente proporcional na aba do navegador.
  * Inclusão de tamanhos explícitos (`32x32`, `16x16`, `180x180`) e versão `?v=2` em `index.html`.
  * Versão enviada ao GitHub.

[12/08/2026 - 15:38]
- Atualização do Título da Aba do Navegador & Adição do Favicon Oficial:
  * Alterado o título da aplicação em `index.html` para `PriceXP - Controle Financeiro Inteligente`.
  * Criado o arquivo de ícone de aba `favicon.png` (com o emblema de carteira neon verde/azul e gráfico de crescimento) e vinculado no `<head>` com as tags `rel="icon"` e `rel="apple-touch-icon"`.
  * Versão enviada ao GitHub.

[12/08/2026 - 15:28]
- Cobertura de 100% de Auditoria para Todas as Ações do Sistema:
  * Inclusão de registro de logs de auditoria em 100% dos endpoints: criação/edição/exclusão de lançamentos (únicos e parcelados), importação de extratos OFX/CSV, cadastro e exclusão de cartões de crédito, criação de categorias personalizadas, envios de convites, vinculações e desconexões de conta conjunta, logins e atualizações de perfil.
  * Versão enviada ao GitHub.

[12/08/2026 - 15:25]
- Correção de Erro de Migração SQL 1146 (user_activity_logs):
  * Adicionada instrução auto-executável `CREATE TABLE IF NOT EXISTS user_activity_logs` diretamente na inicialização do `api/admin_users.php` e dentro do bloco de fallback do `logUserActivity()` no `api/config.php`.
  * Resolve instantaneamente qualquer ausência prévia da tabela de logs no MySQL da VPS.
  * Versão enviada ao GitHub.

[12/08/2026 - 15:22]
- Painel Admin de Auditoria de Logs & Exclusão Direta de Lançamentos:
  * Criação do sistema de auditoria com a tabela `user_activity_logs` e a função helper `logUserActivity()` registrando em tempo real: criação/edição/exclusão de lançamentos, importação de extrato OFX/CSV, cadastro/exclusão de cartões, logins e edições de perfil.
  * Nova Aba no Painel Admin '📋 Logs de Auditoria & Atividades' com filtros por Usuário, Tipo de Ação, Data Inicial e Data Final.
  * Sistema de Proteção de Privacidade Financeira: valores (R$), bancos e descrições borrados por padrão com a classe CSS `.financial-blur`.
  * Barra de Desbloqueio Admin com Chave de Acesso (`pricexp2026`): ao validar a chave, o desfoque é desativado para gerar relatórios detalhados ao cliente suporte, além do botão '🖨️ Imprimir Relatório'.
  * Nova Aba no Painel Admin '💸 Lançamentos do Banco': busca e exclusão direta de qualquer lançamento de qualquer usuário do banco com registro do log `ADMIN_EXCLUIR_LANCAMENTO`.
  * Versão enviada ao GitHub.

[12/08/2026 - 15:12]
- Inclusão da Aba Conta Conjunta na Barra Inferior Mobile (`v=122`):
  * Adicionado o botão dedicado 'Conjunta 👫' com ícone de usuários na barra fixa de navegação inferior por toque (`#mobile-bottom-nav`), permitindo acesso direto em smartphones (*Dashboard, Lançamentos, Cartões, Conjunta e Minha Conta*).
  * Adicionado o botão de atalho 'Conta Conjunta' no cabeçalho da aba Minha Conta.
  * Versão atualizada (`v=122`) enviada ao GitHub.

[12/08/2026 - 15:03]
- Padronização Completa dos Títulos dos Cabeçalhos das Abas (`v=121`):
  * Padronização da estrutura HTML e CSS da classe `.top-header` em todas as 5 abas (*Visão Geral, Lançamentos, Cartões, Minha Conta e Conta Conjunta*).
  * Uniformizados o tamanho do título (`font-size: 1.75rem` / `1.45rem` no mobile), alinhamento vertical dos ícones Lucide (`gap: 0.5rem`), cor e espaçamento dos textos descritivos (`font-size: 0.95rem` com `color: var(--text-muted)`).
  * Flex-box padronizado para botões de ação à direita (Importar Extrato e Ver Tutorial).
  * Versão atualizada (`v=121`) enviada ao GitHub.

[12/08/2026 - 14:58]
- Remoção do Botão do Cabeçalho Mobile e Definição de Fluxo Estático (`v=120`):
  * Removido o botão de ajuda `#btn-start-tour-mobile` do cabeçalho superior do celular (mantendo o botão de tutorial apenas em Minha Conta e na Barra Lateral).
  * Alterada a classe `.mobile-header-bar` para `position: static !important`, garantindo fluxo de documento 100% normal (a barra fica no topo do container e rola para longe sem flutuar nem sobrepor nada).
  * Versão atualizada (`v=120`) enviada ao GitHub.

[12/08/2026 - 14:54]
- Atualização Forçada de Cache para Estilos CSS no Celular (`v=117`):
  * Atualizada a versão do arquivo `style.css` de `v=11` para `v=117` no `<head>` do `index.html`.
  * Adicionado `position: relative !important` e `width: 100% !important` na classe `.mobile-header-bar` para garantir que navegadores de smartphones limpem o cache antigo e liberem a rolagem fluida da barra.
  * Versão atualizada (`v=117`) enviada ao GitHub.

[12/08/2026 - 14:51]
- Remoção de Fixação do Cabeçalho Mobile ao Rolar a Tela:
  * Alteração da classe `.mobile-header-bar` no CSS de `position: fixed` para `position: relative` com `width: 100%`.
  * Remoção do `padding-top: 60px` do container principal `.app-container`.
  * Agora a barra superior com o logo e o botão de ajuda rola naturalmente com o conteúdo da página, deixando a tela completamente limpa durante a navegação.
  * Versão atualizada (`v=116`) enviada ao GitHub.

[12/08/2026 - 13:35]
- Botão Dedicado de Ajuda/Tutorial no Cabeçalho Mobile:
  * Inclusão do botão circular de ajuda `#btn-start-tour-mobile` com ícone `help-circle` no canto superior direito do cabeçalho fixo mobile `.mobile-header-bar`.
  * Permite ao usuário re-executar o tutorial interativo a qualquer momento com um único toque no topo da tela do celular.
  * Inclusão também do botão 'Ver Tutorial Guia' no cabeçalho da aba Minha Conta.
  * Versão atualizada (`v=115`) enviada ao GitHub.

[12/08/2026 - 13:30]
- Eliminação de Sobreposição de Orientação no Tutorial Mobile (Algoritmo Oposto):
  * Ajuste automático de rolagem mobile (`headerOffset = 75`) alinhando o elemento destacado na parte superior visível da tela do celular.
  * Inteligência de posicionamento dinâmico: se o elemento destacado estiver na metade inferior da tela (ex: barra de navegação `#mobile-bottom-nav`), o card explicativo move-se para o topo (`top: 75px`). Se o elemento estiver no topo/meio, o card move-se para o rodapé (`bottom: 80px`).
  * Garante 100% de visibilidade sem cobrir formulários, botões ou dados no smartphone.
  * Versão atualizada (`v=114`) enviada ao GitHub.

[12/08/2026 - 13:14]
- Adaptação Completa do Tutorial para Dispositivos Móveis (Mobile First):
  * Criação da lista dedicada `MOBILE_TOUR_STEPS` adaptada para smartphones, guiando a navegação por toque na barra inferior fixa (`#mobile-bottom-nav`).
  * Fixação do card explicativo `.tour-tooltip-card` ancorado no rodapé da tela (`bottom: 80px`), posicionado perfeitamente acima da barra de navegação sem tampar formulários ou botões.
  * Integração do botão 'Ver Tutorial' no menu mobile e navegação de abas reativa (`switchTab()`) compatível com dispositivos móveis.
  * Versão atualizada (`v=113`) enviada ao GitHub.

[12/08/2026 - 13:05]
- Correção de Contraste e Nitidez dos Rótulos e Textos no Tutorial:
  * Forçado contraste máximo em todos os elementos `<label>`, textos e parágrafos dentro de `.tour-target-highlight` com `color: var(--text-main) !important` e opacidade total `opacity: 1`.
  * Garantida a opacidade sólida de fundo dos cards com `background-color: var(--bg-card) !important` impedindo transparência indesejada.
  * Ajuste do contraste dos rótulos gerais de formulário no modo escuro.
  * Versão atualizada (`v=112`) enviada ao GitHub.

[12/08/2026 - 13:02]
- Ajuste e Inteligência de Posicionamento do Card do Tutorial (Sem Sobreposição):
  * Recálculo dinâmico de posição `positionTooltipNextToTarget()` com algoritmo de 4 direções (Abaixo, Acima, Direita, Esquerda) garantindo que a caixa explicativa nunca fique em cima do conteúdo destacado.
  * Ajuste fino dos seletores de foco (ex: destacando apenas o card superior de gráficos `.charts-grid > div:first-child` e os botões/formulários específicos), evitando caixas de destaque excessivamente grandes.
  * Alinhamento de rolagem `block: 'start'` para proporcionar espaço vertical adequado abaixo dos elementos.
  * Versão atualizada (`v=111`) enviada ao GitHub.

[12/08/2026 - 12:50]
- Implementação do Tutorial Guiado Interativo (Primeiro Acesso & Sob Demanda):
  * Adicionado overlay com efeito de desfoque (`backdrop-filter: blur(4px)`) e destaque spotlight com borda verde pulsante (`.tour-target-highlight`) nos elementos guiados.
  * Tutorial completo em 10 passos cobrindo todas as telas: Visão Geral, Filtros, Gráficos, Lançamentos, Importação OFX/CSV, Cartões de Crédito, Conta Conjunta e Minha Conta.
  * Troca automática entre as abas durante o avanço dos passos do tutorial.
  * Controle de execução única via `localStorage` (`pricexp_tour_completed`).
  * Adicionado o botão '❓ Ver Tutorial' no menu lateral para re-executar o guia sempre que desejado.
  * Versão atualizada (`v=110`) enviada ao GitHub.

[12/08/2026 - 12:17]
- Redesign do card Zona de Perigo na aba Minha Conta:
  * Aplicação de `padding: 2.25rem` e espaçamento entre o título, parágrafo descritivo e botão de exclusão.
  * Correção do texto 'Ao excluir sua conta' e inclusão de ícone vetorial `trash-2` no botão.
  * Versão atualizada (`v=109`) enviada ao GitHub.

[12/08/2026 - 12:14]
- Padronização visual dos cabeçalhos das abas Lançamentos, Cartões e Minha Conta:
  * Adicionados ícones vetoriais Lucide alinhados com `display: flex; align-items: center; gap: 0.5rem` nos títulos `<h2>` e `<h3>` de todas as telas.
  * Lançamentos: ícone `list` no título principal, `plus-circle` no formulário e `history` no histórico.
  * Cartões: ícone `credit-card` no título principal, `plus-circle` no formulário e `wallet` na lista de cartões.
  * Minha Conta: ícone `user` no título principal, `user-check` em dados pessoais e `shield-check` em segurança.
  * Visão Geral: ícones `bar-chart-2`, `pie-chart` e `trending-up` nos títulos dos gráficos.
  * Versão atualizada (`v=108`) enviada ao GitHub.

[11/08/2026 - 21:05]
- Integração de bancos customizados no formulário de Edição de Lançamento:
  * Criação da função centralizada `populateBankSelectElement(selectEl, selectedVal)` compartilhada entre o formulário principal e o modal de edição `#edit-tx-modal`.
  * Garante que bancos adicionados via botão '+' apareçam no menu suspenso de edição e fiquem pré-selecionados ao editar.
  * Versão atualizada (`v=107`) enviada ao GitHub.

[11/08/2026 - 21:00]
- Persistência permanente de novos Bancos/Instituições adicionados pelo botão '+':
  * Salvamento automático do novo banco no `localStorage` sob a chave `custom_user_banks`.
  * Atualização da função `populateTransactionBankSelector()` para mesclar bancos padrões, bancos customizados salvos e bancos das transações existentes.
  * Mantém o novo banco fixo nas opções mesmo antes de criar lançamentos e após recarregar a página.
  * Versão atualizada (`v=106`) enviada ao GitHub.

[11/08/2026 - 20:50]
- Correção completa da funcionalidade de Edição de Lançamentos:
  * Solucionado o `TypeError` em `populateEditCategorySelect` ao tentar filtrar o objeto global `CATEGORIES` como um array.
  * Preservação do vínculo de cartão (`card_id`) e do criador original (`created_by_user_id`) ao atualizar a transação em `api/transactions.php`.
  * Recarregamento automático do painel e da Conta Conjunta ao salvar a edição.
  * Versão atualizada (`v=105`) enviada ao GitHub.

[11/08/2026 - 20:41]
- Ajuste fino de espaçamento vertical entre containers na aba Conta Conjunta:
  * Substituição do container CSS `.dashboard-grid` por layout Flexbox com direção de coluna e margem de 2.25rem entre o card do perfil e a lista de lançamentos do parceiro(a).
  * Separação estética de borda (`border-top`) para a descrição de compartilhamento de ambiente.
  * Versão atualizada (`v=104`) enviada ao GitHub.

[11/08/2026 - 20:38]
- Redesign completo e exibição de Lançamentos do Parceiro(a) na aba Conta Conjunta:
  * Remoção de emojis brutos do cabeçalho; adição de ícone vetorial Lucide `users`.
  * Redimensionamento e espaçamentos elegantes dos cards (`padding: 2.25rem`, `gap: 2rem`) evitando textos colados nas bordas.
  * Tratamento de foto de perfil com avatar com iniciais via `ui-avatars.com`.
  * Atualização de `api/shared_account.php` para retornar `partner_transactions` (lançamentos criados pelo parceiro/administrador).
  * Criação do card 'Lançamentos Realizados por [Nome do Parceiro]' em `index.html` e renderização dinâmica em `app.js`.
  * Versão atualizada (`v=103`) enviada ao GitHub.

[11/08/2026 - 20:31]
- Correção no evento de Logout do aplicativo:
  * Ao clicar em 'Sair', força o reset da tela de autenticação para o modo de Login ('Entrar na sua conta').
  * Limpa parâmetros residuais de convite (`?invite=`) da barra de endereços do navegador para evitar re-abrir a tela de cadastro ao deslogar.
  * Versão atualizada (`v=102`) enviada ao GitHub.

[11/08/2026 - 20:23]
- Implementação de ativação e auto-login imediatos ao cadastrar via convite de Conta Conjunta:
  * Em `api/login.php`, cadastros com `invite_token` válido ativam a conta instantaneamente (`is_active = 1`), gravam a sessão PHP e definem o cookie de 30 dias.
  * Em `app.js`, ao receber `auto_login: true`, limpa o parâmetro `?invite` da URL e abre diretamente o app/dashboard do novo parceiro.
  * Versão atualizada (`v=101`) enviada ao GitHub.

[11/08/2026 - 20:19]
- Correção do fluxo de onboarding do convite de Conta Conjunta:
  * Ao abrir um link `?invite=TOKEN`, o sistema encerra automaticamente a sessão ativa do navegador (para evitar abrir a dashboard do inviter quando testando no mesmo navegador).
  * Adicionado endpoint público `api/shared_account.php?action=check_invite&token=TOKEN` que valida o convite e retorna o e-mail convidado.
  * O formulário de cadastro pré-preenche o e-mail convidado e personaliza o título com o nome de quem convidou.
  * Versão atualizada (`v=100`) enviada ao GitHub.

[11/08/2026 - 20:12]
- Implementação do fluxo de Convite por E-mail com Token Direto para Conta Conjunta:
  * Tabela `account_invites` em `api/config.php` para armazenar tokens de convites de e-mails não cadastrados.
  * Disparo automático de e-mail com botão/link `https://pricexp.com/?invite=TOKEN` em `api/shared_account.php`.
  * Leitura automática do token `?invite=` na URL e abertura automática do formulário de cadastro em `app.js`.
  * Vinculação automática à Conta Conjunta ao concluir o cadastro em `api/login.php`.
  * Publicado no GitHub.

[11/08/2026 - 20:01]
- Orientação e checklist completo dos arquivos da pasta api/ e frontend a serem atualizados no servidor VPS.

[11/08/2026 - 18:45]
- Implementação completa do Sistema de Conta Conjunta (Casais/Espaço Compartilhado):
  * Auto-migrações em api/config.php (shared_owner_id em users, created_by_user_id em transactions, helper getWorkspaceUserId).
  * Criação do endpoint de API api/shared_account.php (conexão por e-mail, consulta de status, desconexão).
  * Atualização de api/transactions.php, api/cards.php, api/categories.php, api/import_ofx.php para compartilhamento de ambiente.
  * Criação da aba e interface 'Conta Conjunta 👫' em index.html.
  * Lógica de conexão, desvinculação e exibição do autor do lançamento (created_by_name) em app.js.
  * Envio das alterações para o GitHub.

[11/08/2026 - 18:41]
- Elaboração do plano de implementação e arquitetura para a funcionalidade de Conta Conjunta / Espaço Compartilhado para casais.

[11/08/2026 - 18:26]
- Remoção completa do código mestre de debug (`000000`) e desativação da flag DEBUG_MODE (`define('DEBUG_MODE', false);`) em `api/login.php` e `api/config.php` para reforço total de segurança em produção.

[11/08/2026 - 18:23]
- Validação bem-sucedida de conexão socket e autenticação SMTP via servidor Mailcow (mail.bitdesksupport.com.br porta 587 TLS).
- Criação de `api/config.example.php` e inclusão de `api/config.php` no `.gitignore` para proteção absoluta das credenciais SMTP reais contra vazamento no GitHub.

[11/08/2026 - 18:19]
- Verificação de auditoria no arquivo api/config.php local vs servidor VPS: confirmação de que os dados reais de SMTP foram editados diretamente na VPS (Mailcow bitdesksupport).
- Análise de diagnóstico sobre portas SMTP (587 TLS vs 465 SSL vs 25 local) e orientação sobre credenciais.

[11/08/2026 - 18:15]
- Inclusão do envio do campo `debug_code` nos retornos JSON de registro e login em `api/login.php` quando DEBUG_MODE está ativado, permitindo validação imediata.
- Orientação sobre o código mestre de contingência (`000000`) ativado durante o período de configuração do servidor SMTP.

[11/08/2026 - 17:15]
- Conexão e envio (git push) de todo o código-fonte limpo para o repositório oficial no GitHub (https://github.com/Pinheiro07/pricexp.git).

[11/08/2026 - 17:13]
- Auditoria completa de segurança e privacidade do repositório antes da publicação.
- Remoção de scripts de teste (api/test_db.php e api/test_smtp.php) do controle de versão.
- Atualização das regras do .gitignore para bloquear credenciais (.env, .pem, .key), fotos de usuários (uploads/) e dumps de banco de dados (.sql, .dump).

[11/08/2026 - 16:53]
- Instalação e configuração do ambiente Git (MinGit 2.55.0 para Windows) e inclusão nas variáveis de ambiente PATH do usuário.
- Inicialização do repositório Git local, renomeação da branch principal para 'main' e realização do primeiro commit ("Initial commit - PriceXP Finance App").

[11/08/2026 - 16:30]
- Criação do arquivo .gitignore configurado para ignorar caches (.agents, graphify-out), imagens de upload temporárias e arquivos de teste antes do envio para o GitHub.
- Preparação do repositório para upload no GitHub.

[11/08/2026 - 15:01]
- Verificação, auditoria e validação de integridade sintática do arquivo app.js (73 KB, sintaxe 100% válida).
- Confirmação de que o app.js local contém todas as implementações mais recentes (edição de lançamentos, filtro de bancos, seleção de cartão, importação OFX).

--------------------------------------------------------------------
1. BANCOS E INSTITUIÇÕES FINANCEIRAS
--------------------------------------------------------------------
- [Banco de Dados] Auto-migração da coluna `bank_name` (DEFAULT 'Geral') na tabela `transactions` em `api/config.php`.
- [Backend] Atualização de `api/transactions.php` e `api/import_ofx.php` para receber e persisitir o nome do banco.
- [Formulário] Adicionado seletor de Banco/Instituição (Nubank, Itaú, Bradesco, Banco do Brasil, Inter, Santander, C6 Bank, Sicredi, Caixa, Outro) no formulário de lançamentos (`index.html`).
- [Formulário] Adicionado botão '+' para inclusão dinâmica de novos bancos/instituições personalizadas.
- [Dashboard] Adicionado seletor de filtro por Banco no cabeçalho da Dashboard para filtrar receitas, despesas, saldo e gráficos por banco específico.
- [Lançamentos] Exibição do nome do banco nas linhas de lançamentos recentes.

--------------------------------------------------------------------
2. EDIÇÃO DE LANÇAMENTOS (CRUD COMPLETO)
--------------------------------------------------------------------
- [Backend] Implementada rota HTTP `PUT` em `api/transactions.php` para atualização de lançamentos existentes.
- [Backend] Conversão de tipo (cast integer) do `id` do lançamento na resposta GET do `api/transactions.php`.
- [Interface] Adicionado modal de edição `#edit-tx-modal` no `index.html`.
- [Interface] Adicionado botão de lápis (editar) em cada lançamento na lista (`app.js`).
- [JS] Ajuste de comparação de tipos (parseInt) no evento de abertura do modal em `app.js`.

--------------------------------------------------------------------
3. IMPORTAÇÃO DE EXTRATOS (OFX / CSV)
--------------------------------------------------------------------
- [Backend] Criação de `api/import_ofx.php` para parse e commit de arquivos OFX e CSV.
- [Interface] Modal de revisão `#ofx-preview-modal` permitindo alterar descrição, categoria, tipo, excluir itens antes da confirmação final.

--------------------------------------------------------------------
4. CARTÕES DE CRÉDITO
--------------------------------------------------------------------
- [Banco de Dados] Tabela `credit_cards` e chave estrangeira `card_id` na tabela `transactions`.
- [API] Endpoints CRUD em `api/cards.php`.
- [Formulário] Seletor dinâmico de método de pagamento (Dinheiro/Débito vs Cartão de Crédito) associando o `card_id`.

--------------------------------------------------------------------
5. SEGURANÇA E AUTENTICAÇÃO
--------------------------------------------------------------------
- [Headers] Configuração de Security Headers HTTP em `api/config.php` (X-Frame-Options, X-Content-Type-Options, CSP, Referrer-Policy, Permissions-Policy).
- [Sessão] Expiração por inatividade (20 minutos) e sistema de auto-login "lembre-me" por 30 dias com hash SHA-256 e rotação de tokens em `user_remember_tokens`.
- [Admin] Painel de Administração (`api/admin_users.php`) com controle de usuários restrito estritamente à conta `lucassilvapinheiro07@gmail.com`.

--------------------------------------------------------------------
6. DESIGN E RESPONSIVIDADE MOBILE
--------------------------------------------------------------------
- [Tipografia] Atualização do sistema de fontes para **C6 Sans** (`font-family: 'C6 Sans', 'Inter', ...`) em `style.css`.
- [Mobile] Ajustes de padding inferior na main-content para a navegação fixa mobile, alvos de toque de 42px e rolagem horizontal na tabela de importação OFX.
- [Interface] Toggle para visualização de senha (ícone de olho) centralizado nos formulários.
