// Progressive Enhancement: Flag JS capability on document root
if (typeof document !== 'undefined' && document.documentElement) {
    document.documentElement.classList.add('js');
}

// 1. Centralized Pricing Constants (Official Business Rules)
const CONFIG_PRICING = {
    MENSAL: 29.90,
    ANUAL_TOTAL: 251.16,
    ANUAL_DE: 358.80,
    DESCONTO_PCT: 30,
    ECONOMIA_ANUAL: 107.64,
    EQUIVALENTE_MENSAL: 20.93
};

document.addEventListener('DOMContentLoaded', () => {
    initParticles();
    initWhatsAppLiveDemo();
    initNLPSimulator();
    initFAQAccordion();
    initMobileNav();
    initScrollAnimations();
    initTypographicAnimations();
});

/* --------------------------------------------------------------------------
   2. Particles.js Background Initializer (Reused Login Style)
   -------------------------------------------------------------------------- */
function initParticles() {
    if (document.getElementById('particles-js') && typeof particlesJS !== 'undefined') {
        particlesJS('particles-js', {
            "particles": {
                "number": { "value": 75, "density": { "enable": true, "value_area": 800 } },
                "color": { "value": "#3b82f6" },
                "shape": { "type": "circle" },
                "opacity": { "value": 0.4, "random": false },
                "size": { "value": 3, "random": true },
                "line_linked": {
                    "enable": true,
                    "distance": 140,
                    "color": "#3b82f6",
                    "opacity": 0.35,
                    "width": 1
                },
                "move": {
                    "enable": true,
                    "speed": 1.8,
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
                    "onhover": { "enable": true, "mode": "grab" },
                    "onclick": { "enable": true, "mode": "push" },
                    "resize": true
                },
                "modes": {
                    "grab": { "distance": 140, "line_linked": { "opacity": 0.8 } },
                    "push": { "particles_nb": 3 }
                }
            },
            "retina_detect": true
        });
    }
}

/* --------------------------------------------------------------------------
   3. WhatsApp Live Simulator + Live Dashboard Sync
   -------------------------------------------------------------------------- */
