// Icons initialization
lucide.createIcons();

// --- STATE MANAGEMENT ---
let transactions = [];
let cards = [];
let isLoginMode = true;
let dashboardFilteredTransactions = [];

let CATEGORIES = {
    receita: ["Salário Líquido", "13º Salário Líquido", "Férias Líquida", "Bônus + Comissões + PLR", "Renda Extra Líquida", "Outras Receitas"],
    despesa: ["Casa", "Saúde", "Locomoção", "Lazer", "Transporte", "Investimentos", "Outras Despesas"]
}

const btnToggleTheme = document.getElementById('btn-toggle-theme');
if (btnToggleTheme) {
    btnToggleTheme.addEventListener('click', toggleTheme);
}
const btnToggleThemeMobile = document.querySelector('.btn-toggle-theme-mobile');
if (btnToggleThemeMobile) {
    btnToggleThemeMobile.addEventListener('click', toggleTheme);
}
const btnToggleThemeAuth = document.getElementById('btn-toggle-theme-auth');
if (btnToggleThemeAuth) {
    btnToggleThemeAuth.addEventListener('click', toggleTheme);
}
const btnToggleThemeMobileHeader = document.getElementById('btn-toggle-theme-mobile-header');
if (btnToggleThemeMobileHeader) {
    btnToggleThemeMobileHeader.addEventListener('click', toggleTheme);
}

// Bind mobile header menu tabs navigation clicks
document.querySelectorAll('[data-mobile-tab]').forEach(el => {
    el.addEventListener('click', () => {
        const tabName = el.dataset.mobileTab;
        const desktopTabButton = document.querySelector(`.nav-links li[data-tab="${tabName}"]`);
        if (desktopTabButton) {
            desktopTabButton.click();
        }
        const dropdown = document.getElementById('hero-menu-dropdown');
        if (dropdown) dropdown.style.display = 'none';

        // Update active state on bottom nav buttons
        document.querySelectorAll('.mob-nav-btn').forEach(btn => btn.classList.remove('active'));
        const activeBtn = document.querySelector(`.mob-nav-btn[data-mobile-tab="${tabName}"]`);
        if (activeBtn) activeBtn.classList.add('active');
    });
});

// Also sync bottom nav active when desktop tabs are clicked
document.querySelectorAll('.nav-links li[data-tab]').forEach(li => {
    li.addEventListener('click', () => {
        const tabName = li.dataset.tab;
        document.querySelectorAll('.mob-nav-btn').forEach(btn => btn.classList.remove('active'));
        const activeBtn = document.querySelector(`.mob-nav-btn[data-mobile-tab="${tabName}"]`);
        if (activeBtn) activeBtn.classList.add('active');
    });
});

// Dropdown Top Hero Menu Trigger Toggle
const btnHeroMenu = document.getElementById('btn-hero-menu');
const heroMenuDropdown = document.getElementById('hero-menu-dropdown');
if (btnHeroMenu && heroMenuDropdown) {
    btnHeroMenu.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = heroMenuDropdown.style.display === 'flex';
        heroMenuDropdown.style.display = isOpen ? 'none' : 'flex';
    });
    
    document.addEventListener('click', (e) => {
        if (!heroMenuDropdown.contains(e.target) && e.target !== btnHeroMenu) {
            heroMenuDropdown.style.display = 'none';
        }
    });
};

// --- DOM ELEMENTS ---
const authScreen = document.getElementById('auth-screen');
const appContainer = document.getElementById('app-container');
const authForm = document.getElementById('auth-form');
const authTitle = document.getElementById('auth-title');
const authSubtitle = document.getElementById('auth-subtitle');
const authBtn = document.getElementById('auth-btn');
const authError = document.getElementById('auth-error');
const authSwitchLink = document.getElementById('auth-switch-link');
const authSwitchText = document.getElementById('auth-switch-text');
const btnLogout = document.getElementById('btn-logout');

// New Auth Elements
const registerFields = document.getElementById('register-fields');
const rememberMeGroup = document.getElementById('remember-me-group');
const verificationForm = document.getElementById('verification-form');
let rememberOnVerify = false;

// Sidebar Elements
const sidebarPhoto = document.getElementById('sidebar-photo');
const userNameDisplay = document.getElementById('user-name-display');
const dashboardWelcome = document.getElementById('dashboard-welcome');

// App Elements
const form = document.getElementById('transaction-form');
const categorySelect = document.getElementById('category');
const typeRadios = document.querySelectorAll('input[name="type"]');
const transactionList = document.getElementById('transaction-list');
const tabs = document.querySelectorAll('.nav-links li');
const tabContents = document.querySelectorAll('.tab-content');
const repeatTypeSelect = document.getElementById('repeat-type');
const installmentsGroup = document.getElementById('installments-group');

let barChart, pieChart, lineChart;

// --- THEME MANAGEMENT ---
function initTheme() {
    const savedTheme = localStorage.getItem('theme') || 'light';
    if (savedTheme === 'light') {
        document.body.classList.add('light-theme');
        updateThemeUI(true);
    } else {
        document.body.classList.remove('light-theme');
        updateThemeUI(false);
    }
}

function toggleTheme() {
    try {
        const isLight = document.body.classList.toggle('light-theme');
        localStorage.setItem('theme', isLight ? 'light' : 'dark');
        updateThemeUI(isLight);
        applyThemeToCharts();
    } catch (err) {
        alert("Erro no toggleTheme: " + err.message + "\nStack: " + err.stack);
    }
}

function updateThemeUI(isLight) {
    const themeIcons = document.querySelectorAll('#btn-toggle-theme i, #btn-toggle-theme svg, .btn-toggle-theme-mobile i, .btn-toggle-theme-mobile svg, #btn-toggle-theme-auth i, #btn-toggle-theme-auth svg');
    const themeTexts = document.querySelectorAll('#btn-toggle-theme span, .btn-toggle-theme-mobile span');
    
    themeIcons.forEach(icon => {
        if (!icon.closest('#btn-toggle-theme-mobile-header')) {
            icon.setAttribute('data-lucide', isLight ? 'moon' : 'sun');
        }
    });
    themeTexts.forEach(text => {
        text.innerText = isLight ? 'Tema Escuro' : 'Tema Claro';
    });

    const mobileHeaderThemeBtn = document.getElementById('btn-toggle-theme-mobile-header');
    if (mobileHeaderThemeBtn) {
        mobileHeaderThemeBtn.textContent = isLight ? '🌙' : '☀️';
    }

    // Troca de logos baseado no tema (Tema Claro = logos escuras, Tema Escuro = logos claras/brancas)
    const sidebarLogoImg = document.querySelector('.sidebar .logo img');
    if (sidebarLogoImg) {
        sidebarLogoImg.src = isLight ? 'logo.png' : 'logo_dark_theme.png';
    }
    const loginLogoImg = document.querySelector('#auth-screen .logo img');
    if (loginLogoImg) {
        loginLogoImg.src = isLight ? 'logo_login.png' : 'logo_login_dark_theme.png';
    }
    const dashboardLogoImg = document.querySelector('.dashboard-logo');
    if (dashboardLogoImg) {
        dashboardLogoImg.src = isLight ? 'logo_horizontal.png' : 'logo_horizontal_dark_theme.png';
    }
    
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

function applyThemeToCharts() {
    const isLight = document.body.classList.contains('light-theme');
    const textColor = isLight ? '#475569' : '#94a3b8';
    const gridColor = isLight ? '#e2e8f0' : 'rgba(255, 255, 255, 0.08)';
    
    [barChart, pieChart, lineChart].forEach(chart => {
        if (chart) {
            const isPie = chart.config.type === 'doughnut' || chart.config.type === 'pie';
            
            if (!isPie) {
                chart.config.options.scales = chart.config.options.scales || {};
                
                chart.config.options.scales.x = chart.config.options.scales.x || {};
                chart.config.options.scales.x.grid = chart.config.options.scales.x.grid || {};
                chart.config.options.scales.x.grid.color = gridColor;
                
                chart.config.options.scales.x.ticks = chart.config.options.scales.x.ticks || {};
                chart.config.options.scales.x.ticks.color = textColor;
                
                chart.config.options.scales.y = chart.config.options.scales.y || {};
                chart.config.options.scales.y.grid = chart.config.options.scales.y.grid || {};
                chart.config.options.scales.y.grid.color = gridColor;
                
                chart.config.options.scales.y.ticks = chart.config.options.scales.y.ticks || {};
                chart.config.options.scales.y.ticks.color = textColor;
            }
            
            // Legend text color
            if (chart.config.options.plugins && chart.config.options.plugins.legend) {
                chart.config.options.plugins.legend.labels = chart.config.options.plugins.legend.labels || {};
                chart.config.options.plugins.legend.labels.color = textColor;
            }
            
            chart.update();
        }
    });
}

// --- INITIALIZATION ---
async function init() {
    initTheme();
    initCharts();
    updateCategoryOptions();
    initMobileNavEvents();
    await checkSession();
    await fetchCategories();
}

function initMobileNavEvents() {
    const btnMobileMenu = document.getElementById('btn-mobile-menu');
    const btnMobileAlerts = document.getElementById('btn-mobile-alerts');
    const sidebarBackdrop = document.getElementById('sidebar-backdrop');
    const mobileAlertsDropdown = document.getElementById('mobile-alerts-dropdown');
    const sidebar = document.querySelector('.sidebar');

    if (btnMobileMenu && sidebar && sidebarBackdrop) {
        btnMobileMenu.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('sidebar-open');
            sidebarBackdrop.classList.toggle('active');
            
            // Close alerts dropdown when menu opens
            if (mobileAlertsDropdown) mobileAlertsDropdown.style.display = 'none';
        });
    }

    if (sidebarBackdrop && sidebar) {
        sidebarBackdrop.addEventListener('click', () => {
            sidebar.classList.remove('sidebar-open');
            sidebarBackdrop.classList.remove('active');
        });
    }

    if (btnMobileAlerts && mobileAlertsDropdown && sidebar && sidebarBackdrop) {
        btnMobileAlerts.addEventListener('click', (e) => {
            e.stopPropagation();
            const isOpen = mobileAlertsDropdown.style.display === 'block';
            mobileAlertsDropdown.style.display = isOpen ? 'none' : 'block';
            
            // Close sidebar when alerts open
            if (sidebar.classList.contains('sidebar-open')) {
                sidebar.classList.remove('sidebar-open');
                sidebarBackdrop.classList.remove('active');
            }
            
            if (!isOpen) {
                renderMobileAlerts();
            }
        });

        document.addEventListener('click', (e) => {
            if (!mobileAlertsDropdown.contains(e.target) && e.target !== btnMobileAlerts) {
                mobileAlertsDropdown.style.display = 'none';
            }
        });
    }
}

