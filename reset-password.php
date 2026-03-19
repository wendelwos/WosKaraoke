<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinir Senha - Karaoke Show</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --bg-primary: #0f0f1a;
            --bg-secondary: #1a1a2e;
            --bg-card: #232340;
            --primary-500: #6366f1;
            --primary-400: #818cf8;
            --text-primary: #ffffff;
            --text-secondary: #a0a0b0;
            --success: #22c55e;
            --error: #ef4444;
            --border: #2a2a4a;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--bg-primary) 0%, var(--bg-secondary) 100%);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .container {
            width: 100%;
            max-width: 400px;
        }

        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 2rem;
            text-align: center;
        }

        .logo {
            font-size: 3rem;
            margin-bottom: 0.5rem;
        }

        h1 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .subtitle {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1rem;
            text-align: left;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .form-input {
            width: 100%;
            padding: 0.875rem 1rem;
            background: var(--bg-primary);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 1rem;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-500);
        }

        .btn {
            width: 100%;
            padding: 0.875rem;
            background: linear-gradient(135deg, var(--primary-500), #4f46e5);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 1rem;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .back-link {
            display: block;
            margin-top: 1.5rem;
            color: var(--primary-400);
            text-decoration: none;
            font-size: 0.9rem;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .success-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--error);
            color: var(--error);
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .password-strength {
            margin-top: 0.5rem;
            font-size: 0.8rem;
        }

        .strength-weak { color: var(--error); }
        .strength-medium { color: #f59e0b; }
        .strength-strong { color: var(--success); }

        /* Loading */
        .loading {
            display: none;
        }

        .loading.active {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid transparent;
            border-top-color: currentColor;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Estado: Verificando Token -->
        <div class="card" id="loading-state">
            <div class="logo">🎤</div>
            <h1>Verificando...</h1>
            <p class="subtitle">Aguarde um momento</p>
            <div class="loading active">
                <div class="spinner"></div>
                <span>Validando link...</span>
            </div>
        </div>

        <!-- Estado: Token Inválido -->
        <div class="card" id="error-state" style="display: none;">
            <div class="success-icon">❌</div>
            <h1>Link Inválido</h1>
            <p class="subtitle">Este link de recuperação expirou ou já foi utilizado.</p>
            <a href="/" class="btn" style="display: inline-block; text-decoration: none;">Voltar ao Início</a>
        </div>

        <!-- Estado: Formulário de Nova Senha -->
        <div class="card" id="reset-form" style="display: none;">
            <div class="logo">🔐</div>
            <h1>Nova Senha</h1>
            <p class="subtitle">Olá, <span id="user-name"></span>! Digite sua nova senha.</p>
            
            <form id="password-form">
                <div class="form-group">
                    <label class="form-label">Nova Senha</label>
                    <input type="password" class="form-input" id="password" required minlength="4" placeholder="Mínimo 4 caracteres">
                    <div class="password-strength" id="password-strength"></div>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirmar Senha</label>
                    <input type="password" class="form-input" id="password-confirm" required placeholder="Digite novamente">
                </div>
                <button type="submit" class="btn" id="submit-btn">
                    <span class="btn-text">Alterar Senha</span>
                    <span class="loading"><div class="spinner"></div> Salvando...</span>
                </button>
            </form>
            <a href="/" class="back-link">← Voltar ao início</a>
        </div>

        <!-- Estado: Sucesso -->
        <div class="card" id="success-state" style="display: none;">
            <div class="success-icon">✅</div>
            <h1>Senha Alterada!</h1>
            <p class="subtitle">Sua senha foi alterada com sucesso. Você já pode fazer login.</p>
            <a href="/" class="btn" style="display: inline-block; text-decoration: none;">Ir para Login</a>
        </div>
    </div>

    <script>
        const API_BASE = 'api/password_recovery.php';
        let currentToken = '';

        document.addEventListener('DOMContentLoaded', () => {
            // Pegar token da URL
            const params = new URLSearchParams(window.location.search);
            currentToken = params.get('token');

            if (!currentToken) {
                showError();
                return;
            }

            verifyToken();
        });

        async function verifyToken() {
            try {
                const resp = await fetch(`${API_BASE}?action=verify`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ token: currentToken })
                });

                const data = await resp.json();

                if (data.success) {
                    document.getElementById('user-name').textContent = data.data.profile_name;
                    showForm();
                } else {
                    showError();
                }
            } catch (e) {
                console.error(e);
                showError();
            }
        }

        function showForm() {
            document.getElementById('loading-state').style.display = 'none';
            document.getElementById('reset-form').style.display = 'block';
        }

        function showError() {
            document.getElementById('loading-state').style.display = 'none';
            document.getElementById('error-state').style.display = 'block';
        }

        function showSuccess() {
            document.getElementById('reset-form').style.display = 'none';
            document.getElementById('success-state').style.display = 'block';
        }

        // Password strength
        document.getElementById('password').addEventListener('input', (e) => {
            const password = e.target.value;
            const strengthEl = document.getElementById('password-strength');
            
            if (password.length < 4) {
                strengthEl.textContent = 'Muito fraca';
                strengthEl.className = 'password-strength strength-weak';
            } else if (password.length < 8) {
                strengthEl.textContent = 'Força: Média';
                strengthEl.className = 'password-strength strength-medium';
            } else {
                strengthEl.textContent = 'Força: Boa';
                strengthEl.className = 'password-strength strength-strong';
            }
        });

        // Form submit
        document.getElementById('password-form').addEventListener('submit', async (e) => {
            e.preventDefault();

            const password = document.getElementById('password').value;
            const confirm = document.getElementById('password-confirm').value;

            if (password !== confirm) {
                Swal.fire({
                    icon: 'error',
                    title: 'Senhas diferentes',
                    text: 'As senhas não coincidem. Verifique e tente novamente.'
                });
                return;
            }

            const btn = document.getElementById('submit-btn');
            btn.disabled = true;
            btn.querySelector('.btn-text').style.display = 'none';
            btn.querySelector('.loading').classList.add('active');

            try {
                const resp = await fetch(`${API_BASE}?action=reset`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        token: currentToken,
                        password: password
                    })
                });

                const data = await resp.json();

                if (data.success) {
                    showSuccess();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: data.error || 'Não foi possível alterar a senha.'
                    });
                }
            } catch (err) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: 'Erro de conexão. Tente novamente.'
                });
            } finally {
                btn.disabled = false;
                btn.querySelector('.btn-text').style.display = 'inline';
                btn.querySelector('.loading').classList.remove('active');
            }
        });
    </script>
</body>
</html>
