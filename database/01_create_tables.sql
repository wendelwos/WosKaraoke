-- ============================================
-- Karaoke Show - Script SQL Completo
-- Execute no phpMyAdmin do Hostinger
-- Banco: u728238878_karaoke
-- ============================================

-- Configurações iniciais
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- 1. TABELAS DE USUÁRIOS
-- ============================================

-- Perfis de clientes (cantores)
CREATE TABLE IF NOT EXISTS `profiles` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `token` VARCHAR(100) UNIQUE NOT NULL,
    `avatar_color` VARCHAR(20) DEFAULT '#6366f1',
    `password_hash` VARCHAR(255) DEFAULT NULL,
    `google_id` VARCHAR(255) DEFAULT NULL,
    `facebook_id` VARCHAR(255) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `avatar_url` TEXT DEFAULT NULL,
    `songs_sung_count` INT DEFAULT 0,
    `level` INT DEFAULT 1,
    `total_points` INT DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_token` (`token`),
    INDEX `idx_name` (`name`),
    INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Administradores/KJs
CREATE TABLE IF NOT EXISTS `admins` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `username` VARCHAR(100) UNIQUE NOT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `password_hash` TEXT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `establishment_id` INT DEFAULT NULL,
    `role` VARCHAR(20) DEFAULT 'kj',
    `is_active` TINYINT DEFAULT 1,
    `avatar_url` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `last_login` DATETIME DEFAULT NULL,
    INDEX `idx_username` (`username`),
    INDEX `idx_establishment` (`establishment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Super Admins
CREATE TABLE IF NOT EXISTS `super_admins` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `username` VARCHAR(100) UNIQUE NOT NULL,
    `email` VARCHAR(255) UNIQUE NOT NULL,
    `password_hash` TEXT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `is_active` TINYINT DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `last_login` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Estabelecimentos
CREATE TABLE IF NOT EXISTS `establishments` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(100) UNIQUE,
    `email` VARCHAR(255) UNIQUE,
    `password_hash` TEXT DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `logo_url` TEXT DEFAULT NULL,
    `is_active` TINYINT DEFAULT 1,
    `max_kjs` INT DEFAULT 5,
    `subscription_plan` VARCHAR(50) DEFAULT 'free',
    `subscription_expires_at` DATE DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `last_login` DATETIME DEFAULT NULL,
    INDEX `idx_email` (`email`),
    INDEX `idx_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. TABELAS DE KARAOKÊ
-- ============================================

-- Eventos/Noites de Karaokê
CREATE TABLE IF NOT EXISTS `event_settings` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `event_code` VARCHAR(20) DEFAULT '1234',
    `is_open` TINYINT DEFAULT 1,
    `event_name` VARCHAR(255) DEFAULT 'Karaokê',
    `admin_id` INT DEFAULT NULL,
    `establishment_id` INT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_code` (`event_code`),
    INDEX `idx_admin` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fila de músicas
