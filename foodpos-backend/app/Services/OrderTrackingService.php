<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Models\User;
use Carbon\Carbon;
use App\Support\OrderWorkflow;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class OrderTrackingService
{
    public const DEFAULT_PREP_MINUTES = 10;

    /**
     * @return list<string>
     */
    public function allowedNextStatuses(string $currentStatus, ?string $orderType = null): array
    {
        return OrderWorkflow::allowedNextStatuses($currentStatus, $orderType);
    }

    public function canTransition(string $fromStatus, string $toStatus, ?string $orderType = null): bool
    {
        return OrderWorkflow::canTransition($fromStatus, $toStatus, $orderType);
    }

    public function changeStatus(
        Order $order,
        string $toStatus,
        ?User $user = null,
        string $source = 'pos',
        ?string $notes = null,
        bool $recalculateEta = true
    ): Order {
        $fromStatus = (string) $order->status;

        if ($fromStatus === $toStatus) {
            return $order;
        }

        if (! $this->canTransition($fromStatus, $toStatus, (string) $order->type)) {
            throw new InvalidArgumentException(
                "Cannot change order status from {$fromStatus} to {$toStatus}."
            );
        }

        $order->status = $toStatus;

        if ($recalculateEta && in_array($toStatus, ['placed', 'preparing'], true)) {
            $order->expected_ready_at = $this->calculateExpectedReadyAt($order);
        }

        $order->save();

        OrderStatusLog::create([
            'order_id' => $order->id,
            'company_id' => $order->company_id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'changed_by' => $user?->id,
            'source' => $source,
            'notes' => $notes,
            'changed_at' => now(),
        ]);

        return $order->fresh();
    }

    /**
     * After kitchen print: mark placed and set ETA when still an open tab.
     */
    public function markPlacedFromKitchen(Order $order, ?User $user = null): Order
    {
        if ($order->status !== 'open') {
            if (in_array($order->status, ['placed', 'preparing'], true) && ! $order->expected_ready_at) {
                $order->expected_ready_at = $this->calculateExpectedReadyAt($order);
                $order->save();
            }

            return $order;
        }

        return $this->changeStatus($order, 'placed', $user, 'pos_kitchen', 'Sent to kitchen');
    }

    public function calculateExpectedReadyAt(Order $order, ?Carbon $from = null): Carbon
    {
        $minutes = $this->estimatePreparationMinutes($order);

        return ($from ?? now())->copy()->addMinutes($minutes);
    }

    public function estimatePreparationMinutes(Order $order): int
    {
        $order->loadMissing(['items']);

        $menuItemIds = $order->items
            ->pluck('menu_item_id')
            ->filter()
            ->unique()
            ->values();

        if ($menuItemIds->isEmpty()) {
            return self::DEFAULT_PREP_MINUTES;
        }

        $prepTimes = MenuItem::withoutGlobalScope('tenant')
            ->where('company_id', $order->company_id)
            ->whereIn('id', $menuItemIds)
            ->pluck('preparation_time')
            ->map(fn ($v) => is_numeric($v) ? (int) $v : 0)
            ->filter(fn ($v) => $v > 0);

        if ($prepTimes->isEmpty()) {
            return self::DEFAULT_PREP_MINUTES;
        }

        return (int) $prepTimes->max();
    }

    /**
     * @return Collection<int, OrderStatusLog>
     */
    public function logsForOrder(Order $order): Collection
    {
        return OrderStatusLog::query()
            ->where('order_id', $order->id)
            ->orderBy('changed_at')
            ->orderBy('id')
            ->get();
    }
}
