<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Esqueci Minha Senha - Karaoke Show</title>
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

        * { box-sizing: border-box; margin: 0; padding: 0; }

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

        .container { width: 100%; max-width: 400px; }

        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 2rem;
            text-align: center;
        }

        .logo { font-size: 3rem; margin-bottom: 0.5rem; }
        h1 { font-size: 1.5rem; margin-bottom: 0.5rem; }
        .subtitle { color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 2rem; }

        .form-group { margin-bottom: 1rem; text-align: left; }
        .form-label { display: block; margin-bottom: 0.5rem; font-size: 0.875rem; color: var(--text-secondary); }

        .form-input {
            width: 100%;
            padding: 0.875rem 1rem;
            background: var(--bg-primary);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 1rem;
        }

        .form-input:focus { outline: none; border-color: var(--primary-500); }

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

        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4); }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

        .back-link {
            display: block;
            margin-top: 1.5rem;
            color: var(--primary-400);
            text-decoration: none;
            font-size: 0.9rem;
        }

        .back-link:hover { text-decoration: underline; }
        .success-icon { font-size: 4rem; margin-bottom: 1rem; }

        .info-box {
            background: rgba(99, 102, 241, 0.1);
            border: 1px solid var(--primary-500);
            color: var(--text-secondary);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
            text-align: left;
        }

        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid transparent;
            border-top-color: currentColor;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            display: inline-block;
            margin-right: 0.5rem;
        }

        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="container">
        <!-- Formulário -->
        <div class="card" id="form-state">
            <div class="logo">🔑</div>
            <h1>Esqueci Minha Senha</h1>
            <p class="subtitle">Digite seu email cadastrado para receber o link de recuperação.</p>
            
            <div class="info-box">
                ℹ️ <strong>Requisitos:</strong><br>
                • Você precisa ter uma conta com email e senha definidos<br>
                • O link expira em 1 hora
            </div>
            
            <form id="recovery-form">
                <div class="form-group">
                    <label class="form-label">Email Cadastrado</label>
                    <input type="email" class="form-input" id="email" required placeholder="seu@email.com">
                </div>
                <button type="submit" class="btn" id="submit-btn">
                    <span class="btn-text">Enviar Link de Recuperação</span>
                </button>
            </form>
            <a href="/" class="back-link">← Voltar ao início</a>
        </div>

        <!-- Sucesso -->
        <div class="card" id="success-state" style="display: none;">
            <div class="success-icon">📧</div>
            <h1>Email Enviado!</h1>
            <p class="subtitle">Se o email <strong id="sent-email"></strong> estiver cadastrado, você receberá um link de recuperação.</p>
            <p style="color: var(--text-secondary); font-size: 0.8rem; margin-top: 1rem;">
                Não recebeu? Verifique a pasta de spam ou tente novamente em alguns minutos.
            </p>
            <a href="/" class="btn" style="display: inline-block; text-decoration: none; margin-top: 1.5rem;">Voltar ao Início</a>
        </div>
    </div>

    <script>
        document.getElementById('recovery-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const email = document.getElementById('email').value;
            const btn = document.getElementById('submit-btn');
            
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span> Enviando...';
            
            try {
                const resp = await fetch('api/password_recovery.php?action=request', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email })
                });
                
                const data = await resp.json();
                
                // Sempre mostra sucesso (não revelamos se email existe)
                document.getElementById('sent-email').textContent = email;
                document.getElementById('form-state').style.display = 'none';
                document.getElementById('success-state').style.display = 'block';
                
            } catch (err) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: 'Erro de conexão. Tente novamente.'
                });
                btn.disabled = false;
                btn.innerHTML = '<span class="btn-text">Enviar Link de Recuperação</span>';
            }
        });
    </script>
</body>
</html>
