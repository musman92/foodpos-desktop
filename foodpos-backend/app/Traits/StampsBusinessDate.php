<?php

namespace App\Traits;

use App\Models\OrderItem;
use App\Models\Shift;
use App\Support\CurrentShift;
use Carbon\CarbonInterface;

trait StampsBusinessDate
{
    protected static function bootStampsBusinessDate(): void
    {
        static::creating(function ($model) {
            if (filled($model->business_date)) {
                $model->business_date = self::normalizeBusinessDate($model->business_date);

                return;
            }

            $model->business_date = $model->resolveBusinessDateForStamp();
        });
    }

    public function resolveBusinessDateForStamp(): ?string
    {
        $shiftId = $this->getAttribute('shift_id');
        if ($shiftId) {
            $shiftDate = Shift::query()->whereKey($shiftId)->value('shift_date');
            if ($shiftDate) {
                return self::normalizeBusinessDate($shiftDate);
            }
        }

        $fromOrder = $this->businessDateFromOrderReference();
        if ($fromOrder) {
            return $fromOrder;
        }

        $branchId = $this->getAttribute('branch_id');
        $branchId = $branchId !== null ? (int) $branchId : null;

        // Prefer active shift; leave null when none so reports can fall back to created_at
        // until backfill / an explicit business_date is supplied.
        return CurrentShift::businessDate($branchId);
    }

    protected function businessDateFromOrderReference(): ?string
    {
        $referenceType = $this->getAttribute('reference_type');
        $referenceId = $this->getAttribute('reference_id');
        if (! $referenceType || ! $referenceId) {
            return null;
        }

        if ($referenceType !== OrderItem::class && $referenceType !== 'App\\Models\\OrderItem') {
            return null;
        }

        $order = OrderItem::query()
            ->whereKey($referenceId)
            ->with('order:id,shift_id,business_date')
            ->first()
            ?->order;

        if (! $order) {
            return null;
        }

        if (filled($order->business_date)) {
            return self::normalizeBusinessDate($order->business_date);
        }

        if ($order->shift_id) {
            $shiftDate = Shift::query()->whereKey($order->shift_id)->value('shift_date');
            if ($shiftDate) {
                return self::normalizeBusinessDate($shiftDate);
            }
        }

        return null;
    }

    protected static function normalizeBusinessDate(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value) && $value !== '') {
            return substr($value, 0, 10);
        }

        return null;
    }
}
