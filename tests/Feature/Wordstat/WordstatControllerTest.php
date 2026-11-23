<?php

namespace Tests\Feature\Wordstat;

use App\Jobs\Wordstat\StartWordstatRun;
use App\User;
use App\Wordstat\WordstatProject;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class WordstatControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function user(): User
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(1);
        $user = User::query()->whereNotNull('email_verified_at')->get()->first(fn (User $u) => $u->can('Wordstat keywords'));
        if (! $user) {
            $this->markTestSkipped('Нет пользователя с правом Wordstat keywords');
        }

        return $user;
    }

    public function testPreviewAndStartRun(): void
    {
        Queue::fake();
        $user = $this->user();
        $project = WordstatProject::query()->create(['user_id' => $user->id, 'domain' => 'example.ru', 'region_ids' => [213]]);

        $preview = $this->actingAs($user)->postJson(route('pages.wordstat-keywords.preview', $project->id), [
            'text' => "Окна: окна пвх\nдвери",
            'settings' => ['depth' => 1, 'depth_max_seeds' => 10],
        ]);
        $preview->assertOk()->assertJson(['ok' => true, 'estimate' => ['total' => 12]]);
        $this->assertCount(2, $preview->json('seeds'));

        $start = $this->actingAs($user)->postJson(route('pages.wordstat-keywords.runs.store', $project->id), [
            'text' => "Окна: окна пвх\nдвери",
            'settings' => [],
        ]);
        $start->assertOk()->assertJson(['ok' => true, 'run' => ['status' => 'queued', 'seeds_total' => 2]]);
        Queue::assertPushed(StartWordstatRun::class);

        $runId = $start->json('run.id');
        $this->actingAs($user)->getJson(route('pages.wordstat-keywords.runs.show', [$project->id, $runId]))
            ->assertOk()->assertJson(['run' => ['id' => $runId, 'active' => true]]);
        $this->actingAs($user)->postJson(route('pages.wordstat-keywords.runs.cancel', [$project->id, $runId]))
            ->assertOk()->assertJson(['run' => ['status' => 'cancelled']]);
    }

    public function testForeignProjectIs404(): void
    {
        $user = $this->user();
        $project = WordstatProject::query()->create(['user_id' => 999001, 'domain' => 'x.ru', 'region_ids' => [213]]);
        $this->actingAs($user)->postJson(route('pages.wordstat-keywords.preview', $project->id), ['text' => 'окна'])->assertNotFound();
    }

    public function testEmptyInputIs422(): void
    {
        $user = $this->user();
        $project = WordstatProject::query()->create(['user_id' => $user->id, 'domain' => 'example.ru', 'region_ids' => [213]]);
        $this->actingAs($user)->postJson(route('pages.wordstat-keywords.runs.store', $project->id), ['text' => "  \n "])
            ->assertStatus(422)->assertJson(['ok' => false]);
    }
}
