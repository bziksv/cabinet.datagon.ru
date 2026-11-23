<?php

namespace Tests\Feature\Wordstat;

use App\User;
use App\Wordstat\WordstatGroup;
use App\Wordstat\WordstatPhrase;
use App\Wordstat\WordstatPreset;
use App\Wordstat\WordstatProject;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class WordstatEditingTest extends TestCase
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

    private function seedProject(User $user): array
    {
        $project = WordstatProject::query()->create(['user_id' => $user->id, 'domain' => 'example.ru', 'region_ids' => [213]]);
        $a = WordstatGroup::query()->create(['project_id' => $project->id, 'name' => 'А']);
        $b = WordstatGroup::query()->create(['project_id' => $project->id, 'name' => 'Б']);
        $p1 = WordstatPhrase::query()->create(['project_id' => $project->id, 'group_id' => $a->id, 'phrase' => 'окна', 'phrase_key' => 'окна', 'freq' => 1000, 'source' => 'manual']);
        $p2 = WordstatPhrase::query()->create(['project_id' => $project->id, 'group_id' => $a->id, 'phrase' => 'окна пвх', 'phrase_key' => 'окна пвх', 'freq' => 300, 'source' => 'left']);

        return [$project, $a, $b, $p1, $p2];
    }

    public function testBulkMoveMinusAndMerge(): void
    {
        $user = $this->user();
        [$project, $a, $b, $p1, $p2] = $this->seedProject($user);

        $this->actingAs($user)->postJson(route('pages.wordstat-keywords.phrases.bulk', $project->id), ['ids' => [$p2->id], 'action' => 'move', 'group_id' => $b->id])
            ->assertOk()->assertJson(['affected' => 1]);
        $this->assertSame($b->id, (int) $p2->fresh()->group_id);

        $this->actingAs($user)->postJson(route('pages.wordstat-keywords.phrases.bulk', $project->id), ['ids' => [$p1->id], 'action' => 'minus'])->assertOk();
        $this->assertSame('minus', $p1->fresh()->status);

        $this->actingAs($user)->postJson(route('pages.wordstat-keywords.groups.merge', [$project->id, $b->id]), ['target_id' => $a->id])->assertOk();
        $this->assertNull(WordstatGroup::query()->find($b->id));
        $this->assertSame($a->id, (int) $p2->fresh()->group_id);

        $list = $this->actingAs($user)->getJson(route('pages.wordstat-keywords.phrases', $project->id) . '?group_id=' . $a->id);
        $list->assertOk();
        $this->assertSame(['окна пвх'], array_column($list->json('phrases'), 'phrase'));
    }

    public function testRenameGroupRejectsDuplicateName(): void
    {
        $user = $this->user();
        [$project, $a] = $this->seedProject($user);
        $this->actingAs($user)->postJson(route('pages.wordstat-keywords.groups.update', [$project->id, $a->id]), ['name' => 'Б'])->assertStatus(422);
        $this->actingAs($user)->postJson(route('pages.wordstat-keywords.groups.update', [$project->id, $a->id]), ['name' => 'Окна'])
            ->assertOk()->assertJson(['group' => ['name' => 'Окна']]);
    }

    public function testPresetCrudAndOwnership(): void
    {
        $user = $this->user();
        $res = $this->actingAs($user)->postJson(route('pages.wordstat-keywords.presets.store'), [
            'title' => 'Москва, обе колонки', 'settings' => ['region_ids' => [213], 'left_limit' => 500],
        ]);
        $res->assertOk()->assertJson(['preset' => ['title' => 'Москва, обе колонки', 'mine' => true]]);
        $this->assertSame(500, WordstatPreset::query()->find($res->json('preset.id'))->settings['left_limit']);

        $foreign = WordstatPreset::query()->create(['user_id' => 999001, 'title' => 'чужой', 'settings' => []]);
        $this->actingAs($user)->postJson(route('pages.wordstat-keywords.presets.destroy', $foreign->id))->assertNotFound();
    }

    public function testExportReturnsXlsx(): void
    {
        $user = $this->user();
        [$project] = $this->seedProject($user);
        $res = $this->actingAs($user)->get(route('pages.wordstat-keywords.export', $project->id));
        $res->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $res->headers->get('content-type'));
    }
}
