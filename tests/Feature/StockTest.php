<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Notification;
use App\Models\StockTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockTest extends TestCase
{
    use RefreshDatabase;

    protected int $branch1Id;

    protected int $branch2Id;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->branch1Id = Branch::where('name', 'UIRI Nakawa')->firstOrFail()->id;
        $this->branch2Id = Branch::where('name', 'UIRI Namanve')->firstOrFail()->id;
    }

    protected function makeItem(array $overrides = []): InventoryItem
    {
        $category = Category::first();

        return InventoryItem::create(array_merge([
            'branch_id' => $this->branch1Id,
            'category_id' => $category->id,
            'item_code' => 'STK-001',
            'name' => 'Stock Test Item',
            'unit_price' => 1000,
            'current_stock' => 10,
            'minimum_stock' => 5,
            'is_active' => true,
        ], $overrides));
    }

    public function test_stock_in_increments_current_stock_and_creates_a_transaction(): void
    {
        $manager = User::factory()->create(['role' => 'Store Manager', 'branch_id' => $this->branch1Id]);
        $item = $this->makeItem();

        $response = $this->actingAs($manager)->post(route('stock.in.store'), [
            'item_id' => $item->id,
            'quantity' => 5,
            'unit_price' => 1200,
            'reference_number' => 'PO-TEST-001',
            'transaction_date' => '2026-07-01',
            'remarks' => 'Test stock in',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertSame(15, $item->current_stock);

        $this->assertDatabaseHas('stock_transactions', [
            'item_id' => $item->id,
            'branch_id' => $this->branch1Id,
            'user_id' => $manager->id,
            'transaction_type' => 'stock_in',
            'quantity' => 5,
            'reference_number' => 'PO-TEST-001',
        ]);
    }

    public function test_stock_out_decrements_current_stock_and_creates_a_transaction(): void
    {
        $manager = User::factory()->create(['role' => 'Store Manager', 'branch_id' => $this->branch1Id]);
        $item = $this->makeItem(['current_stock' => 10, 'minimum_stock' => 2]);

        $response = $this->actingAs($manager)->post(route('stock.out.store'), [
            'item_id' => $item->id,
            'quantity' => 4,
            'unit_price' => 1000,
            'reference_number' => 'ISS-TEST-001',
            'transaction_date' => '2026-07-01',
            'remarks' => 'Test stock out',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertSame(6, $item->current_stock);

        $this->assertDatabaseHas('stock_transactions', [
            'item_id' => $item->id,
            'transaction_type' => 'stock_out',
            'quantity' => 4,
        ]);
    }

    public function test_stock_out_with_quantity_greater_than_current_stock_is_rejected(): void
    {
        $manager = User::factory()->create(['role' => 'Store Manager', 'branch_id' => $this->branch1Id]);
        $item = $this->makeItem(['current_stock' => 3, 'minimum_stock' => 1]);

        $response = $this->actingAs($manager)->post(route('stock.out.store'), [
            'item_id' => $item->id,
            'quantity' => 10,
            'transaction_date' => '2026-07-01',
        ]);

        $response->assertSessionHasErrors();

        $item->refresh();
        $this->assertSame(3, $item->current_stock);
        $this->assertDatabaseMissing('stock_transactions', ['item_id' => $item->id]);
    }

    public function test_adjustment_sets_new_absolute_stock_and_records_transaction(): void
    {
        $manager = User::factory()->create(['role' => 'Store Manager', 'branch_id' => $this->branch1Id]);
        $item = $this->makeItem(['current_stock' => 10, 'minimum_stock' => 2]);

        $response = $this->actingAs($manager)->post(route('stock.adjust.store'), [
            'item_id' => $item->id,
            'current_stock' => 7,
            'remarks' => 'Stock count correction',
            'transaction_date' => '2026-07-01',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertSame(7, $item->current_stock);

        $this->assertDatabaseHas('stock_transactions', [
            'item_id' => $item->id,
            'transaction_type' => 'adjustment',
            'quantity' => 3,
        ]);
    }

    public function test_low_stock_notification_created_when_stock_out_drops_to_minimum(): void
    {
        $manager = User::factory()->create(['role' => 'Store Manager', 'branch_id' => $this->branch1Id]);
        $item = $this->makeItem(['current_stock' => 6, 'minimum_stock' => 5]);

        $this->actingAs($manager)->post(route('stock.out.store'), [
            'item_id' => $item->id,
            'quantity' => 1,
            'transaction_date' => '2026-07-01',
        ]);

        $item->refresh();
        $this->assertTrue($item->isLowStock());

        $this->assertDatabaseHas('notifications', [
            'branch_id' => $this->branch1Id,
            'type' => 'low_stock',
        ]);
        $this->assertSame(1, Notification::where('type', 'low_stock')->count());
    }

    public function test_low_stock_notification_is_not_duplicated_while_still_unread(): void
    {
        $manager = User::factory()->create(['role' => 'Store Manager', 'branch_id' => $this->branch1Id]);
        $item = $this->makeItem(['current_stock' => 6, 'minimum_stock' => 5]);

        // First drop to low stock.
        $this->actingAs($manager)->post(route('stock.out.store'), [
            'item_id' => $item->id,
            'quantity' => 1,
            'transaction_date' => '2026-07-01',
        ]);

        $item->refresh();
        $this->assertSame(5, $item->current_stock);

        // Stock in then out again, still ending up low-stock, unread notification still present.
        $this->actingAs($manager)->post(route('stock.in.store'), [
            'item_id' => $item->id,
            'quantity' => 5,
            'transaction_date' => '2026-07-01',
        ]);
        $this->actingAs($manager)->post(route('stock.out.store'), [
            'item_id' => $item->id,
            'quantity' => 5,
            'transaction_date' => '2026-07-01',
        ]);

        $item->refresh();
        $this->assertTrue($item->isLowStock());
        $this->assertSame(1, Notification::where('type', 'low_stock')->count());
    }

    public function test_stock_forms_render_for_a_manager(): void
    {
        $manager = User::factory()->create(['role' => 'Store Manager', 'branch_id' => $this->branch1Id]);
        $this->makeItem();

        $this->actingAs($manager)->get(route('stock.in'))->assertOk()->assertSee('Stock In');
        $this->actingAs($manager)->get(route('stock.out'))->assertOk()->assertSee('Stock Out');
        $this->actingAs($manager)->get(route('stock.adjust'))->assertOk()->assertSee('Stock Adjustment');
    }

    public function test_staff_without_manage_stock_gets_403_from_stock_in(): void
    {
        $staff = User::factory()->create(['role' => 'Staff', 'branch_id' => $this->branch1Id]);
        $item = $this->makeItem();

        $response = $this->actingAs($staff)->post(route('stock.in.store'), [
            'item_id' => $item->id,
            'quantity' => 5,
            'transaction_date' => '2026-07-01',
        ]);

        $response->assertForbidden();

        $item->refresh();
        $this->assertSame(10, $item->current_stock);
    }

    public function test_staff_without_manage_stock_gets_403_from_stock_out(): void
    {
        $staff = User::factory()->create(['role' => 'Staff', 'branch_id' => $this->branch1Id]);
        $item = $this->makeItem();

        $response = $this->actingAs($staff)->post(route('stock.out.store'), [
            'item_id' => $item->id,
            'quantity' => 1,
            'transaction_date' => '2026-07-01',
        ]);

        $response->assertForbidden();
    }

    public function test_staff_without_manage_stock_gets_403_from_adjustment(): void
    {
        $staff = User::factory()->create(['role' => 'Staff', 'branch_id' => $this->branch1Id]);
        $item = $this->makeItem();

        $response = $this->actingAs($staff)->post(route('stock.adjust.store'), [
            'item_id' => $item->id,
            'current_stock' => 2,
            'transaction_date' => '2026-07-01',
        ]);

        $response->assertForbidden();
    }

    public function test_manager_cannot_stock_in_an_item_from_another_branch(): void
    {
        $category = Category::first();
        $otherBranchItem = InventoryItem::create([
            'branch_id' => $this->branch2Id,
            'category_id' => $category->id,
            'item_code' => 'X-STK-001',
            'name' => 'Other Branch Item',
            'unit_price' => 1000,
            'current_stock' => 10,
            'minimum_stock' => 2,
        ]);

        $manager = User::factory()->create(['role' => 'Store Manager', 'branch_id' => $this->branch1Id]);

        $response = $this->actingAs($manager)->post(route('stock.in.store'), [
            'item_id' => $otherBranchItem->id,
            'quantity' => 5,
            'transaction_date' => '2026-07-01',
        ]);

        $response->assertNotFound();

        $otherBranchItem->refresh();
        $this->assertSame(10, $otherBranchItem->current_stock);
    }
}
