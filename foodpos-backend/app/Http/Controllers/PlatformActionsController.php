<?php

namespace App\Http\Controllers;

use App\Support\PlatformActions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PlatformActionsController extends Controller
{
    public function index(): View
    {
        $this->authorizeSuperAdmin();

        return view('platform-actions.index', [
            'groups' => PlatformActions::grouped(),
        ]);
    }

    public function run(Request $request, string $action): RedirectResponse
    {
        $this->authorizeSuperAdmin();

        $definition = PlatformActions::find($action);
        abort_unless($definition, 404);

        $options = $this->buildCommandOptions($definition, $request);

        try {
            $exitCode = Artisan::call($definition['command'], $options);
            $output = trim(Artisan::output());
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('platform-actions.index')
                ->with('action_result', [
                    'key' => $action,
                    'title' => $definition['title'],
                    'command' => $this->formatCommandLabel($definition['command'], $options),
                    'success' => false,
                    'output' => $e->getMessage(),
                ]);
        }

        return redirect()
            ->route('platform-actions.index')
            ->with('action_result', [
                'key' => $action,
                'title' => $definition['title'],
                'command' => $this->formatCommandLabel($definition['command'], $options),
                'success' => $exitCode === 0,
                'output' => $output !== '' ? $output : ($exitCode === 0 ? 'Command completed successfully.' : 'Command finished with errors.'),
            ]);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function buildCommandOptions(array $definition, Request $request): array
    {
        $options = $definition['options'] ?? [];

        foreach ($definition['inputs'] ?? [] as $input) {
            $flag = $input['flag'] ?? null;
            if ($flag === null) {
                continue;
            }

            if (($input['type'] ?? '') === 'checkbox') {
                if ($request->boolean($input['name'])) {
                    $options[$flag] = true;
                }

                continue;
            }

            if (($input['type'] ?? '') === 'text' && $request->filled($input['name'])) {
                $options[$flag] = $request->string($input['name'])->toString();
            }
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function formatCommandLabel(string $command, array $options): string
    {
        $parts = ['php artisan', $command];

        foreach ($options as $key => $value) {
            if ($value === false || $value === null || $value === '') {
                continue;
            }

            if ($value === true) {
                $parts[] = $key;

                continue;
            }

            $parts[] = "{$key}={$value}";
        }

        return implode(' ', $parts);
    }

    private function authorizeSuperAdmin(): void
    {
        abort_unless(Auth::user()?->isSuperAdmin(), 403, 'Only platform administrators can run system actions.');
    }
}
