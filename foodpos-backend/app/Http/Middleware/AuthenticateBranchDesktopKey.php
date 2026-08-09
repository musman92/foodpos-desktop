<?php

namespace App\Http\Middleware;

use App\Models\BranchDesktopKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateBranchDesktopKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainKey = (string) $request->header('X-Branch-Key', '');

        $desktopKey = BranchDesktopKey::findByPlainKey($plainKey);

        if (! $desktopKey) {
            return response()->json(['message' => 'Invalid branch key.'], 401);
        }

        $desktopKey->forceFill(['last_used_at' => now()])->save();

        $request->attributes->set('branch_desktop_key', $desktopKey);
        $request->attributes->set('desktop_key_id', (int) $desktopKey->id);
        $request->attributes->set('desktop_branch_id', (int) $desktopKey->branch_id);
        $request->attributes->set('desktop_company_id', (int) $desktopKey->company_id);

        return $next($request);
    }
}
