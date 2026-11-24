<?php
namespace Tests\Feature\Wordstat;

use App\Exports\WordstatPhrasesExport;
use App\Services\Wordstat\WordstatClient;
use App\Services\Wordstat\WordstatCollector;
use App\Wordstat\WordstatGroup;
use App\Wordstat\WordstatPhrase;
use App\Wordstat\WordstatProject;
use App\Wordstat\WordstatRun;
use App\Wordstat\WordstatRunSeed;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class WordstatHardeningTest extends TestCase
{
    use DatabaseTransactions;

    public function testExportEscapesFormulas(): void
    {
        $this->assertSame("'=HYPERLINK(\"http://x\")", WordstatPhrasesExport::safe('=HYPERLINK("http://x")'));
        $this->assertSame("'+1", WordstatPhrasesExport::safe('+1'));
        $this->assertSame('окна пвх', WordstatPhrasesExport::safe('окна пвх'));
    }

    public function testMinusPhraseIsNotCollectedAgain(): void
    {
        $project = WordstatProject::query()->create(['user_id' => 999001, 'domain' => 'example.ru', 'region_ids' => [213]]);
        $group = WordstatGroup::query()->create(['project_id' => $project->id, 'name' => 'Окна']);
        $run = WordstatRun::query()->create([
            'project_id' => $project->id, 'user_id' => 999001, 'settings' => [], 'status' => WordstatRun::STATUS_RUNNING,
            'seeds_total' => 1, 'seeds_done' => 0,
        ]);
        $seed = WordstatRunSeed::query()->create(['run_id' => $run->id, 'group_id' => $group->id, 'phrase' => 'окна', 'depth' => 0]);
        WordstatPhrase::query()->create([
            'project_id' => $project->id, 'group_id' => $group->id, 'phrase' => 'окна бесплатно', 'phrase_key' => 'окна бесплатно',
            'source' => 'left', 'status' => WordstatPhrase::STATUS_MINUS,
        ]);

        $client = new WordstatClient('https://api.test/', 'u', 'k', function () {
            return json_encode([
                'totalCount' => '10',
                'results' => [['phrase' => 'окна бесплатно', 'count' => '5']],
                'associations' => [],
            ]);
        });
        (new WordstatCollector($client, false))->collectSeed($seed);

        $rows = WordstatPhrase::query()->where('project_id', $project->id)->where('phrase_key', 'окна бесплатно')->get();
        $this->assertCount(1, $rows);
        $this->assertSame(WordstatPhrase::STATUS_MINUS, $rows->first()->status);
    }

    public function testSeedIsClaimedOnlyOnce(): void
    {
        $project = WordstatProject::query()->create(['user_id' => 999001, 'domain' => 'example.ru', 'region_ids' => [213]]);
        $group = WordstatGroup::query()->create(['project_id' => $project->id, 'name' => 'Окна']);
        $run = WordstatRun::query()->create([
            'project_id' => $project->id, 'user_id' => 999001, 'settings' => [], 'status' => WordstatRun::STATUS_RUNNING,
            'seeds_total' => 1, 'seeds_done' => 0,
        ]);
        $seed = WordstatRunSeed::query()->create(['run_id' => $run->id, 'group_id' => $group->id, 'phrase' => 'окна', 'depth' => 0, 'status' => WordstatRunSeed::STATUS_DONE]);

        $calls = 0;
        $client = new WordstatClient('https://api.test/', 'u', 'k', function () use (&$calls) {
            $calls++;

            return json_encode(['totalCount' => '1', 'results' => [], 'associations' => []]);
        });
        (new WordstatCollector($client, false))->collectSeed($seed);

        $this->assertSame(0, $calls, 'Уже обработанная затравка не должна снова ходить в API');
    }

    public function testLongPhrasesWithSamePrefixStaySeparate(): void
    {
        [$project, $seed] = $this->runningSeed();
        $prefix = str_repeat('окна пвх недорого ', 12);
        $client = new WordstatClient('https://api.test/', 'u', 'k', function () use ($prefix) {
            return json_encode([
                'totalCount' => '10',
                'results' => [['phrase' => $prefix . 'москва', 'count' => '5'], ['phrase' => $prefix . 'казань', 'count' => '4']],
                'associations' => [],
            ]);
        });
        (new WordstatCollector($client, false))->collectSeed($seed);

        $this->assertSame(3, WordstatPhrase::query()->where('project_id', $project->id)->count());
    }

    public function testRequestsAreCountedPerApiCall(): void
    {
        [, $seed] = $this->runningSeed();
        $client = new WordstatClient('https://api.test/', 'u', 'k', function () {
            return json_encode(['totalCount' => '1', 'results' => [], 'associations' => []]);
        });
        (new WordstatCollector($client, false))->collectSeed($seed);

        $this->assertSame(1, (int) $seed->fresh()->requests_used);
        $this->assertSame(1, (int) WordstatRun::query()->find($seed->run_id)->requests_used);
    }

    private function runningSeed(): array
    {
        $project = WordstatProject::query()->create(['user_id' => 999001, 'domain' => 'example.ru', 'region_ids' => [213]]);
        $group = WordstatGroup::query()->create(['project_id' => $project->id, 'name' => 'Окна']);
        $run = WordstatRun::query()->create([
            'project_id' => $project->id, 'user_id' => 999001, 'settings' => [], 'status' => WordstatRun::STATUS_RUNNING,
            'seeds_total' => 1, 'seeds_done' => 0,
        ]);

        return [$project, WordstatRunSeed::query()->create(['run_id' => $run->id, 'group_id' => $group->id, 'phrase' => 'окна', 'depth' => 0])];
    }
}
