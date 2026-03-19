<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planos - Estabelecimento WosKaraoke</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0f0f1a;
            --bg-secondary: #1a1a2e;
            --bg-card: #16162a;
            --text-primary: #ffffff;
            --text-secondary: #a0a0b0;
            --text-muted: #6b6b80;
            --primary-500: #f59e0b;
            --primary-600: #d97706;
            --accent: #8b5cf6;
            --success: #22c55e;
            --error: #ef4444;
            --border: #2a2a40;
            --radius: 12px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border);
        }

        .header-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.25rem;
            font-weight: 700;
        }

        .header-nav {
            display: flex;
            gap: 0.5rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all 0.2s;
        }

        .nav-link:hover {
            background: var(--bg-card);
            color: var(--text-primary);
        }

        .nav-link.active {
            background: var(--primary-500);
            color: white;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .btn-logout {
            padding: 0.5rem 1rem;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-primary);
            cursor: pointer;
        }

        .btn-logout:hover {
            background: var(--error);
        }

        .main {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-title {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: var(--text-muted);
            margin-bottom: 2rem;
        }

        .section {
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.125rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Usage Grid */
        .usage-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .usage-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.25rem;
            text-align: center;
        }

        .usage-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary-500);
        }

        .usage-label {
            color: var(--text-muted);
            font-size: 0.8rem;
            margin-top: 0.25rem;
        }

        /* Plans Grid */
        .plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1rem;
        }

        .plan-card {
            background: var(--bg-card);
            border: 2px solid var(--border);
            border-radius: var(--radius);
            padding: 1.5rem;
        }

        .plan-card.current {
            border-color: var(--success);
        }

        .plan-card.featured {
            border-color: var(--primary-500);
        }

        .plan-badge {
            font-size: 0.7rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .plan-badge.current {
            color: var(--success);
        }

        .plan-badge.featured {
            color: var(--primary-500);
        }

        .plan-name {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .plan-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--accent);
        }

        .plan-price span {
            font-size: 0.875rem;
            font-weight: 400;
            color: var(--text-muted);
        }

        .plan-features {
            list-style: none;
            margin: 1rem 0;
        }

        .plan-features li {
            padding: 0.4rem 0;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .plan-features li::before {
            content: '✓ ';
            color: var(--success);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
        }

        .btn-primary {
            background: var(--accent);
            color: white;
        }

        .btn-primary:hover {
            opacity: 0.9;
        }

        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-secondary);
            border: 1px solid var(--border);
        }

        /* Invoices */
        .invoices-list {
            background: var(--bg-card);
            border-radius: var(--radius);
            overflow: hidden;
        }

        .invoice-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            border-bottom: 1px solid var(--border);
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .invoice-item:last-child {
            border-bottom: none;
        }

        .invoice-date {
            font-weight: 600;
        }

        .invoice-amount {
            color: var(--text-secondary);
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-badge.paid {
            background: rgba(34, 197, 94, 0.2);
            color: var(--success);
        }

        .status-badge.pending {
            background: rgba(234, 179, 8, 0.2);
            color: #eab308;
        }

        @media (max-width: 768px) {
            .header {
                flex-wrap: wrap;
                gap: 1rem;
            }

            .header-nav {
                order: 3;
                width: 100%;
                justify-content: center;
                flex-wrap: wrap;
            }

            .usage-grid {
                grid-template-columns: 1fr;
            }

            .plans-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <header class="header">
        <div class="header-brand">🏢 <span id="establishment-name">Estabelecimento</span></div>
        <nav class="header-nav">
            <a href="index.php" class="nav-link">📊 Dashboard</a>
            <a href="kjs.php" class="nav-link">👤 KJs</a>
            <a href="events.php" class="nav-link">🎵 Eventos</a>
            <a href="billing.php" class="nav-link active">💰 Planos</a>
            <a href="settings.php" class="nav-link">⚙️ Config</a>
        </nav>
        <div class="header-right">
            <button class="btn-logout" onclick="logout()">Sair</button>
        </div>
    </header>

    <main class="main">
        <h1 class="page-title">💰 Planos e Faturamento</h1>
        <p class="page-subtitle">Gerencie seu plano de assinatura</p>

        <!-- Usage Stats -->
        <section class="section">
            <h2 class="section-title">📊 Uso Atual</h2>
            <div class="usage-grid">
                <div class="usage-card">
                    <div class="usage-value" id="songs-used">-</div>
                    <div class="usage-label">Músicas hoje</div>
                </div>
                <div class="usage-card">
                    <div class="usage-value" id="events-used">-</div>
                    <div class="usage-label">Eventos ativos</div>
                </div>
                <div class="usage-card">
                    <div class="usage-value" id="kjs-used">-</div>
                    <div class="usage-label">KJs cadastrados</div>
                </div>
            </div>
        </section>

        <!-- Plans -->
        <section class="section">
            <h2 class="section-title">📦 Planos Disponíveis</h2>
            <div class="plans-grid" id="plans-grid">
                <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                    Carregando planos...
                </div>
            </div>
        </section>

        <!-- Invoices -->
        <section class="section">
            <h2 class="section-title">🧾 Histórico de Faturas</h2>
            <div class="invoices-list" id="invoices-list">
                <div class="invoice-item" style="justify-content: center; color: var(--text-muted);">
                    Nenhuma fatura ainda
                </div>
            </div>
        </section>
    </main>

    <script>
        const API_BASE = '../api/establishment/';
        const establishmentId = localStorage.getItem('establishment_id') || 1;
        let currentPlan = 'free';

        document.addEventListener('DOMContentLoaded', async () => {
            await checkAuth();
            loadBillingData();
        });

        async function checkAuth() {
            try {
                const response = await fetch(API_BASE + 'auth.php', { credentials: 'include' });
                const data = await response.json();
                if (!data.logged) {
                    window.location.href = 'login.php';
                    return;
                }
                document.getElementById('establishment-name').textContent = data.logged.name;
                currentPlan = data.logged.subscription_plan || 'free';
            } catch (e) {
                window.location.href = 'login.php';
            }
        }

        async function loadBillingData() {
            await loadUsage();
            await loadPlans();
            await loadInvoices();
        }

        async function loadUsage() {
            try {
                const response = await fetch(`/WosKaraoke/api/billing/usage.php?establishment_id=${establishmentId}`);
                const result = await response.json();
                if (result.success && result.data) {
                    const usage = result.data.usage || {};
                    const limits = result.data.limits || {};
                    
                    const songsToday = usage.songs_today ?? 0;
                    const maxSongs = limits.max_songs_per_day ?? 30;
                    const activeEvents = usage.active_events ?? 0;
                    const maxEvents = limits.max_events ?? 1;
                    const totalKjs = usage.total_kjs ?? 0;
                    const maxKjs = limits.max_kjs ?? 1;
                    
                    document.getElementById('songs-used').textContent =
                        `${songsToday}/${maxSongs >= 999999 ? '∞' : maxSongs}`;
                    document.getElementById('events-used').textContent =
                        `${activeEvents}/${maxEvents >= 999999 ? '∞' : maxEvents}`;
                    document.getElementById('kjs-used').textContent =
                        `${totalKjs}/${maxKjs >= 999999 ? '∞' : maxKjs}`;
                } else {
                    // Fallback para valores padrão se API falhar
                    document.getElementById('songs-used').textContent = '0/30';
                    document.getElementById('events-used').textContent = '0/1';
                    document.getElementById('kjs-used').textContent = '0/1';
                }
            } catch (e) {
                console.error('Error loading usage:', e);
                // Fallback em caso de erro
                document.getElementById('songs-used').textContent = '0/30';
                document.getElementById('events-used').textContent = '0/1';
                document.getElementById('kjs-used').textContent = '0/1';
            }
        }

        async function loadPlans() {
            try {
                const response = await fetch('/WosKaraoke/api/billing/plans.php');
                const result = await response.json();

                if (result.success) {
                    const grid = document.getElementById('plans-grid');
                    grid.innerHTML = result.data.map(plan => {
                        const isCurrent = plan.code === currentPlan;
                        const isFeatured = plan.code === 'pro';

                        return `
                            <div class="plan-card ${isCurrent ? 'current' : ''} ${isFeatured ? 'featured' : ''}">
                                ${isCurrent ? '<div class="plan-badge current">✓ PLANO ATUAL</div>' : ''}
                                ${isFeatured && !isCurrent ? '<div class="plan-badge featured">⭐ RECOMENDADO</div>' : ''}
                                <div class="plan-name">${plan.name}</div>
                                <div class="plan-price">
                                    ${plan.price_monthly > 0 ? `R$ ${plan.price_monthly.toFixed(2).replace('.', ',')}` : 'Grátis'}
                                    ${plan.price_monthly > 0 ? '<span>/mês</span>' : ''}
                                </div>
                                <ul class="plan-features">
                                    <li>${plan.max_songs_per_day >= 999999 ? 'Músicas ilimitadas' : `${plan.max_songs_per_day} músicas/dia`}</li>
                                    <li>${plan.max_events >= 999999 ? 'Eventos ilimitados' : `${plan.max_events} evento${plan.max_events > 1 ? 's' : ''} ativo${plan.max_events > 1 ? 's' : ''}`}</li>
                                    <li>${plan.max_kjs >= 999999 ? 'KJs ilimitados' : `${plan.max_kjs} KJ${plan.max_kjs > 1 ? 's' : ''}`}</li>
                                    ${plan.features?.analytics ? '<li>Analytics avançado</li>' : ''}
                                    ${plan.features?.api ? '<li>Acesso à API</li>' : ''}
                                    ${plan.features?.watermark === false ? '<li>Sem marca d\'água</li>' : ''}
                                </ul>
                                <div>
                                    ${isCurrent
                                ? '<button class="btn btn-secondary" disabled>Plano Atual</button>'
                                : `<button class="btn btn-primary" onclick="selectPlan('${plan.code}')">${plan.price_monthly > 0 ? 'Assinar' : 'Usar Grátis'}</button>`
                            }
                                </div>
                            </div>
                        `;
                    }).join('');
                }
            } catch (e) {
                console.error('Error loading plans:', e);
            }
        }

        async function loadInvoices() {
            try {
                const response = await fetch(`/WosKaraoke/api/billing/invoices.php?establishment_id=${establishmentId}&limit=10`);
                const result = await response.json();

                if (result.success && result.data.invoices.length > 0) {
                    const list = document.getElementById('invoices-list');
                    list.innerHTML = result.data.invoices.map(invoice => `
                        <div class="invoice-item">
                            <div class="invoice-date">${formatDate(invoice.due_date)}</div>
                            <div class="invoice-amount">R$ ${invoice.amount.toFixed(2).replace('.', ',')}</div>
                            <span class="status-badge ${invoice.status}">${translateStatus(invoice.status)}</span>
                        </div>
                    `).join('');
                }
            } catch (e) {
                console.error('Error loading invoices:', e);
            }
        }

        function formatDate(dateStr) {
            return new Date(dateStr).toLocaleDateString('pt-BR');
        }

        function translateStatus(status) {
            const map = { paid: 'Pago', pending: 'Pendente', failed: 'Falhou' };
            return map[status] || status;
        }

        async function selectPlan(planCode) {
            let billingCycle = 'monthly';

            const plansResponse = await fetch('/WosKaraoke/api/billing/plans.php');
            const plansResult = await plansResponse.json();
            const plan = plansResult.data?.find(p => p.code === planCode);

            if (plan && plan.price_monthly > 0) {
                const { value: cycle } = await Swal.fire({
                    title: `Plano ${plan.name}`,
                    html: `
                        <p><strong>Mensal:</strong> R$ ${plan.price_monthly.toFixed(2).replace('.', ',')}/mês</p>
                        <p><strong>Anual:</strong> R$ ${(plan.price_yearly || plan.price_monthly * 10).toFixed(2).replace('.', ',')} (2 meses grátis)</p>
                    `,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Mensal',
                    cancelButtonText: 'Anual',
                    confirmButtonColor: '#8b5cf6',
                    cancelButtonColor: '#22c55e'
                });
                if (cycle === undefined) return; // Fechou o modal
                billingCycle = cycle ? 'monthly' : 'yearly';
            }

            const confirmResult = await Swal.fire({
                title: 'Confirmar Assinatura',
                text: `Deseja assinar o plano ${plan?.name || planCode}?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sim, assinar!',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#8b5cf6'
            });
            if (!confirmResult.isConfirmed) return;

            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(btn => btn.disabled = true);

            try {
                const response = await fetch('/WosKaraoke/api/billing/checkout.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        establishment_id: parseInt(establishmentId),
                        plan_code: planCode,
                        billing_cycle: billingCycle
                    })
                });

                const result = await response.json();

                if (result.success) {
                    if (result.data.redirect && result.data.init_point) {
                        await Swal.fire({
                            title: 'Redirecionando...',
                            text: 'Você será redirecionado para o Mercado Pago para finalizar o pagamento.',
                            icon: 'info',
                            timer: 2000,
                            showConfirmButton: false
                        });
                        window.location.href = result.data.init_point;
                    } else {
                        await Swal.fire({
                            title: 'Sucesso!',
                            text: result.message || 'Plano ativado com sucesso!',
                            icon: 'success'
                        });
                        loadBillingData();
                    }
                } else {
                    Swal.fire({
                        title: 'Erro!',
                        text: result.error || 'Erro ao processar assinatura',
                        icon: 'error'
                    });
                    buttons.forEach(btn => btn.disabled = false);
                }
            } catch (e) {
                console.error('Error selecting plan:', e);
                Swal.fire({
                    title: 'Erro!',
                    text: 'Erro ao processar solicitação',
                    icon: 'error'
                });
                buttons.forEach(btn => btn.disabled = false);
            }
        }

        async function logout() {
            try { await fetch(API_BASE + 'auth.php?action=logout', { method: 'POST', credentials: 'include' }); } catch (e) { }
            localStorage.removeItem('establishment_token');
            localStorage.removeItem('establishment_id');
            window.location.href = 'login.php';
        }
    </script>
</body>

</html>