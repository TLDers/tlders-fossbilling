<?php
// Run: php tests/CoreTest.php   (no framework or network needed)
require __DIR__ . '/../core/Core.php';

use TLDers\PriceMonitor\Core;

$fail = 0;
function check(string $name, bool $ok): void
{
    global $fail;
    echo ($ok ? 'ok   ' : 'FAIL ') . $name . "\n";
    $fail += $ok ? 0 : 1;
}

// --- compare()
$market = [
    'com' => ['register' => [10.0, 'Cheapo'], 'renew' => [12.0, 'Cheapo']],
    'io' => ['register' => [30.0, 'IoReg'], 'renew' => null],
];
$own = [
    ['tld' => '.com', 'register' => 15.0, 'renew' => 11.0],   // +50% over (rate 1), renew below market
    ['tld' => 'io', 'register' => 30.0, 'renew' => 40.0],     // exactly market; no renew market
    ['tld' => 'xyz', 'register' => 5.0, 'renew' => 0.0],      // not in market data; renew not priced
];
$rows = Core::compare($own, $market, 1.0, 25.0);
check('tld normalised', $rows[0]['tld'] === 'com');
check('over threshold flagged', $rows[0]['register']['status'] === 'over' && (int) round($rows[0]['register']['diff']) === 50);
check('below market is "under"', $rows[0]['renew']['status'] === 'under');
check('equal to market is ok', $rows[1]['register']['status'] === 'ok' && $rows[1]['register']['diff'] === 0.0);
check('no market data -> none', $rows[1]['renew']['status'] === 'none' && $rows[1]['renew']['market'] === null);
check('unknown tld -> none', $rows[2]['register']['status'] === 'none');
check('zero own price -> null', $rows[2]['renew']['yours'] === null);

$conv = Core::compare([['tld' => 'com', 'register' => 920.0, 'renew' => null]], $market, 92.0, 25.0);
check('USD rate applied (10 USD x 92 = 920)', (int) round($conv[0]['register']['market']) === 920 && $conv[0]['register']['status'] === 'ok');

// --- marketPrices(): missing key, API paging + cache, errors (local stub server)
$cache = sys_get_temp_dir() . '/tlders-core-test-' . getmypid() . '.json';
@unlink($cache);
[$p, $e] = (new Core('', $cache))->marketPrices();
check('missing key -> error, no request', $p === null && str_contains($e, 'API key'));

$router = sys_get_temp_dir() . '/tlders-stub-' . getmypid() . '.php';
file_put_contents($router, <<<'PHP'
<?php
$q = []; parse_str($_SERVER['QUERY_STRING'] ?? '', $q);
file_put_contents(sys_get_temp_dir() . '/tlders-stub-hits', "1\n", FILE_APPEND);
if (($_SERVER['HTTP_AUTHORIZATION'] ?? '') !== 'Bearer goodkey') { http_response_code(401); echo json_encode(['error' => 'Invalid API key']); return; }
$off = (int) ($q['offset'] ?? 0);
$limit = (int) ($q['limit'] ?? 500);
$all = [];
for ($i = 0; $i < 501; $i++) { $all[] = ['tld' => "t$i", 'cheapestRegister' => ['price' => 1 + $i, 'registrar' => ['name' => "R$i"]], 'cheapestRenew' => null]; }
header('Content-Type: application/json');
echo json_encode(['data' => array_slice($all, $off, $limit), 'meta' => ['total' => 501]]);
PHP);
@unlink(sys_get_temp_dir() . '/tlders-stub-hits');
$port = 18000 + getmypid() % 1000;
$proc = proc_open(['php', '-S', "127.0.0.1:$port", $router], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
usleep(600000);
$base = "http://127.0.0.1:$port";
$bad = new Core('badkey', $cache, 'test', $base);
[$p, $e] = $bad->marketPrices();
check('API error surfaced', $p === null && $e === 'Invalid API key');
$core = new Core('goodkey', $cache, 'test', $base);
[$p, $e] = $core->marketPrices();
$hits = count(file(sys_get_temp_dir() . '/tlders-stub-hits'));
check('fetched in 3 requests total incl. the bad-key one', $hits === 3);
check('pages through 501 TLDs (2 requests)', $e === null && count($p) === 501 && $p['t500']['register'] === [501.0, 'R500'] && $p['t0']['renew'] === null);
$before = $hits;
[$p2] = $core->marketPrices();
check('second call served from cache', count(file(sys_get_temp_dir() . '/tlders-stub-hits')) === $before && $p2 == $p);
$core->marketPrices(true);
check('refresh bypasses cache', count(file(sys_get_temp_dir() . '/tlders-stub-hits')) > $before);
$core->clearCache();
check('clearCache removes file', !file_exists($cache));
proc_terminate($proc);
@unlink($router);
@unlink(sys_get_temp_dir() . '/tlders-stub-hits');

echo $fail ? "\n$fail FAILED\n" : "\nall passed\n";
exit($fail ? 1 : 0);
