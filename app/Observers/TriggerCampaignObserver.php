<?php

namespace App\Observers;

use App\Services\UserNotificationPreferenceService;
use App\TriggerCampaign;

class TriggerCampaignObserver
{
    public function saved(TriggerCampaign $campaign): void
    {
        UserNotificationPreferenceService::flushCatalogCache();
    }

    public function deleted(TriggerCampaign $campaign): void
    {
        UserNotificationPreferenceService::flushCatalogCache();
    }
}
