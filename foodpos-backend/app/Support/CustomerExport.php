<?php

namespace App\Support;

use App\Models\Customer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerExport
{
    public function download(string $format): StreamedResponse
    {
        $rows = Customer::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Customer $customer) => [
                $customer->code ?? '',
                $customer->name,
                $customer->email ?? '',
                $customer->phone ?? '',
                $customer->date_of_birth?->format('Y-m-d') ?? '',
                $customer->gender ?? '',
                $this->formatNumber($customer->balance),
                $customer->notes ?? '',
                $customer->is_active ? 'yes' : 'no',
            ])
            ->all();

        return (new SpreadsheetTabularExport)->download(
            $format,
            'customers',
            'Customers',
            CustomerImportSampleExport::HEADERS,
            $rows,
        );
    }

    private function formatNumber(mixed $value): float|int|string
    {
        $number = (float) $value;

        if (fmod($number, 1.0) === 0.0) {
            return (int) $number;
        }

        return $number;
    }
}
