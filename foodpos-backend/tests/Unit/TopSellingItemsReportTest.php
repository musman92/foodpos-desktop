<?php

namespace Tests\Unit;

use App\Support\TopSellingItemsReport;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TopSellingItemsReportTest extends TestCase
{
    #[DataProvider('displayLabelCases')]
    public function test_display_label_includes_variant_option(string $itemName, ?array $variants, string $expected): void
    {
        $this->assertSame($expected, TopSellingItemsReport::displayLabel($itemName, $variants));
    }

    /**
     * @return array<string, array{0: string, 1: ?array<string, mixed>, 2: string}>
     */
    public static function displayLabelCases(): array
    {
        return [
            'no variant' => ['Pizza A', null, 'Pizza A'],
            'size option' => ['Pizza A', ['variant_name' => 'Size', 'option_name' => 'Large'], 'Pizza A Large'],
            'small option' => ['Pizza A', ['variant_name' => 'Size', 'option_name' => 'Small'], 'Pizza A Small'],
        ];
    }

    public function test_aggregate_returns_parent_totals_with_variant_children(): void
    {
        $result = TopSellingItemsReport::aggregateRows($this->sampleRows(), 10);

        $pizza = $result->firstWhere('item_name', 'Chicken Fajita Pizza');
        $this->assertNotNull($pizza);
        $this->assertSame(35.0, $pizza->total_quantity);
        $this->assertSame(2900.0, $pizza->total_revenue);
        $this->assertCount(3, $pizza->variants);
        $this->assertSame('Large', $pizza->variants[0]->label);
        $this->assertSame(20.0, $pizza->variants[0]->total_quantity);
        $this->assertSame('Small', $pizza->variants[1]->label);
        $this->assertSame(10.0, $pizza->variants[1]->total_quantity);
        $this->assertSame('Medium', $pizza->variants[2]->label);
        $this->assertSame(5.0, $pizza->variants[2]->total_quantity);

        $fries = $result->firstWhere('item_name', 'French Fries');
        $this->assertNotNull($fries);
        $this->assertSame(50.0, $fries->total_quantity);
        $this->assertTrue($fries->variants->isEmpty());
    }

    public function test_limit_applies_to_parent_items_not_variant_rows(): void
    {
        $result = TopSellingItemsReport::aggregateRows(collect([
            $this->row('Item A', 1, ['option_name' => 'Large'], 5, 100),
            $this->row('Item A', 1, ['option_name' => 'Small'], 5, 100),
            $this->row('Item B', 2, null, 8, 200),
            $this->row('Item C', 3, null, 6, 150),
        ]), 2);

        $this->assertCount(2, $result);
        $this->assertSame('Item A', $result[0]->item_name);
        $this->assertSame(10.0, $result[0]->total_quantity);
        $this->assertCount(2, $result[0]->variants);
        $this->assertSame('Item B', $result[1]->item_name);
    }

    public function test_standard_child_row_when_some_sales_have_no_variant(): void
    {
        $result = TopSellingItemsReport::aggregateRows(collect([
            $this->row('Pizza A', 1, ['option_name' => 'Large'], 20, 2000),
            $this->row('Pizza A', 1, null, 2, 200),
        ]), 10);

        $pizza = $result->first();
        $this->assertSame(22.0, $pizza->total_quantity);
        $this->assertCount(2, $pizza->variants);
        $this->assertSame('Large', $pizza->variants[0]->label);
        $this->assertSame('Standard', $pizza->variants[1]->label);
    }

    /**
     * @return Collection<int, object>
     */
    private function sampleRows(): Collection
    {
        return collect([
            $this->row('Chicken Fajita Pizza', 1, ['option_name' => 'Large'], 12, 1200),
            $this->row('Chicken Fajita Pizza', 1, ['option_name' => 'Large'], 8, 800),
            $this->row('Chicken Fajita Pizza', 1, ['option_name' => 'Small'], 10, 500),
            $this->row('Chicken Fajita Pizza', 1, ['option_name' => 'Medium'], 5, 400),
            $this->row('French Fries', 2, null, 50, 2500),
            $this->row('Burger', 3, null, 30, 3000),
        ]);
    }

    /**
     * @param  ?array<string, mixed>  $variants
     */
    private function row(string $name, int $menuItemId, ?array $variants, float $qty, float $revenue): object
    {
        return (object) [
            'item_name' => $name,
            'menu_item_id' => $menuItemId,
            'deal_id' => null,
            'variants' => $variants,
            'quantity' => $qty,
            'total_price' => $revenue,
        ];
    }
}
