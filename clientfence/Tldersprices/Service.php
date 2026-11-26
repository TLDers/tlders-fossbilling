<?php

declare(strict_types=1);

/**
 * TLDers Price Monitor: compares the domain prices you charge with the cheapest
 * registrar price for each TLD from the TLDers API (https://www.tlders.com).
 */

namespace ClientFence\Mod\Tldersprices;

use TLDers\PriceMonitor\Core;

require_once __DIR__ . '/Core.php';

class Service
{
    protected ?\Pimple\Container $di = null;

    public function setDi(?\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getModulePermissions(): array
    {
        return [
            'view' => [
                'type' => 'bool',
                'display_name' => 'View TLDers price comparison',
                'description' => 'Allows the staff member to open the TLDers Price Monitor page.',
            ],
            'manage_settings' => [], // adds a permission for the settings page (API key, threshold, rate)
        ];
    }

    public function install(): bool
    {
        return true;
    }

    public function uninstall(): bool
    {
        (new Core('', Core::cacheFileFor(__DIR__)))->clearCache();

        return true;
    }

    public function update(array $manifest): bool
    {
        return true;
    }

    /**
     * Your TLD prices next to the cheapest market price.
     *
     * @return array<string,mixed> ['error' => ?string, 'rows' => list, 'currency' => array, 'threshold' => float]
     */
    public function comparison(bool $refresh = false): array
    {
        $config = $this->di['mod_config']('tldersprices');
        $apiKey = trim((string) ($config['apiKey'] ?? ''));
        $threshold = is_numeric($config['threshold'] ?? null) ? (float) $config['threshold'] : 25.0;

        $currency = $this->di['api_guest']->currency_get([]);
        $result = ['error' => null, 'rows' => [], 'currency' => $currency, 'threshold' => $threshold];

        $rate = $this->usdRate($config['usdRate'] ?? '', $currency);
        if ($rate === null) {
            $result['error'] = 'Your default currency is not USD and no USD currency is set up. Enter the "USD → your currency" rate in the module settings.';

            return $result;
        }

        $core = new Core($apiKey, Core::cacheFileFor(__DIR__), 'TLDers-FOSSBilling/1.0', getenv('TLDERS_API_BASE') ?: Core::API); // env override is for tests only
        [$market, $error] = $core->marketPrices($refresh);
        if ($error !== null) {
            $result['error'] = $error;

            return $result;
        }

        $result['rows'] = Core::compare($this->ownPrices(), $market, $rate, $threshold);

        return $result;
    }

    /** @return array<int,array{tld:string,register:?float,renew:?float}> */
    private function ownPrices(): array
    {
        $own = [];
        $page = 1;
        do {
            $list = $this->di['api_admin']->servicedomain_tld_get_list(['per_page' => 100, 'page' => $page]);
            foreach ($list['list'] ?? [] as $tld) {
                $own[] = [
                    'tld' => (string) $tld['tld'],
                    'register' => isset($tld['price_registration']) ? (float) $tld['price_registration'] : null,
                    'renew' => isset($tld['price_renew']) ? (float) $tld['price_renew'] : null,
                ];
            }
            $pages = (int) ($list['pages'] ?? 1);
            ++$page;
        } while ($page <= $pages);

        return $own;
    }

    /** Multiplier from USD to the default currency, or null when unknown. */
    private function usdRate(mixed $configured, array $default): ?float
    {
        if (is_numeric($configured) && (float) $configured > 0) {
            return (float) $configured;
        }
        if (strtoupper((string) $default['code']) === 'USD') {
            return 1.0;
        }
        try {
            $usd = $this->di['api_guest']->currency_get(['code' => 'USD']);
        } catch (\Throwable) {
            return null;
        }
        // conversion_rate is how many units of that currency equal one unit of the default currency.
        $rate = (float) ($usd['conversion_rate'] ?? 0);

        return $rate > 0 ? 1 / $rate : null;
    }
}
