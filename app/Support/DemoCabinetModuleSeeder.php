<?php

namespace App\Support;

use App\AiGenerationHistory;
use App\ClusterResults;
use App\DomainInformation;
use App\DomainMonitoring;
use App\EseninTextCheckSession;
use App\EseninTextCheckVersion;
use App\LinkTracking;
use App\MetaTag;
use App\MetaTagsHistory;
use App\PasswordsGenerator;
use App\Project;
use App\ProjectDescription;
use App\ProjectTracking;
use App\TextUniquenessHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Демо-данные модулей кабинета (истории / проекты для просмотра).
 */
class DemoCabinetModuleSeeder
{
    /** @var callable(string):void */
    private $line;

    /** @var callable(string):void */
    private $warn;

    public function __construct(callable $line, callable $warn)
    {
        $this->line = $line;
        $this->warn = $warn;
    }

    public function purge(int $userId): void
    {
        EseninTextCheckSession::query()->where('user_id', $userId)->each(function (EseninTextCheckSession $s) {
            EseninTextCheckVersion::query()->where('session_id', $s->id)->delete();
            $s->delete();
        });

        TextUniquenessHistory::query()->where('user_id', $userId)->delete();
        ClusterResults::query()->where('user_id', $userId)->delete();

        $projectIds = Project::query()->where('user_id', $userId)->pluck('id');
        if ($projectIds->isNotEmpty()) {
            ProjectDescription::query()->whereIn('project_id', $projectIds)->delete();
            Project::query()->whereIn('id', $projectIds)->delete();
        }

        DomainInformation::query()->where('user_id', $userId)->delete();
        DomainMonitoring::query()->where('user_id', $userId)->delete();

        $metaIds = MetaTag::query()->where('user_id', $userId)->pluck('id');
        if ($metaIds->isNotEmpty()) {
            MetaTagsHistory::query()->whereIn('meta_tag_id', $metaIds)->delete();
            MetaTag::query()->whereIn('id', $metaIds)->delete();
        }

        $trackIds = ProjectTracking::query()->where('user_id', $userId)->pluck('id');
        if ($trackIds->isNotEmpty()) {
            LinkTracking::query()->whereIn('project_tracking_id', $trackIds)->delete();
            ProjectTracking::query()->whereIn('id', $trackIds)->delete();
        }

        PasswordsGenerator::query()->where('user_id', $userId)->delete();
        AiGenerationHistory::query()->where('user_id', $userId)->delete();

        $this->purgeMonitoring($userId);
    }

    public function seedAll(int $userId): array
    {
        $status = [];
        $status['esenin'] = $this->seedEsenin($userId);
        $status['text-uniqueness'] = $this->seedTextUniqueness($userId);
        $status['cluster'] = $this->seedCluster($userId);
        $status['html-editor'] = $this->seedHtmlEditor($userId);
        $status['domain-information'] = $this->seedDomainInformation($userId);
        $status['site-monitoring'] = $this->seedSiteMonitoring($userId);
        $status['meta-tags'] = $this->seedMetaTags($userId);
        $status['backlink'] = $this->seedBacklink($userId);
        $status['password-generator'] = $this->seedPasswords($userId);
        $status['monitoring-v2'] = $this->seedMonitoring($userId);
        $status['ai-generation'] = $this->seedAiGeneration($userId);

        return $status;
    }

    private function out(string $msg): void
    {
        ($this->line)($msg);
    }

    private function warnOut(string $msg): void
    {
        ($this->warn)($msg);
    }

