<?php

namespace App\Support;

class OrderWorkflow
{
    public const OUT_FOR_DELIVERY = 'out_for_delivery';

    public const DELIVERED = 'delivered';

    /**
     * Dine-in / takeaway: ready → served.
     *
     * @var array<string, list<string>>
     */
    private const POS_TRANSITIONS_DEFAULT = [
        'open' => ['placed'],
        'placed' => ['preparing', 'ready'],
        'preparing' => ['ready'],
        'ready' => ['served'],
        'served' => [],
    ];

    /**
     * Delivery: ready → on the way → delivered.
     *
     * @var array<string, list<string>>
     */
    private const POS_TRANSITIONS_DELIVERY = [
        'open' => ['placed'],
        'placed' => ['preparing', 'ready'],
        'preparing' => ['ready'],
        'ready' => [self::OUT_FOR_DELIVERY],
        self::OUT_FOR_DELIVERY => [self::DELIVERED],
        self::DELIVERED => [],
    ];

    /**
     * @return array<string, list<string>>
     */
    public static function posTransitions(?string $orderType): array
    {
        return $orderType === 'delivery'
            ? self::POS_TRANSITIONS_DELIVERY
            : self::POS_TRANSITIONS_DEFAULT;
    }

    /**
     * @return list<string>
     */
    public static function allowedNextStatuses(string $currentStatus, ?string $orderType = null): array
    {
        return self::posTransitions($orderType)[$currentStatus] ?? [];
    }

    public static function canTransition(string $fromStatus, string $toStatus, ?string $orderType = null): bool
    {
        return in_array($toStatus, self::allowedNextStatuses($fromStatus, $orderType), true);
    }

    /**
     * Human label for POS, customer app, and order tracking UI.
     */
    public static function label(string $status, ?string $orderType = null): string
    {
        $shared = [
            'open' => 'Serving',
            'placed' => 'Sent to kitchen',
            'pending' => 'Pending',
            'confirmed' => 'Confirmed',
            'preparing' => 'Preparing',
            'ready' => 'Ready',
            'completed' => 'Done',
            'cancelled' => 'Cancelled',
        ];

        if ($orderType === 'delivery') {
            return match ($status) {
                self::OUT_FOR_DELIVERY => 'On the way',
                self::DELIVERED => 'Delivered',
                'served' => 'Delivered',
                default => $shared[$status] ?? ucfirst(str_replace('_', ' ', $status)),
            };
        }

        return match ($status) {
            'served' => $orderType === 'takeaway' ? 'Picked up' : 'Served',
            self::OUT_FOR_DELIVERY => 'On the way',
            self::DELIVERED => 'Delivered',
            default => $shared[$status] ?? ucfirst(str_replace('_', ' ', $status)),
        };
    }

    /**
     * Unpaid POS tabs that staff may still work on.
     *
     * @return list<string>
     */
    public static function activePosTabStatuses(?string $orderType = null): array
    {
        $base = ['open', 'placed', 'preparing', 'ready'];

        if ($orderType === 'delivery') {
            return array_values(array_unique([...$base, self::OUT_FOR_DELIVERY, self::DELIVERED]));
        }

        return array_values(array_unique([...$base, 'served']));
    }

    /**
     * Orders still waiting on the kitchen (not ready, served, or completed).
     *
     * @return list<string>
     */
    public static function kitchenQueueOrderStatuses(): array
    {
        return ['placed', 'preparing'];
    }

    /**
     * All in-progress statuses across order types (POS guards, queues).
     *
     * @return list<string>
     */
    public static function allActivePosTabStatuses(): array
    {
        return array_values(array_unique([
            ...self::activePosTabStatuses('dine_in'),
            ...self::activePosTabStatuses('delivery'),
        ]));
    }
}
