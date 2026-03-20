<?php

namespace App\Policies;

use App\Models\DailyCallReport;
use App\Models\User;

class DailyCallReportPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, DailyCallReport $dailyCallReport): bool
    {
        return $user->role === 'admin'
            || $user->role === 'account_manager'
            || (int) $dailyCallReport->user_id === (int) $user->id;
    }
}
