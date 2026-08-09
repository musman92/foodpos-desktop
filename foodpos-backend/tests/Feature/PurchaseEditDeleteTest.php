<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BranchStock;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\IngredientUnit;
use App\Models\MenuItem;
use App\Models\MoneySource;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\Recipe;
use App\Models\Supplier;
use App\Services\PurchaseService;
use App\Services\TenantTransactionalResetService;
use App\Support\TenantTransactionalResetOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class PurchaseEditDeleteTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
        $this->openTenantShift();
    }

    public function test_purchase_can_be_deleted_when_stock_is_still_available(): void
    {
        $supplier = Supplier::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Food Supplier',
            'code' => 'SUP01',
            'status' => 'active',
            'balance' => 0,
        ]);

        $ingredient = $this->createIngredient('Flour', 'FL01', 100);

        $purchase = app(PurchaseService::class)->createPurchase(
            [
                'company_id' => $this->tenantCompany->id,
                'branch_id' => $this->tenantBranch->id,
                'supplier_id' => $supplier->id,
                'purchase_date' => now()->toDateString(),
                'subtotal' => 100,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => 100,
                'paid_amount' => 0,
                'payment_method' => 'credit',
                'money_source_id' => null,
                'payment_status' => 'pending',
                'notes' => null,
            ],
            [[
                'item_type' => 'ingredient',
                'item_id' => $ingredient->id,
                'quantity' => 1,
                'unit_id' => 'kg',
                'unit_price' => 100,
                'expiry_date' => null,
                'notes' => null,
            ]],
            $this->companyAdmin->id,
        );

        $this->assertSame(1000.0, (float) BranchStock::withoutGlobalScopes()
            ->where('branch_id', $this->tenantBranch->id)
            ->where('ingredient_id', $ingredient->id)
            ->value('quantity'));

        $response = $this->actingAsCompanyAdmin()
            ->delete(route('purchases.destroy', $purchase));

        $response->assertRedirect(route('purchases.index'));
        $response->assertSessionHas('success');

        $this->assertSoftDeleted('purchases', ['id' => $purchase->id]);
        $this->assertNull(BranchStock::withoutGlobalScopes()
            ->where('branch_id', $this->tenantBranch->id)
            ->where('ingredient_id', $ingredient->id)
            ->first());
        $this->assertSame(0.0, (float) $supplier->fresh()->balance);
    }

    public function test_purchase_delete_proceeds_with_warning_when_stock_was_consumed(): void
    {
        $supplier = Supplier::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Food Supplier',
            'code' => 'SUP02',
            'status' => 'active',
            'balance' => 0,
        ]);

        $ingredient = $this->createIngredient('Sugar', 'SU01', 80);

        $purchase = app(PurchaseService::class)->createPurchase(
            [
                'company_id' => $this->tenantCompany->id,
                'branch_id' => $this->tenantBranch->id,
                'supplier_id' => $supplier->id,
                'purchase_date' => now()->toDateString(),
                'subtotal' => 80,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => 80,
                'paid_amount' => 0,
                'payment_method' => 'credit',
                'money_source_id' => null,
                'payment_status' => 'pending',
                'notes' => null,
            ],
            [[
                'item_type' => 'ingredient',
                'item_id' => $ingredient->id,
                'quantity' => 1,
                'unit_id' => 'kg',
                'unit_price' => 80,
                'expiry_date' => null,
                'notes' => null,
            ]],
            $this->companyAdmin->id,
        );

        $stock = BranchStock::withoutGlobalScopes()
            ->where('branch_id', $this->tenantBranch->id)
            ->where('ingredient_id', $ingredient->id)
            ->firstOrFail();
        $stock->update(['quantity' => 200]);

        $report = app(\App\Support\PurchaseModificationImpact::class)->analyzeDelete($purchase->fresh());
        $this->assertTrue($report['can_proceed']);
        $this->assertFalse($report['blocked']);

        $response = $this->actingAsCompanyAdmin()
            ->delete(route('purchases.destroy', $purchase));

        $response->assertRedirect(route('purchases.index'));
        $response->assertSessionHas('success');

        $this->assertSoftDeleted('purchases', ['id' => $purchase->id]);
    }

    public function test_purchase_can_be_updated_when_stock_is_still_available(): void
    {
        $supplier = Supplier::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Food Supplier',
            'code' => 'SUP03',
            'status' => 'active',
            'balance' => 0,
        ]);

        $ingredient = $this->createIngredient('Salt', 'SA01', 20);

        $purchase = app(PurchaseService::class)->createPurchase(
            [
                'company_id' => $this->tenantCompany->id,
                'branch_id' => $this->tenantBranch->id,
                'supplier_id' => $supplier->id,
                'purchase_date' => now()->toDateString(),
                'subtotal' => 20,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => 20,
                'paid_amount' => 0,
                'payment_method' => 'credit',
                'money_source_id' => null,
                'payment_status' => 'pending',
                'notes' => 'Original note',
            ],
            [[
                'item_type' => 'ingredient',
                'item_id' => $ingredient->id,
                'quantity' => 1,
                'unit_id' => 'kg',
                'unit_price' => 20,
                'expiry_date' => null,
                'notes' => null,
            ]],
            $this->companyAdmin->id,
        );

        $response = $this->actingAsCompanyAdmin()
            ->put(route('purchases.update', $purchase), [
                'branch_id' => $this->tenantBranch->id,
                'supplier_id' => $supplier->id,
                'purchase_date' => now()->toDateString(),
                'payment_selection' => 'credit',
                'paid_amount' => '',
                'subtotal' => 40,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => 40,
                'notes' => 'Updated note',
                'items' => [[
                    'item_type' => 'ingredient',
                    'item_id' => $ingredient->id,
                    'quantity' => 2,
                    'unit_id' => 'kg',
                    'unit_price' => 20,
                    'expiry_date' => null,
                    'notes' => null,
                ]],
            ]);

        $response->assertRedirect(route('purchases.show', $purchase));
        $response->assertSessionHas('success');

        $purchase->refresh();
        $this->assertSame('Updated note', $purchase->notes);
        $this->assertEquals(40, (float) $purchase->total_amount);
        $this->assertSame(2000.0, (float) BranchStock::withoutGlobalScopes()
            ->where('branch_id', $this->tenantBranch->id)
            ->where('ingredient_id', $ingredient->id)
            ->value('quantity'));
    }

    public function test_purchase_update_allows_increment_on_one_line_when_other_lines_are_consumed(): void
    {
        $supplier = Supplier::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Food Supplier',
            'code' => 'SUP03B',
            'status' => 'active',
            'balance' => 0,
        ]);

        $ketchup = $this->createIngredient('Ketchup Jar', 'KJ-MULTI', 100);
        $soyaSauce = $this->createIngredient('Soya Souse', 'SS-MULTI', 50);

        $purchase = app(PurchaseService::class)->createPurchase(
            [
                'company_id' => $this->tenantCompany->id,
                'branch_id' => $this->tenantBranch->id,
                'supplier_id' => $supplier->id,
                'purchase_date' => now()->toDateString(),
                'subtotal' => 150,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => 150,
                'paid_amount' => 0,
                'payment_method' => 'credit',
                'money_source_id' => null,
                'payment_status' => 'pending',
                'notes' => null,
            ],
            [
                [
                    'item_type' => 'ingredient',
                    'item_id' => $ketchup->id,
                    'quantity' => 1,
                    'unit_id' => 'kg',
                    'unit_price' => 100,
                    'expiry_date' => null,
                    'notes' => null,
                ],
                [
                    'item_type' => 'ingredient',
                    'item_id' => $soyaSauce->id,
                    'quantity' => 1,
                    'unit_id' => 'kg',
                    'unit_price' => 50,
                    'expiry_date' => null,
                    'notes' => null,
                ],
            ],
            $this->companyAdmin->id,
        );

        $ketchupStock = BranchStock::withoutGlobalScopes()
            ->where('branch_id', $this->tenantBranch->id)
            ->where('ingredient_id', $ketchup->id)
            ->firstOrFail();
        $ketchupStock->update(['quantity' => 200]);

        $report = app(\App\Support\PurchaseModificationImpact::class)->analyzeUpdate($purchase->fresh(), [
            [
                'item_type' => 'ingredient',
                'item_id' => $ketchup->id,
                'quantity' => 1,
                'unit_id' => 'kg',
                'unit_price' => 100,
                'expiry_date' => null,
            ],
            [
                'item_type' => 'ingredient',
                'item_id' => $soyaSauce->id,
                'quantity' => 2,
                'unit_id' => 'kg',
                'unit_price' => 50,
                'expiry_date' => null,
            ],
        ], 200.0);

        $this->assertTrue($report['can_proceed']);
        $this->assertFalse($report['blocked']);

        $response = $this->actingAsCompanyAdmin()
            ->put(route('purchases.update', $purchase), [
                'branch_id' => $this->tenantBranch->id,
                'supplier_id' => $supplier->id,
                'purchase_date' => now()->toDateString(),
                'payment_selection' => 'credit',
                'paid_amount' => '',
                'subtotal' => 200,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => 200,
                'items' => [
                    [
                        'item_type' => 'ingredient',
                        'item_id' => $ketchup->id,
                        'quantity' => 1,
                        'unit_id' => 'kg',
                        'unit_price' => 100,
                        'expiry_date' => null,
                        'notes' => null,
                    ],
                    [
                        'item_type' => 'ingredient',
                        'item_id' => $soyaSauce->id,
                        'quantity' => 2,
                        'unit_id' => 'kg',
                        'unit_price' => 50,
                        'expiry_date' => null,
                        'notes' => null,
                    ],
                ],
            ]);

        $response->assertRedirect(route('purchases.show', $purchase));
        $response->assertSessionHas('success');

        $this->assertSame(200.0, (float) BranchStock::withoutGlobalScopes()
            ->where('branch_id', $this->tenantBranch->id)
            ->where('ingredient_id', $ketchup->id)
            ->value('quantity'));
        $this->assertSame(2000.0, (float) BranchStock::withoutGlobalScopes()
            ->where('branch_id', $this->tenantBranch->id)
            ->where('ingredient_id', $soyaSauce->id)
            ->value('quantity'));
    }

    public function test_purchase_update_still_blocks_decrement_on_consumed_line(): void
    {
        $supplier = Supplier::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Food Supplier',
            'code' => 'SUP03C',
            'status' => 'active',
            'balance' => 0,
        ]);

        $ingredient = $this->createIngredient('Mayo Pouch', 'MP01', 30);

        $purchase = app(PurchaseService::class)->createPurchase(
            [
                'company_id' => $this->tenantCompany->id,
                'branch_id' => $this->tenantBranch->id,
                'supplier_id' => $supplier->id,
                'purchase_date' => now()->toDateString(),
                'subtotal' => 30,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => 30,
                'paid_amount' => 0,
                'payment_method' => 'credit',
                'money_source_id' => null,
                'payment_status' => 'pending',
                'notes' => null,
            ],
            [[
                'item_type' => 'ingredient',
                'item_id' => $ingredient->id,
                'quantity' => 2,
                'unit_id' => 'kg',
                'unit_price' => 15,
                'expiry_date' => null,
                'notes' => null,
            ]],
            $this->companyAdmin->id,
        );

        $stock = BranchStock::withoutGlobalScopes()
            ->where('branch_id', $this->tenantBranch->id)
            ->where('ingredient_id', $ingredient->id)
            ->firstOrFail();
        $stock->update(['quantity' => 500]);

        $response = $this->actingAsCompanyAdmin()
            ->put(route('purchases.update', $purchase), [
                'branch_id' => $this->tenantBranch->id,
                'supplier_id' => $supplier->id,
                'purchase_date' => now()->toDateString(),
                'payment_selection' => 'credit',
                'paid_amount' => '',
                'subtotal' => 15,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => 15,
                'items' => [[
                    'item_type' => 'ingredient',
                    'item_id' => $ingredient->id,
                    'quantity' => 1,
                    'unit_id' => 'kg',
                    'unit_price' => 15,
                    'expiry_date' => null,
                    'notes' => null,
                ]],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_new_purchase_gets_next_number_after_previous_was_deleted(): void
    {
        $supplier = Supplier::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Food Supplier',
            'code' => 'SUP04',
            'status' => 'active',
            'balance' => 0,
        ]);

        $ingredient = $this->createIngredient('Oil', 'OI01', 50);
        $service = app(PurchaseService::class);

        $purchaseData = [
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'supplier_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'subtotal' => 50,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 50,
            'paid_amount' => 0,
            'payment_method' => 'credit',
            'money_source_id' => null,
            'payment_status' => 'pending',
            'notes' => null,
        ];

        $item = [[
            'item_type' => 'ingredient',
            'item_id' => $ingredient->id,
            'quantity' => 1,
            'unit_id' => 'kg',
            'unit_price' => 50,
            'expiry_date' => null,
            'notes' => null,
        ]];

        $first = $service->createPurchase($purchaseData, $item, $this->companyAdmin->id);
        $firstNumber = $first->purchase_number;

        $service->deletePurchase($first);

        $second = $service->createPurchase($purchaseData, $item, $this->companyAdmin->id);

        $this->assertSame($firstNumber, $second->purchase_number);
        $this->assertSame(
            $firstNumber.'-d01',
            Purchase::withoutGlobalScopes()->withTrashed()->find($first->id)->purchase_number
        );
    }

    public function test_purchase_can_be_updated_after_inventory_was_cleared_without_consumption(): void
    {
        $supplier = Supplier::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Food Supplier',
            'code' => 'SUP05',
            'status' => 'active',
            'balance' => 0,
        ]);

        $ingredient = $this->createIngredient('Ketchup Jar', 'KJ01', 120);

        $purchase = app(PurchaseService::class)->createPurchase(
            [
                'company_id' => $this->tenantCompany->id,
                'branch_id' => $this->tenantBranch->id,
                'supplier_id' => $supplier->id,
                'purchase_date' => now()->toDateString(),
                'subtotal' => 120,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => 120,
                'paid_amount' => 0,
                'payment_method' => 'credit',
                'money_source_id' => null,
                'payment_status' => 'pending',
                'notes' => 'Before reset',
            ],
            [[
                'item_type' => 'ingredient',
                'item_id' => $ingredient->id,
                'quantity' => 1,
                'unit_id' => 'kg',
                'unit_price' => 120,
                'expiry_date' => null,
                'notes' => null,
            ]],
            $this->companyAdmin->id,
        );

        BranchStock::withoutGlobalScopes()
            ->where('branch_id', $this->tenantBranch->id)
            ->where('ingredient_id', $ingredient->id)
            ->delete();

        $response = $this->actingAsCompanyAdmin()
            ->put(route('purchases.update', $purchase), [
                'branch_id' => $this->tenantBranch->id,
                'supplier_id' => $supplier->id,
                'purchase_date' => now()->toDateString(),
                'payment_selection' => 'credit',
                'paid_amount' => '',
                'subtotal' => 240,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => 240,
                'notes' => 'After reset',
                'items' => [[
                    'item_type' => 'ingredient',
                    'item_id' => $ingredient->id,
                    'quantity' => 2,
                    'unit_id' => 'kg',
                    'unit_price' => 120,
                    'expiry_date' => null,
                    'notes' => null,
                ]],
            ]);

        $response->assertRedirect(route('purchases.show', $purchase));
        $response->assertSessionHas('success');

        $purchase->refresh();
        $this->assertSame('After reset', $purchase->notes);
        $this->assertSame(2000.0, (float) BranchStock::withoutGlobalScopes()
            ->where('branch_id', $this->tenantBranch->id)
            ->where('ingredient_id', $ingredient->id)
            ->value('quantity'));
    }

    public function test_purchase_can_be_updated_after_orders_reset_rebuilds_stock(): void
    {
        $supplier = Supplier::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Food Supplier',
            'code' => 'SUP06',
            'status' => 'active',
            'balance' => 0,
        ]);

        $ingredient = $this->createIngredient('Ketchup Jar', 'KJ02', 120);

        $purchase = app(PurchaseService::class)->createPurchase(
            [
                'company_id' => $this->tenantCompany->id,
                'branch_id' => $this->tenantBranch->id,
                'supplier_id' => $supplier->id,
                'purchase_date' => now()->toDateString(),
                'subtotal' => 120,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => 120,
                'paid_amount' => 0,
                'payment_method' => 'credit',
                'money_source_id' => null,
                'payment_status' => 'pending',
                'notes' => 'Original',
            ],
            [[
                'item_type' => 'ingredient',
                'item_id' => $ingredient->id,
                'quantity' => 1,
                'unit_id' => 'kg',
                'unit_price' => 120,
                'expiry_date' => null,
                'notes' => null,
            ]],
            $this->companyAdmin->id,
        );

        app(TenantTransactionalResetService::class)->reset(
            $this->tenantCompany,
            [TenantTransactionalResetOptions::ORDERS]
        );

        $this->assertSame(1000.0, (float) BranchStock::withoutGlobalScopes()
            ->where('branch_id', $this->tenantBranch->id)
            ->where('ingredient_id', $ingredient->id)
            ->value('quantity'));

        $this->openTenantShift();

        $response = $this->actingAsCompanyAdmin()
            ->put(route('purchases.update', $purchase), [
                'branch_id' => $this->tenantBranch->id,
                'supplier_id' => $supplier->id,
                'purchase_date' => now()->toDateString(),
                'payment_selection' => 'credit',
                'paid_amount' => '',
                'subtotal' => 240,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => 240,
                'notes' => 'Updated after reset',
                'items' => [[
                    'item_type' => 'ingredient',
                    'item_id' => $ingredient->id,
                    'quantity' => 2,
                    'unit_id' => 'kg',
                    'unit_price' => 120,
                    'expiry_date' => null,
                    'notes' => null,
                ]],
            ]);

        $response->assertRedirect(route('purchases.show', $purchase));
        $response->assertSessionHas('success');
    }

    public function test_purchase_can_be_deleted_after_related_order_is_deleted(): void
    {
        $supplier = Supplier::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Food Supplier',
            'code' => 'SUP08',
            'status' => 'active',
            'balance' => 0,
        ]);

        $tikka = $this->createIngredient('Tikka Masala', 'TM02', 120);
        $gobi = $this->createPackIngredient('Band Gobi', 'BG01', 'C03', '16 Kg', 16000, 500, $tikka);
        $mirch = $this->createIngredient('Shimla Mirch', 'SM01', 80);

        $purchase = app(PurchaseService::class)->createPurchase(
            [
                'company_id' => $this->tenantCompany->id,
                'branch_id' => $this->tenantBranch->id,
                'supplier_id' => $supplier->id,
                'purchase_date' => now()->toDateString(),
                'subtotal' => 700,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => 700,
                'paid_amount' => 0,
                'payment_method' => 'credit',
                'money_source_id' => null,
                'payment_status' => 'pending',
                'notes' => null,
            ],
            [
                [
                    'item_type' => 'ingredient',
                    'item_id' => $tikka->id,
                    'quantity' => 1,
                    'unit_id' => (string) $tikka->purchase_unit_id,
                    'unit_price' => 120,
                    'expiry_date' => null,
                    'notes' => null,
                ],
                [
                    'item_type' => 'ingredient',
                    'item_id' => $gobi->id,
                    'quantity' => 2,
                    'unit_id' => (string) $gobi->purchase_unit_id,
                    'unit_price' => 250,
                    'expiry_date' => null,
                    'notes' => null,
                ],
                [
                    'item_type' => 'ingredient',
                    'item_id' => $mirch->id,
                    'quantity' => 0.5,
                    'unit_id' => (string) $mirch->purchase_unit_id,
                    'unit_price' => 80,
                    'expiry_date' => null,
                    'notes' => null,
                ],
            ],
            $this->companyAdmin->id,
        );

        $stockAfterPurchase = [
            'tikka' => $this->ingredientStock($tikka),
            'gobi' => $this->ingredientStock($gobi),
            'mirch' => $this->ingredientStock($mirch),
        ];

        $menuItem = $this->createMultiIngredientMenuItem($tikka, $gobi, $mirch);
        $order = $this->completePosCheckout($menuItem, 1);

        $this->assertLessThan($stockAfterPurchase['tikka'], $this->ingredientStock($tikka));
        $this->assertLessThan($stockAfterPurchase['gobi'], $this->ingredientStock($gobi));
        $this->assertLessThan($stockAfterPurchase['mirch'], $this->ingredientStock($mirch));

        $this->actingAsCompanyAdmin()
            ->delete(route('order-management.destroy', $order))
            ->assertRedirect(route('order-management.index'));

        $this->assertEqualsWithDelta($stockAfterPurchase['tikka'], $this->ingredientStock($tikka), 0.01);
        $this->assertEqualsWithDelta($stockAfterPurchase['gobi'], $this->ingredientStock($gobi), 0.01);
        $this->assertEqualsWithDelta($stockAfterPurchase['mirch'], $this->ingredientStock($mirch), 0.01);

        $report = app(\App\Support\PurchaseModificationImpact::class)->analyzeDelete($purchase->fresh());

        $this->assertTrue($report['can_proceed'], json_encode($report['stock_lines'], JSON_PRETTY_PRINT));
        $this->assertFalse($report['blocked']);

        $this->actingAsCompanyAdmin()
            ->delete(route('purchases.destroy', $purchase))
            ->assertRedirect(route('purchases.index'))
            ->assertSessionHas('success');
    }

    public function test_purchase_delete_allowed_when_total_ingredient_stock_covers_purchase_batch_shortfall(): void
    {
        $supplier = Supplier::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Food Supplier',
            'code' => 'SUP07',
            'status' => 'active',
            'balance' => 0,
        ]);

        $ingredient = $this->createIngredient('Tikka Masala', 'TM01', 120);

        $purchase = app(PurchaseService::class)->createPurchase(
            [
                'company_id' => $this->tenantCompany->id,
                'branch_id' => $this->tenantBranch->id,
                'supplier_id' => $supplier->id,
                'purchase_date' => now()->toDateString(),
                'subtotal' => 120,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => 120,
                'paid_amount' => 0,
                'payment_method' => 'credit',
                'money_source_id' => null,
                'payment_status' => 'pending',
                'notes' => null,
            ],
            [[
                'item_type' => 'ingredient',
                'item_id' => $ingredient->id,
                'quantity' => 1,
                'unit_id' => 'kg',
                'unit_price' => 120,
                'expiry_date' => null,
                'notes' => null,
            ]],
            $this->companyAdmin->id,
        );

        $purchaseBatch = BranchStock::withoutGlobalScopes()
            ->where('branch_id', $this->tenantBranch->id)
            ->where('ingredient_id', $ingredient->id)
            ->firstOrFail();
        $purchaseBatch->update(['quantity' => 997.8]);

        BranchStock::withoutGlobalScopes()->create([
            'branch_id' => $this->tenantBranch->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 2.2,
            'reserved_quantity' => 0,
            'unit_id' => $purchaseBatch->unit_id,
            'average_cost' => 0.5,
            'last_restocked_at' => now(),
        ]);

        $report = app(\App\Support\PurchaseModificationImpact::class)->analyzeDelete($purchase->fresh());

        $this->assertTrue($report['can_proceed']);
        $this->assertFalse($report['blocked']);

        $this->actingAsCompanyAdmin()
            ->delete(route('purchases.destroy', $purchase))
            ->assertRedirect(route('purchases.index'))
            ->assertSessionHas('success');
    }

    private function createIngredient(string $name, string $sku, float $purchasePrice): Ingredient
    {
        $gramUnit = IngredientUnit::withoutGlobalScopes()->firstOrCreate(
            ['company_id' => $this->tenantCompany->id, 'name' => 'Gram'],
            ['code' => 'g-'.uniqid()]
        );

        $kgUnit = IngredientUnit::withoutGlobalScopes()->firstOrCreate(
            ['company_id' => $this->tenantCompany->id, 'name' => 'Kilogram'],
            ['code' => 'kg-'.uniqid()]
        );

        return Ingredient::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => $name,
            'sku' => $sku,
            'base_unit_id' => 'g',
            'consumption_unit_id' => $gramUnit->id,
            'purchase_unit_id' => $kgUnit->id,
            'conversion_rate' => 1000,
            'purchase_price' => $purchasePrice,
            'cost_per_unit' => $purchasePrice / 1000,
            'track_stock' => 'yes',
            'is_active' => true,
        ]);
    }

    private function createPackIngredient(
        string $name,
        string $sku,
        string $packCode,
        string $packName,
        float $conversionRate,
        float $purchasePrice,
        Ingredient $referenceIngredient,
    ): Ingredient {
        $gramUnitId = $referenceIngredient->consumption_unit_id;

        $packUnit = IngredientUnit::withoutGlobalScopes()->firstOrCreate(
            ['company_id' => $this->tenantCompany->id, 'code' => $packCode],
            ['name' => $packName]
        );

        return Ingredient::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => $name,
            'sku' => $sku,
            'base_unit_id' => (string) $gramUnitId,
            'consumption_unit_id' => $gramUnitId,
            'purchase_unit_id' => $packUnit->id,
            'conversion_rate' => $conversionRate,
            'purchase_price' => $purchasePrice,
            'cost_per_unit' => $purchasePrice / $conversionRate,
            'track_stock' => 'yes',
            'is_active' => true,
        ]);
    }

    private function createMultiIngredientMenuItem(Ingredient $tikka, Ingredient $gobi, Ingredient $mirch): MenuItem
    {
        $recipe = Recipe::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Mixed Veg BOM',
            'code' => 'R-MIX',
            'is_active' => true,
        ]);

        $recipe->items()->createMany([
            [
                'ingredient_id' => $tikka->id,
                'quantity' => 22,
                'unit_id' => (string) $tikka->consumption_unit_id,
                'waste_percentage' => 0,
            ],
            [
                'ingredient_id' => $gobi->id,
                'quantity' => 61.5,
                'unit_id' => (string) $gobi->consumption_unit_id,
                'waste_percentage' => 0,
            ],
            [
                'ingredient_id' => $mirch->id,
                'quantity' => 61.5,
                'unit_id' => (string) $mirch->consumption_unit_id,
                'waste_percentage' => 0,
            ],
        ]);

        $category = Category::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Curries',
            'code' => 'CUR',
            'slug' => 'curries-'.uniqid(),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        return MenuItem::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'category_id' => $category->id,
            'type' => 'recipe',
            'default_recipe_id' => $recipe->id,
            'name' => 'Mixed Veg Curry',
            'slug' => 'mixed-veg-'.uniqid(),
            'sku' => 'MI-MIX',
            'price' => 500,
            'is_available' => true,
            'track_inventory' => true,
            'sort_order' => 1,
        ]);
    }

    private function completePosCheckout(MenuItem $menuItem, int $quantity): Order
    {
        Account::withoutTenantScope()->firstOrCreate(
            ['company_id' => $this->tenantCompany->id, 'name' => 'Sales'],
            ['type' => 'income', 'is_active' => true]
        );

        $cashSource = MoneySource::withoutTenantScope()->firstOrCreate(
            ['company_id' => $this->tenantCompany->id, 'name' => 'Cash'],
            ['type' => 'CASH', 'opening_balance' => 50000, 'active' => true]
        );
        $cashSource->branches()->syncWithoutDetaching([$this->tenantBranch->id]);

        $orderTotal = $quantity * 500;

        $response = $this->actingAsCompanyAdmin()
            ->postJson(route('pos.store'), [
                'mode' => 'pay',
                'type' => 'takeaway',
                'branch_id' => $this->tenantBranch->id,
                'items' => [[
                    'menu_item_id' => $menuItem->id,
                    'item_name' => $menuItem->name,
                    'name' => $menuItem->name,
                    'quantity' => $quantity,
                    'unit_price' => 500,
                    'variants' => null,
                    'addons' => null,
                    'special_instructions' => '',
                ]],
                'subtotal' => $orderTotal,
                'tax_amount' => 0,
                'discount_type' => null,
                'discount_value' => null,
                'service_charge' => 0,
                'delivery_fee' => 0,
                'total_amount' => $orderTotal,
                'paid_amount' => $orderTotal,
                'money_source_id' => $cashSource->id,
                'payment_status' => 'paid',
                'notes' => 'Purchase delete roundtrip test',
            ]);

        $response->assertOk();

        return Order::withoutGlobalScopes()->findOrFail($response->json('order.id'));
    }

    private function ingredientStock(Ingredient $ingredient): float
    {
        return (float) BranchStock::withoutGlobalScopes()
            ->where('branch_id', $this->tenantBranch->id)
            ->where('ingredient_id', $ingredient->id)
            ->get()
            ->sum(fn (BranchStock $batch) => max(0, (float) $batch->quantity - (float) $batch->reserved_quantity));
    }
}
