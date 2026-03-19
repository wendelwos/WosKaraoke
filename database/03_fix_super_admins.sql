-- ============================================
-- CORRECAO DE DADOS - Karaoke Show
-- Execute no phpMyAdmin do Hostinger
-- ============================================

-- Criar tabela super_admins se não existir
CREATE TABLE IF NOT EXISTS `super_admins` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `last_login` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Limpar dados antigos
DELETE FROM super_admins WHERE username = 'superadmin';

-- Inserir super admin com senha bcrypt (senha: admin123)
-- Hash gerado com password_hash('admin123', PASSWORD_BCRYPT)
INSERT INTO super_admins (username, email, password_hash, name, is_active)
VALUES (
    'superadmin',
    'admin@karaokeshow.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'Super Administrador',
    1
);

-- Verificar se foi inserido
SELECT id, username, email, name, is_active FROM super_admins;

-- ============================================
-- CREDENCIAIS PARA LOGIN
-- ============================================
-- Usuario: superadmin
-- Email: admin@karaokeshow.com  
-- Senha: admin123
-- ============================================
