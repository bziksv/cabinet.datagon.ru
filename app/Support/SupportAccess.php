<?php

namespace App\Support;

use App\SupportTicket;
use App\User;
use Illuminate\Support\Facades\Auth;

class SupportAccess
{
    public static function isStaff(): bool
    {
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        return $user->hasRole(['admin', 'Super Admin']) || User::isUserAdmin();
    }

    public static function canViewTicket(SupportTicket $ticket): bool
    {
        if (!Auth::check()) {
            return false;
        }

        if (self::isStaff()) {
            return true;
        }

        return (int) $ticket->user_id === (int) Auth::id();
    }

    public static function canReplyAsStaff(SupportTicket $ticket): bool
    {
        return self::isStaff() && self::canViewTicket($ticket) && !$ticket->isClosed();
    }

    public static function canReplyAsUser(SupportTicket $ticket): bool
    {
        if (self::isStaff() || $ticket->isClosed()) {
            return false;
        }

        return (int) $ticket->user_id === (int) Auth::id();
    }

    public static function canReopen(SupportTicket $ticket): bool
    {
        if (!$ticket->isClosed() || !Auth::check()) {
            return false;
        }

        if (self::isStaff()) {
            return true;
        }

        return (int) $ticket->user_id === (int) Auth::id();
    }

    /**
     * Счётчик для бейджа в шапке: staff — открытые тикеты; пользователь — ответ поддержки.
     */
    public static function headerBadgeCount(): int
    {
        if (!Auth::check()) {
            return 0;
        }

        if (self::isStaff()) {
            return (int) SupportTicket::where('status', SupportTicket::STATUS_OPEN)->count();
        }

        return (int) SupportTicket::where('user_id', Auth::id())
            ->where('status', SupportTicket::STATUS_ANSWERED)
            ->count();
    }
}