function renderMobileAlerts() {
    const alertsList = document.getElementById('alerts-list');
    const badge = document.getElementById('alerts-badge');
    if (!alertsList) return;

    const list = [];
    
    // 1. Check current balance
    const totalReceitas = transactions.filter(t => t.type === 'receita').reduce((sum, t) => sum + t.amount, 0);
    const totalDespesas = transactions.filter(t => t.type === 'despesa').reduce((sum, t) => sum + t.amount, 0);
    const saldo = totalReceitas - totalDespesas;

    if (saldo < 500 && transactions.length > 0) {
        list.push({
            title: "Atenção: Saldo Baixo",
            desc: `Seu saldo acumulado atual é de ${formatCurrency(saldo)}. Considere limitar novas despesas.`,
            time: "Agora"
        });
    }

    // 2. Check card limit usage
    cards.forEach(card => {
        const cardTx = transactions.filter(t => t.card_id === card.id && t.type === 'despesa');
        const invoiceTotal = cardTx.reduce((sum, t) => sum + t.amount, 0);
        const limitPercentage = (invoiceTotal / card.credit_limit) * 100;
        
        if (limitPercentage >= 80) {
            list.push({
                title: `Limite do Cartão: ${card.name}`,
                desc: `Você já consumiu ${limitPercentage.toFixed(0)}% do limite do cartão (${formatCurrency(invoiceTotal)} de ${formatCurrency(card.credit_limit)}).`,
                time: "Há 10 min"
            });
        }
    });

    // 3. Welcoming default alert if list is empty
    if (list.length === 0) {
        list.push({
            title: "Tudo sob controle!",
            desc: "Não há alertas de gastos ou limite pendentes no momento. Bom trabalho!",
            time: "Hoje"
        });
    }

    // Update red dot badge
    if (badge) {
        const warnings = list.filter(item => item.title.includes("Atenção") || item.title.includes("Limite"));
        badge.style.display = warnings.length > 0 ? 'block' : 'none';
    }

    alertsList.innerHTML = list.map(item => `
        <div class="alert-item">
            <span style="font-weight: 600; color: #fff;">${item.title}</span>
            <span style="color: var(--text-muted); font-size: 0.8rem; line-height: 1.3;">${item.desc}</span>
            <span class="alert-time">${item.time}</span>
        </div>
    `).join('');
}

// --- AUTHENTICATION ---
async function checkSession() {
    try {
        const res = await fetch('api/login.php?action=check');
        const data = await res.json();
        
        if (data.logged_in) {
            showApp(data);
            await fetchTransactions();
            await fetchCards();
        } else {
            showAuth();
        }
    } catch (e) {
        showAuth();
    }
}

function showApp(userData) {
    authScreen.style.display = 'none';
    appContainer.style.display = 'flex';
    
    userNameDisplay.textContent = userData.first_name || 'Usuário';
    
    const userLastnameDisplay = document.getElementById('user-lastname-display');
    if (userLastnameDisplay) {
        userLastnameDisplay.textContent = userData.last_name || '';
    }
    
    dashboardWelcome.innerHTML = `Bem-vindo de volta, ${userData.first_name || ''}! 👋`;
    
    // Configura a foto de perfil
    if (userData.profile_picture && userData.profile_picture !== 'default.png') {
        sidebarPhoto.src = 'uploads/' + userData.profile_picture;
    } else {
        // Fallback genérico se não tiver foto
        sidebarPhoto.src = 'https://ui-avatars.com/api/?name=' + (userData.first_name || 'U') + '&background=3b82f6&color=fff';
    }
    
    // Botão exclusivo para a conta Admin
    const btnAdminPanel = document.getElementById('btn-admin-panel');
    if (btnAdminPanel) {
        const isAdmin = userData.email && userData.email.toLowerCase() === 'lucassilvapinheiro07@gmail.com';
        btnAdminPanel.style.display = isAdmin ? 'flex' : 'none';
    }

    tabs[0].click(); // Go to dashboard
    lucide.createIcons();
    populateProfileForm(userData);
}

function showAuth() {
    authScreen.style.display = 'flex';
    appContainer.style.display = 'none';
}

authSwitchLink.addEventListener('click', (e) => {
    e.preventDefault();
    isLoginMode = !isLoginMode;
    authError.style.display = 'none';
    
    const confirmPasswordGroup = document.getElementById('confirm-password-group');
    const confirmPasswordInput = document.getElementById('auth-confirm-password');
    
    if (isLoginMode) {
        authTitle.textContent = 'Entrar na sua conta';
        authSubtitle.textContent = 'Gerencie suas finanças em um só lugar';
        authBtn.textContent = 'Entrar';
        authSwitchText.textContent = 'Não tem uma conta?';
        authSwitchLink.textContent = 'Cadastre-se';
        registerFields.style.display = 'none';
        
        document.getElementById('auth-firstname').removeAttribute('required');
        
        if (confirmPasswordGroup) confirmPasswordGroup.style.display = 'none';
        if (confirmPasswordInput) confirmPasswordInput.removeAttribute('required');
    } else {
        authTitle.textContent = 'Criar nova conta';
        authSubtitle.textContent = 'Comece a gerenciar suas finanças agora';
        authBtn.textContent = 'Cadastrar';
        authSwitchText.textContent = 'Já tem uma conta?';
        authSwitchLink.textContent = 'Faça login';
        registerFields.style.display = 'block';
        
        document.getElementById('auth-firstname').setAttribute('required', 'true');
        
        if (confirmPasswordGroup) confirmPasswordGroup.style.display = 'block';
        if (confirmPasswordInput) confirmPasswordInput.setAttribute('required', 'true');
    }
});


authForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const action = isLoginMode ? 'login' : 'register';
    
    authBtn.disabled = true;
    authBtn.textContent = 'Aguarde...';
    
    try {
        let reqOptions = {};
        
        if (isLoginMode) {
            // Login simples (JSON)
            const email = document.getElementById('auth-email').value;
            const password = document.getElementById('auth-password').value;
            reqOptions = {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, password })
            };
        } else {
            // Cadastro com Imagem (FormData)
            const password = document.getElementById('auth-password').value;
            const confirmPassword = document.getElementById('auth-confirm-password').value;
            if (password !== confirmPassword) {
                authError.textContent = 'As senhas não coincidem!';
                authError.style.display = 'block';
                authBtn.disabled = false;
                authBtn.textContent = 'Cadastrar';
                return;
            }
            const formData = new FormData(authForm);
            reqOptions = {
                method: 'POST',
                body: formData // Não seta Content-Type para o browser resolver o boundary
            };
        }
        
        const res = await fetch(`api/login.php?action=${action}`, reqOptions);
        const data = await res.json();
        
        if (data.success) {
            authError.style.display = 'none';
            if (data.require_verification) {
                document.getElementById('verify-email').value = data.email;
                authForm.style.display = 'none';
                verificationForm.style.display = 'block';
                rememberOnVerify = document.getElementById('auth-remember').checked;
            } else {
                await checkSession();
                authForm.reset();
            }
            
        } else {
            authError.textContent = data.error || 'Ocorreu um erro.';
            authError.style.display = 'block';
        }

    } catch (err) {
        authError.textContent = 'Erro de conexão.';
        authError.style.display = 'block';
    } finally {
        authBtn.disabled = false;
        authBtn.textContent = isLoginMode ? 'Entrar' : 'Cadastrar';
        lucide.createIcons();
    }
});

const verifyBackLink = document.getElementById('verify-back-link');
if (verifyBackLink) {
    verifyBackLink.addEventListener('click', (e) => {
        e.preventDefault();
        document.getElementById('verification-form').style.display = 'none';
        authForm.style.display = 'block';
        document.getElementById('auth-switch-wrapper').style.display = 'block';
        isLoginMode = true;
        authTitle.textContent = 'Entrar na sua conta';
        authSubtitle.textContent = 'Acesse seus dados financeiros com segurança';
        authBtn.textContent = 'Entrar';
        authSwitchText.textContent = 'Não tem uma conta?';
        authSwitchLink.textContent = 'Cadastre-se';
        registerFields.style.display = 'none';
        document.getElementById('auth-firstname').removeAttribute('required');
    });
}

if (verificationForm) {
    verificationForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const email = document.getElementById('verify-email').value;
        const code = document.getElementById('verify-code').value;
        const verifyBtn = document.getElementById('verify-btn');
        const verifyError = document.getElementById('verify-error');
        
        verifyBtn.disabled = true;
        verifyBtn.textContent = 'Confirmando...';
        verifyError.style.display = 'none';
        
        try {
            const res = await fetch('api/login.php?action=verify', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, code, remember: rememberOnVerify })
            });
            const data = await res.json();
            if (data.success) {
                await checkSession();
                verificationForm.style.display = 'none';
                authForm.reset();
            } else {
                verifyError.textContent = data.error || 'Código inválido.';
                verifyError.style.display = 'block';
            }
        } catch (err) {
            verifyError.textContent = 'Erro de conexão: ' + err.message;
            verifyError.style.display = 'block';
        } finally {
            verifyBtn.disabled = false;
            verifyBtn.textContent = 'Confirmar Código';
        }
    });
}

btnLogout.addEventListener('click', async () => {
    await fetch('api/login.php?action=logout');
    transactions = [];
    showAuth();
});

const btnLogoutMobile = document.querySelector('.btn-logout-mobile');
if (btnLogoutMobile) {
    btnLogoutMobile.addEventListener('click', async () => {
        await fetch('api/login.php?action=logout');
        transactions = [];
        showAuth();
    });
}

const DEFAULT_BANKS = ["Geral", "Nubank", "Itaú", "Bradesco", "Banco do Brasil", "Inter", "Santander", "C6 Bank", "Sicredi", "Caixa", "Outro"];

function populateTransactionBankSelector() {
    const bankSelect = document.getElementById('bank-name');
    if (!bankSelect) return;
    const currentVal = bankSelect.value;
    
    // Obter bancos adicionais salvos em transações antigas
    const txBanks = transactions.map(t => t.bank_name).filter(Boolean);
    const allBanks = Array.from(new Set([...DEFAULT_BANKS, ...txBanks]));
    
    bankSelect.innerHTML = '';
    allBanks.forEach(bank => {
        const opt = document.createElement('option');
        opt.value = bank;
        opt.textContent = bank === 'Geral' ? 'Conta Geral / Dinheiro' : (bank === 'Outro' ? 'Outro / Carteira' : bank);
        bankSelect.appendChild(opt);
    });
    
    if (currentVal && allBanks.includes(currentVal)) {
        bankSelect.value = currentVal;
    } else {
        bankSelect.value = 'Geral';
    }
}

// --- TRANSACTIONS API ---
async function fetchTransactions() {
    try {
        const res = await fetch('api/transactions.php');
        if (res.ok) {
            transactions = await res.json();
            populateTransactionBankSelector();
            renderTransactions();
            updateDashboard();
        }
    } catch (e) {
        console.error('Failed to fetch transactions');
    }
}

async function addTransaction(tx) {
    try {
        const res = await fetch('api/transactions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(tx)
        });
        if (res.ok) {
            await fetchTransactions();
            await fetchCards();
        }
    } catch (e) {
        console.error('Failed to add tx');
    }
}

async function deleteTransaction(id) {
    if(!confirm('Deseja excluir este lançamento?')) return;
    try {
        const res = await fetch(`api/transactions.php?id=${id}`, { method: 'DELETE' });
        if (res.ok) {
            await fetchTransactions();
            await fetchCards();
        }
    } catch (e) {
        console.error('Failed to delete tx');
    }
}

// --- NAVIGATION ---
tabs.forEach(tab => {
    tab.addEventListener('click', () => {
        tabs.forEach(t => t.classList.remove('active'));
        tabContents.forEach(c => c.classList.remove('active'));
        tab.classList.add('active');
        document.getElementById(`tab-${tab.dataset.tab}`).classList.add('active');
        if(tab.dataset.tab === 'dashboard') updateDashboard();
        if(tab.dataset.tab === 'cartoes') renderCards();
        if(tab.dataset.tab === 'shared') fetchSharedAccountStatus();

        // Fechar gaveta mobile ao navegar
        const sidebar = document.querySelector('.sidebar');
        const backdrop = document.getElementById('sidebar-backdrop');
        if (sidebar && sidebar.classList.contains('sidebar-open')) {
            sidebar.classList.remove('sidebar-open');
            if (backdrop) backdrop.classList.remove('active');
        }
    });
});

async function fetchCategories() {
    try {
        const res = await fetch('api/categories.php');
        if (res.ok) {
            CATEGORIES = await res.json();
            updateCategoryOptions();
        }
    } catch (e) {
        console.error('Failed to fetch categories');
    }
}

const btnAddCategory = document.getElementById('btn-add-category');
if (btnAddCategory) {
    btnAddCategory.addEventListener('click', async () => {
        const newCat = prompt('Digite o nome da nova categoria:');
        if (!newCat) return;
        
        const trimmedCat = newCat.trim();
        if (trimmedCat === '') return;
        
        const type = document.querySelector('input[name="type"]:checked').value;
        
        try {
            const res = await fetch('api/categories.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name: trimmedCat, type: type })
            });
            const data = await res.json();
            if (data.success) {
                await fetchCategories();
                document.getElementById('category').value = trimmedCat;
            } else {
                alert(data.error || 'Erro ao adicionar categoria.');
            }
        } catch (e) {
            alert('Erro de conexão ao adicionar categoria.');
        }
    });
}

const btnAddBank = document.getElementById('btn-add-bank');
if (btnAddBank) {
    btnAddBank.addEventListener('click', () => {
        const newBank = prompt('Digite o nome do novo banco ou instituição:');
        if (!newBank) return;
        
        const trimmedBank = newBank.trim();
        if (trimmedBank === '') return;
        
        const bankSelect = document.getElementById('bank-name');
        if (bankSelect) {
            // Verificar se o banco já existe nas opções
            let exists = false;
            for (let opt of bankSelect.options) {
                if (opt.value.toLowerCase() === trimmedBank.toLowerCase()) {
                    exists = true;
                    bankSelect.value = opt.value;
                    break;
                }
            }
            if (!exists) {
                const opt = document.createElement('option');
                opt.value = trimmedBank;
                opt.textContent = trimmedBank;
                bankSelect.appendChild(opt);
                bankSelect.value = trimmedBank;
            }
        }
    });
}

// --- FORM HANDLING ---
typeRadios.forEach(radio => radio.addEventListener('change', updateCategoryOptions));

if (repeatTypeSelect && installmentsGroup) {
    repeatTypeSelect.addEventListener('change', () => {
        installmentsGroup.style.display = repeatTypeSelect.value === 'installment' ? 'block' : 'none';
    });
}

