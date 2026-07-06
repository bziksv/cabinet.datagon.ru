<?php

use App\DomainMonitoring;
use App\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

class AddNotifyChannelsToDomainMonitoringTable extends Migration
{
    public function up(): void
    {
        Schema::table('domain_monitoring', function (Blueprint $table) {
            $table->boolean('notify_telegram')->default(false)->after('send_notification');
            $table->boolean('notify_email')->default(false)->after('notify_telegram');
        });

        app(PermissionRegistrar::class)->setPermissionsTeamId(1);

        DomainMonitoring::query()
            ->with(['user.roles'])
            ->orderBy('id')
            ->chunkById(200, function ($projects) {
                foreach ($projects as $project) {
                    $enabled = (bool) $project->send_notification;
                    $paid = $project->user instanceof User && $project->user->hasPaidTariffRole();

                    $project->notify_telegram = $enabled;
                    $project->notify_email = $enabled && $paid;
                    $project->save();
                }
            });
    }

    public function down(): void
    {
        Schema::table('domain_monitoring', function (Blueprint $table) {
            $table->dropColumn(['notify_telegram', 'notify_email']);
        });
    }
}
