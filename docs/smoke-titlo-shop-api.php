<?php
/**
 * Offline smoke for Titlo Shop API + vilmed module (no live SERP/DeepSeek).
 * Run: php docs/smoke-titlo-shop-api.php
 */

$root = dirname(__DIR__);
$vilmed = dirname($root) . '/vilmed/vilmed.ru/local/modules/titlo.relevance';

$checks = [];

function ok($label, $cond, &$checks) {
	$checks[] = [$cond ? 'OK' : 'FAIL', $label];
	echo ($cond ? '[OK]  ' : '[FAIL] ') . $label . PHP_EOL;
}

$cabinetFiles = [
	'database/migrations/2026_09_15_150000_create_integration_api_tables.php',
	'app/IntegrationApiKey.php',
	'app/IntegrationAnalysis.php',
	'app/IntegrationBatch.php',
	'app/IntegrationBatchItem.php',
	'app/Services/Integration/ApiKeyService.php',
	'app/Services/Integration/RelevanceAnalysisService.php',
	'app/Services/Integration/AiGenerateService.php',
	'app/Http/Middleware/AuthenticateIntegrationApiKey.php',
	'app/Http/Controllers/Api/V1/RelevanceAnalysisController.php',
	'app/Http/Controllers/Api/V1/AiGenerateController.php',
	'app/Http/Controllers/Api/V1/RelevanceBatchController.php',
	'app/Http/Controllers/IntegrationApiKeysController.php',
	'app/Jobs/Integration/ProcessIntegrationBatchJob.php',
	'config/integration_api.php',
	'resources/views/integration/api-keys.blade.php',
	'docs/titlo-shop-api.md',
];

foreach ($cabinetFiles as $rel) {
	ok('cabinet file ' . $rel, is_file($root . '/' . $rel), $checks);
}

$apiRoutes = file_get_contents($root . '/routes/api.php');
ok('api routes have /v1/relevance/histories list', strpos($apiRoutes, "relevance/histories'") !== false || strpos($apiRoutes, 'relevance/histories') !== false, $checks);
ok('api routes have historiesIndex', strpos(file_get_contents($root . '/app/Http/Controllers/Api/V1/RelevanceAnalysisController.php'), 'historiesIndex') !== false, $checks);
ok('service listHistoriesForLanding', strpos(file_get_contents($root . '/app/Services/Integration/RelevanceAnalysisService.php'), 'listHistoriesForLanding') !== false, $checks);
ok('api routes have /v1/ai/generate', strpos($apiRoutes, 'ai/generate') !== false, $checks);
ok('api routes have batches', strpos($apiRoutes, 'relevance/batches') !== false, $checks);

$kernel = file_get_contents($root . '/app/Http/Kernel.php');
ok('middleware integration.api registered', strpos($kernel, 'integration.api') !== false, $checks);

$web = file_get_contents($root . '/routes/web.php');
ok('web route integration.api-keys', strpos($web, 'integration.api-keys') !== false, $checks);

$config = include $root . '/config/integration_api.php';
ok('prompt category', !empty($config['prompts']['category']), $checks);
ok('prompt preview', !empty($config['prompts']['preview']), $checks);
ok('prompt detail', !empty($config['prompts']['detail']), $checks);

$vilmedFiles = [
	'install/index.php',
	'install/version.php',
	'include.php',
	'options.php',
	'default_option.php',
	'admin/menu.php',
	'admin/single.php',
	'admin/ajax.php',
	'lib/ApiClient.php',
	'lib/UrlBuilder.php',
	'lib/CatalogRepository.php',
	'lib/BatchQueue.php',
	'lib/Agent.php',
	'lib/Config.php',
];

foreach ($vilmedFiles as $rel) {
	ok('vilmed file ' . $rel, is_file($vilmed . '/' . $rel), $checks);
}

$adminStub = dirname($vilmed) . '/../../bitrix/admin/titlo_relevance_single.php';
// path: vilmed.ru/bitrix/admin
$adminStub = dirname(dirname(dirname($vilmed))) . '/bitrix/admin/titlo_relevance_single.php';
ok('bitrix admin stub single', is_file($adminStub), $checks);
ok('bitrix admin stub ajax', is_file(str_replace('_single', '_ajax', $adminStub)), $checks);

$docs = file_get_contents(dirname($vilmed) . '/../../../DOCUMENTATION.md');
ok('DOCUMENTATION mentions titlo.relevance', strpos($docs, 'titlo.relevance') !== false, $checks);

// Key hashing smoke (no Laravel)
$plain = 'titlo_' . bin2hex(random_bytes(20));
$hash = hash('sha256', $plain);
ok('api key hash length 64', strlen($hash) === 64, $checks);
ok('api key prefix', strpos($plain, 'titlo_') === 0, $checks);

$failed = array_filter($checks, function ($c) { return $c[0] === 'FAIL'; });
echo PHP_EOL . 'Total: ' . count($checks) . ', failed: ' . count($failed) . PHP_EOL;
exit(count($failed) ? 1 : 0);