function updateCategoryOptions() {
    const selectedType = document.querySelector('input[name="type"]:checked').value;
    categorySelect.innerHTML = '';
    CATEGORIES[selectedType].forEach(cat => {
        const option = document.createElement('option');
        option.value = cat;
        option.textContent = cat;
        categorySelect.appendChild(option);
    });

    const paymentGroup = document.getElementById('payment-method-group');
    const paymentSelector = document.getElementById('tx-payment-method');
    if (paymentGroup && paymentSelector) {
        if (selectedType === 'receita') {
            paymentGroup.style.display = 'none';
            paymentSelector.value = 'cash'; // Reset to cash/default
        } else {
            paymentGroup.style.display = 'block';
            populatePaymentMethodSelector();
        }
    }
}

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const typeInput = document.querySelector('input[name="type"]:checked');
    if (!typeInput) return;
    const type = typeInput.value;

    const repeatTypeEl = document.getElementById('repeat-type');
    const repeatType = repeatTypeEl ? repeatTypeEl.value : 'none';
    const installmentsEl = document.getElementById('installments');
    const installments = (repeatType === 'installment' && installmentsEl) ? parseInt(installmentsEl.value) || 12 : null;

    const paymentSelector = document.getElementById('tx-payment-method');
    const paymentVal = (type === 'despesa' && paymentSelector) ? paymentSelector.value : 'cash';
    const cardId = (paymentVal && paymentVal !== 'cash') ? parseInt(paymentVal) : null;

    const bankNameEl = document.getElementById('bank-name');
    const bankName = bankNameEl ? bankNameEl.value : 'Geral';

    const tx = {
        type: type,
        category: document.getElementById('category').value,
        description: document.getElementById('description').value,
        amount: parseFloat(document.getElementById('amount').value),
        date: document.getElementById('date').value,
        is_fixed: repeatType === 'fixed' ? 1 : 0,
        repeat_type: repeatType,
        installments: installments,
        card_id: cardId,
        bank_name: bankName
    };

    const btn = form.querySelector('button[type="submit"]');
    if (btn) btn.disabled = true;
    
    try {
        const res = await fetch('api/transactions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(tx)
        });
        if (res.ok) {
            await fetchTransactions();
            await fetchCards();
            form.reset();
            updateCategoryOptions();
            // Reseta o selector de tipo para Receita
            const receitaRadio = document.querySelector('input[name="type"][value="receita"]');
            if (receitaRadio) receitaRadio.checked = true;
            updateCategoryOptions();
        } else {
            const errData = await res.json().catch(() => null);
            console.error('Failed to add tx:', errData);
        }
    } catch (err) {
        console.error('Failed to add tx', err);
    }
    
    if (btn) btn.disabled = false;
});

function renderTransactions() {
    transactionList.innerHTML = '';
    if (transactions.length === 0) {
        transactionList.innerHTML = '<p class="text-muted text-center" style="padding: 2rem;">Nenhum lançamento encontrado.</p>';
        return;
    }
    
    transactions.forEach(tx => {
        const isReceita = tx.type === 'receita';
        const colorClass = isReceita ? 'text-success' : 'text-danger';
        const sign = isReceita ? '+' : '-';
        const authorTag = tx.created_by_name ? ` • ${tx.created_by_name}` : '';
        
        const item = document.createElement('div');
        item.className = 'tx-item';
        item.innerHTML = `
            <div class="tx-left">
                <span class="tx-desc">${tx.description}</span>
                <span class="tx-cat-date">${tx.category} ${tx.bank_name ? '• ' + tx.bank_name : ''}${authorTag} • ${formatDate(tx.date)}</span>
            </div>
            <div class="tx-actions">
                <span class="tx-amount ${colorClass}">${sign} ${formatCurrency(tx.amount)}</span>
                <button class="tx-edit" title="Editar lançamento" onclick="openEditTxModal(${tx.id})" style="background:transparent; border:none; color:var(--text-muted); cursor:pointer; padding:4px; border-radius:4px; display:flex; align-items:center; transition:color 0.2s;" onmouseover="this.style.color='#10b981'" onmouseout="this.style.color='var(--text-muted)'">
                    <i data-lucide="pencil" style="width:1rem; height:1rem;"></i>
                </button>
                <button class="tx-delete" onclick="deleteTransaction(${tx.id})">
                    <i data-lucide="trash-2"></i>
                </button>
            </div>
        `;
        transactionList.appendChild(item);
    });
    lucide.createIcons();
}

// --- EDIT TRANSACTION MODAL ---
const editTxModal = document.getElementById('edit-tx-modal');
const editTxForm  = document.getElementById('edit-tx-form');

function openEditTxModal(id) {
    id = parseInt(id);
    const tx = transactions.find(t => parseInt(t.id) === id);
    if (!tx) return;

    document.getElementById('edit-tx-id').value          = tx.id;
    document.getElementById('edit-tx-description').value = tx.description;
    document.getElementById('edit-tx-amount').value      = tx.amount;
    document.getElementById('edit-tx-date').value        = tx.date;
    document.getElementById('edit-tx-msg').style.display = 'none';

    // Tipo
    const typeRadio = document.querySelector(`input[name="edit-type"][value="${tx.type}"]`);
    if (typeRadio) typeRadio.checked = true;

    // Banco — adiciona entrada personalizada se não existir
    const bankSel = document.getElementById('edit-tx-bank');
    const bankVal = tx.bank_name || 'Geral';
    let bankExists = false;
    for (let opt of bankSel.options) {
        if (opt.value === bankVal) { bankExists = true; break; }
    }
    if (!bankExists) {
        const opt = document.createElement('option');
        opt.value = bankVal;
        opt.textContent = bankVal;
        bankSel.appendChild(opt);
    }
    bankSel.value = bankVal;

    // Categorias
    populateEditCategorySelect(tx.type, tx.category);

    // Atualiza categorias ao mudar tipo
    document.querySelectorAll('input[name="edit-type"]').forEach(r => {
        r.onchange = () => populateEditCategorySelect(r.value, null);
    });

    editTxModal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function populateEditCategorySelect(type, selected) {
    const catSel = document.getElementById('edit-tx-category');
    catSel.innerHTML = '';
    const cats = (CATEGORIES || []).filter(c => c.type === type || c.type === 'all');
    // fallback estático se CATEGORIES ainda não carregou
    const fallback = {
        receita: ['Salário','Freelance','Investimento','Outros'],
        despesa: ['Alimentação','Moradia','Transporte','Saúde','Educação','Lazer','Outros'],
        investimento: ['Renda Fixa','Ações','Fundos','Criptomoedas','Outros']
    };
    const list = cats.length > 0 ? cats.map(c => c.name) : (fallback[type] || ['Outros']);
    list.forEach(name => {
        const opt = document.createElement('option');
        opt.value = name;
        opt.textContent = name;
        catSel.appendChild(opt);
    });
    if (selected) catSel.value = selected;
}

function closeEditTxModal() {
    editTxModal.style.display = 'none';
    document.body.style.overflow = '';
}

document.getElementById('btn-close-edit-tx').addEventListener('click', closeEditTxModal);
document.getElementById('btn-cancel-edit-tx').addEventListener('click', closeEditTxModal);
editTxModal.addEventListener('click', (e) => { if (e.target === editTxModal) closeEditTxModal(); });

editTxForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const saveBtn = document.getElementById('btn-save-edit-tx');
    const msgEl   = document.getElementById('edit-tx-msg');
    saveBtn.disabled = true;
    saveBtn.textContent = 'Salvando...';
    msgEl.style.display = 'none';

    const typeRadio = document.querySelector('input[name="edit-type"]:checked');
    const payload = {
        id:          parseInt(document.getElementById('edit-tx-id').value),
        type:        typeRadio ? typeRadio.value : 'despesa',
        category:    document.getElementById('edit-tx-category').value,
        description: document.getElementById('edit-tx-description').value,
        amount:      parseFloat(document.getElementById('edit-tx-amount').value),
        date:        document.getElementById('edit-tx-date').value,
        bank_name:   document.getElementById('edit-tx-bank').value,
    };

    try {
        const res = await fetch('api/transactions.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            closeEditTxModal();
            await fetchTransactions();
        } else {
            msgEl.textContent = data.error || 'Erro ao salvar.';
            msgEl.style.display = 'block';
        }
    } catch (err) {
        msgEl.textContent = 'Erro de conexão.';
        msgEl.style.display = 'block';
    }

    saveBtn.disabled = false;
    saveBtn.textContent = '💾 Salvar Alterações';
});


