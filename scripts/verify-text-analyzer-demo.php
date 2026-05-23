<?php

/**
 * Проверка POST /api/demo/analiz-teksta/run
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$text = str_repeat('seo анализ текста ', 40);
$request = Illuminate\Http\Request::create(
    '/api/demo/analiz-teksta/run',
    'POST',
    [],
    [],
    [],
    ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
    json_encode(['text' => $text, 'exclude_stop_words' => true], JSON_UNESCAPED_UNICODE)
);

$response = $kernel->handle($request);
$body = $response->getContent();
$status = $response->getStatusCode();
$data = json_decode($body, true);

if ($status !== 200 || !is_array($data)) {
    fwrite(STDERR, "verify_demo_ta: FAIL http=$status body=$body\n");
    exit(1);
}

$errors = [];
if (empty($data['demo']) || ($data['module'] ?? '') !== 'analiz-teksta') {
    $errors[] = 'bad module payload';
}
if (empty($data['result']['general']['countWords'])) {
    $errors[] = 'missing general.countWords';
}
if (empty($data['result']['words']['rows']) || !is_array($data['result']['words']['rows'])) {
    $errors[] = 'missing words.rows';
}
if (empty($data['result']['zipf']['graph']) || empty($data['result']['zipf']['rows'])) {
    $errors[] = 'missing zipf preview';
}
if (empty($data['result']['cloud']['text'])) {
    $errors[] = 'missing cloud.text';
}
if (!isset($data['remaining']) || !isset($data['upgrade']['register_url'])) {
    $errors[] = 'missing limits/upgrade';
}

if ($errors !== []) {
    foreach ($errors as $e) {
        fwrite(STDERR, "verify_demo_ta: FAIL $e\n");
    }
    exit(1);
}

echo 'verify_demo_ta: OK words=' . count($data['result']['words']['rows'])
    . ' zipf=' . count($data['result']['zipf']['rows'])
    . ' cloud=' . count($data['result']['cloud']['text'])
    . " remaining=" . ($data['remaining'] ?? '?') . "\n";
exit(0);
