<?php

namespace Tests\Feature\Wordstat;

use App\Wordstat\WordstatGroup;
use App\Wordstat\WordstatProject;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class WordstatModelsTest extends TestCase
{
    use DatabaseTransactions;

    public function testProjectAccessibleByOwnerOnly(): void
    {
        $project = WordstatProject::query()->create([
            'user_id' => 999001, 'domain' => 'example.ru', 'region_ids' => [213], 'device' => 'all',
        ]);
        WordstatGroup::query()->create(['project_id' => $project->id, 'name' => 'Окна']);

        $this->assertTrue($project->isAccessibleBy(999001));
        $this->assertFalse($project->isAccessibleBy(999002));
        $this->assertSame(1, WordstatProject::query()->accessibleBy(999001)->where('id', $project->id)->count());
        $this->assertSame(0, WordstatProject::query()->accessibleBy(999002)->where('id', $project->id)->count());
        $this->assertSame([213], $project->fresh()->region_ids);
        $this->assertSame('Окна', $project->groups()->first()->name);
    }
}