    private function seedEsenin(int $userId): string
    {
        if (EseninTextCheckSession::query()->where('user_id', $userId)->exists()) {
            $this->out('esenin: уже есть');

            return 'skip';
        }

        $sourceEmail = (string) config('cabinet-demo-cabinet.source_email', 'sv6@list.ru');
        $sourceUserId = (int) (DB::table('users')->where('email', $sourceEmail)->value('id') ?: 0);

        $source = null;
        if ($sourceUserId > 0) {
            $source = EseninTextCheckSession::query()
                ->where('user_id', $sourceUserId)
                ->whereHas('versions', function ($q) {
                    $q->whereNotNull('result_json')->where('result_json', '!=', '');
                })
                ->orderByDesc('id')
                ->first();
        }

        if (! $source) {
            $source = EseninTextCheckSession::query()
                ->whereHas('versions', function ($q) {
                    $q->whereNotNull('result_json')->where('result_json', '!=', '');
                })
                ->orderByDesc('id')
                ->first();
        }

        if (! $source) {
            $this->warnOut('esenin: нет исходной сессии');

            return 'fail';
        }

        $versions = EseninTextCheckVersion::query()
            ->where('session_id', $source->id)
            ->whereNotNull('result_json')
            ->where('result_json', '!=', '')
            ->orderBy('id')
            ->get();

        if ($versions->isEmpty()) {
            $this->warnOut('esenin: нет версий с результатом');

            return 'fail';
        }

        $session = EseninTextCheckSession::query()->create([
            'user_id' => $userId,
            'name' => 'Демо: ' . mb_substr((string) ($source->name ?: 'проверка Есенин'), 0, 80),
            'source' => $source->source ?: 'text',
            'source_url' => $source->source_url,
            'tbclass' => $source->tbclass,
        ]);

        foreach ($versions as $version) {
            DB::table('esenin_text_check_versions')->insert([
                'session_id' => $session->id,
                'text' => $version->text,
                'result_json' => $version->result_json,
                'risk_score' => $version->risk_score,
                'risk_level' => $version->risk_level,
                'is_check' => $version->is_check ? 1 : 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->out('esenin: OK (клон ' . $sourceEmail . ' session #' . $source->id . ', версий ' . $versions->count() . ')');

        return 'ok';
    }

    private function seedTextUniqueness(int $userId): string
    {
        if (TextUniquenessHistory::query()->where('user_id', $userId)->exists()) {
            $this->out('text-uniqueness: уже есть');

            return 'skip';
        }

        $showcase = \App\Support\DemoCabinet::textAnalyzerShowcase();
        $historyRows = $showcase['history'] ?? [];

        if ($historyRows !== []) {
            foreach ($historyRows as $row) {
                TextUniquenessHistory::query()->create([
                    'user_id' => $userId,
                    'title' => (string) ($row['title'] ?? 'Демо: анализ текста'),
                    'mode' => (string) ($row['mode'] ?? 'internet'),
                    'params' => $row['params'] ?? [],
                    'results' => $row['results'] ?? [],
                    'uniqueness_pct' => (float) ($row['uniqueness_pct'] ?? 0),
                    'cost' => (int) ($row['cost'] ?? 0),
                ]);
            }
            $this->out('text-uniqueness: OK (из showcase, ' . count($historyRows) . ')');

            return 'ok';
        }

        $sourceEmail = (string) config('cabinet-demo-cabinet.source_email', 'sv6@list.ru');
        $sourceUserId = (int) (DB::table('users')->where('email', $sourceEmail)->value('id') ?: 0);
        $source = null;
        if ($sourceUserId > 0) {
            $source = TextUniquenessHistory::query()
                ->where('user_id', $sourceUserId)
                ->where('uniqueness_pct', '>=', 50)
                ->orderByDesc('uniqueness_pct')
                ->orderByDesc('id')
                ->first();
        }

        if (! $source) {
            $this->warnOut('text-uniqueness: нет хорошего снимка у ' . $sourceEmail);

            return 'fail';
        }

        $row = $source->replicate();
        $row->user_id = $userId;
        $row->title = 'Демо: ' . mb_substr((string) $source->title, 0, 80);
        $row->cost = 0;
        $row->save();

        $this->out('text-uniqueness: OK (клон ' . $sourceEmail . ' #' . $source->id . ')');

        return 'ok';
    }

    private function seedCluster(int $userId): string
    {
        if (ClusterResults::query()->where('user_id', $userId)->exists()) {
            $this->out('cluster: уже есть');

            return 'skip';
        }

        $sourceId = (int) (DB::table('cluster_results')
            ->whereBetween('count_phrases', [15, 80])
            ->orderByRaw('LENGTH(result) ASC')
            ->value('id') ?: 0);

        if ($sourceId < 1) {
            $sourceId = (int) (DB::table('cluster_results')->orderByDesc('id')->value('id') ?: 0);
        }

        if ($sourceId < 1) {
            $this->warnOut('cluster: нет исходного результата');

            return 'fail';
        }

        $cols = Schema::getColumnListing('cluster_results');
        $cols = array_values(array_filter($cols, static function ($c) {
            return $c !== 'id';
        }));

        $select = [];
        foreach ($cols as $col) {
            if ($col === 'user_id') {
                $select[] = (int) $userId . ' AS user_id';
            } elseif ($col === 'comment') {
                $select[] = "'Демо: кластеризация диванов' AS comment";
            } elseif (in_array($col, ['created_at', 'updated_at'], true)) {
                $select[] = 'NOW() AS ' . $col;
            } elseif ($col === 'show') {
                $select[] = '1 AS `show`';
            } else {
                $select[] = '`' . $col . '`';
            }
        }

        DB::statement(
            'INSERT INTO cluster_results (`' . implode('`,`', $cols) . '`) '
            . 'SELECT ' . implode(', ', $select) . ' FROM cluster_results WHERE id = ?',
            [$sourceId]
        );

        $this->out('cluster: OK (клон #' . $sourceId . ')');

        return 'ok';
    }

    private function seedHtmlEditor(int $userId): string
    {
        if (Project::query()->where('user_id', $userId)->exists()) {
            $this->out('html-editor: уже есть');

            return 'skip';
        }

        $html = <<<'HTML'
<h1>Купить диван в Москве — демо-магазин</h1>
<p>Прямые поставки, доставка за 1 день, рассрочка без переплат.</p>
<h2>Почему выбирают нас</h2>
<ul>
<li>Более 500 моделей в наличии</li>
<li>Гарантия 18 месяцев</li>
<li>Бесплатный подъём</li>
</ul>
<p>Оставьте заявку — менеджер перезвонит за 10 минут.</p>
HTML;

        $project = Project::query()->create([
            'project_name' => 'Демо: лендинг диванов',
            'short_description' => 'Купить диван в Москве — демо-магазин',
            'user_id' => $userId,
            'files' => null,
        ]);

        ProjectDescription::storeDescriptionProject($html, $project->id);
        $this->out('html-editor: OK');

        return 'ok';
    }

    private function seedDomainInformation(int $userId): string
    {
        if (DomainInformation::query()->where('user_id', $userId)->exists()) {
            $this->out('domain-information: уже есть');

            return 'skip';
        }

        $expires = Carbon::now()->addMonths(8)->format('Y-m-d');
        $registered = '2020-03-15';
        $summary = DomainInformation::formatRegistrationSummary(
            __('Registration date') . ' ' . $registered,
            $expires
        );

        DomainInformation::query()->create([
            'user_id' => $userId,
            'domain' => 'titlo.ru',
            'check_dns' => 0,
            'check_dns_email' => 0,
            'check_registration_date' => 0,
            'check_registration_date_email' => 0,
            'broken' => 0,
            'last_check' => now(),
            'domain_information' => $summary,
            'dns' => "dns1.dns-root.ru\ndns2.dns-root.org",
        ]);

        DomainInformation::query()->create([
            'user_id' => $userId,
            'domain' => 'demo-shop.ru',
            'check_dns' => 0,
            'check_dns_email' => 0,
            'check_registration_date' => 0,
            'check_registration_date_email' => 0,
            'broken' => 0,
            'last_check' => now(),
            'domain_information' => DomainInformation::formatRegistrationSummary(
                __('Registration date') . ' 2019-06-01',
                Carbon::now()->addDays(45)->format('Y-m-d')
            ),
            'dns' => "ns1.example.com\nns2.example.com",
        ]);

        $this->out('domain-information: OK');

        return 'ok';
    }

    private function seedSiteMonitoring(int $userId): string
    {
        if (DomainMonitoring::query()->where('user_id', $userId)->exists()) {
            $this->out('site-monitoring: уже есть');

            return 'skip';
        }

        DomainMonitoring::query()->create([
            'user_id' => $userId,
            'project_name' => 'Титло',
            'link' => 'https://titlo.ru/',
            'timing' => 60,
            'phrase' => 'Титло',
            'status' => 'Everything all right',
            'code' => 200,
            'broken' => 0,
            'uptime_percent' => 99.8,
            'up_time' => 86400 * 30,
            'uptime_since' => Carbon::now()->subDays(30),
            'last_check' => now(),
            'waiting_time' => 15,
            'send_notification' => 0,
        ]);

        DomainMonitoring::query()->create([
            'user_id' => $userId,
            'project_name' => 'Демо-магазин',
            'link' => 'https://demo-shop.ru/',
            'timing' => 60,
            'phrase' => null,
            'status' => 'Everything all right',
            'code' => 200,
            'broken' => 0,
            'uptime_percent' => 100,
            'up_time' => 86400 * 14,
            'uptime_since' => Carbon::now()->subDays(14),
            'last_check' => now(),
            'waiting_time' => 15,
            'send_notification' => 0,
        ]);

        $this->out('site-monitoring: OK');

        return 'ok';
    }

    private function seedMetaTags(int $userId): string
    {
        if (MetaTag::query()->where('user_id', $userId)->exists()) {
            $this->out('meta-tags: уже есть');

            return 'skip';
        }

        $hist = DB::table('meta_tags_histories')
            ->whereRaw('LENGTH(data) BETWEEN 2000 AND 80000')
            ->orderByDesc('id')
            ->first();

        if (! $hist) {
            $tagId = DB::table('meta_tags')->insertGetId([
                'user_id' => $userId,
                'status' => 0,
                'name' => 'Демо: demo-shop.ru',
                'period' => 24,
                'links' => "https://demo-shop.ru/\nhttps://demo-shop.ru/catalog/",
                'timeout' => 15,
                'title_min' => 10,
                'title_max' => 70,
                'description_min' => 50,
                'description_max' => 160,
                'keywords_min' => 0,
                'keywords_max' => 20,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            MetaTagsHistory::query()->create([
                'meta_tag_id' => $tagId,
                'ideal' => 1,
                'quantity' => 2,
                'errors_count' => 0,
                'data' => json_encode([
                    [
                        'link' => 'https://demo-shop.ru/',
                        'title' => 'Купить диван — демо-магазин',
                        'description' => 'Диваны с доставкой по Москве',
                        'keywords' => 'диван, купить диван',
                        'errors' => [],
                    ],
                ], JSON_UNESCAPED_UNICODE),
            ]);
            $this->out('meta-tags: OK (фикстура)');

            return 'ok';
        }

        $srcTag = DB::table('meta_tags')->where('id', $hist->meta_tag_id)->first();
        $tagId = DB::table('meta_tags')->insertGetId([
            'user_id' => $userId,
            'status' => 0,
            'name' => 'Демо: мета-теги',
            'period' => $srcTag->period ?? 24,
            'links' => $srcTag->links ?? 'https://demo-shop.ru/',
            'timeout' => $srcTag->timeout ?? 15,
            'title_min' => $srcTag->title_min ?? 10,
            'title_max' => $srcTag->title_max ?? 70,
            'description_min' => $srcTag->description_min ?? 50,
            'description_max' => $srcTag->description_max ?? 160,
            'keywords_min' => $srcTag->keywords_min ?? 0,
            'keywords_max' => $srcTag->keywords_max ?? 20,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::statement(
            'INSERT INTO meta_tags_histories (meta_tag_id, ideal, quantity, errors_count, data, created_at, updated_at)
             SELECT ?, ideal, quantity, errors_count, data, NOW(), NOW() FROM meta_tags_histories WHERE id = ?',
            [$tagId, $hist->id]
        );

        $this->out('meta-tags: OK (клон history #' . $hist->id . ')');

        return 'ok';
    }

    private function seedBacklink(int $userId): string
    {
        if (ProjectTracking::query()->where('user_id', $userId)->exists()) {
            $this->out('backlink: уже есть');

            return 'skip';
        }

        $project = ProjectTracking::query()->create([
            'user_id' => $userId,
            'monitoring_project_id' => null,
            'project_name' => 'Демо: ссылки demo-shop.ru',
            'total_link' => 3,
            'total_broken_link' => 0,
            'notify_telegram' => 0,
            'notify_email' => 0,
        ]);

        $links = [
            ['link' => 'https://partner-a.ru/review', 'site_donor' => 'partner-a.ru', 'anchor' => 'купить диван', 'yandex' => 1, 'google' => 1],
            ['link' => 'https://blog-mebel.ru/top-10', 'site_donor' => 'blog-mebel.ru', 'anchor' => 'диваны Москва', 'yandex' => 1, 'google' => 0],
            ['link' => 'https://catalog.example/shop', 'site_donor' => 'catalog.example', 'anchor' => 'demo-shop.ru', 'yandex' => 1, 'google' => 1],
        ];

        foreach ($links as $row) {
            LinkTracking::query()->create([
                'project_tracking_id' => $project->id,
                'link' => $row['link'],
                'site_donor' => $row['site_donor'],
                'anchor' => $row['anchor'],
                'noindex' => 0,
                'nofollow' => 0,
                'yandex' => $row['yandex'],
                'google' => $row['google'],
                'last_check' => now(),
                'status' => 'Link found, anchor matches.',
                'broken' => 0,
                'mail_sent' => 0,
            ]);
        }

        $this->out('backlink: OK');

        return 'ok';
    }

    private function seedPasswords(int $userId): string
    {
        if (PasswordsGenerator::query()->where('user_id', $userId)->exists()) {
            $this->out('password-generator: уже есть');

            return 'skip';
        }

        $rows = [
            ['password' => 'TitloDemo#92kx', 'comment' => 'Демо: админка'],
            ['password' => 'ShopView$7nQm', 'comment' => 'Демо: витрина'],
            ['password' => 'ApiRead!4pLm', 'comment' => 'Демо: API'],
        ];
        foreach ($rows as $row) {
            PasswordsGenerator::query()->create([
                'user_id' => $userId,
                'password' => $row['password'],
                'comment' => $row['comment'],
            ]);
        }

        $this->out('password-generator: OK');

        return 'ok';
    }

    private function seedAiGeneration(int $userId): string
    {
        if (! Schema::hasTable('ai_generation_histories')) {
            return 'n/a';
        }

        if (AiGenerationHistory::query()->where('user_id', $userId)->exists()) {
            $this->out('ai-generation: уже есть');

            return 'skip';
        }

        $source = DB::table('ai_generation_histories')
            ->where('status', 'completed')
            ->whereRaw('LENGTH(result) BETWEEN 500 AND 30000')
            ->orderByDesc('id')
            ->first();

        if (! $source) {
            AiGenerationHistory::query()->create([
                'user_id' => $userId,
                'status' => 'completed',
                'type' => 'category',
                'prompt' => 'Сгенерируй описание категории «Диваны»',
                'parrameters' => ['source' => 'demo'],
                'result' => "Диваны — мягкая мебель для гостиной.\nВ категории: угловые, прямые, модульные модели.",
                'used_tokens' => 120,
            ]);
            $this->out('ai-generation: OK (фикстура)');

            return 'ok';
        }

        DB::table('ai_generation_histories')->insert([
            'user_id' => $userId,
            'parrameters' => $source->parrameters,
            'prompt' => $source->prompt ?: 'Демо-генерация',
            'status' => 'completed',
            'type' => $source->type ?: 'category',
            'result' => $source->result,
            'used_tokens' => $source->used_tokens ?? 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->out('ai-generation: OK (клон #' . $source->id . ')');

        return 'ok';
    }

    private function seedMonitoring(int $userId): string
    {
        if (! Schema::hasTable('monitoring_projects')) {
            return 'n/a';
        }

        $exists = DB::table('monitoring_projects')
            ->where('creator', $userId)
            ->exists();
        if (! $exists && Schema::hasTable('monitoring_project_user')) {
            $exists = DB::table('monitoring_project_user')->where('user_id', $userId)->exists();
        }
        if ($exists) {
            $this->out('monitoring-v2: уже есть');

            return 'skip';
        }

        $projectId = DB::table('monitoring_projects')->insertGetId([
            'creator' => $userId,
            'status' => 1,
            'budget' => null,
            'url' => 'demo-shop.ru',
            'favicon_path' => null,
            'favicon_host' => 'demo-shop.ru',
            'favicon_updated_at' => null,
            'name' => 'Демо: demo-shop.ru',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (Schema::hasTable('monitoring_project_user')) {
            DB::table('monitoring_project_user')->insert([
                'user_id' => $userId,
                'status' => 1,
                'monitoring_project_id' => $projectId,
                'admin' => 1,
                'approved' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $groupId = null;
        if (Schema::hasTable('monitoring_groups')) {
            $groupPayload = [
                'monitoring_project_id' => $projectId,
                'name' => 'Основные',
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('monitoring_groups', 'type')) {
                $groupPayload['type'] = 'keyword';
            }
            $groupId = DB::table('monitoring_groups')->insertGetId($groupPayload);
        }

        $engineId = DB::table('monitoring_searchengines')->insertGetId([
            'monitoring_project_id' => $projectId,
            'engine' => 'yandex',
            'lr' => 213,
            'google_depth' => 10,
            'auto_update' => 0,
            'time' => null,
            'weekdays' => null,
            'monthday' => null,
            'day' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $queries = [
            ['query' => 'купить диван', 'target' => 'https://demo-shop.ru/', 'start' => 8],
            ['query' => 'диван москва', 'target' => 'https://demo-shop.ru/catalog/', 'start' => 12],
            ['query' => 'угловой диван', 'target' => 'https://demo-shop.ru/uglovoy/', 'start' => 18],
            ['query' => 'диван недорого', 'target' => 'https://demo-shop.ru/', 'start' => 25],
            ['query' => 'купить диван онлайн', 'target' => 'https://demo-shop.ru/', 'start' => 15],
        ];

        $kwIds = [];
        foreach ($queries as $q) {
            $payload = [
                'monitoring_project_id' => $projectId,
                'target' => $q['target'],
                'query' => $q['query'],
                'page' => null,
                'dynamic' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if ($groupId !== null && Schema::hasColumn('monitoring_keywords', 'monitoring_group_id')) {
                $payload['monitoring_group_id'] = $groupId;
            }
            $kwIds[] = [
                'id' => DB::table('monitoring_keywords')->insertGetId($payload),
                'start' => $q['start'],
                'target' => $q['target'],
            ];
        }

        // 14 дней позиций — чтобы таблица/график не были пустыми
        for ($day = 13; $day >= 0; $day--) {
            $at = Carbon::now()->subDays($day)->setTime(10, 0, 0);
            foreach ($kwIds as $i => $kw) {
                $pos = max(1, (int) round($kw['start'] + sin(($day + $i) / 2) * 3 + ($day % 3) - 1));
                DB::table('monitoring_positions')->insert([
                    'monitoring_keyword_id' => $kw['id'],
                    'monitoring_searchengine_id' => $engineId,
                    'position' => $pos,
                    'url' => $kw['target'],
                    'target' => 1,
                    'created_at' => $at,
                    'updated_at' => $at,
                ]);
            }
        }

        if (Schema::hasTable('monitoring_project_settings')) {
            DB::table('monitoring_project_settings')->insert([
                'monitoring_project_id' => $projectId,
                'name' => 'length',
                'value' => '100',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->out('monitoring-v2: OK (project #' . $projectId . ')');

        return 'ok';
    }

    private function purgeMonitoring(int $userId): void
    {
        if (! Schema::hasTable('monitoring_projects')) {
            return;
        }

        $ids = DB::table('monitoring_projects')->where('creator', $userId)->pluck('id');
        if ($ids->isEmpty() && Schema::hasTable('monitoring_project_user')) {
            $ids = DB::table('monitoring_project_user')->where('user_id', $userId)->pluck('monitoring_project_id');
        }
        if ($ids->isEmpty()) {
            return;
        }

        $kwIds = DB::table('monitoring_keywords')->whereIn('monitoring_project_id', $ids)->pluck('id');
        if ($kwIds->isNotEmpty()) {
            DB::table('monitoring_positions')->whereIn('monitoring_keyword_id', $kwIds)->delete();
        }
        DB::table('monitoring_keywords')->whereIn('monitoring_project_id', $ids)->delete();
        DB::table('monitoring_searchengines')->whereIn('monitoring_project_id', $ids)->delete();
        if (Schema::hasTable('monitoring_groups')) {
            DB::table('monitoring_groups')->whereIn('monitoring_project_id', $ids)->delete();
        }
        if (Schema::hasTable('monitoring_project_settings')) {
            DB::table('monitoring_project_settings')->whereIn('monitoring_project_id', $ids)->delete();
        }
        if (Schema::hasTable('monitoring_project_user')) {
            DB::table('monitoring_project_user')->whereIn('monitoring_project_id', $ids)->delete();
        }
        DB::table('monitoring_projects')->whereIn('id', $ids)->delete();
    }
}
