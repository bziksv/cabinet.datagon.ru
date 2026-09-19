<?php
/**
 * Smoke / invariant checks for Titlo Relevance security audit (§14 + Critical/High).
 * Run: php tools/security-smoke-check.php
 * Exit 0 = pass.
 */

$root = dirname(__DIR__);
$fails = [];

function assertTrue($cond, string $msg, array &$fails): void
{
	if (!$cond) {
		$fails[] = $msg;
	}
}

function fileContains(string $path, string $needle): bool
{
	if (!is_file($path)) {
		return false;
	}
	return strpos(file_get_contents($path), $needle) !== false;
}

$mod = $root . '/../vilmed/vilmed.ru/local/modules/titlo.relevance';
// Workspace may be cabinet-only; allow VILMED_MODULE_PATH
if (!is_dir($mod)) {
	$mod = getenv('VILMED_MODULE_PATH') ?: $mod;
}

$cab = $root;

// --- Module ownership ---
if (is_dir($mod)) {
	assertTrue(fileContains($mod . '/lib/CatalogRepository.php', 'elementBelongsToCatalog'), 'CatalogRepository element ownership', $fails);
	assertTrue(fileContains($mod . '/lib/CatalogRepository.php', 'sectionBelongsToCatalog'), 'CatalogRepository section ownership', $fails);
	assertTrue(fileContains($mod . '/lib/IndexingControl.php', 'elementBelongsToCatalog'), 'IndexingControl setNoindex ownership', $fails);
	assertTrue(fileContains($mod . '/lib/IndexingControl.php', 'currentUriMatchesElementDetail'), 'IndexingControl no REQUEST ID spoof', $fails);
	assertTrue(fileContains($mod . '/lib/ProductDuplicates.php', 'filterCatalogElementIds'), 'ProductDuplicates cluster ownership', $fails);
	assertTrue(fileContains($mod . '/lib/SectionDuplicates.php', 'filterCatalogSectionIds'), 'SectionDuplicates cluster ownership', $fails);
	assertTrue(fileContains($mod . '/lib/BatchQueue.php', 'isAllowedAnalysisUrl'), 'BatchQueue URL allowlist', $fails);
	assertTrue(fileContains($mod . '/options.php', 'api_key_clear') || fileContains($mod . '/options.php', 'сменить'), 'Options API key not echoed', $fails);
	assertTrue(fileContains($mod . '/options.php', 'api_base_custom_confirm'), 'Options custom API base confirm', $fails);
	assertTrue(fileContains($mod . '/admin/ajax.php', "confirm'] ?? '') !== '1'"), 'PhraseBulk confirm required', $fails);
	assertTrue(fileContains($mod . '/lib/Agent.php', 'sleep') === false || !preg_match('/sleep\s*\(/', file_get_contents($mod . '/lib/Agent.php')), 'Agent no blocking sleep', $fails);
	assertTrue(fileContains($mod . '/install/index.php', 'titlo_phrase_batches'), 'Uninstall drops phrase tables', $fails);
	assertTrue(fileContains($mod . '/install/index.php', 'titlo_prompts'), 'Uninstall drops prompts', $fails);
	assertTrue(fileContains($mod . '/lib/ApiClient.php', "'redirect' => false"), 'ApiClient no Bearer redirect follow', $fails);
	assertTrue(fileContains($mod . '/lib/Config.php', 'isSafeCustomApiBaseUrl'), 'Config custom API base host allowlist', $fails);
	assertTrue(fileContains($mod . '/lib/Config.php', 'configuredSiteOrigin'), 'Config analysis allowlist without HTTP_HOST', $fails);
	$cfg = is_file($mod . '/lib/Config.php') ? (string) file_get_contents($mod . '/lib/Config.php') : '';
	assertTrue(
		preg_match(
			'/public static function isAllowedAnalysisUrl\(string \$url\): bool\s*\{(.*?)\n\tpublic static function/s',
			$cfg,
			$m
		) === 1
		&& strpos($m[1], 'configuredSiteOrigin') !== false
		&& strpos($m[1], 'browseOrigin') === false
		&& strpos($m[1], 'requestOrigin') === false,
		'isAllowedAnalysisUrl must use configuredSiteOrigin, not HTTP_HOST/browseOrigin',
		$fails
	);
	assertTrue(fileContains($mod . '/options.php', 'isAllowedSiteUrl'), 'Options validates site_url', $fails);
	assertTrue(fileContains($mod . '/lib/BatchQueue.php', 'function claim'), 'BatchQueue atomic claim', $fails);
	assertTrue(fileContains($mod . '/lib/BatchQueue.php', 'already queued or running'), 'BatchQueue reject duplicate active', $fails);
	assertTrue(fileContains($mod . '/lib/BatchQueue.php', 'ageSeconds'), 'BatchQueue Moscow ageSeconds', $fails);
	assertTrue(fileContains($mod . '/lib/Agent.php', 'BatchQueue::claim'), 'Agent uses claim', $fails);
	assertTrue(fileContains($mod . '/lib/Agent.php', 'isAllowedAnalysisUrl'), 'Agent crawlUrl re-validates allowlist', $fails);
	assertTrue(fileContains($mod . '/lib/IndexingControl.php', 'fail closed'), 'IndexingControl enum fail-closed', $fails);
	assertTrue(fileContains($mod . '/lib/Config.php', 'setAutoOption'), 'Config auto Options E/S split', $fails);
	assertTrue(fileContains($mod . '/lib/ProductDuplicates.php', 'LIMIT 5000'), 'ProductDuplicates SQL LIMIT', $fails);
	assertTrue(fileContains($mod . '/lib/CatalogRepository.php', 'sanitizeCatalogHtml'), 'Catalog HTML sanitize', $fails);
	assertTrue(fileContains($mod . '/admin/ajax.php', 'TYPE_PREVIEW'), 'start_generate type allowlist', $fails);
	assertTrue(fileContains($mod . '/admin/ajax.php', "'internal error'"), 'ajax generic client error', $fails);
	assertTrue(fileContains($mod . '/lib/PhraseBulk.php', 'MAX_LIMIT = 500'), 'PhraseBulk MAX_LIMIT capped', $fails);

	// Stubs in install/admin
	$stubs = glob($mod . '/install/admin/*.php') ?: [];
	assertTrue(count($stubs) >= 5, 'install/admin stubs present', $fails);
} else {
	$fails[] = 'Bitrix module path not found: ' . $mod;
}

