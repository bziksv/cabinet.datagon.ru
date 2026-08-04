<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RenameSeoChecklistMenuToChecklist extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('main_projects')) {
            return;
        }

        $rows = DB::table('main_projects')
            ->where('link', 'like', '%seo-checklist%')
            ->get(['id', 'link']);

        foreach ($rows as $row) {
            $link = str_replace('/seo-checklist', '/checklist', (string) $row->link);
            DB::table('main_projects')->where('id', $row->id)->update([
                'link' => $link,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('main_projects')) {
            return;
        }

        $rows = DB::table('main_projects')
            ->where('controller', 'like', '%SeoChecklistController%')
            ->where('link', 'like', '%/checklist%')
            ->get(['id', 'link']);

        foreach ($rows as $row) {
            $link = preg_replace('#/checklist(/|$)#', '/seo-checklist$1', (string) $row->link, 1) ?: $row->link;
            DB::table('main_projects')->where('id', $row->id)->update([
                'link' => $link,
                'updated_at' => now(),
            ]);
        }
    }
}
