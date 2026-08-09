<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class IngredientImportReferences
{
    /**
     * Normalize spreadsheet cell values for codes (Excel may store C24 as 24).
     */
    public static function normalizeCode(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_int($value) || is_float($value)) {
            if (fmod((float) $value, 1.0) === 0.0) {
                return (string) (int) $value;
            }

            return trim((string) $value);
        }

        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (is_numeric($value)) {
            $float = (float) $value;

            if (fmod($float, 1.0) === 0.0) {
                return (string) (int) $float;
            }
        }

        return $value;
    }

    /**
     * Normalize unit column values from spreadsheets (codes, ids, labels, Excel quirks).
     */
    public static function normalizeUnitReference(mixed $value): string
    {
        $value = self::normalizeCode($value);
        if ($value === '') {
            return '';
        }

        // "C20 — Gram", "C20 - Gram"
        if (preg_match('/^(.+?)\s+[-—–]\s+/u', $value, $matches)) {
            return self::normalizeCode(trim($matches[1]));
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    public static function codeCandidates(string $reference): array
    {
        $reference = self::normalizeCode($reference);
        if ($reference === '') {
            return [];
        }

        $candidates = [$reference];

        if (preg_match('/^\d+$/', $reference)) {
            $number = (int) $reference;
            $candidates[] = 'C'.$reference;
            $candidates[] = 'C'.str_pad((string) $number, 2, '0', STR_PAD_LEFT);
        }

        if (preg_match('/^c(\d+)$/i', $reference, $matches)) {
            $number = (int) $matches[1];
            $candidates[] = 'C'.$matches[1];
            $candidates[] = 'C'.str_pad((string) $number, 2, '0', STR_PAD_LEFT);
        }

        return array_values(array_unique($candidates));
    }

    public static function codesReferToSame(?string $left, ?string $right): bool
    {
        $left = self::normalizeCode((string) $left);
        $right = self::normalizeCode((string) $right);

        if ($left === '' || $right === '') {
            return false;
        }

        if (strcasecmp($left, $right) === 0) {
            return true;
        }

        $leftCandidates = array_map('strtolower', self::codeCandidates($left));
        $rightCandidates = array_map('strtolower', self::codeCandidates($right));

        return array_intersect($leftCandidates, $rightCandidates) !== [];
    }

    public static function restoreIfTrashed(Model $model): bool
    {
        if (in_array(SoftDeletes::class, class_uses_recursive($model), true)
            && method_exists($model, 'trashed')
            && $model->trashed()) {
            $model->restore();

            return true;
        }

        return false;
    }
}