function initWhatsAppLiveDemo() {
    const waBody = document.getElementById('wa-demo-body');
    const dashVal = document.getElementById('dash-demo-val');
    const dashList = document.getElementById('dash-demo-list');
    
    if (!waBody || !dashVal || !dashList) return;

    let currentBalance = 3450.00;
    
    const scenarios = [
        {
            userMsg: "Gastei 89,90 no mercado paguei no Nubank no crédito",
            botText: "✅ **Lançamento registrado!**\n🔴 Despesa\n💰 R$ 89,90\n📝 Mercado\n📁 Alimentação\n🏦 Nubank\n💳 Crédito",
            txDesc: "Mercado",
            txCat: "Alimentação",
            txAmt: 89.90,
            txBadgeStyle: "background:rgba(245, 158, 11, 0.2); color:#fbbf24; border:1px solid rgba(245, 158, 11, 0.4);"
        },
        {
            userMsg: "Coloca 120 de gasolina no C6 Bank crédito",
            botText: "✅ **Lançamento registrado!**\n🔴 Despesa\n💰 R$ 120,00\n📝 Gasolina\n📁 Transporte\n🏦 C6 Bank\n💳 Crédito",
            txDesc: "Gasolina",
            txCat: "Transporte",
            txAmt: 120.00,
            txBadgeStyle: "background:rgba(6, 182, 212, 0.2); color:#22d3ee; border:1px solid rgba(6, 182, 212, 0.4);"
        },
        {
            userMsg: "Recebi 1500 de freela no Inter pix",
            botText: "✅ **Lançamento registrado!**\n🟢 Receita\n💰 R$ 1.500,00\n📝 Freela\n📁 Renda Extra\n🏦 Inter\n⚡ PIX",
            txDesc: "Freela",
            txCat: "Receitas",
            txAmt: -1500.00, // Increase balance
            txBadgeStyle: "background:rgba(16, 185, 129, 0.2); color:#34d399; border:1px solid rgba(16, 185, 129, 0.4);"
        }
    ];

    let scenarioIndex = 0;

    function runScenarioLoop() {
        const item = scenarios[scenarioIndex];
        
        // Add User Bubble
        const timeNow = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const userBubble = document.createElement('div');
        userBubble.className = 'wa-bubble wa-bubble-user';
        userBubble.innerHTML = `${item.userMsg} <div class="wa-bubble-time">${timeNow} ✓✓</div>`;
        waBody.appendChild(userBubble);
        waBody.scrollTop = waBody.scrollHeight;

        // Bot Response Delay
        setTimeout(() => {
            const botBubble = document.createElement('div');
            botBubble.className = 'wa-bubble wa-bubble-bot';
            botBubble.innerHTML = `${item.botText.replace(/\n/g, '<br>')} <div class="wa-bubble-time">${timeNow}</div>`;
            waBody.appendChild(botBubble);
            waBody.scrollTop = waBody.scrollHeight;

            // Sync Dashboard Balance & Add Item
            if (item.txAmt > 0) {
                currentBalance -= item.txAmt;
            } else {
                currentBalance += Math.abs(item.txAmt);
            }
            
            dashVal.textContent = `R$ ${currentBalance.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;
            
            const txEl = document.createElement('div');
            txEl.className = 'dash-mini-tx-item';
            const isReceita = item.txAmt < 0;
            const sign = isReceita ? '+' : '-';
            const colorClass = isReceita ? 'color:#10b981;' : 'color:#ef4444;';
            const displayAmt = Math.abs(item.txAmt).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
            
            txEl.innerHTML = `
                <div style="display:flex; flex-direction:column; gap:2px;">
                    <span style="font-weight:600; color:var(--text-main);">${item.txDesc}</span>
                    <span class="cat-badge" style="${item.txBadgeStyle}">${item.txCat}</span>
                </div>
                <span style="font-weight:700; ${colorClass}">${sign} R$ ${displayAmt}</span>
            `;
            
            dashList.insertBefore(txEl, dashList.firstChild);
            if (dashList.children.length > 5) {
                dashList.removeChild(dashList.lastChild);
            }

            scenarioIndex = (scenarioIndex + 1) % scenarios.length;
        }, 1200);
    }

    // Run first step and repeat every 5.5 seconds
    runScenarioLoop();
    setInterval(runScenarioLoop, 6000);
}

/* --------------------------------------------------------------------------
   4. Natural Language Interactive Parser Demo
   -------------------------------------------------------------------------- */
function initNLPSimulator() {
    const items = document.querySelectorAll('.nl-item');
    const valDesc = document.getElementById('nlp-val-desc');
    const valCat  = document.getElementById('nlp-val-cat');
    const valBank = document.getElementById('nlp-val-bank');
    const valPay  = document.getElementById('nlp-val-pay');

    if (!items.length || !valDesc) return;

    items.forEach(item => {
        item.addEventListener('click', () => {
            items.forEach(i => i.classList.remove('active'));
            item.classList.add('active');

            const desc = item.dataset.desc || '-';
            const cat  = item.dataset.cat || '-';
            const bank = item.dataset.bank || '-';
            const pay  = item.dataset.pay || '-';

            // Add highlight flash effect
            [valDesc, valCat, valBank, valPay].forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(4px)';
            });

            setTimeout(() => {
                valDesc.textContent = desc;
                valCat.textContent  = cat;
                valBank.textContent = bank;
                valPay.textContent  = pay;

                [valDesc, valCat, valBank, valPay].forEach(el => {
                    el.style.transition = 'all 0.3s ease';
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                });
            }, 150);
        });
    });
}

/* --------------------------------------------------------------------------
   5. FAQ Accordion Handler
   -------------------------------------------------------------------------- */
function initFAQAccordion() {
    const faqItems = document.querySelectorAll('.faq-item');

    faqItems.forEach(item => {
        const btn = item.querySelector('.faq-question');
        if (btn) {
            btn.addEventListener('click', () => {
                const isActive = item.classList.contains('active');
                
                // Close all other FAQs
                faqItems.forEach(i => i.classList.remove('active'));

                if (!isActive) {
                    item.classList.add('active');
                }
            });
        }
    });
}

/* --------------------------------------------------------------------------
   6. Mobile Navigation Handler
   -------------------------------------------------------------------------- */
function initMobileNav() {
    const toggleBtn = document.querySelector('.mobile-nav-toggle');
    const navMenu = document.querySelector('.nav-menu');

    if (toggleBtn && navMenu) {
        toggleBtn.addEventListener('click', () => {
            const isVisible = navMenu.style.display === 'flex';
            navMenu.style.display = isVisible ? 'none' : 'flex';
            if (!isVisible) {
                navMenu.style.flexDirection = 'column';
                navMenu.style.position = 'absolute';
                navMenu.style.top = '100%';
                navMenu.style.left = '0';
                navMenu.style.width = '100%';
                navMenu.style.background = '#0b0f19';
                navMenu.style.padding = '1.5rem';
                navMenu.style.borderBottom = '1px solid rgba(255,255,255,0.1)';
            }
        });
    }
}

/* --------------------------------------------------------------------------
   7. Scroll Animations (IntersectionObserver)
   -------------------------------------------------------------------------- */
function initScrollAnimations() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-in');
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.glass-card, .comp-card, .price-card, .reveal-title').forEach(el => {
        observer.observe(el);
    });
}

/* --------------------------------------------------------------------------
   8. Premium Typographic Animations System (3D Stagger Flip, Kinetic Grid, Dynamic Weight)
   -------------------------------------------------------------------------- */
function initTypographicAnimations() {
    // A. 3D Stagger Flip Word-by-Word Initialization (Single Trigger)
    const flipTarget = document.querySelector('.stagger-flip-target');
    if (flipTarget) {
        const text = flipTarget.getAttribute('data-text') || flipTarget.textContent;
        const words = text.trim().split(/\s+/);
        flipTarget.innerHTML = '';
        words.forEach((word, index) => {
            const span = document.createElement('span');
            span.className = 'stagger-word';
            span.textContent = word + (index < words.length - 1 ? '\u00A0' : '');
            span.style.transitionDelay = `${index * 120}ms`;
            flipTarget.appendChild(span);
        });

        const flipObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    entry.target.classList.add('animated');
                    flipObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.2 });

        const trigger = document.querySelector('.stagger-flip-trigger') || flipTarget;
        flipObserver.observe(trigger);
    }

    // B. Kinetic Grid Converge (Single Trigger)
    const kineticCard = document.querySelector('.kinetic-converge-card');
    if (kineticCard) {
        const kineticObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    setTimeout(() => {
                        kineticCard.classList.add('converged');
                    }, 400);
                    kineticObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.25 });
        kineticObserver.observe(kineticCard);
    }

    // C. Dynamic Weight Banner (Cursor Proximity on Desktop)
    const dwBanner = document.getElementById('dw-banner');
    if (dwBanner && window.matchMedia('(min-width: 769px)').matches) {
        const rawText = dwBanner.textContent.trim();
        dwBanner.innerHTML = '';
        const charSpans = [];

        [...rawText].forEach(char => {
            const span = document.createElement('span');
            span.className = 'dw-char';
            span.textContent = char === ' ' ? '\u00A0' : char;
            dwBanner.appendChild(span);
            charSpans.push(span);
        });

        let ticking = false;
        dwBanner.addEventListener('mousemove', (e) => {
            if (!ticking) {
                requestAnimationFrame(() => {
                    charSpans.forEach(span => {
                        const rect = span.getBoundingClientRect();
                        const charCenterX = rect.left + rect.width / 2;
                        const charCenterY = rect.top + rect.height / 2;
                        const dist = Math.hypot(e.clientX - charCenterX, e.clientY - charCenterY);
                        
                        const maxDist = 130;
                        if (dist < maxDist) {
                            const factor = 1 - (dist / maxDist);
                            const weight = Math.round(400 + (factor * 400));
                            span.style.setProperty('--char-weight', weight);
                        } else {
                            span.style.setProperty('--char-weight', '400');
                        }
                    });
                    ticking = false;
                });
                ticking = true;
            }
        });

        dwBanner.addEventListener('mouseleave', () => {
            charSpans.forEach(span => {
                span.style.setProperty('--char-weight', '400');
            });
        });
    }
}
