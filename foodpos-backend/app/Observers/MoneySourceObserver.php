<?php

namespace App\Observers;

use App\Models\MoneySource;
use App\Services\ActivityLogger;

class MoneySourceObserver
{
    public function created(MoneySource $moneySource): void
    {
        ActivityLogger::log(
            'money_source.created',
            (int) $moneySource->company_id,
            'Money source created: '.$moneySource->name,
            [
                'name' => $moneySource->name,
                'type' => $moneySource->type,
                'opening_balance' => $moneySource->opening_balance,
                'active' => $moneySource->active,
            ],
            $moneySource
        );
    }

    public function updated(MoneySource $moneySource): void
    {
        $changes = ActivityLogger::changes(
            $moneySource->getOriginal(),
            $moneySource->getAttributes()
        );

        if ($changes['before'] === []) {
            return;
        }

        unset(
            $changes['before']['updated_at'],
            $changes['after']['updated_at'],
            $changes['before']['created_at'],
            $changes['after']['created_at']
        );

        if ($changes['before'] === []) {
            return;
        }

        ActivityLogger::log(
            'money_source.updated',
            (int) $moneySource->company_id,
            'Money source updated: '.$moneySource->name,
            $changes,
            $moneySource
        );
    }

    public function deleted(MoneySource $moneySource): void
    {
        ActivityLogger::log(
            'money_source.deleted',
            (int) $moneySource->company_id,
            'Money source deleted: '.$moneySource->name,
            [
                'name' => $moneySource->name,
                'type' => $moneySource->type,
            ],
            $moneySource
        );
    }
}
