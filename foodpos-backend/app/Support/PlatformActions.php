<?php

namespace App\Support;

class PlatformActions
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return config('platform_actions.actions', []);
    }

    /**
     * @return array<string, array<string, mixed>>|null
     */
    public static function find(string $key): ?array
    {
        $action = self::all()[$key] ?? null;

        return is_array($action) ? $action : null;
    }

    /**
     * Actions grouped by section for the index page.
     *
     * @return array<string, list<array{key: string} & array<string, mixed>>>
     */
    public static function grouped(): array
    {
        $groupLabels = config('platform_actions.groups', []);
        $grouped = [];

        foreach ($groupLabels as $groupKey => $label) {
            $grouped[$groupKey] = [
                'label' => $label,
                'actions' => [],
            ];
        }

        foreach (self::all() as $key => $action) {
            $groupKey = $action['group'] ?? 'other';

            if (! isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [
                    'label' => ucfirst(str_replace('_', ' ', $groupKey)),
                    'actions' => [],
                ];
            }

            $grouped[$groupKey]['actions'][] = array_merge($action, ['key' => $key]);
        }

        return array_filter($grouped, fn (array $group) => $group['actions'] !== []);
    }
}
