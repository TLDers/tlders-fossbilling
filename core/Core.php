<?php

declare(strict_types=1);

namespace TLDers\PriceMonitor;

/**
 * Framework-independent part of the TLDers price monitor: fetches the cheapest
 * market prices from the TLDers API (cached) and compares them with the prices
 * a hosting business charges. Shared by the FOSSBilling and ClientFence modules
 * (copied into each by build.php), so it must not touch either framework.
 */
final class Core
{
    public const API = 'https://www.tlders.com/api/v1';
    // TLDers refreshes prices once a day, so a day's cache loses nothing and keeps a free key
    // (100 requests/month; one refresh = 3 requests) within its quota.
    public const CACHE_TTL = 86400;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $cacheFile,
        private readonly string $userAgent = 'TLDers-FOSSBilling/1.0',
        private readonly string $apiBase = self::API
    ) {
    }

    public static function cacheFileFor(string $dir): string
    {
        return rtrim(sys_get_temp_dir(), '/\\') . '/tlders-prices-' . md5($dir) . '.json';
    }

    public function clearCache(): void
    {
        @unlink($this->cacheFile);
    }

    /**
     * tld => ['register' => [usdPrice, registrar]|null, 'renew' => [usdPrice, registrar]|null]
     *
     * @return array{0: array<string,array>|null, 1: string|null} [prices, error]
     */
    public function marketPrices(bool $refresh = false): array
    {
        if (trim($this->apiKey) === '') {
            return [null, 'Add your TLDers API key in the module settings. Get a free one at https://www.tlders.com/developers'];
        }
        if (!$refresh && is_readable($this->cacheFile) && filemtime($this->cacheFile) > time() - self::CACHE_TTL) {
            $cached = json_decode((string) file_get_contents($this->cacheFile), true);
            if (is_array($cached)) {
                return [$cached, null];
            }
        }

        $prices = [];
        $offset = 0;
        do {
            [$json, $error] = $this->request("/tlds?limit=500&offset=$offset");
            if ($error !== null) {
                return [null, $error];
            }
            foreach ($json['data'] ?? [] as $t) {
                $prices[$t['tld']] = [
                    'register' => !empty($t['cheapestRegister']) ? [(float) $t['cheapestRegister']['price'], (string) $t['cheapestRegister']['registrar']['name']] : null,
                    'renew' => !empty($t['cheapestRenew']) ? [(float) $t['cheapestRenew']['price'], (string) $t['cheapestRenew']['registrar']['name']] : null,
                ];
            }
            $offset += 500;
        } while ($offset < (int) ($json['meta']['total'] ?? 0));

        @file_put_contents($this->cacheFile, json_encode($prices));

        return [$prices, null];
    }

    /** @return array{0: array|null, 1: string|null} */
    private function request(string $path): array
    {
        $ch = curl_init($this->apiBase . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->apiKey, 'Accept: application/json'],
            CURLOPT_USERAGENT => $this->userAgent,
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        if ($body === false) {
            return [null, 'Could not reach TLDers: ' . $curlError];
        }
        $json = json_decode((string) $body, true);
        if ($status !== 200 || !is_array($json)) {
            return [null, is_array($json) && isset($json['error']) ? (string) $json['error'] : "TLDers returned HTTP $status"];
        }

        return [$json, null];
    }

    /**
     * Compares the business's own 1-year prices with the cheapest market price.
     *
     * @param array<int,array{tld:string,register:?float,renew:?float}> $own    prices in the display currency
     * @param array<string,array> $market  from marketPrices() (USD)
     * @param float $usdRate               multiplier from USD to the display currency
     * @param float $threshold             flag as "over" when more than this % above market
     *
     * @return array<int,array<string,mixed>>
     */
    public static function compare(array $own, array $market, float $usdRate, float $threshold): array
    {
        $rows = [];
        foreach ($own as $row) {
            $tld = ltrim(strtolower($row['tld']), '.');
            $entry = $market[$tld] ?? null;
            $rows[] = [
                'tld' => $tld,
                'register' => self::cell($row['register'], $entry['register'] ?? null, $usdRate, $threshold),
                'renew' => self::cell($row['renew'], $entry['renew'] ?? null, $usdRate, $threshold),
            ];
        }

        return $rows;
    }

    /**
     * @param array{0:float,1:string}|null $market
     *
     * @return array{yours:?float,market:?float,registrar:?string,diff:?float,status:string}
     */
    private static function cell(?float $yours, ?array $market, float $usdRate, float $threshold): array
    {
        $yours = ($yours !== null && $yours > 0) ? $yours : null;
        if ($market === null || $market[0] <= 0) {
            return ['yours' => $yours, 'market' => null, 'registrar' => null, 'diff' => null, 'status' => 'none'];
        }
        $marketPrice = $market[0] * $usdRate;
        if ($yours === null) {
            return ['yours' => null, 'market' => $marketPrice, 'registrar' => $market[1], 'diff' => null, 'status' => 'none'];
        }
        $diff = ($yours - $marketPrice) / $marketPrice * 100;

        return [
            'yours' => $yours,
            'market' => $marketPrice,
            'registrar' => $market[1],
            'diff' => $diff,
            'status' => $diff > $threshold ? 'over' : ($diff < 0 ? 'under' : 'ok'),
        ];
    }
}
