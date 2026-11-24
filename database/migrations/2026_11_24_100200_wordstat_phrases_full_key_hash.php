<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class WordstatPhrasesFullKeyHash extends Migration
{
    // Уникальность по хешу полного ключа: индекс по VARCHAR(191) склеивал длинные фразы с одинаковым началом
    public function up()
    {
        DB::statement('ALTER TABLE wordstat_phrases ADD phrase_hash CHAR(40) NULL AFTER phrase_key');
        DB::statement('UPDATE wordstat_phrases SET phrase_hash = SHA1(phrase_key)');
        DB::statement('ALTER TABLE wordstat_phrases MODIFY phrase_key VARCHAR(400) NOT NULL, MODIFY phrase_hash CHAR(40) NOT NULL');
        DB::statement('ALTER TABLE wordstat_phrases DROP INDEX wordstat_phrases_project_id_phrase_key_unique, ADD UNIQUE wordstat_phrases_project_id_phrase_hash_unique (project_id, phrase_hash)');
    }

    public function down()
    {
        DB::statement('ALTER TABLE wordstat_phrases DROP INDEX wordstat_phrases_project_id_phrase_hash_unique');
        DB::statement('UPDATE wordstat_phrases SET phrase_key = LEFT(phrase_key, 191)');
        DB::statement('ALTER TABLE wordstat_phrases MODIFY phrase_key VARCHAR(191) NOT NULL, DROP COLUMN phrase_hash');
        DB::statement('ALTER TABLE wordstat_phrases ADD UNIQUE wordstat_phrases_project_id_phrase_key_unique (project_id, phrase_key)');
    }
}