CREATE TABLE IF NOT EXISTS `queue` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `profile_id` INT NOT NULL,
    `profile_name` VARCHAR(255) NOT NULL,
    `song_code` VARCHAR(50) NOT NULL,
    `song_title` VARCHAR(255) NOT NULL,
    `song_artist` VARCHAR(255) DEFAULT NULL,
    `status` VARCHAR(20) DEFAULT 'waiting',
    `message` TEXT DEFAULT NULL,
    `table_number` VARCHAR(10) DEFAULT NULL,
    `event_id` INT DEFAULT 1,
    `added_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `started_at` DATETIME DEFAULT NULL,
    `finished_at` DATETIME DEFAULT NULL,
    INDEX `idx_status` (`status`),
    INDEX `idx_profile` (`profile_id`),
    INDEX `idx_event` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catálogo de músicas
CREATE TABLE IF NOT EXISTS `songs` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `code` VARCHAR(20) UNIQUE,
    `title` VARCHAR(255) NOT NULL,
    `artist` VARCHAR(255) DEFAULT NULL,
    `category` VARCHAR(100) DEFAULT NULL,
    `search_text` TEXT DEFAULT NULL,
    INDEX `idx_code` (`code`),
    INDEX `idx_title` (`title`),
    INDEX `idx_artist` (`artist`),
    FULLTEXT `idx_search` (`title`, `artist`, `search_text`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Favoritos
CREATE TABLE IF NOT EXISTS `favorites` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `profile_id` INT NOT NULL,
    `song_code` VARCHAR(50) NOT NULL,
    `song_title` VARCHAR(255) NOT NULL,
    `song_artist` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_favorite` (`profile_id`, `song_code`),
    INDEX `idx_profile` (`profile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Histórico de músicas cantadas
CREATE TABLE IF NOT EXISTS `song_history` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `profile_id` INT NOT NULL,
    `song_code` VARCHAR(50) NOT NULL,
    `song_title` VARCHAR(255) NOT NULL,
    `song_artist` VARCHAR(255) DEFAULT NULL,
    `event_id` INT DEFAULT 1,
    `sung_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_profile` (`profile_id`),
    INDEX `idx_event` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. TABELAS DE BILLING/ASSINATURAS
-- ============================================

-- Planos
CREATE TABLE IF NOT EXISTS `plans` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `code` VARCHAR(20) UNIQUE NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `price_monthly` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `price_yearly` DECIMAL(10,2) DEFAULT NULL,
    `max_events` INT DEFAULT 1,
    `max_songs_per_day` INT DEFAULT 30,
    `max_kjs` INT DEFAULT 1,
    `features` JSON DEFAULT NULL,
    `is_active` TINYINT DEFAULT 1,
    `sort_order` INT DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Assinaturas
CREATE TABLE IF NOT EXISTS `subscriptions` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `establishment_id` INT NOT NULL,
    `plan_id` INT NOT NULL,
    `status` VARCHAR(20) DEFAULT 'active',
    `billing_cycle` VARCHAR(20) DEFAULT 'monthly',
    `current_period_start` DATE NOT NULL,
    `current_period_end` DATE NOT NULL,
    `trial_ends_at` DATE DEFAULT NULL,
    `external_id` VARCHAR(255) DEFAULT NULL,
    `payment_method` VARCHAR(50) DEFAULT NULL,
    `cancelled_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_establishment` (`establishment_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Faturas
CREATE TABLE IF NOT EXISTS `invoices` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `subscription_id` INT DEFAULT NULL,
    `establishment_id` INT NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `status` VARCHAR(20) DEFAULT 'pending',
    `due_date` DATE NOT NULL,
    `paid_at` DATETIME DEFAULT NULL,
    `payment_method` VARCHAR(50) DEFAULT NULL,
    `external_id` VARCHAR(255) DEFAULT NULL,
    `pdf_url` TEXT DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_establishment` (`establishment_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notificações
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `establishment_id` INT NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT DEFAULT NULL,
    `action_url` VARCHAR(500) DEFAULT NULL,
    `is_read` TINYINT DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `read_at` DATETIME DEFAULT NULL,
    INDEX `idx_establishment` (`establishment_id`),
    INDEX `idx_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Uso diário
CREATE TABLE IF NOT EXISTS `usage_logs` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `establishment_id` INT NOT NULL,
    `usage_date` DATE NOT NULL,
    `songs_played` INT DEFAULT 0,
    `events_created` INT DEFAULT 0,
    UNIQUE KEY `unique_usage` (`establishment_id`, `usage_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. TABELAS AUXILIARES
-- ============================================

-- Estatísticas de sessão
CREATE TABLE IF NOT EXISTS `session_stats` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `profile_id` INT NOT NULL,
    `session_date` DATE NOT NULL,
    `songs_sung` INT DEFAULT 0,
    `last_sung_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `unique_session` (`profile_id`, `session_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Anúncios
CREATE TABLE IF NOT EXISTS `announcements` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `message` TEXT NOT NULL,
    `type` VARCHAR(20) DEFAULT 'info',
    `is_active` TINYINT DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reset de senha
CREATE TABLE IF NOT EXISTS `password_resets` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `admin_id` INT NOT NULL,
    `token` VARCHAR(64) UNIQUE NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used` TINYINT DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pontos dos usuários
CREATE TABLE IF NOT EXISTS `user_points` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `profile_id` INT NOT NULL,
    `event_id` INT DEFAULT NULL,
    `points` INT NOT NULL,
    `action_type` VARCHAR(50) NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_profile` (`profile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Badges
CREATE TABLE IF NOT EXISTS `user_badges` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `profile_id` INT NOT NULL,
    `badge_code` VARCHAR(50) NOT NULL,
    `badge_name` VARCHAR(100) NOT NULL,
    `badge_icon` VARCHAR(10) DEFAULT NULL,
    `earned_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_badge` (`profile_id`, `badge_code`),
    INDEX `idx_profile` (`profile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Logs de auditoria
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `actor_type` VARCHAR(50) DEFAULT NULL,
    `actor_id` INT DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `entity_type` VARCHAR(50) DEFAULT NULL,
    `entity_id` INT DEFAULT NULL,
    `details` JSON DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(500) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_actor` (`actor_type`, `actor_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Batalhas de Karaokê
CREATE TABLE IF NOT EXISTS `battles` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `event_id` INT NOT NULL,
    `contestant1_id` INT NOT NULL,
    `contestant1_name` VARCHAR(255) NOT NULL,
    `contestant1_song` VARCHAR(255) NOT NULL,
    `contestant2_id` INT NOT NULL,
    `contestant2_name` VARCHAR(255) NOT NULL,
    `contestant2_song` VARCHAR(255) NOT NULL,
    `status` VARCHAR(20) DEFAULT 'waiting',
    `winner_id` INT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `started_at` DATETIME DEFAULT NULL,
    `finished_at` DATETIME DEFAULT NULL,
    INDEX `idx_event` (`event_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Votos de batalha
CREATE TABLE IF NOT EXISTS `battle_votes` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `battle_id` INT NOT NULL,
    `voter_id` INT NOT NULL,
    `voted_for` INT NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_vote` (`battle_id`, `voter_id`),
    INDEX `idx_battle` (`battle_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- 5. DADOS INICIAIS
-- ============================================

-- Planos padrão
INSERT INTO `plans` (`code`, `name`, `price_monthly`, `price_yearly`, `max_events`, `max_songs_per_day`, `max_kjs`, `features`, `sort_order`) VALUES
('free', 'Gratuito', 0, NULL, 1, 30, 1, '{"analytics":false,"api":false,"support":"community","watermark":true}', 1),
('starter', 'Starter', 49.00, 470.00, 3, 100, 2, '{"analytics":true,"api":false,"support":"email","watermark":false}', 2),
('pro', 'Profissional', 99.00, 950.00, 10, 999999, 5, '{"analytics":true,"api":false,"support":"priority","watermark":false}', 3),
('enterprise', 'Enterprise', 299.00, 2870.00, 999999, 999999, 999999, '{"analytics":true,"api":true,"support":"dedicated","watermark":false,"whitelabel":true}', 4)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Super Admin padrão (senha: admin123)
INSERT INTO `super_admins` (`username`, `email`, `password_hash`, `name`) VALUES
('superadmin', 'admin@Karaoke Show.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super Administrador')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Admin padrão para testes (senha: admin123)
INSERT INTO `admins` (`username`, `email`, `password_hash`, `name`, `role`) VALUES
('admin', 'kj@Karaoke Show.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador', 'admin')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Evento padrão
INSERT INTO `event_settings` (`id`, `event_code`, `is_open`, `event_name`) VALUES
(1, '1234', 1, 'Karaokê Karaoke Show')
ON DUPLICATE KEY UPDATE `event_name` = VALUES(`event_name`);

-- Estabelecimento demo (senha: demo123)
INSERT INTO `establishments` (`name`, `slug`, `email`, `password_hash`, `subscription_plan`) VALUES
('Karaoke Demo', 'demo', 'demo@Karaoke Show.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'starter')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- ============================================
-- FIM DO SCRIPT
-- ============================================
