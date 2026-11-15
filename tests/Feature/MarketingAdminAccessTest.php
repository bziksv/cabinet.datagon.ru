<?php

namespace Tests\Feature;

use App\MarketingContentItem;
use App\MarketingStage;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «Маркетинг сайта»: доступ только Super Admin / admin.
 * Работает на текущей (локальной) БД в транзакции — ничего не сохраняет. Нужны мигрированные marketing_* таблицы.
 */
class MarketingAdminAccessTest extends TestCase
{
    use DatabaseTransactions;

    private function adminUser(): ?User
    {
        $id = DB::table('model_has_roles')->where('model_type', User::class)
            ->whereIn('role_id', DB::table('roles')->whereIn('name', ['Super Admin', 'admin'])->pluck('id'))
            ->value('model_id');

        return $id ? User::query()->whereNotNull('email_verified_at')->find($id) : null;
    }

    private function plainUser(): ?User
    {
        $adminIds = DB::table('model_has_roles')->where('model_type', User::class)
            ->whereIn('role_id', DB::table('roles')->whereIn('name', ['Super Admin', 'admin'])->pluck('id'))
            ->pluck('model_id');

        return User::query()->whereNotNull('email_verified_at')->whereNotIn('id', $adminIds)->orderBy('id')->first();
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (!\Illuminate\Support\Facades\Schema::hasTable('marketing_stages')) {
            $this->markTestSkipped('marketing_* tables are not migrated');
        }
    }

    public function testGuestIsRedirectedToLogin(): void
    {
        $this->get(route('admin.marketing.index'))->assertRedirect();
    }

    public function testPlainUserGets403(): void
    {
        $user = $this->plainUser();
        if (!$user) {
            $this->markTestSkipped('no plain user');
        }

        $this->actingAs($user)->get(route('admin.marketing.index'))->assertStatus(403);
        $this->actingAs($user)->get(route('admin.marketing.content'))->assertStatus(403);
        $this->actingAs($user)->post(route('admin.marketing.stages.store'), ['title' => 'x', 'priority' => 'P1', 'status' => 'planned'])->assertStatus(403);
    }

    public function testAdminSeesPlanAndContent(): void
    {
        $admin = $this->adminUser();
        if (!$admin) {
            $this->markTestSkipped('no admin user');
        }

        $this->actingAs($admin)->get(route('admin.marketing.index'))
            ->assertOk()
            ->assertSee('SEO-словарь');

        $this->actingAs($admin)->get(route('admin.marketing.content', ['type' => 'article', 'top' => 1]))
            ->assertOk()
            ->assertSee('Как собрать семантическое ядро сайта');

        $item = MarketingContentItem::query()->where('type', 'term')->where('title', 'Семантическое ядро')->firstOrFail();
        $this->actingAs($admin)->get(route('admin.marketing.content.edit', $item->id))->assertOk()->assertSee('LSI');
    }

    public function testAdminCrud(): void
    {
        $admin = $this->adminUser();
        if (!$admin) {
            $this->markTestSkipped('no admin user');
        }

        $this->actingAs($admin)->post(route('admin.marketing.stages.store'), [
            'title' => 'Тестовый этап', 'priority' => 'P2', 'status' => 'planned',
        ])->assertRedirect();
        $stage = MarketingStage::query()->where('title', 'Тестовый этап')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.marketing.tasks.store'), [
            'stage_id' => $stage->id, 'title' => 'Тестовая задача', 'priority' => 'P2', 'status' => 'planned',
        ])->assertRedirect();
        $task = $stage->tasks()->firstOrFail();

        $this->actingAs($admin)->patch(route('admin.marketing.tasks.status', $task->id), ['status' => 'done'])->assertRedirect();
        $this->assertSame('done', $task->fresh()->status);
        $this->assertNotNull($task->fresh()->done_at);

        $item = MarketingContentItem::query()->where('type', 'term')->where('title', 'Семантическое ядро')->firstOrFail();
        $this->actingAs($admin)->put(route('admin.marketing.content.update', $item->id), [
            'type' => 'term', 'title' => $item->title, 'priority' => 'P1', 'status' => 'brief_ready',
            'main_query' => 'что такое семантическое ядро', 'outline' => "## Что такое\n## Как собрать",
            'lsi_words' => 'частотность, кластеризация', 'lsi_source' => 'Анализ релевантности, Яндекс', 'lsi_analyzed_at' => '2026-11-16',
            'is_top' => '1',
        ])->assertRedirect();
        $fresh = $item->fresh();
        $this->assertSame('brief_ready', $fresh->status);
        $this->assertTrue($fresh->hasBrief());
        $this->assertTrue($fresh->hasLsi());
    }
}
