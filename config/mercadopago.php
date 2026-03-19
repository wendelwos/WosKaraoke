<?php
/**
 * Configuracao do Mercado Pago - WosKaraoke
 *
 * Backward-compatible wrapper. Funcoes globais delegam para MercadoPagoConfig.
 * Novos codigos devem usar WosKaraoke\MercadoPagoConfig::getInstance() diretamente.
 */

require_once __DIR__ . '/../includes/MercadoPagoConfig.php';

function getMercadoPagoConfig(): array
{
    return WosKaraoke\MercadoPagoConfig::getInstance()->toArray();
}

function getMPAccessToken(): string
{
    return WosKaraoke\MercadoPagoConfig::getInstance()->getAccessToken();
}

function getMPPublicKey(): string
{
    return WosKaraoke\MercadoPagoConfig::getInstance()->getPublicKey();
}

function getMPCallbackUrl(string $type): string
{
    return WosKaraoke\MercadoPagoConfig::getInstance()->getCallbackUrl($type);
}

function isMPSandbox(): bool
{
    return WosKaraoke\MercadoPagoConfig::getInstance()->isSandbox();
}

function getTrialDays(): int
{
    return WosKaraoke\MercadoPagoConfig::getInstance()->getTrialDays();
}