// Collapsible Mobile Header Bar on Scroll
const mainContent = document.querySelector('.main-content');
const headerBar = document.querySelector('.mobile-header-bar');
if (mainContent && headerBar) {
    mainContent.addEventListener('scroll', () => {
        if (mainContent.scrollTop > 50) {
            headerBar.classList.add('collapsed');
        } else {
            headerBar.classList.remove('collapsed');
        }
    });
}

// --- UTILS ---
function formatCurrency(val) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val);
}
function formatDate(dateStr) {
    const [y, m, d] = dateStr.split('-');
    return `${d}/${m}/${y}`;
}

// --- DASHBOARD LOGIC ---
Chart.defaults.color = '#94a3b8';


const dashboardPeriodSelect = document.getElementById('dashboard-period');
const dashboardMonthPicker = document.getElementById('dashboard-month-picker');
const dashboardYearPicker = document.getElementById('dashboard-year-picker');


if(dashboardPeriodSelect) {
    dashboardPeriodSelect.addEventListener('change', () => {
        if(dashboardPeriodSelect.value === 'month') {
            dashboardMonthPicker.style.display = 'block';
            dashboardYearPicker.style.display = 'none';
        } else if(dashboardPeriodSelect.value === 'year') {
            dashboardMonthPicker.style.display = 'none';
            dashboardYearPicker.style.display = 'block';
        } else {
            dashboardMonthPicker.style.display = 'none';
            dashboardYearPicker.style.display = 'none';
        }
        updateDashboard();
    });
}
const dashboardBankSelect = document.getElementById('dashboard-bank');
if(dashboardBankSelect) dashboardBankSelect.addEventListener('change', updateDashboard);
if(dashboardMonthPicker) dashboardMonthPicker.addEventListener('change', updateDashboard);
if(dashboardYearPicker) dashboardYearPicker.addEventListener('change', updateDashboard);

// --- DASHBOARD CLICK DETAILS MODAL ---
const detailsModal = document.getElementById('details-modal');
const detailsModalTitle = document.getElementById('details-modal-title');
const detailsModalBody = document.getElementById('details-modal-body');
const btnCloseDetails = document.getElementById('btn-close-details');

function openDetailsModal(type) {
    if (!detailsModal || !detailsModalTitle || !detailsModalBody) return;
    
    const periodSelect = document.getElementById('dashboard-period');
    const period = periodSelect ? periodSelect.value : 'month';
    let periodText = 'Todos os Tempos';
    
    if (period === 'month') {
        const monthVal = document.getElementById('dashboard-month-picker').value;
        if (monthVal) {
            const [y, m] = monthVal.split('-');
            const months = ["Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho", "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"];
            periodText = `${months[parseInt(m) - 1]} de ${y}`;
        }
    } else if (period === 'year') {
        periodText = document.getElementById('dashboard-year-picker').value || 'Ano';
    }
    
    const isReceita = type === 'receita';
    detailsModalTitle.innerText = isReceita ? `Receitas (${periodText})` : `Despesas e Inv. (${periodText})`;
    
    const items = dashboardFilteredTransactions.filter(t => t.type === type);
    detailsModalBody.innerHTML = '';
    
    if (items.length === 0) {
        detailsModalBody.innerHTML = '<p class="text-muted text-center" style="padding: 2rem; margin: 0;">Nenhum lançamento encontrado neste período.</p>';
    } else {
        items.forEach(tx => {
            const item = document.createElement('div');
            item.style.cssText = 'display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; background: rgba(255,255,255,0.03); border-radius: 0.5rem; border: 1px solid rgba(255,255,255,0.05);';
            item.innerHTML = `
                <div>
                    <span style="font-weight: 500; color: #fff; display: block;">${tx.description}</span>
                    <span style="font-size: 0.75rem; color: var(--text-muted);">${tx.category} • ${formatDate(tx.date)}</span>
                </div>
                <span style="font-weight: 600;" class="${isReceita ? 'text-success' : 'text-danger'}">${isReceita ? '+' : '-'} ${formatCurrency(tx.amount)}</span>
            `;
            detailsModalBody.appendChild(item);
        });
    }
    
    detailsModal.style.display = 'flex';
    lucide.createIcons();
}

const cardReceitas = document.getElementById('card-receitas');
const cardDespesas = document.getElementById('card-despesas');

if (cardReceitas) {
    cardReceitas.addEventListener('click', () => openDetailsModal('receita'));
}
if (cardDespesas) {
    cardDespesas.addEventListener('click', () => openDetailsModal('despesa'));
}
if (btnCloseDetails) {
    btnCloseDetails.addEventListener('click', () => {
        detailsModal.style.display = 'none';
    });
}
if (detailsModal) {
    window.addEventListener('click', (e) => {
        if (e.target === detailsModal) {
            detailsModal.style.display = 'none';
        }
    });
}

// Set default values
const now = new Date();
const defaultMonth = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');
const defaultYear = now.getFullYear();
if(dashboardMonthPicker) dashboardMonthPicker.value = defaultMonth;
if(dashboardYearPicker) dashboardYearPicker.value = defaultYear;

Chart.defaults.font.family = "'Inter', sans-serif";

function initCharts() {
    const barCtx = document.getElementById('barChart').getContext('2d');
    const pieCtx = document.getElementById('pieChart').getContext('2d');
    const lineCtx = document.getElementById('lineChart').getContext('2d');
    
    barChart = new Chart(barCtx, { type: 'bar', data: { labels: [], datasets: [] }, options: { responsive: true, maintainAspectRatio: false } });
    pieChart = new Chart(pieCtx, { type: 'doughnut', data: { labels: [], datasets: [] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } } } });
    lineChart = new Chart(lineCtx, { type: 'line', data: { labels: [], datasets: [] }, options: { responsive: true, maintainAspectRatio: false } });
    
    applyThemeToCharts();
}



let isPopulatingBankFilter = false;
function populateDashboardBankFilter() {
    const bankSelect = document.getElementById('dashboard-bank');
    if (!bankSelect || isPopulatingBankFilter) return;
    isPopulatingBankFilter = true;
    const currentSelected = bankSelect.value;
    
    // Obter lista única de bancos presentes nas transações do usuário
    const uniqueBanks = Array.from(new Set(transactions.map(t => t.bank_name || 'Geral'))).filter(Boolean);
    
    let html = '<option value="all">Todos os Bancos</option>';
    uniqueBanks.sort().forEach(b => {
        html += `<option value="${b}">${b}</option>`;
    });
    bankSelect.innerHTML = html;
    
    if (currentSelected && (currentSelected === 'all' || uniqueBanks.includes(currentSelected))) {
        bankSelect.value = currentSelected;
    } else {
        bankSelect.value = 'all';
    }
    isPopulatingBankFilter = false;
}

