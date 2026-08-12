====================================================================
               PRICE XP - HISTÓRICO COMPLETO DE ALTERAÇÕES (README)
====================================================================

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
