<?php
namespace Tests\Feature\Wordstat;

use App\Jobs\Wordstat\CollectWordstatSeed;
use App\Services\Wordstat\WordstatClient;
use App\Services\Wordstat\WordstatCollector;
use App\Services\Wordstat\WordstatException;
use App\Wordstat\WordstatPhrase;
use App\Wordstat\WordstatProject;
use App\Wordstat\WordstatRun;
use App\Wordstat\WordstatRunSeed;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WordstatCollectorTest extends TestCase
{
    use DatabaseTransactions;

    private function collector(array $responses): WordstatCollector
    {
        $client = new WordstatClient('https://api.test/', 'u', 'k', function (string $url) use (&$responses) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $q);

            return $responses[$q['query']] ?? '{"totalCount":"0","results":[],"associations":[]}';
        });

        return new WordstatCollector($client, false);
    }

    private function project(): WordstatProject
    {
        return WordstatProject::query()->create(['user_id' => 999001, 'domain' => 'example.ru', 'region_ids' => [213]]);
    }

    public function testCollectsIntoGroupsWithoutDuplicates(): void
    {
        $collector = $this->collector([
            'окна' => json_encode([
                'totalCount' => '1000',
                'results' => [['phrase' => 'окна', 'count' => '1000'], ['phrase' => 'окна пвх', 'count' => '300'], ['phrase' => 'окна бесплатно', 'count' => '5']],
                'associations' => [['phrase' => 'стеклопакеты', 'count' => '200']],
            ]),
            'двери' => json_encode([
                'totalCount' => '500',
                'results' => [['phrase' => 'двери', 'count' => '500'], ['phrase' => 'окна пвх', 'count' => '300']],
                'associations' => [],
            ]),
        ]);
        $project = $this->project();
        $run = $collector->createRun($project, 999001, [
            ['group' => 'Окна', 'phrase' => 'окна'],
            ['group' => 'Двери', 'phrase' => 'двери'],
        ], ['minus_words' => ['бесплатно'], 'columns' => ['left', 'right']]);

        $this->assertSame(2, (int) $run->requests_estimated);

        foreach ($run->seeds()->orderBy('id')->get() as $seed) {
            $collector->collectSeed($seed);
        }

        $phrases = WordstatPhrase::query()->where('project_id', $project->id)->get()->keyBy('phrase');
        $this->assertEqualsCanonicalizing(['окна', 'окна пвх', 'стеклопакеты', 'двери'], $phrases->keys()->all());
        $this->assertSame(1000, $phrases['окна']->freq);
        $this->assertSame('manual', $phrases['окна']->source);
        $this->assertSame('right', $phrases['стеклопакеты']->source);
        $this->assertSame($phrases['окна']->group_id, $phrases['окна пвх']->group_id);

        $run->refresh();
        $this->assertSame(WordstatRun::STATUS_DONE, $run->status);
        $this->assertSame(2, (int) $run->seeds_done);
        $this->assertSame(2, (int) $run->requests_used);
        $this->assertSame(4, (int) $run->phrases_added);
        $this->assertSame(2, \DB::table('wordstat_request_log')->where('run_id', $run->id)->count());
    }

    public function testDepthCreatesChildSeedsWithinCap(): void
    {
        Queue::fake();
        $collector = $this->collector([
            'окна' => json_encode([
                'totalCount' => '1000',
                'results' => [['phrase' => 'окна', 'count' => '1000'], ['phrase' => 'окна пвх', 'count' => '300'], ['phrase' => 'окна дерево', 'count' => '100']],
                'associations' => [],
            ]),
        ]);
        $run = $collector->createRun($this->project(), 999001, [['group' => 'Окна', 'phrase' => 'окна']], ['depth' => 1, 'depth_max_seeds' => 1]);

        $collector->collectSeed($run->seeds()->first());

        $children = $run->seeds()->where('depth', 1)->get();
        $this->assertCount(1, $children);
        $this->assertSame('окна пвх', $children[0]->phrase);
        $this->assertSame(2, (int) $run->fresh()->seeds_total);
        $this->assertSame(WordstatRun::STATUS_RUNNING, $run->fresh()->status);
        Queue::assertPushed(CollectWordstatSeed::class, 1);
    }

    public function testCancelledRunSkipsSeeds(): void
    {
        $collector = $this->collector([]);
        $run = $collector->createRun($this->project(), 999001, [['group' => 'А', 'phrase' => 'окна']], []);
        $collector->cancelRun($run);

        $this->assertSame(WordstatRun::STATUS_CANCELLED, $run->fresh()->status);
        $this->assertSame(WordstatRunSeed::STATUS_SKIPPED, $run->seeds()->first()->status);
    }

    public function testFatalErrorFailsRun(): void
    {
        $collector = $this->collector(['окна' => '{"error":{"code":-34,"message":"Не корректный ID пользователя или ключ"}}']);
        $run = $collector->createRun($this->project(), 999001, [['group' => 'А', 'phrase' => 'окна']], []);

        try {
            $collector->collectSeed($run->seeds()->first());
            $this->fail('expected exception');
        } catch (WordstatException $e) {
            $this->assertFalse($e->isRetryable());
            $collector->failRun($run, $e->getMessage());
        }

        $this->assertSame(WordstatRun::STATUS_FAILED, $run->fresh()->status);
    }
}
