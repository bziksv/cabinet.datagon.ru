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
        return false;
    }
}