function updateDashboard() {
    populateDashboardBankFilter();

    const periodSelect = document.getElementById('dashboard-period');
    const monthPicker = document.getElementById('dashboard-month-picker');
    const yearPicker = document.getElementById('dashboard-year-picker');
    const bankSelect = document.getElementById('dashboard-bank');
    
    const period = periodSelect ? periodSelect.value : 'month';
    const selectedBank = bankSelect ? bankSelect.value : 'all';
    const now = new Date();
    
    let targetYear = now.getFullYear();
    let targetMonth = now.getMonth();
    
    if (period === 'month' && monthPicker && monthPicker.value) {
        const [y, m] = monthPicker.value.split('-');
        targetYear = parseInt(y);
        targetMonth = parseInt(m) - 1;
    } else if (period === 'year' && yearPicker && yearPicker.value) {
        targetYear = parseInt(yearPicker.value);
    }

    let filteredTx = transactions;
    
    if (selectedBank !== 'all') {
        filteredTx = filteredTx.filter(t => (t.bank_name || 'Geral') === selectedBank);
    }

    if (period === 'month') {
        filteredTx = filteredTx.filter(t => {
            const d = new Date(t.date + 'T00:00:00');
            return d.getFullYear() === targetYear && d.getMonth() === targetMonth;
        });
    } else if (period === 'year') {
        filteredTx = filteredTx.filter(t => {
            const d = new Date(t.date + 'T00:00:00');
            return d.getFullYear() === targetYear;
        });
    }
    
    dashboardFilteredTransactions = filteredTx;

    const totalReceitas = filteredTx.filter(t => t.type === 'receita').reduce((sum, t) => sum + t.amount, 0);
    const totalDespesasTotal = filteredTx.filter(t => t.type === 'despesa').reduce((sum, t) => sum + t.amount, 0);
    // Saldo do período: receitas menos despesas de caixa (sem cartão)
    const totalDespesasCaixa = filteredTx.filter(t => t.type === 'despesa' && (t.card_id === null || t.card_id === undefined)).reduce((sum, t) => sum + t.amount, 0);
    const saldo = totalReceitas - totalDespesasCaixa;

    document.getElementById('val-receitas').innerText = formatCurrency(totalReceitas);
    document.getElementById('val-despesas').innerText = formatCurrency(totalDespesasTotal);
    document.getElementById('val-saldo').innerText = formatCurrency(saldo);

    const mobileValSaldo = document.getElementById('mobile-val-saldo');
    if (mobileValSaldo) {
        mobileValSaldo.innerText = formatCurrency(saldo);
    }

    const labelReceitas = document.getElementById('label-receitas');
    if (labelReceitas) {
        if (period === 'month') labelReceitas.innerText = 'Receitas (Mês)';
        else if (period === 'year') labelReceitas.innerText = 'Receitas (Ano)';
        else labelReceitas.innerText = 'Receitas (Total)';
    }
    
    let labels = [];
    let recData = [];
    let desData = [];
    
    if (period === 'month') {
        const daysInMonth = new Date(targetYear, targetMonth + 1, 0).getDate();
        labels = Array.from({length: daysInMonth}, (_, i) => (i + 1).toString());
        recData = new Array(daysInMonth).fill(0);
        desData = new Array(daysInMonth).fill(0);
        
        filteredTx.forEach(tx => {
            const d = new Date(tx.date + 'T00:00:00').getDate() - 1;
            if(tx.type === 'receita') recData[d] += tx.amount;
            else desData[d] += tx.amount;
        });
    } else if (period === 'year') {
        labels = ["Jan", "Fev", "Mar", "Abr", "Mai", "Jun", "Jul", "Ago", "Set", "Out", "Nov", "Dez"];
        recData = new Array(12).fill(0);
        desData = new Array(12).fill(0);
        
        filteredTx.forEach(tx => {
            const m = new Date(tx.date + 'T00:00:00').getMonth();
            if(tx.type === 'receita') recData[m] += tx.amount;
            else desData[m] += tx.amount;
        });
    } else {
        // 'all'
        const yearsMap = {};
        filteredTx.forEach(tx => {
            const y = new Date(tx.date + 'T00:00:00').getFullYear();
            if(!yearsMap[y]) yearsMap[y] = { rec: 0, des: 0 };
            if(tx.type === 'receita') yearsMap[y].rec += tx.amount;
            else yearsMap[y].des += tx.amount;
        });
        labels = Object.keys(yearsMap).sort();
        if(labels.length === 0) {
            labels = [targetYear.toString()];
            recData = [0];
            desData = [0];
        } else {
            labels.forEach(y => {
                recData.push(yearsMap[y].rec);
                desData.push(yearsMap[y].des);
            });
        }
    }
    
    const saldos = [];
    let currentSaldo = 0;
    for(let i=0; i<labels.length; i++) {
        currentSaldo += recData[i] - desData[i];
        saldos.push(currentSaldo);
    }
    
    const catMap = {};
    filteredTx.filter(t => t.type === 'despesa').forEach(t => {
        catMap[t.category] = (catMap[t.category] || 0) + t.amount;
    });
    
    barChart.data = {
        labels: labels,
        datasets: [
            { label: 'Receitas', data: recData, backgroundColor: '#10b981', borderRadius: 4 },
            { label: 'Despesas', data: desData, backgroundColor: '#ef4444', borderRadius: 4 }
        ]
    };
    barChart.update();
    
    pieChart.data = {
        labels: Object.keys(catMap),
        datasets: [{
            data: Object.values(catMap),
            backgroundColor: ['#3b82f6', '#8b5cf6', '#ec4899', '#f43f5e', '#f97316', '#eab308', '#22c55e', '#06b6d4'],
            borderWidth: 0
        }]
    };
    pieChart.update();
    
    lineChart.data = {
        labels: labels,
        datasets: [{
            label: 'Saldo Acumulado',
            data: saldos,
            borderColor: '#60a5fa',
            backgroundColor: 'rgba(96, 165, 250, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4
        }]
    };
    lineChart.update();
    
    // Atualizar badge de alertas mobile
    renderMobileAlerts();
}

// Start the app
window.onload = init;

// OFX / CSV Import - Preview & Edit Flow
const ofxUpload = document.getElementById("ofx-upload");
const ofxPreviewModal = document.getElementById("ofx-preview-modal");
const ofxPreviewBody = document.getElementById("ofx-preview-body");
const ofxPreviewSummary = document.getElementById("ofx-preview-summary");
const ofxConfirmCount = document.getElementById("ofx-confirm-count");
const btnCloseOfxPreview = document.getElementById("btn-close-ofx-preview");
const btnOfxCancel = document.getElementById("btn-ofx-cancel");
const btnOfxConfirm = document.getElementById("btn-ofx-confirm");

let ofxParsedTransactions = [];

function formatDateBR(dateStr) {
    if (!dateStr) return '';
    const [y, m, d] = dateStr.split('-');
    return `${d}/${m}/${y}`;
}

function buildOfxPreviewRow(tx, idx) {
    const cats = [...(CATEGORIES['receita'] || []), ...(CATEGORIES['despesa'] || [])];
    const catOptions = cats.map(c => `<option value="${c}" ${c === tx.category ? 'selected' : ''}>${c}</option>`).join('');
    const isReceita = tx.type === 'receita';
    const color = isReceita ? '#10b981' : '#ef4444';
    const sign = isReceita ? '+' : '-';
    return `
    <tr data-idx="${idx}" style="border-bottom:1px solid var(--border-color); transition:background 0.15s;" onmouseover="this.style.background='var(--bg-input)'" onmouseout="this.style.background='transparent'">
        <td style="padding:0.6rem 0.5rem; white-space:nowrap; color:var(--text-muted);">${formatDateBR(tx.date)}</td>
        <td style="padding:0.6rem 0.5rem;">
            <input type="text" value="${tx.description}" data-field="description" data-idx="${idx}"
                style="width:100%; background:var(--bg-input); border:1px solid var(--border-color); border-radius:0.375rem; color:var(--text-main); padding:0.35rem 0.5rem; font-size:0.82rem; font-family:inherit;"
                oninput="ofxParsedTransactions[this.dataset.idx].description=this.value">
        </td>
        <td style="padding:0.6rem 0.5rem;">
            <select data-field="category" data-idx="${idx}"
                style="width:100%; background:var(--bg-input); border:1px solid var(--border-color); border-radius:0.375rem; color:var(--text-main); padding:0.35rem 0.5rem; font-size:0.82rem; font-family:inherit;"
                onchange="ofxParsedTransactions[this.dataset.idx].category=this.value">
                ${catOptions}
            </select>
        </td>
        <td style="padding:0.6rem 0.5rem;">
            <select data-field="type" data-idx="${idx}"
                style="width:100%; background:var(--bg-input); border:1px solid var(--border-color); border-radius:0.375rem; color:var(--text-main); padding:0.35rem 0.5rem; font-size:0.82rem; font-family:inherit;"
                onchange="ofxParsedTransactions[this.dataset.idx].type=this.value; updateOfxSummary();">
                <option value="receita" ${tx.type==='receita'?'selected':''}>Receita</option>
                <option value="despesa" ${tx.type==='despesa'?'selected':''}>Despesa</option>
            </select>
        </td>
        <td style="padding:0.6rem 0.5rem; text-align:right; font-weight:600; color:${color}; white-space:nowrap;">${sign} ${formatCurrency(tx.amount)}</td>
        <td style="padding:0.6rem 0.25rem; text-align:center;">
            <button onclick="ofxRemoveRow(${idx})" title="Remover"
                style="background:rgba(239,68,68,0.15); border:none; color:#ef4444; width:28px; height:28px; border-radius:50%; cursor:pointer; font-size:0.85rem; display:inline-flex; align-items:center; justify-content:center;">✕</button>
        </td>
    </tr>`;
}

function ofxRemoveRow(idx) {
    ofxParsedTransactions[idx] = null;
    const row = ofxPreviewBody.querySelector(`tr[data-idx="${idx}"]`);
    if (row) row.remove();
    updateOfxSummary();
}

function updateOfxSummary() {
    const active = ofxParsedTransactions.filter(Boolean);
    const totalRec = active.filter(t => t.type === 'receita').reduce((s, t) => s + t.amount, 0);
    const totalDes = active.filter(t => t.type === 'despesa').reduce((s, t) => s + t.amount, 0);
    if (ofxPreviewSummary) {
        ofxPreviewSummary.innerHTML = `
            <span>📋 <strong>${active.length}</strong> lançamento(s)</span>
            <span style="color:#10b981;">↑ Receitas: <strong>${formatCurrency(totalRec)}</strong></span>
            <span style="color:#ef4444;">↓ Despesas: <strong>${formatCurrency(totalDes)}</strong></span>`;
    }
    if (ofxConfirmCount) ofxConfirmCount.textContent = active.length;
}

