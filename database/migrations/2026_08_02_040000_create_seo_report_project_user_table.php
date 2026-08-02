<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSeoReportProjectUserTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('seo_report_project_user')) {
            return;
        }

        Schema::create('seo_report_project_user', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('seo_report_project_id');
            $table->unsignedBigInteger('user_id');
            // read = client/employee read-only; edit = сотрудник с правкой текстов
            $table->string('role', 16)->default('read');
            $table->timestamps();

            $table->unique(['seo_report_project_id', 'user_id'], 'sr_project_user_unique');
            $table->foreign('seo_report_project_id', 'sr_project_user_project_fk')
                ->references('id')->on('seo_report_projects')->onDelete('cascade');
            $table->foreign('user_id', 'sr_project_user_user_fk')
                ->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('seo_report_project_user');
    }
}
