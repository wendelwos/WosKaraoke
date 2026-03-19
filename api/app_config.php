<?php
/**
 * ============================================
 * CONFIGURAÇÃO GLOBAL DO SISTEMA - Karaoke Show
 * ============================================
 * 
 * Altere o nome do sistema APENAS AQUI
 * Todos os arquivos usarão esta constante
 */

// ============================================
// NOME DO SISTEMA (ALTERAR APENAS AQUI)
// ============================================
define('APP_NAME', 'Karaoke Show');
define('APP_TAGLINE', 'Sua plataforma de karaokê');
define('APP_EMAIL_DOMAIN', 'karaokeshow.com');

// ============================================
// CONFIGURAÇÕES DERIVADAS (NÃO ALTERAR)
// ============================================
define('APP_WELCOME_MESSAGE', 'Bem-vindo ao ' . APP_NAME . '!');
define('APP_SUPPORT_EMAIL', 'suporte@' . APP_EMAIL_DOMAIN);
define('APP_ADMIN_EMAIL', 'admin@' . APP_EMAIL_DOMAIN);

/**
 * Retorna configurações do app em formato JSON para JavaScript
 */
function getAppConfigJson(): string {
    return json_encode([
        'name' => APP_NAME,
        'tagline' => APP_TAGLINE,
        'welcomeMessage' => APP_WELCOME_MESSAGE,
        'supportEmail' => APP_SUPPORT_EMAIL,
    ]);
}