function openOfxPreviewModal(transactions) {
    ofxParsedTransactions = transactions.map(t => Object.assign({}, t));
    ofxPreviewBody.innerHTML = ofxParsedTransactions.map((tx, i) => buildOfxPreviewRow(tx, i)).join('');
    updateOfxSummary();
    ofxPreviewModal.style.display = 'flex';
}

function closeOfxPreviewModal() {
    ofxPreviewModal.style.display = 'none';
    ofxParsedTransactions = [];
    if (ofxUpload) ofxUpload.value = '';
}

if (btnCloseOfxPreview) btnCloseOfxPreview.addEventListener('click', closeOfxPreviewModal);
if (btnOfxCancel) btnOfxCancel.addEventListener('click', closeOfxPreviewModal);

if (btnOfxConfirm) {
    btnOfxConfirm.addEventListener('click', async () => {
        const toSave = ofxParsedTransactions.filter(Boolean);
        if (toSave.length === 0) { closeOfxPreviewModal(); return; }
        btnOfxConfirm.disabled = true;
        btnOfxConfirm.textContent = 'Salvando...';
        try {
            const res = await fetch('api/import_ofx.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ mode: 'commit', transactions: toSave })
            });
            const data = await res.json();
            if (data.success) {
                closeOfxPreviewModal();
                await fetchTransactions();
                await fetchCards();
                alert(`✓ ${data.imported_count} lançamento(s) importado(s) com sucesso!`);
            } else {
                alert('Erro ao salvar: ' + (data.error || 'Tente novamente.'));
            }
        } catch (err) {
            console.error(err);
            alert('Erro de conexão ao salvar os lançamentos.');
        }
        btnOfxConfirm.disabled = false;
        btnOfxConfirm.innerHTML = '✓ Confirmar Importação (<span id="ofx-confirm-count">' + ofxParsedTransactions.filter(Boolean).length + '</span>)';
    });
}

if (ofxUpload) {
    ofxUpload.addEventListener("change", async (e) => {
        if (!e.target.files || e.target.files.length === 0) return;
        const file = e.target.files[0];
        const formData = new FormData();
        formData.append("ofx_file", file);

        const btn = document.querySelector('button[onclick*="ofx-upload"]');
        if (btn) { btn.innerHTML = '<i data-lucide="loader"></i> Processando...'; btn.disabled = true; lucide.createIcons(); }

        try {
            const res = await fetch("api/import_ofx.php", { method: "POST", body: formData });
            const data = await res.json();
            if (data.success && data.preview && data.transactions) {
                openOfxPreviewModal(data.transactions);
            } else {
                alert("Erro ao processar: " + (data.error || "Formato inválido."));
            }
        } catch (err) {
            console.error(err);
            alert("Erro de conexão ao enviar o arquivo OFX/CSV.");
        } finally {
            if (btn) { btn.innerHTML = '<i data-lucide="upload"></i> Importar Extrato (OFX / CSV)'; btn.disabled = false; lucide.createIcons(); }
            ofxUpload.value = "";
        }
    });
}


// --- PROFILE SETTINGS ---
const profileForm = document.getElementById('profile-form');
const profileMsg = document.getElementById('profile-msg');
const btnDeleteAccount = document.getElementById('btn-delete-account');
const profilePhotoInput = document.getElementById('profile-photo');
const profilePhotoPreview = document.getElementById('profile-photo-preview');

// Preenche o formulário quando logado
function populateProfileForm(userData) {
    if (!document.getElementById('profile-firstname')) return;
    document.getElementById('profile-firstname').value = userData.first_name || '';
    document.getElementById('profile-lastname').value = userData.last_name || '';
    document.getElementById('profile-email').value = userData.email || '';
    
    if (userData.profile_picture && userData.profile_picture !== 'default.png') {
        profilePhotoPreview.src = 'uploads/' + userData.profile_picture;
    } else {
        profilePhotoPreview.src = 'https://ui-avatars.com/api/?name=' + (userData.first_name || 'U') + '&background=3b82f6&color=fff';
    }
}

if (profilePhotoInput) {
    profilePhotoInput.addEventListener('change', function(e) {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                profilePhotoPreview.src = e.target.result;
            };
            reader.readAsDataURL(this.files[0]);
        }
    });
}

if (profileForm) {
    profileForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('btn-save-profile');
        btn.disabled = true;
        btn.textContent = 'Salvando...';
        profileMsg.style.display = 'none';

        const formData = new FormData(profileForm);

        try {
            const res = await fetch('api/profile.php?action=update', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            
            profileMsg.style.display = 'block';
            if (data.success) {
                profileMsg.textContent = 'Perfil atualizado com sucesso!';
                profileMsg.className = 'mt-2 text-center text-success';
                document.getElementById('profile-current-password').value = '';
                document.getElementById('profile-new-password').value = '';
                await checkSession(); // Atualiza dados globais
            } else {
                profileMsg.textContent = data.error || 'Erro ao atualizar perfil.';
                profileMsg.className = 'mt-2 text-center text-danger';
            }
        } catch (err) {
            profileMsg.style.display = 'block';
            profileMsg.textContent = 'Erro de conexão.';
            profileMsg.className = 'mt-2 text-center text-danger';
        } finally {
            btn.disabled = false;
            btn.textContent = 'Salvar Alterações';
        }
    });
}

if (btnDeleteAccount) {
    btnDeleteAccount.addEventListener('click', async () => {
        if (!confirm('ATENÇÃO: Você tem certeza ABSOLUTA que deseja excluir sua conta? TODOS os seus dados financeiros serão perdidos para sempre!')) return;
        
        const confirmText = prompt('Digite "EXCLUIR" em maiúsculo para confirmar:');
        if (confirmText !== 'EXCLUIR') {
            alert('A exclusão foi cancelada.');
            return;
        }

        try {
            const res = await fetch('api/profile.php?action=delete', { method: 'DELETE' });
            const rawText = await res.text();
            try {
                const data = JSON.parse(rawText);
                if (data.success) {
                    alert('Sua conta foi excluída permanentemente. Sentiremos sua falta!');
                    window.location.reload();
                } else {
                    alert(data.error || 'Erro ao excluir conta.');
                }
            } catch (parseErr) {
                alert('RESPOSTA CRUA DO SERVIDOR:\n\n' + rawText);
            }
        } catch (e) {
            alert('Erro de conexão ao tentar excluir.');
        }
    });
}



// --- PARTICLES JS (Login Background) ---
try {
    if (document.getElementById('particles-js') && typeof particlesJS !== 'undefined') {
        particlesJS('particles-js',
          {
            "particles": {
              "number": {
                "value": 80,
                "density": {
                  "enable": true,
                  "value_area": 800
                }
              },
              "color": {
                "value": "#3b82f6"
              },
              "shape": {
                "type": "circle"
              },
              "opacity": {
                "value": 0.5,
                "random": false
              },
              "size": {
                "value": 3,
                "random": true
              },
              "line_linked": {
                "enable": true,
                "distance": 150,
                "color": "#3b82f6",
                "opacity": 0.4,
                "width": 1
              },
              "move": {
                "enable": true,
                "speed": 2,
                "direction": "none",
                "random": false,
                "straight": false,
                "out_mode": "out",
                "bounce": false
              }
            },
            "interactivity": {
              "detect_on": "canvas",
              "events": {
                "onhover": {
                  "enable": true,
                  "mode": "grab"
                },
                "onclick": {
                  "enable": true,
                  "mode": "push"
                },
                "resize": true
              },
              "modes": {
                "grab": {
                  "distance": 140,
                  "line_linked": {
                    "opacity": 1
                  }
                },
                "push": {
                  "particles_nb": 4
                }
              }
            },
            "retina_detect": true
          }
        );
    } else {
        console.warn("Particles.js library not loaded or particles-js container missing.");
    }
} catch (e) {
    console.error("Error initializing particles.js:", e);
}


// --- CARDS API & LOGIC ---
async function fetchCards() {
    try {
        const res = await fetch('api/cards.php');
        if (res.ok) {
            cards = await res.json();
            populatePaymentMethodSelector();
            if (document.getElementById('tab-cartoes').classList.contains('active')) {
                renderCards();
            }
        }
    } catch (e) {
        console.error('Failed to fetch cards');
    }
}

