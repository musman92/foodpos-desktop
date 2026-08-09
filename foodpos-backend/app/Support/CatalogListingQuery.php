<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

class CatalogListingQuery
{
    public static function searchFromRequest(Request $request): string
    {
        return trim((string) $request->input('search', ''));
    }

    public static function escapeLike(string $term): string
    {
        return addcslashes($term, '%_\\');
    }

    /**
     * @param  list<string>  $columns
     */
    public static function applySearch(Builder|Relation $query, string $search, array $columns): void
    {
        if ($search === '') {
            return;
        }

        $like = '%'.self::escapeLike($search).'%';

        $query->where(function (Builder $builder) use ($columns, $like) {
            foreach ($columns as $column) {
                $builder->orWhere($column, 'like', $like);
            }
        });
    }
}
