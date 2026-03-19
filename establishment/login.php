<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Estabelecimento WosKaraoke</title>
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
            /* Laranja para estabelecimentos */
            --primary-600: #d97706;
            --accent: #8b5cf6;
            --error: #ef4444;
            --border: #2a2a40;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--bg-primary) 0%, #1a0f2e 100%);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 2rem;
        }

        .login-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 2.5rem;
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-icon {
            font-size: 3.5rem;
            margin-bottom: 1rem;
        }

        .login-title {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .login-subtitle {
            color: var(--text-muted);
            font-size: 0.875rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .form-input {
            width: 100%;
            padding: 0.875rem 1rem;
            background: var(--bg-primary);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 1rem;
            transition: all 0.2s;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-500);
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
        }

        .btn-login {
            width: 100%;
            padding: 1rem;
            background: var(--primary-500);
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 0.5rem;
        }

        .btn-login:hover {
            background: var(--primary-600);
            transform: translateY(-1px);
        }

        .btn-login:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--error);
            color: var(--error);
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.875rem;
            display: none;
        }

        .links {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
        }

        .links a {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.875rem;
        }

        .links a:hover {
            color: var(--primary-500);
        }
    </style>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../assets/js/swal-helper.js"></script>
</head>

<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-icon">🏢</div>
                <h1 class="login-title">Painel do Estabelecimento</h1>
                <p class="login-subtitle">Acesse para gerenciar seus KJs</p>
            </div>

            <div class="error-message" id="error-message"></div>

            <form id="login-form">
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-input" id="email" placeholder="seu@email.com" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Senha</label>
                    <input type="password" class="form-input" id="password" placeholder="••••••••" required>
                </div>

                <button type="submit" class="btn-login" id="btn-login">
                    Entrar
                </button>
            </form>

            <div class="links">
                <a href="../admin/login.php">Sou um KJ</a>
            </div>
        </div>
    </div>

    <script>
        // Verificar se já está logado
        fetch('../api/establishment/auth.php', { credentials: 'include' })
            .then(r => r.json())
            .then(data => {
                if (data.logged) {
                    window.location.href = 'index.php';
                }
            });

        document.getElementById('login-form').addEventListener('submit', async (e) => {
            e.preventDefault();

            const btn = document.getElementById('btn-login');
            const errorEl = document.getElementById('error-message');

            btn.disabled = true;
            btn.textContent = 'Entrando...';
            errorEl.style.display = 'none';

            try {
                const response = await fetch('../api/establishment/auth.php?action=login', {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        email: document.getElementById('email').value,
                        password: document.getElementById('password').value
                    })
                });

                const data = await response.json();

                if (data.success) {
                    window.location.href = 'index.php';
                } else {
                    errorEl.textContent = data.error || 'Erro ao fazer login';
                    errorEl.style.display = 'block';
                }
            } catch (err) {
                errorEl.textContent = 'Erro de conexão';
                errorEl.style.display = 'block';
            } finally {
                btn.disabled = false;
                btn.textContent = 'Entrar';
            }
        });
    </script>
</body>

</html>