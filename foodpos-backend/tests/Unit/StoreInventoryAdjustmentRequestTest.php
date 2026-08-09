<?php

namespace Tests\Unit;

use App\Http\Requests\StoreInventoryAdjustmentRequest;
use Illuminate\Http\Request;
use Tests\TestCase;

class StoreInventoryAdjustmentRequestTest extends TestCase
{
    public function test_defaults_mode_and_unit_when_missing(): void
    {
        $request = $this->makeRequest(['quantity' => 10]);

        $this->assertSame('change', $request->input('mode'));
        $this->assertSame('consumption', $request->input('unit'));
        $this->assertSame(10.0, (float) $request->input('quantity'));
        $this->assertNull($request->input('direction'));
    }

    public function test_change_mode_keeps_signed_quantity(): void
    {
        $request = $this->makeRequest([
            'mode' => 'change',
            'quantity' => -5.5,
        ]);

        $this->assertSame('change', $request->input('mode'));
        $this->assertSame(-5.5, (float) $request->input('quantity'));
        $this->assertNull($request->input('direction'));
    }

    public function test_menu_item_adjustment_forces_consumption_unit(): void
    {
        $request = $this->makeRequest([
            'adjustable' => 'menu_item',
            'ingredient_id' => null,
            'menu_item_id' => 9,
            'unit' => 'purchase',
            'quantity' => 2,
        ]);

        $this->assertNull($request->input('ingredient_id'));
        $this->assertSame(9, (int) $request->input('menu_item_id'));
        $this->assertSame('consumption', $request->input('unit'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeRequest(array $overrides = []): StoreInventoryAdjustmentRequest
    {
        $payload = array_merge([
            'branch_id' => 1,
            'adjustable' => 'ingredient',
            'ingredient_id' => 1,
            'menu_item_id' => null,
            'notes' => 'Test adjustment',
        ], $overrides);

        $base = Request::create('/inventory/adjustment', 'POST', $payload);
        $request = StoreInventoryAdjustmentRequest::createFrom($base);
        $request->setContainer($this->app);

        $method = new \ReflectionMethod(StoreInventoryAdjustmentRequest::class, 'prepareForValidation');
        $method->invoke($request);

        return $request;
    }
}
