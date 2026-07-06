<?php

namespace App\Services;

use App\TriggerCampaign;
use App\User;
use App\UserNotificationPreference;
use Illuminate\Support\Facades\Cache;

class UserNotificationPreferenceService
{
    public const TRIGGER_KEY_PREFIX = 'trigger.';

    public function triggerPreferenceKey(TriggerCampaign $campaign): string
    {
        return self::TRIGGER_KEY_PREFIX . $campaign->slug;
    }

    public function isEnabled(User $user, string $preferenceKey): bool
    {
        if ($preferenceKey === '') {
            return true;
        }

        $row = UserNotificationPreference::query()
            ->where('user_id', $user->id)
            ->where('preference_key', $preferenceKey)
            ->first();

        if ($row === null) {
            return true;
        }

        return (bool) $row->enabled;
    }

    public function setEnabled(User $user, string $preferenceKey, bool $enabled): void
    {
        if ($preferenceKey === '') {
            return;
        }

        UserNotificationPreference::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'preference_key' => $preferenceKey,
            ],
            ['enabled' => $enabled]
        );

        $this->forgetUserCache($user);
    }

    /**
     * @param  array<string, bool>  $preferences
     */
    public function syncFromRequest(User $user, array $preferences): void
    {
        $allowed = $this->allowedKeys();

        foreach (array_keys($allowed) as $key) {
            $this->setEnabled($user, $key, !empty($preferences[$key]));
        }
    }

    /**
     * @return array<string, true> key => true
     */
    public function allowedKeys(): array
    {
        $keys = [];

        foreach ($this->emailPreferenceGroupsForProfile() as $group) {
            foreach ($group['items'] as $item) {
                if (!empty($item['can_toggle'])) {
                    $keys[(string) $item['key']] = true;
                }
            }
        }

        return $keys;
    }

    /**
     * @return list<array{slug: string, title: string, items: list<array<string, mixed>>}>
     */
    public function emailPreferenceGroupsForProfile(): array
    {
        return Cache::remember('cabinet.email-preferences.catalog', now()->addMinutes(2), function () {
            $groups = [];

            foreach ((array) config('cabinet-users-notifications.events', []) as $group) {
                $items = [];

                foreach ((array) ($group['items'] ?? []) as $item) {
                    if (!$this->itemHasProfileEmail($item)) {
                        continue;
                    }

                    $canToggle = $this->itemCanToggle($item);

                    $items[] = [
                        'key' => (string) ($item['id'] ?? ''),
                        'title' => __($item['title'] ?? ''),
                        'description' => __($item['trigger'] ?? ''),
                        'can_toggle' => $canToggle,
                        'is_trigger' => false,
                        'is_service' => !$canToggle,
                    ];
                }

                if ($items !== []) {
                    $groups[] = [
                        'slug' => (string) ($group['slug'] ?? ''),
                        'title' => __($group['title'] ?? ''),
                        'items' => $items,
                    ];
                }
            }

            $triggerItems = [];
            foreach (TriggerCampaign::allOrderedForAdmin() as $campaign) {
                $triggerItems[] = [
                    'key' => $this->triggerPreferenceKey($campaign),
                    'title' => $campaign->name,
                    'description' => $campaign->profileDescription(),
                    'can_toggle' => true,
                    'is_trigger' => true,
                ];
            }

            if ($triggerItems !== []) {
                $groups[] = [
                    'slug' => 'trigger-campaigns',
                    'title' => __('Profile notify group triggers'),
                    'items' => $triggerItems,
                ];
            }

            return $groups;
        });
    }

    /**
     * @return list<array{slug: string, title: string, items: list<array<string, mixed>}>}
     */
    public function profileGroupsForUser(User $user): array
    {
        $groups = $this->emailPreferenceGroupsForProfile();

        foreach ($groups as &$group) {
            foreach ($group['items'] as &$item) {
                $item['enabled'] = $this->isEnabled($user, (string) $item['key']);
            }
            unset($item);
        }
        unset($group);

        return $groups;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function itemHasProfileEmail(array $item): bool
    {
        $channels = (array) ($item['channels'] ?? []);

        return in_array('email_event', $channels, true)
            || in_array('email_service', $channels, true);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function itemCanToggle(array $item): bool
    {
        $channels = (array) ($item['channels'] ?? []);

        return in_array('email_event', $channels, true);
    }

    private function forgetUserCache(User $user): void
    {
        Cache::forget('cabinet.email-preferences.catalog');
    }

    public static function flushCatalogCache(): void
    {
        Cache::forget('cabinet.email-preferences.catalog');
    }
}
