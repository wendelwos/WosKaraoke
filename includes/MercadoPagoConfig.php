<?php
/**
 * MercadoPagoConfig - Configuracao do Mercado Pago como classe
 *
 * Substitui o antigo config/mercadopago.php baseado em global.
 */

declare(strict_types=1);

namespace WosKaraoke;

class MercadoPagoConfig
{
    private static ?self $instance = null;

    private string $mode;
    private array $credentials;
    private array $urls;
    private int $trialDays;
    private string $statementDescriptor;
    private string $currency;

    private function __construct()
    {
        $this->mode = Env::get('MP_MODE', 'sandbox');

        $this->credentials = [
            'sandbox' => [
                'access_token' => Env::get('MP_SANDBOX_ACCESS_TOKEN', ''),
                'public_key'   => Env::get('MP_SANDBOX_PUBLIC_KEY', ''),
            ],
            'production' => [
                'access_token' => Env::get('MP_ACCESS_TOKEN', ''),
                'public_key'   => Env::get('MP_PUBLIC_KEY', ''),
            ],
        ];

        $this->urls = [
            'success' => '/establishment/billing.php?status=success',
            'failure' => '/establishment/billing.php?status=failure',
            'pending' => '/establishment/billing.php?status=pending',
            'webhook' => '/api/billing/webhook.php',
        ];

        $this->statementDescriptor = 'WOSKARAOKE';
        $this->currency = 'BRL';
        $this->trialDays = Env::int('MP_TRIAL_DAYS', 14);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function isSandbox(): bool
    {
        return $this->mode === 'sandbox';
    }

    public function getAccessToken(): string
    {
        return $this->credentials[$this->mode]['access_token'] ?? '';
    }

    public function getPublicKey(): string
    {
        return $this->credentials[$this->mode]['public_key'] ?? '';
    }

    public function getTrialDays(): int
    {
        return $this->trialDays;
    }

    public function getStatementDescriptor(): string
    {
        return $this->statementDescriptor;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getCallbackUrl(string $type): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        if (strpos($host, 'localhost') !== false) {
            return '';
        }

        $basePath = Env::get('APP_BASE_PATH', '');
        return $protocol . '://' . $host . $basePath . ($this->urls[$type] ?? '');
    }

    /**
     * Backward-compatible: retorna array igual ao antigo $mpConfig
     */
    public function toArray(): array
    {
        return [
            'mode' => $this->mode,
            'sandbox' => $this->credentials['sandbox'],
            'production' => $this->credentials['production'],
            'urls' => $this->urls,
            'statement_descriptor' => $this->statementDescriptor,
            'currency' => $this->currency,
            'trial_days' => $this->trialDays,
        ];
    }
}
