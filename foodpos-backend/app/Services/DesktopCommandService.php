<?php

namespace App\Services;

use App\Events\DesktopCommandRequested;
use App\Models\BranchDesktopKey;
use Illuminate\Support\Facades\Cache;

class DesktopCommandService
{
    private function cacheKey(int $desktopKeyId): string
    {
        return "desktop_key_command:{$desktopKeyId}";
    }

    public function queue(BranchDesktopKey $key, string $command): void
    {
        Cache::put($this->cacheKey($key->id), [
            'type' => $command,
            'requested_at' => now()->toIso8601String(),
        ], now()->addMinutes(2));

        DesktopCommandRequested::dispatch($key->branch_id, $key->id, $command);
    }

    /**
     * @return list<string>
     */
    public function pullPending(int $desktopKeyId): array
    {
        $data = Cache::pull($this->cacheKey($desktopKeyId));

        if (! is_array($data) || empty($data['type'])) {
            return [];
        }

        return [(string) $data['type']];
    }
}
