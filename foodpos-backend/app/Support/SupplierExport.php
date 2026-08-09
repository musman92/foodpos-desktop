<?php

namespace App\Support;

use App\Models\Supplier;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierExport
{
    public function download(string $format): StreamedResponse
    {
        $rows = Supplier::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Supplier $supplier) => [
                $supplier->code ?? '',
                $supplier->name,
                $supplier->contact_person ?? '',
                $supplier->email ?? '',
                $supplier->phone ?? '',
                $supplier->whatsapp ?? '',
                $supplier->address ?? '',
                $supplier->tax_id ?? '',
                $this->formatNumber($supplier->balance),
                $supplier->notes ?? '',
                $supplier->status === 'inactive' ? 'inactive' : 'active',
            ])
            ->all();

        return (new SpreadsheetTabularExport)->download(
            $format,
            'suppliers',
            'Suppliers',
            SupplierImportSampleExport::HEADERS,
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
