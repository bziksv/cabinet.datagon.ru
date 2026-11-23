<?php

/**
 * Smoke страниц «Подбор слов Wordstat».
 *
 *   /opt/homebrew/opt/php@8.5/bin/php scripts/smoke-wordstat-keywords.php [user_id]
 *
 * Если у пользователя нет проектов — создаёт временный внутри транзакции и откатывает её.
 */

require __DIR__ . '/../bootstrap/php85-compat.php';
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$userId = (int) ($argv[1] ?? 4);
$user = App\User::find($userId);
if (! $user) {
    fwrite(STDERR, "User not found: {$userId}\n");
    exit(1);
}

Auth::login($user);
if (function_exists('apply_global_team_permissions')) {
    apply_global_team_permissions();
}

echo 'PHP ' . PHP_VERSION . " | user #{$user->id} {$user->email}\n";

DB::beginTransaction();

$projectId = (int) App\Wordstat\WordstatProject::query()->accessibleBy($user->id)->orderByDesc('id')->value('id');
$temporary = false;
if (! $projectId) {
    $project = App\Wordstat\WordstatProject::query()->create([
        'user_id' => $user->id, 'domain' => 'smoke-wordstat.example', 'region_ids' => [213], 'device' => 'all',
    ]);
    $group = App\Wordstat\WordstatGroup::query()->create(['project_id' => $project->id, 'name' => 'Окна']);
    App\Wordstat\WordstatPhrase::query()->create([
        'project_id' => $project->id, 'group_id' => $group->id, 'phrase' => 'пластиковые окна',
        'phrase_key' => 'окна пластиковые', 'freq' => 123456, 'source' => 'left', 'status' => 'active',
    ]);
    $projectId = (int) $project->id;
    $temporary = true;
}

$fail = 0;
$check = static function (string $label, bool $ok) use (&$fail): void {
    echo '  ' . $label . str_repeat(' ', max(1, 60 - mb_strlen($label))) . ($ok ? 'ok' : 'FAIL') . "\n";
    if (! $ok) {
        $fail++;
    }
};

$pages = [
    ['/wordstat-keywords', 'список проектов'],
    ['/wordstat-keywords/' . $projectId, 'страница проекта' . ($temporary ? ' (временный)' : '')],
];

foreach ($pages as [$uri, $label]) {
    try {
        ob_start();
        $request = Illuminate\Http\Request::create($uri, 'GET');
        $resp = $kernel->handle($request);
        $body = (string) $resp->getContent();
        ob_end_clean();
        $code = (int) $resp->getStatusCode();
        $kernel->terminate($request, $resp);
    } catch (Throwable $e) {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        printf("%-40s EXC %s\n", $uri, get_class($e) . ': ' . mb_substr($e->getMessage(), 0, 160));
        $fail++;
        continue;
    }

    printf("%-40s %d  %s\n", $uri, $code, $label);
    $check('HTTP 200', $code === 200);
    if ($code !== 200) {
        continue;
    }

    $start = strpos($body, 'class="cabinet-wsk-page"');
    $end = strpos($body, 'id="cabinet-confirm-modal"');
    $chunk = ($start !== false && $end !== false && $end > $start) ? substr($body, $start, $end - $start) : '';

    $check('есть корень .cabinet-wsk-page', $chunk !== '');
    $check('нет dataTables', stripos($body, 'dataTables') === false);
    $check('нет jqcloud', stripos($body, 'jqcloud') === false);
    $check('нет title="…" внутри .cabinet-wsk-page', $chunk !== '' && strpos($chunk, ' title="') === false);
    $check('нет имён поставщиков API', ! preg_match('/xmlstock|xmlriver|xmlproxy/i', $chunk));
    $check('select2 подключён', strpos($body, 'select2.full.min.js') !== false);
    $check('cabinet-wordstat-keywords.js подключён', strpos($body, 'js/cabinet-wordstat-keywords.js') !== false);
    $check('нет ошибок в теле', ! preg_match('/Whoops|ErrorException|Fatal error|Parse error/i', $body));

    if (strpos($uri, '/wordstat-keywords/') === 0) {
        $check('есть cabinetWskData', strpos($body, 'id="cabinetWskData"') !== false);
        $json = null;
        if (preg_match('#<script type="application/json" id="cabinetWskData">(.*?)</script>#s', $body, $m)) {
            $json = json_decode($m[1], true);
        }
        $check('cabinetWskData — валидный JSON с groups/presets/defaults', is_array($json)
            && array_key_exists('groups', $json) && array_key_exists('presets', $json) && array_key_exists('defaults', $json));
    }
}

DB::rollBack();

echo $fail ? "FAIL={$fail}\n" : "ALL OK\n";
exit($fail ? 2 : 0);
