<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('branch.{branchId}.print-jobs', function ($user, int $branchId) {
    if ($user->isSuperAdmin()) {
        return true;
    }

    if ($user->branch_id && (int) $user->branch_id === $branchId) {
        return true;
    }

    return $user->branches()->where('branches.id', $branchId)->exists();
});