function populatePaymentMethodSelector() {
    const selector = document.getElementById('tx-payment-method');
    if (!selector) return;
    
    // Save current selection
    const currentVal = selector.value;
    
    selector.innerHTML = '<option value="cash">Dinheiro / Pix / Débito</option>';
    cards.forEach(card => {
        selector.innerHTML += `<option value="${card.id}">Cartão: ${card.name}</option>`;
    });
    
    if (currentVal) selector.value = currentVal;
}

// Card Form Submission
const cardForm = document.getElementById('card-form');
if (cardForm) {
    cardForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const name = document.getElementById('card-name').value;
        const credit_limit = parseFloat(document.getElementById('card-limit').value);
        const closing_day = parseInt(document.getElementById('card-closing-day').value);
        const due_day = parseInt(document.getElementById('card-due-day').value);
        
        const btn = cardForm.querySelector('button');
        btn.disabled = true;
        
        try {
            const res = await fetch('api/cards.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name, credit_limit, closing_day, due_day })
            });
            if (res.ok) {
                cardForm.reset();
                await fetchCards();
            } else {
                const err = await res.json();
                alert(err.error || 'Erro ao cadastrar cartão.');
            }
        } catch (e) {
            console.error(e);
        } finally {
            btn.disabled = false;
        }
    });
}

async function deleteCard(id) {
    if (!confirm('Deseja realmente excluir este cartão? Compras associadas a ele ficarão sem cartão.')) return;
    try {
        const res = await fetch(`api/cards.php?id=${id}`, { method: 'DELETE' });
        if (res.ok) {
            await fetchCards();
            await fetchTransactions();
            await fetchCards(); // Refresh transactions as some may have lost their card association
        }
    } catch (e) {
        console.error(e);
    }
}

function getInvoiceMonthAndYear(dateStr, closingDay) {
    const d = new Date(dateStr + 'T00:00:00');
    const day = d.getDate();
    let month = d.getMonth();
    let year = d.getFullYear();
    if (day > closingDay) {
        month++;
        if (month > 11) {
            month = 0;
            year++;
        }
    }
    return { month, year };
}

function renderCards() {
    const cardsList = document.getElementById('cards-list');
    if (!cardsList) return;
    
    cardsList.innerHTML = '';
    if (cards.length === 0) {
        cardsList.innerHTML = '<p class="text-muted text-center" style="padding: 2rem;">Nenhum cartão cadastrado.</p>';
        return;
    }
    
    const now = new Date();
    const currentMonth = now.getMonth();
    const currentYear = now.getFullYear();
    
    cards.forEach(card => {
        // Calculate current invoice total
        const cardTx = transactions.filter(t => t.card_id === card.id && t.type === 'despesa');
        const invoiceTotal = cardTx.filter(t => {
            const inv = getInvoiceMonthAndYear(t.date, card.closing_day);
            return inv.month === currentMonth && inv.year === currentYear;
        }).reduce((sum, t) => sum + t.amount, 0);
        
        const availableLimit = card.credit_limit - invoiceTotal;
        const usePercentage = Math.min((invoiceTotal / card.credit_limit) * 100, 100);
        
        const cardElement = document.createElement('div');
        cardElement.className = 'credit-card-item';
        
        cardElement.innerHTML = `
            <div style="position: absolute; top: 0; right: 0; width: 150px; height: 150px; background: radial-gradient(circle, rgba(255, 255, 255, 0.08) 0%, transparent 70%); pointer-events: none;"></div>
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem;">
                <div>
                    <h4 style="margin: 0; font-size: 1.25rem; font-weight: 600;">${card.name}</h4>
                    <span class="card-label" style="font-size: 0.75rem;">Melhor dia de compra: dia ${card.closing_day + 1 > 31 ? 1 : card.closing_day + 1}</span>
                </div>
                <button onclick="deleteCard(${card.id})" style="background: transparent; border: none; color: #ef4444; cursor: pointer; padding: 4px;">
                    <i data-lucide="trash-2" style="width: 1.2rem; height: 1.2rem;"></i>
                </button>
            </div>
            
            <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 1rem;">
                <div>
                    <span class="card-label" style="font-size: 0.75rem; display: block; margin-bottom: 0.25rem;">FATURA ATUAL (VENCE DIA ${card.due_day})</span>
                    <span class="card-value-danger" style="font-size: 1.5rem; font-weight: 700;">${formatCurrency(invoiceTotal)}</span>
                </div>
                <div style="text-align: right;">
                    <span class="card-label" style="font-size: 0.75rem; display: block; margin-bottom: 0.25rem;">LIMITE DISPONÍVEL</span>
                    <span class="card-value-success" style="font-size: 1.1rem; font-weight: 600;">${formatCurrency(availableLimit)} / ${formatCurrency(card.credit_limit)}</span>
                </div>
            </div>
            
            <div style="width: 100%; height: 6px; background: rgba(255,255,255,0.15); border-radius: 3px; overflow: hidden; margin-top: 0.5rem;">
                <div style="width: ${usePercentage}%; height: 100%; background: #ffffff; border-radius: 3px;"></div>
            </div>
        `;
        cardsList.appendChild(cardElement);
    });
    lucide.createIcons();
}

// Show/Hide password toggle
function togglePassword(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const icon = btn.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.setAttribute('data-lucide', 'eye-off');
    } else {
        input.type = 'password';
        icon.setAttribute('data-lucide', 'eye');
    }
    lucide.createIcons();
}

// --- CONTA CONJUNTA (JOINT ACCOUNT) ---
async function fetchSharedAccountStatus() {
    const unconnectedCard = document.getElementById('shared-unconnected-card');
    const connectedCard   = document.getElementById('shared-connected-card');
    if (!unconnectedCard || !connectedCard) return;

    try {
        const res = await fetch('api/shared_account.php');
        if (res.ok) {
            const data = await res.json();
            if (data.is_connected && data.partner) {
                unconnectedCard.style.display = 'none';
                connectedCard.style.display   = 'block';

                document.getElementById('shared-partner-name').innerText          = `${data.partner.first_name} ${data.partner.last_name}`.trim();
                document.getElementById('shared-partner-email-display').innerText  = data.partner.email;
                document.getElementById('shared-partner-avatar').src               = `uploads/${data.partner.profile_picture || 'default.png'}`;
            } else {
                unconnectedCard.style.display = 'block';
                connectedCard.style.display   = 'none';
            }
        }
    } catch (e) {
        console.error('Failed to fetch shared account status', e);
    }
}

const sharedConnectForm = document.getElementById('shared-connect-form');
if (sharedConnectForm) {
    sharedConnectForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const emailInput = document.getElementById('shared-partner-email');
        const msgEl      = document.getElementById('shared-msg');
        const btn        = document.getElementById('btn-shared-connect');

        if (!emailInput || !emailInput.value) return;

        btn.disabled = true;
        msgEl.style.display = 'none';

        try {
            const res = await fetch('api/shared_account.php?action=connect', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: emailInput.value })
            });
            const data = await res.json().catch(() => null);
            if (data && data.success) {
                msgEl.style.color = '#10b981';
                msgEl.innerText   = data.message || 'Conta conectada com sucesso!';
                msgEl.style.display = 'block';
                emailInput.value  = '';
                setTimeout(() => {
                    fetchSharedAccountStatus();
                    fetchTransactions();
                    fetchCards();
                }, 1000);
            } else {
                msgEl.style.color = 'var(--danger-color)';
                msgEl.innerText   = (data && data.error) ? data.error : 'Erro ao conectar conta. Verifique se a API foi enviada para o servidor.';
                msgEl.style.display = 'block';
            }
        } catch (err) {
            msgEl.style.color = 'var(--danger-color)';
            msgEl.innerText   = 'Erro de conexão com o servidor.';
            msgEl.style.display = 'block';
        }

        btn.disabled = false;
    });
}

const btnSharedDisconnect = document.getElementById('btn-shared-disconnect');
if (btnSharedDisconnect) {
    btnSharedDisconnect.addEventListener('click', async () => {
        if (!confirm('Deseja realmente desconectar a Conta Conjunta? Cada usuário voltará a ver apenas seu espaço individual.')) return;

        try {
            const res = await fetch('api/shared_account.php?action=disconnect', {
                method: 'POST'
            });
            const data = await res.json();
            if (data.success) {
                fetchSharedAccountStatus();
                fetchTransactions();
                fetchCards();
            }
        } catch (e) {
            alert('Erro ao desconectar conta conjunta.');
        }
    });
}
