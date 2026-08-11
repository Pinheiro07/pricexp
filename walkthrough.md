# Controle de Cartões de Crédito e Faturas no PriceXP 💳

A nova funcionalidade de **Cartões de Crédito** e **Faturas Inteligentes** foi completamente implementada! Agora você tem uma gestão profissional das despesas em cartão de crédito separadas do seu saldo em conta (dinheiro em espécie ou saldo bancário).

## O que foi construído

1. **Auto-Migração no Banco de Dados (`api/config.php`):**
   - Para que você não precise rodar comandos complexos no banco de dados, criamos uma inteligência na conexão que verifica automaticamente se as tabelas existem. 
   - No momento em que você subir o arquivo e atualizar a página, o PHP criará a tabela `credit_cards` e adicionará a coluna `card_id` na tabela `transactions` de forma totalmente transparente!

2. **Aba de Gestão de Cartões (`index.html` & `app.js`):**
   - Uma nova aba **"Cartões"** foi adicionada na barra lateral.
   - Nela, você pode cadastrar cartões definindo o **Nome**, **Limite Total (R$)**, **Dia de Fechamento** e **Dia de Vencimento**.
   - A lista renderiza os cartões de forma elegante (design estilo cartão físico escuro com efeitos translúcidos e de bordas) exibindo a **Fatura Atual do Mês**, o **Limite Disponível** e uma **Barra de Progresso de Uso** em tempo real!

3. **Seletor de Forma de Pagamento nos Lançamentos:**
   - No formulário de lançamentos, adicionamos um campo chamado **"Forma de Pagamento"**.
   - Por padrão, as compras são feitas em *"Dinheiro / Pix / Débito"* (que deduzem imediatamente do seu saldo).
   - Se você selecionar um dos cartões de crédito cadastrados, a despesa será computada diretamente para a fatura correspondente.

4. **Cálculo Dinâmico das Faturas:**
   - O aplicativo calcula automaticamente em qual fatura mensal a despesa cai com base no dia do fechamento. Por exemplo: se a fatura fecha dia 5 e você compra algo no dia 6, o app joga essa compra para a fatura do mês seguinte de forma automática!
   - **Saldo Inteligente:** Compras no cartão de crédito continuam aparecendo na estatística de "Despesas Totais", mas o seu **"Saldo Atual"** do dashboard (caixa) não é deduzido imediatamente, mantendo o seu fluxo de dinheiro de verdade alinhado!

---

## Como testar agora mesmo:

Para ativar esse recurso, envie para o seu servidor via WinSCP os seguintes arquivos da sua Área de Trabalho:
1. **`index.html`**
2. **`app.js`**
3. Toda a pasta **`api/`** (para atualizar o `config.php`, `transactions.php` e adicionar o novo `cards.php`).

Após atualizar o servidor, dê um `Ctrl + F5` no seu navegador. Acesse a aba **"Cartões"** e cadastre o seu primeiro cartão para ver a mágica começar!