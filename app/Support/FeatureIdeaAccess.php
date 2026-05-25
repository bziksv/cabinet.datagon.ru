<?php

namespace App\Support;

use App\FeatureIdea;
use Illuminate\Support\Facades\Auth;

class FeatureIdeaAccess
{
    public static function isStaff(): bool
    {
        return SupportAccess::isStaff();
    }

    public static function canView(FeatureIdea $idea): bool
    {
        if (!Auth::check()) {
            return false;
        }

        if ($idea->isApproved()) {
            return true;
        }

        if (self::isStaff()) {
            return true;
        }

        return (int) $idea->user_id === (int) Auth::id();
    }

    public static function canVote(FeatureIdea $idea): bool
    {
        if (!Auth::check() || !$idea->isApproved()) {
            return false;
        }

        return (int) $idea->user_id !== (int) Auth::id();
    }

    public static function canModerate(FeatureIdea $idea): bool
    {
        return self::isStaff() && $idea->isPending();
    }

    public static function staffPendingCount(): int
    {
        if (!self::isStaff()) {
            return 0;
        }

        return (int) FeatureIdea::where('status', FeatureIdea::STATUS_PENDING)->count();
    }
}
