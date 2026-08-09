<?php

namespace App\Models;

use App\Traits\HasTenantAndBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupplierPayment extends Model
{
    use HasFactory, HasTenantAndBranch, SoftDeletes;

    public const KIND_PAYMENT = 'payment';

    public const KIND_ADVANCE = 'advance';

    protected $fillable = [
        'company_id',
        'branch_id',
        'supplier_id',
        'account_id',
        'money_source_id',
        'created_by',
        'payment_number',
        'kind',
        'payment_date',
        'total_amount',
        'payment_method',
        'notes',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function moneySource()
    {
        return $this->belongsTo(MoneySource::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function purchases()
    {
        return $this->belongsToMany(Purchase::class, 'supplier_payment_purchase')
            ->withPivot('amount')
            ->withTimestamps();
    }

    /**
     * Allocate a unique supplier payment number for a branch on the current business day.
     * Must be called inside an open DB transaction.
     */
    public static function allocatePaymentNumber(?int $branchId = null): string
    {
        for ($attempt = 0; $attempt < 25; $attempt++) {
            $candidate = static::nextPaymentNumberFromCounter($branchId);
            if (! static::paymentNumberExists($candidate)) {
                return $candidate;
            }
        }

        return static::allocatePaymentNumberWithSuffix($branchId);
    }

    /**
     * @deprecated Use allocatePaymentNumber()
     */
    public static function generatePaymentNumber(?int $branchId = null): string
    {
        return static::allocatePaymentNumber($branchId);
    }

    public static function paymentNumberExists(string $paymentNumber): bool
    {
        return static::withoutGlobalScopes(['tenant', 'branch'])
            ->withTrashed()
            ->where('payment_number', $paymentNumber)
            ->exists();
    }

    public static function isDuplicateKeyException(QueryException $exception): bool
    {
        $code = (string) $exception->getCode();

        return str_contains($code, '23000') || str_contains($exception->getMessage(), 'Duplicate entry');
    }

    protected static function nextPaymentNumberFromCounter(?int $branchId): string
    {
        [$prefix, $businessDate, $dateKey] = static::paymentNumberContext($branchId);

        $counterQuery = DB::table('branch_supplier_payment_counters')
            ->where('business_date', $businessDate);

        if ($branchId) {
            $counterQuery->where('branch_id', $branchId);
        } else {
            $counterQuery->whereNull('branch_id');
        }

        $counter = (clone $counterQuery)->lockForUpdate()->first();

        if (! $counter) {
            $startSeq = static::maxSequenceForBranchDate($branchId, $prefix, $dateKey);

            try {
                DB::table('branch_supplier_payment_counters')->insert([
                    'branch_id' => $branchId,
                    'business_date' => $businessDate,
                    'last_payment_number' => $startSeq,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (QueryException $e) {
                if (! static::isDuplicateKeyException($e)) {
                    throw $e;
                }
            }

            $counter = (clone $counterQuery)->lockForUpdate()->first();
        }

        if (! $counter) {
            $sequence = static::maxSequenceForBranchDate($branchId, $prefix, $dateKey) + 1;

            return static::formatPaymentNumber($prefix, $dateKey, $sequence);
        }

        $sequence = (int) $counter->last_payment_number + 1;

        DB::table('branch_supplier_payment_counters')
            ->where('id', $counter->id)
            ->update([
                'last_payment_number' => $sequence,
                'updated_at' => now(),
            ]);

        return static::formatPaymentNumber($prefix, $dateKey, $sequence);
    }

    protected static function allocatePaymentNumberWithSuffix(?int $branchId): string
    {
        [$prefix, $businessDate, $dateKey] = static::paymentNumberContext($branchId);
        $sequence = max(1, static::maxSequenceForBranchDate($branchId, $prefix, $dateKey));
        $base = static::formatPaymentNumber($prefix, $dateKey, $sequence);

        static::syncCounterSequence($branchId, $businessDate, $sequence);

        for ($n = 1; $n <= 99; $n++) {
            $candidate = sprintf('%s-%02d', $base, $n);
            if (! static::paymentNumberExists($candidate)) {
                return $candidate;
            }
        }

        do {
            $candidate = $base.'-'.strtolower(Str::random(3));
        } while (static::paymentNumberExists($candidate));

        return $candidate;
    }

    /**
     * @return array{0: string, 1: string, 2: string} [prefix, Y-m-d, Ymd]
     */
    protected static function paymentNumberContext(?int $branchId): array
    {
        $branch = $branchId
            ? Branch::withoutGlobalScopes(['tenant', 'branch'])->find($branchId)
            : null;
        $prefix = $branch ? ($branch->code ?? 'SP') : 'SP';
        [$businessDate, $dateKey] = tz()->businessDateParts($branchId);

        return [$prefix, $businessDate, $dateKey];
    }

    protected static function formatPaymentNumber(string $prefix, string $dateKey, int $sequence): string
    {
        return sprintf('%s-%s-%04d', $prefix, $dateKey, $sequence);
    }

    protected static function syncCounterSequence(?int $branchId, string $businessDate, int $sequence): void
    {
        $query = DB::table('branch_supplier_payment_counters')
            ->where('business_date', $businessDate);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        } else {
            $query->whereNull('branch_id');
        }

        $updated = $query->update([
            'last_payment_number' => $sequence,
            'updated_at' => now(),
        ]);

        if ($updated === 0) {
            try {
                DB::table('branch_supplier_payment_counters')->insert([
                    'branch_id' => $branchId,
                    'business_date' => $businessDate,
                    'last_payment_number' => $sequence,
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

    protected static function maxSequenceForBranchDate(?int $branchId, string $prefix, string $dateKey): int
    {
        $pattern = $prefix.'-'.$dateKey.'-%';

        $query = static::withoutGlobalScopes(['tenant', 'branch'])
            ->withTrashed()
            ->where('payment_number', 'like', $pattern);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        } else {
            $query->whereNull('branch_id');
        }

        $paymentNumbers = $query->pluck('payment_number');

        $max = 0;
        $dateSegment = preg_quote($dateKey, '/');

        foreach ($paymentNumbers as $paymentNumber) {
            if (preg_match('/-'.$dateSegment.'-(\d{4})/', $paymentNumber, $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return $max;
    }
}
