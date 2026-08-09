<?php

namespace Tests\Unit;

use App\Support\IngredientImportReferences;
use PHPUnit\Framework\TestCase;

class IngredientImportReferencesTest extends TestCase
{
    public function test_normalize_code_strips_excel_numeric_formatting(): void
    {
        $this->assertSame('24', IngredientImportReferences::normalizeCode(24));
        $this->assertSame('24', IngredientImportReferences::normalizeCode(24.0));
        $this->assertSame('24', IngredientImportReferences::normalizeCode('24.0'));
        $this->assertSame('C24', IngredientImportReferences::normalizeCode('C24'));
    }

    public function test_normalize_unit_reference_strips_display_label_suffix(): void
    {
        $this->assertSame('C20', IngredientImportReferences::normalizeUnitReference('C20 — Gram'));
        $this->assertSame('C20', IngredientImportReferences::normalizeUnitReference('C20 - Gram'));
        $this->assertSame('20', IngredientImportReferences::normalizeUnitReference(20));
    }

    public function test_code_candidates_include_padded_and_unpadded_variants(): void
    {
        $from24 = IngredientImportReferences::codeCandidates('24');
        $this->assertContains('24', $from24);
        $this->assertContains('C24', $from24);

        $from2 = IngredientImportReferences::codeCandidates('2');
        $this->assertContains('2', $from2);
        $this->assertContains('C2', $from2);
        $this->assertContains('C02', $from2);

        $fromC2 = IngredientImportReferences::codeCandidates('c2');
        $this->assertContains('C2', $fromC2);
        $this->assertContains('C02', $fromC2);
    }

    public function test_codes_refer_to_same_treats_excel_variants_as_equivalent(): void
    {
        $this->assertTrue(IngredientImportReferences::codesReferToSame('C02', '2'));
        $this->assertTrue(IngredientImportReferences::codesReferToSame('C24', '24'));
        $this->assertFalse(IngredientImportReferences::codesReferToSame('C02', 'C03'));
    }
}
