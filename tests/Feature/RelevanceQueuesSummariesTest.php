<?php

namespace Tests\Feature;

use App\Classes\AnalyzeRelevance\RelevanceQueues;
use App\Http\Controllers\RelevanceController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RelevanceQueuesSummariesTest extends TestCase
{
    use DatabaseTransactions;

    public function testExtractsFieldsWithoutUnserializing(): void
    {
        $command = new RelevanceQueuesSummariesFakeJob();
        $command->request = ['phrase' => 'купить окна "пвх"', 'link' => 'https://окна.рф/catalog/'];
        $command->userId = 77;
        $command->historyId = 1234;
        $command->type = 'mainPage';
        $command->relevance = str_repeat('x', 200000);

        DB::table('jobs')->insert([
            'queue' => RelevanceController::HIGH_QUEUE,
            'payload' => json_encode([
                'uuid' => 'test',
                'displayName' => 'App\\Jobs\\Relevance\\RelevanceHistoryQueue',
                'data' => ['commandName' => 'App\\Jobs\\Relevance\\RelevanceHistoryQueue', 'command' => serialize($command)],
            ]),
            'attempts' => 0,
            'available_at' => time(),
            'created_at' => time(),
        ]);

        $job = (new RelevanceQueues())->summaries([RelevanceController::HIGH_QUEUE])->last();

        $this->assertSame('RelevanceHistoryQueue', $job->job);
        $this->assertSame(RelevanceController::HIGH_QUEUE, $job->queue);
        $this->assertSame(77, $job->userId);
        $this->assertSame(1234, $job->historyId);
        $this->assertSame(0, $job->mainId);
        $this->assertSame('mainPage', $job->type);
        $this->assertSame('купить окна "пвх"', $job->phrase);
        $this->assertSame('https://окна.рф/catalog/', $job->link);
    }
}

class RelevanceQueuesSummariesFakeJob
{
    public $request;
    public $userId;
    public $historyId;
    public $type;
    public $relevance;
}
