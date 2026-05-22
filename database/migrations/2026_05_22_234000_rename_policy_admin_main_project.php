<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * main_projects.id=33: «Администрирование» → политики ПДн (/edit-policy-files).
 */
class RenamePolicyAdminMainProject extends Migration
{
    public function up(): void
    {
        DB::table('main_projects')
            ->where('id', 33)
            ->update([
                'title' => 'Policy management',
                'description' => 'Edit privacy policy and terms of use for the site',
                'link' => '/edit-policy-files',
            ]);
    }

    public function down(): void
    {
        DB::table('main_projects')
            ->where('id', 33)
            ->update([
                'title' => 'Администрирование',
                'description' => 'Настройки политик и других модулей',
                'link' => 'https://lk.redbox.su/edit-policy-files',
            ]);
    }
}
