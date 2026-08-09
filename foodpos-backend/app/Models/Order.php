<?php

namespace App\Models;

use App\Traits\HasTenantAndBranch;
use App\Traits\StampsBusinessDate;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory, HasTenantAndBranch, SoftDeletes, StampsBusinessDate;

    protected $fillable = [
        'company_id',
        'branch_id',
        'table_id',
        'cashier_id',
        'shift_id',
        'waiter_id',
        'delivery_rider_id',
        'customer_id',
        'order_number',
        'type',
        'status',
        'payment_status',
        'payment_method',
        'money_source_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'customer_address',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'discount_type',
        'discount_value',
        'service_charge',
        'delivery_fee',
        'total_amount',
        'paid_amount',
        'paid_at_sale',
        'coupon_id',
        'notes',
        'management_notes',
        'kitchen_cart_snapshot',
        'completed_at',
        'expected_ready_at',
        'business_date',
    ];

    public function outstandingAmount(): float
    {
        return round(max(0, (float) $this->total_amount - (float) ($this->paid_amount ?? 0)), 2);
    }

    public function scopeWithOutstandingPayment(Builder $query): Builder
    {
        return $query->where('payment_status', 'partial')
            ->whereColumn('paid_amount', '<', 'total_amount');
    }

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'service_charge' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'paid_at_sale' => 'decimal:2',
        'completed_at' => 'datetime',
        'expected_ready_at' => 'datetime',
        'kitchen_cart_snapshot' => 'array',
        'business_date' => 'date',
    ];

    /**
     * Get the company that owns this order.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the branch.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the table (if dine-in).
     */
    public function table()
    {
        return $this->belongsTo(Table::class);
    }

    /**
     * Get the cashier.
     */
    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function waiter()
    {
        return $this->belongsTo(User::class, 'waiter_id');
    }

    public function deliveryRider()
    {
        return $this->belongsTo(User::class, 'delivery_rider_id');
    }

    /**
     * Registered customer (credit sales).
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Best timestamp for list display / “when did this order land?” (not tab opened_at).
     */
    public function listSortAt(): Carbon
    {
        return $this->completed_at ?? $this->updated_at ?? $this->created_at;
    }

    /**
     * Default list sort: newest orders first (by id — reliable even when created_at was backfilled).
     */
    public function scopeNewestFirst(Builder $query): Builder
    {
        return $query->orderByDesc($query->getModel()->getTable().'.id');
    }

    /**
     * Get all order items.
     */
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the coupon (if applied).
     */
    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * Get the money source used for this order.
     */
    public function moneySource()
    {
        return $this->belongsTo(MoneySource::class);
    }

    public function payments()
    {
        return $this->hasMany(OrderPayment::class)->orderBy('sort_order');
    }

    /**
     * Kitchen order tickets (KOT slips) for this order.
     */
    public function kitchenKots()
    {
        return $this->hasMany(KitchenKot::class)->orderBy('kot_number');
    }

    public function statusLogs()
    {
        return $this->hasMany(OrderStatusLog::class)->orderBy('changed_at')->orderBy('id');
    }

    /**
     * Get kitchen display orders.
     */
    public function kitchenDisplayOrders()
    {
        return $this->hasMany(KitchenDisplayOrder::class);
    }

    /**
     * Refund batches applied to this order.
     */
    public function refunds()
    {
        return $this->hasMany(OrderRefund::class)->orderByDesc('created_at');
    }

    /**
     * Allocate a unique order number for a branch on the current business day.
     *
     * Uses a locked counter, then suffix fallbacks (-01, -02, …, random) if needed.
     * Must be called inside an open DB transaction.
     */
    public static function allocateOrderNumber(int $branchId): string
    {
        for ($attempt = 0; $attempt < 25; $attempt++) {
            $candidate = static::nextOrderNumberFromCounter($branchId);
            if (! static::orderNumberExists($candidate)) {
                return $candidate;
            }
        }

        return static::allocateOrderNumberWithSuffix($branchId);
    }

    /**
     * @deprecated Use allocateOrderNumber()
     */
    public static function generateOrderNumber(int $branchId): string
    {
        return static::allocateOrderNumber($branchId);
    }

    public static function orderNumberExists(string $orderNumber): bool
    {
        return static::withoutGlobalScopes(['tenant', 'branch'])
            ->withTrashed()
            ->where('order_number', $orderNumber)
            ->exists();
    }

    public static function isDuplicateKeyException(QueryException $exception): bool
    {
        $code = (string) $exception->getCode();

        return str_contains($code, '23000') || str_contains($exception->getMessage(), 'Duplicate entry');
    }

    protected static function nextOrderNumberFromCounter(int $branchId): string
    {
        [$prefix, $businessDate, $dateKey] = static::orderNumberContext($branchId);

        $counter = DB::table('branch_order_counters')
            ->where('branch_id', $branchId)
            ->where('business_date', $businessDate)
            ->lockForUpdate()
            ->first();

        if (! $counter) {
            $startSeq = static::maxSequenceForBranchDate($branchId, $prefix, $dateKey);

            try {
                DB::table('branch_order_counters')->insert([
                    'branch_id' => $branchId,
                    'business_date' => $businessDate,
                    'last_order_number' => $startSeq,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (QueryException $e) {
                if (! static::isDuplicateKeyException($e)) {
                    throw $e;
                }
            }

            $counter = DB::table('branch_order_counters')
                ->where('branch_id', $branchId)
                ->where('business_date', $businessDate)
                ->lockForUpdate()
                ->first();
        }

        if (! $counter) {
            $sequence = static::maxSequenceForBranchDate($branchId, $prefix, $dateKey) + 1;

            return static::formatOrderNumber($prefix, $dateKey, $sequence);
        }

        $sequence = (int) $counter->last_order_number + 1;

        DB::table('branch_order_counters')
            ->where('id', $counter->id)
            ->update([
                'last_order_number' => $sequence,
                'updated_at' => now(),
            ]);

        return static::formatOrderNumber($prefix, $dateKey, $sequence);
    }

    protected static function allocateOrderNumberWithSuffix(int $branchId): string
    {
        [$prefix, $businessDate, $dateKey] = static::orderNumberContext($branchId);
        $sequence = max(1, static::maxSequenceForBranchDate($branchId, $prefix, $dateKey));
        $base = static::formatOrderNumber($prefix, $dateKey, $sequence);

        static::syncCounterSequence($branchId, $businessDate, $sequence);

        for ($n = 1; $n <= 99; $n++) {
            $candidate = sprintf('%s-%02d', $base, $n);
            if (! static::orderNumberExists($candidate)) {
                return $candidate;
            }
        }

        do {
            $candidate = $base.'-'.strtolower(Str::random(3));
        } while (static::orderNumberExists($candidate));

        return $candidate;
    }

    /**
     * @return array{0: string, 1: string, 2: string} [prefix, Y-m-d, Ymd]
     */
    protected static function orderNumberContext(int $branchId): array
    {
        $branch = Branch::withoutGlobalScopes(['tenant', 'branch'])->find($branchId);
        $prefix = $branch ? $branch->code : 'ORD';
        [$businessDate, $dateKey] = tz()->businessDateParts($branchId);

        return [$prefix, $businessDate, $dateKey];
    }

    protected static function formatOrderNumber(string $prefix, string $dateKey, int $sequence): string
    {
        return sprintf('%s-%s-%04d', $prefix, $dateKey, $sequence);
    }

    protected static function syncCounterSequence(int $branchId, string $businessDate, int $sequence): void
    {
        $updated = DB::table('branch_order_counters')
            ->where('branch_id', $branchId)
            ->where('business_date', $businessDate)
            ->update([
                'last_order_number' => $sequence,
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            try {
                DB::table('branch_order_counters')->insert([
                    'branch_id' => $branchId,
                    'business_date' => $businessDate,
                    'last_order_number' => $sequence,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (QueryException $e) {
                if (! static::isDuplicateKeyException($e)) {
                    throw $e;
                }
            }
        }
    }

    protected static function maxSequenceForBranchDate(int $branchId, string $prefix, string $dateKey): int
    {
        $pattern = $prefix.'-'.$dateKey.'-%';

        $orderNumbers = static::withoutGlobalScopes(['tenant', 'branch'])
            ->withTrashed()
            ->where('branch_id', $branchId)
            ->where('order_number', 'like', $pattern)
            ->pluck('order_number');

        $max = 0;
        $dateSegment = preg_quote($dateKey, '/');

        foreach ($orderNumbers as $orderNumber) {
            if (preg_match('/-'.$dateSegment.'-(\d{4})/', $orderNumber, $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return $max;
    }

    /**
     * Release the order number for reuse by appending -d01, -d02, … before soft delete.
     */
    public function archiveOrderNumber(): void
    {
        if (preg_match('/-d\d+$/', (string) $this->order_number)) {
            return;
        }

        $base = (string) $this->order_number;
        $suffix = 1;

        while (true) {
            $candidate = sprintf('%s-d%02d', $base, $suffix);
            $exists = static::withoutGlobalScopes(['tenant', 'branch'])
                ->withTrashed()
                ->where('order_number', $candidate)
                ->where('id', '!=', $this->id)
                ->exists();

            if (! $exists) {
                $this->forceFill(['order_number' => $candidate])->saveQuietly();

                return;
            }

            $suffix++;
        }
    }
}