// --- Cabinet API ---
assertTrue(fileContains($cab . '/app/Http/Middleware/AuthenticateIntegrationApiKey.php', 'Query ?api_key= отключён')
	|| !fileContains($cab . '/app/Http/Middleware/AuthenticateIntegrationApiKey.php', "query('api_key'"), 'No query api_key auth', $fails);
assertTrue(fileContains($cab . '/routes/api.php', 'integration.api:relevance'), 'Relevance scope on routes', $fails);
assertTrue(fileContains($cab . '/routes/api.php', 'integration.api:ai'), 'AI scope on routes', $fails);
assertTrue(is_file($cab . '/app/Http/Middleware/ThrottleIntegrationApiKey.php'), 'Throttle by api_key middleware', $fails);
assertTrue(fileContains($cab . '/app/Services/Integration/AiGenerateService.php', 'assertMonthlyBudget'), 'AI monthly budget', $fails);
assertTrue(fileContains($cab . '/app/Services/Integration/AiGenerateService.php', 'history_id not found'), 'AI history_id ownership', $fails);
assertTrue(fileContains($cab . '/app/Services/Integration/RelevanceAnalysisService.php', 'tlp_missing_limit'), 'TLP server caps', $fails);
assertTrue(fileContains($cab . '/app/Services/Integration/RelevanceAnalysisService.php', 'where(\'user_id\', $analysis->user_id)'), 'attachHistory ownership', $fails);
assertTrue(fileContains($cab . '/app/TextAnalyzer.php', 'isSafePublicFetchUrl'), 'SSRF guard on curlInitV2', $fails);
assertTrue(fileContains($cab . '/app/TextAnalyzer.php', 'CURLOPT_FOLLOWLOCATION, false'), 'curlInitV2 no blind FOLLOWLOCATION', $fails);
assertTrue(fileContains($cab . '/app/TextAnalyzer.php', 'redirectLocationFromHeaders'), 'curlInitV2 manual redirect re-validate', $fails);
assertTrue(fileContains($cab . '/app/Relevance.php', 'publicIntegrationFailureCode'), 'Integration public failure codes', $fails);
assertTrue(fileContains($cab . '/app/Jobs/AIGeneration/GenerationCategoryQueue.php', 'is_array($word)'), 'getCancelWords no Array dump', $fails);
assertTrue(fileContains($cab . '/config/integration_api.php', 'ai_monthly_request_limit'), 'integration_api AI config', $fails);

// --- Functional smoke checklist file ---
$checklist = $mod . '/docs/security-smoke-checklist.md';
if (is_dir($mod)) {
	assertTrue(is_file($checklist), 'Manual smoke checklist doc', $fails);
}

if ($fails === []) {
	echo "OK: security smoke invariants passed (" . date('c') . ")\n";
	exit(0);
}

echo "FAIL (" . count($fails) . "):\n";
foreach ($fails as $f) {
	echo " - $f\n";
}
exit(1);
