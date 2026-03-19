# 🎤 Karaoke Show - Tutorial de Deploy no Hostinger

## Pré-requisitos

- Conta no Hostinger com plano de hospedagem PHP
- Acesso ao painel hPanel
- Credenciais do banco MySQL já criadas

---

## 📋 Passo a Passo

### 1. Acessar o Painel Hostinger

1. Entre em [hpanel.hostinger.com](https://hpanel.hostinger.com)
2. Selecione seu domínio
3. Vá para **Hospedagem** > **Gerenciador de Arquivos**

---

### 2. Limpar a pasta public_html

1. No Gerenciador de Arquivos, acesse `public_html`
2. Selecione todos os arquivos existentes
3. Delete tudo (exceto `.htaccess` se quiser manter)

---

### 3. Fazer Upload dos Arquivos

**Opção A: Via Gerenciador de Arquivos**
1. Compacte a pasta `deploy_hostinger` em um arquivo `.zip`
2. No Gerenciador de Arquivos, clique em **Enviar Arquivos**
3. Suba o arquivo `.zip`
4. Extraia o conteúdo para `public_html`

**Opção B: Via FTP (FileZilla)**
1. Vá em **Hospedagem** > **Contas FTP**
2. Crie uma conta FTP ou use as credenciais existentes
3. Conecte via FileZilla:
   - Host: ftp.seu-dominio.com
   - Usuário: seu usuário FTP
   - Senha: sua senha FTP
   - Porta: 21
4. Faça upload de todo o conteúdo de `deploy_hostinger` para `public_html`

---

### 4. Configurar o Banco de Dados

#### 4.1 Acessar phpMyAdmin
1. No hPanel, vá para **Bancos de Dados** > **phpMyAdmin**
2. Selecione o banco `u728238878_karaoke`

#### 4.2 Executar o Script SQL
1. Clique na aba **SQL**
2. Copie todo o conteúdo do arquivo `database/01_create_tables.sql`
3. Cole no campo de texto
4. Clique em **Executar**
5. Verifique se todas as tabelas foram criadas (deve haver ~25 tabelas)

---

### 5. Verificar Permissões de Pastas

Algumas pastas precisam de permissão de escrita:

| Pasta | Permissão | Função |
|-------|-----------|--------|
| `/data` | 755 | Cache de músicas |
| `/data/rate_limit` | 755 | Rate limiting |
| `/logs` | 755 | Logs de erro |

**Como definir permissões:**
1. No Gerenciador de Arquivos, clique com botão direito na pasta
2. Selecione **Permissões**
3. Marque: Proprietário (Ler, Escrever, Executar), Grupo (Ler, Executar), Público (Ler, Executar)
4. Defina para `755`

---

### 6. Configurar .htaccess

O arquivo `.htaccess` já está configurado, mas verifique se o mod_rewrite está ativo:

```apache
RewriteEngine On

# Remove extensão .php das URLs
RewriteCond %{THE_REQUEST} ^[A-Z]{3,}\s([^.]+)\.php [NC]
RewriteRule ^ %1 [R=301,L]

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME}.php -f
RewriteRule ^(.*)$ $1.php [L]

# Protege arquivos sensíveis
<FilesMatch "\.(env|log|sql|md)$">
    Order allow,deny
    Deny from all
</FilesMatch>
```

---

### 7. Testar o Sistema

#### 7.1 Página Inicial (Clientes)
```
https://seu-dominio.com/
```
Deve mostrar a tela de login do karaokê.

#### 7.2 Painel Admin (KJ)
```
https://seu-dominio.com/admin/
```
- **Usuário:** admin
- **Senha:** admin123

#### 7.3 Painel Estabelecimento
```
https://seu-dominio.com/establishment/login
```
- **Email:** demo@Karaoke Show.com
- **Senha:** demo123

#### 7.4 Super Admin
```
https://seu-dominio.com/superadmin/
```
- **Usuário:** superadmin
- **Senha:** admin123

---

## ⚙️ Configurações de Produção

### Alterar Credenciais Padrão

**IMPORTANTE:** Após o deploy, altere as senhas padrão!

1. Acesse o Super Admin
2. Altere a senha do superadmin
3. Altere a senha do admin
4. Altere a senha do estabelecimento demo

### Configurar Mercado Pago (Opcional)

Edite o arquivo `config/mercadopago.php`:
```php
define('MP_PRODUCTION', true);
define('MP_ACCESS_TOKEN_PRODUCTION', 'SEU_TOKEN_AQUI');
define('MP_PUBLIC_KEY_PRODUCTION', 'SUA_CHAVE_AQUI');
```

---

## 🔧 Solução de Problemas

### Erro 500 Internal Server Error
1. Verifique o arquivo `logs/php_errors.log`
2. Ative erros temporariamente em `api/config.php`:
   ```php
   ini_set('display_errors', '1');
   ```
3. Verifique permissões das pastas

### Erro de Banco de Dados
1. Verifique credenciais em `api/config.php`
2. Confirme que o banco foi criado no Hostinger
3. Execute novamente o script SQL

### Páginas em Branco
1. Verifique se PHP está na versão 8.0+
2. No hPanel: **Avançado** > **Configuração PHP**
3. Selecione PHP 8.1 ou superior

### Rate Limit
Se receber erro 429 (muitas requisições):
1. Delete os arquivos em `data/rate_limit/`
2. Ou aguarde 60 segundos

---

## 📁 Estrutura de Arquivos

```
public_html/
├── index.php              # Página inicial (clientes)
├── manifest.json          # PWA manifest
├── sw.js                  # Service Worker
├── .htaccess              # Configurações Apache
│
├── api/                   # Backend APIs
│   ├── config.php         # Configuração principal
│   ├── profiles.php       # API de perfis
│   ├── songs.php          # API de músicas
│   ├── favorites.php      # API de favoritos
│   └── admin/             # APIs do painel admin
│
├── admin/                 # Painel do KJ
├── establishment/         # Painel do estabelecimento
├── superadmin/            # Painel super admin
├── tv/                    # Modo TV
│
├── assets/                # CSS, JS, imagens
├── includes/              # Classes PHP
├── config/                # Configurações extras
├── data/                  # Cache e dados
├── logs/                  # Logs de erro
└── database/              # Scripts SQL
```

---

## 📞 Credenciais de Acesso

| Sistema | URL | Usuário | Senha |
|---------|-----|---------|-------|
| Cliente | `/` | (criar perfil) | - |
| Admin/KJ | `/admin/` | admin | admin123 |
| Estabelecimento | `/establishment/login` | demo@Karaoke Show.com | demo123 |
| Super Admin | `/superadmin/` | superadmin | admin123 |
| Banco MySQL | phpMyAdmin | u728238878_admin | w0sK@raoke |

---

## ✅ Checklist Final

- [ ] Arquivos enviados para public_html
- [ ] Script SQL executado no phpMyAdmin
- [ ] Permissões das pastas configuradas (755)
- [ ] Página inicial carregando
- [ ] Login admin funcionando
- [ ] Senhas padrão alteradas
- [ ] HTTPS ativo no domínio

---

**Desenvolvido com ❤️ para Karaoke Show**
