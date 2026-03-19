<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin - WosKaraoke</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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
            --radius: 12px;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 2rem;
        }
        
        .login-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 2rem;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .login-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .login-subtitle {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .form-group {
            margin-bottom: 1.25rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
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
            transition: border-color 0.2s;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary-500);
        }
        
        .btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--primary-500), #4f46e5);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
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
        
        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--error);
            color: var(--error);
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.875rem;
            display: none;
        }
        
        .error-message.show {
            display: block;
        }
        
        .footer-text {
            text-align: center;
            margin-top: 1.5rem;
            color: var(--text-secondary);
            font-size: 0.8rem;
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
                <div class="login-icon">🎛️</div>
                <h1 class="login-title">Super Admin</h1>
                <p class="login-subtitle">Painel de Gestão WosKaraoke</p>
            </div>
            
            <div id="error-msg" class="error-message"></div>
            
            <form id="login-form">
                <div class="form-group">
                    <label class="form-label">Usuário ou Email</label>
                    <input type="text" class="form-input" id="username" placeholder="superadmin" required autofocus>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Senha</label>
                    <input type="password" class="form-input" id="password" placeholder="••••••••" required>
                </div>
                
                <button type="submit" class="btn" id="login-btn">Entrar</button>
            </form>
            
            <p class="footer-text">Acesso restrito a administradores do sistema</p>
        </div>
    </div>
    
    <script>
        const API_BASE = '../api/superadmin/';
        
        // Verificar se já está logado
        fetch(API_BASE + 'auth.php?action=check', { credentials: 'include' })
            .then(r => r.json())
            .then(data => {
                if (data.authenticated) {
                    window.location.href = 'index.php';
                }
            });
        
        document.getElementById('login-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const btn = document.getElementById('login-btn');
            const errorMsg = document.getElementById('error-msg');
            
            btn.disabled = true;
            btn.textContent = 'Entrando...';
            errorMsg.classList.remove('show');
            
            try {
                const response = await fetch(API_BASE + 'auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({
                        username: document.getElementById('username').value,
                        password: document.getElementById('password').value
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    localStorage.setItem('superAdminToken', data.data.token);
                    localStorage.setItem('superAdminName', data.data.name);
                    window.location.href = 'index.php';
                } else {
                    throw new Error(data.error || 'Erro ao fazer login');
                }
            } catch (err) {
                errorMsg.textContent = err.message;
                errorMsg.classList.add('show');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Entrar';
            }
        });
    </script>
</body>
</html>
