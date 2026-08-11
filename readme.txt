====================================================================
               PRICE XP - HISTÓRICO COMPLETO DE ALTERAÇÕES (README)
====================================================================

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
