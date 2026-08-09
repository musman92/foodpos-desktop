<?php

namespace App\Support;

final class PartyBalance
{
    public static function customerCreditAvailable(float $balance): float
    {
        return round(max(0, -$balance), 2);
    }

    public static function supplierPrepaymentAvailable(float $balance): float
    {
        return round(max(0, -$balance), 2);
    }

    public static function customerStatusLabel(float $balance): string
    {
        if ($balance > 0.001) {
            return 'Pending';
        }

        if ($balance < -0.001) {
            return 'Advance / credit';
        }

        return 'Settled';
    }

    public static function supplierStatusLabel(float $balance): string
    {
        if ($balance > 0.001) {
            return 'Pending';
        }

        if ($balance < -0.001) {
            return 'Prepaid';
        }

        return 'Settled';
    }

    public static function customerOpeningHint(): string
    {
        return 'Positive = customer owes you. Negative = you owe the customer (advance/credit).';
    }

    public static function supplierOpeningHint(): string
    {
        return 'Positive = you owe the supplier. Negative = you prepaid the supplier.';
    }

    public static function employeeOpeningHint(): string
    {
        return 'Positive = you owe the employee (payable). Negative = employee advance already paid.';
    }

    public static function customerOwedAmount(float $balance): float
    {
        return round(max(0, $balance), 2);
    }

    public static function supplierOwedAmount(float $balance): float
    {
        return round(max(0, $balance), 2);
    }
}
