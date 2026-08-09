<?php

namespace App\Services;

use App\Models\BranchDesktopKey;
use App\Models\Printer;

class PrinterVerificationService
{
    /**
     * @return array{
     *     ok: bool,
     *     message: string,
     *     match_type?: string,
     *     suggested_name?: string,
     *     available_printers?: list<array{name: string, source: string, is_default?: bool}>,
     *     needs_fetch?: bool,
     *     list_updated_at?: string|null,
     * }
     */
    public function verify(Printer $printer): array
    {
        if ($printer->printing_mode !== Printer::MODE_DIRECT) {
            return [
                'ok' => true,
                'message' => 'Browser popup printer — no OS printer name to verify.',
                'match_type' => 'browser',
            ];
        }

        $deviceName = trim((string) $printer->device_name);
        if ($deviceName === '') {
            return [
                'ok' => false,
                'message' => 'No OS printer name configured.',
                'match_type' => 'missing_config',
            ];
        }

        $catalog = $this->branchPrinterCatalog($printer->branch_id);

        if ($catalog === []) {
            return [
                'ok' => false,
                'message' => 'No printer list from the desktop app yet. Click Fetch printers on a connected desktop key, wait a few seconds, then verify again.',
                'match_type' => 'needs_fetch',
                'needs_fetch' => true,
            ];
        }

        $listUpdatedAt = $this->latestPrinterListTimestamp($printer->branch_id);

        foreach ($catalog as $entry) {
            if ($entry['name'] === $deviceName) {
                return [
                    'ok' => true,
                    'message' => "Verified — \"{$deviceName}\" exists on {$entry['source']}.",
                    'match_type' => 'exact',
                    'list_updated_at' => $listUpdatedAt,
                ];
            }
        }

        foreach ($catalog as $entry) {
            if (strcasecmp($entry['name'], $deviceName) === 0) {
                return [
                    'ok' => false,
                    'message' => "Name mismatch — desktop PC has \"{$entry['name']}\" but settings say \"{$deviceName}\". Letter case must match exactly.",
                    'match_type' => 'case',
                    'suggested_name' => $entry['name'],
                    'list_updated_at' => $listUpdatedAt,
                ];
            }
        }

        return [
            'ok' => false,
            'message' => "\"{$deviceName}\" was not found on any connected desktop PC for this branch.",
            'match_type' => 'missing',
            'available_printers' => $catalog,
            'list_updated_at' => $listUpdatedAt,
        ];
    }

    /**
     * @return list<array{name: string, source: string, is_default: bool}>
     */
    public function branchPrinterCatalog(int $branchId): array
    {
        $keys = BranchDesktopKey::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->whereNotNull('system_printers')
            ->orderByDesc('system_printers_at')
            ->get();

        $catalog = [];

        foreach ($keys as $key) {
            foreach ($key->system_printers ?? [] as $printer) {
                if (! is_array($printer) || trim((string) ($printer['name'] ?? '')) === '') {
                    continue;
                }

                $catalog[] = [
                    'name' => (string) $printer['name'],
                    'source' => $key->name,
                    'is_default' => (bool) ($printer['is_default'] ?? false),
                ];
            }
        }

        return $catalog;
    }

    protected function latestPrinterListTimestamp(int $branchId): ?string
    {
        $latest = BranchDesktopKey::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->max('system_printers_at');

        return $latest ? (string) $latest : null;
    }
}
