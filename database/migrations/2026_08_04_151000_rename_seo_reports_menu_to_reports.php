<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RenameSeoReportsMenuToReports extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('main_projects')) {
            return;
        }

        $rows = DB::table('main_projects')
            ->where('link', 'like', '%seo-reports%')
            ->get(['id', 'link', 'title']);

        foreach ($rows as $row) {
            $link = str_replace('/seo-reports', '/reports', (string) $row->link);
            $link = preg_replace('#(^|://)([^/]*)seo-reports$#', '$1$2reports', $link) ?: $link;

            $title = (string) ($row->title ?? '');
            if ($title === 'SEO Reports' || $title === '') {
                $title = 'Reports';
            }

            DB::table('main_projects')->where('id', $row->id)->update([
                'link' => $link,
                'title' => $title,
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
            ->where(function ($q) {
                $q->where('link', 'like', '%/reports%')
                    ->where('controller', 'like', '%SeoReportsController%');
            })
            ->orWhere(function ($q) {
                $q->where('title', 'Reports')
                    ->where('controller', 'like', '%SeoReportsController%');
            })
            ->get(['id', 'link', 'title']);

        foreach ($rows as $row) {
            $link = (string) $row->link;
            // Не трогаем вложенные /reports/{id}/reports/...
            $link = preg_replace('#/reports(/|$)#', '/seo-reports$1', $link, 1) ?: $link;

            DB::table('main_projects')->where('id', $row->id)->update([
                'link' => $link,
                'title' => 'SEO Reports',
                'updated_at' => now(),
            ]);
        }
    }
}
