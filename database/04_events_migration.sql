-- ============================================
-- Migração: Adicionar novos campos em event_settings
-- Execute no phpMyAdmin
-- ============================================

-- Adicionar campo status (open, paused, closed)
ALTER TABLE `event_settings` 
ADD COLUMN IF NOT EXISTS `status` VARCHAR(20) DEFAULT 'closed' AFTER `event_name`;

-- Adicionar campo starts_at (data/hora início)
ALTER TABLE `event_settings` 
ADD COLUMN IF NOT EXISTS `starts_at` DATETIME DEFAULT NULL AFTER `status`;

-- Adicionar campo ends_at (data/hora término)
ALTER TABLE `event_settings` 
ADD COLUMN IF NOT EXISTS `ends_at` DATETIME DEFAULT NULL AFTER `starts_at`;

-- Adicionar campo max_songs_per_person (limite de músicas por cantor)
ALTER TABLE `event_settings` 
ADD COLUMN IF NOT EXISTS `max_songs_per_person` INT DEFAULT 3 AFTER `ends_at`;

-- Adicionar campo is_template (se é um template reutilizável)
ALTER TABLE `event_settings` 
ADD COLUMN IF NOT EXISTS `is_template` TINYINT DEFAULT 0 AFTER `max_songs_per_person`;

-- Migrar dados existentes: converter is_open para status
UPDATE `event_settings` 
SET `status` = CASE 
    WHEN `is_open` = 1 THEN 'open' 
    ELSE 'closed' 
END 
WHERE `status` IS NULL OR `status` = '';

-- Adicionar índice para status
CREATE INDEX IF NOT EXISTS `idx_status` ON `event_settings` (`status`);
CREATE INDEX IF NOT EXISTS `idx_establishment` ON `event_settings` (`establishment_id`);

-- ============================================
-- FIM DA MIGRAÇÃO
-- ============================================
