<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSeoChecklistItemAttachmentsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('seo_checklist_item_attachments')) {
            return;
        }

        Schema::create('seo_checklist_item_attachments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('item_id')->index();
            $table->unsignedBigInteger('note_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('original_name', 255);
            $table->string('path', 500);
            $table->string('mime', 150)->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('seo_checklist_item_attachments');
    }
}
