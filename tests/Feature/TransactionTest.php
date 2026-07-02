<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\StockTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionTest extends TestCase
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

    protected function makeItem(int $branchId, string $code): InventoryItem
    {
        $category = Category::first();

        return InventoryItem::create([
            'branch_id' => $branchId,
            'category_id' => $category->id,
            'item_code' => $code,
            'name' => "Item {$code}",
            'unit_price' => 1000,
            'current_stock' => 10,
            'minimum_stock' => 2,
            'is_active' => true,
        ]);
    }

    public function test_index_is_viewable_by_any_authenticated_user(): void
    {
        $staff = User::factory()->create(['role' => 'Staff', 'branch_id' => $this->branch1Id]);

        $response = $this->actingAs($staff)->get(route('transactions.index'));

        $response->assertOk();
    }

    public function test_index_is_scoped_to_the_users_active_branch(): void
    {
        $item1 = $this->makeItem($this->branch1Id, 'TX-B1-001');
        $item2 = $this->makeItem($this->branch2Id, 'TX-B2-001');
        $user = User::factory()->create(['role' => 'Staff', 'branch_id' => $this->branch1Id]);

        StockTransaction::create([
            'item_id' => $item1->id,
            'branch_id' => $this->branch1Id,
            'user_id' => $user->id,
            'transaction_type' => 'stock_in',
            'quantity' => 5,
            'unit_price' => 1000,
            'reference_number' => 'BR1-REF',
            'transaction_date' => now()->toDateString(),
        ]);

        StockTransaction::create([
            'item_id' => $item2->id,
            'branch_id' => $this->branch2Id,
            'user_id' => $user->id,
            'transaction_type' => 'stock_in',
            'quantity' => 5,
            'unit_price' => 1000,
            'reference_number' => 'BR2-REF',
            'transaction_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($user)->get(route('transactions.index'));

        $response->assertOk();
        $response->assertSee('BR1-REF');
        $response->assertDontSee('BR2-REF');
    }

    public function test_index_can_filter_by_type(): void
    {
        $item = $this->makeItem($this->branch1Id, 'TX-FILTER-001');
        $user = User::factory()->create(['role' => 'Staff', 'branch_id' => $this->branch1Id]);

        StockTransaction::create([
            'item_id' => $item->id,
            'branch_id' => $this->branch1Id,
            'user_id' => $user->id,
            'transaction_type' => 'stock_in',
            'quantity' => 5,
            'unit_price' => 1000,
            'reference_number' => 'IN-REF',
            'transaction_date' => now()->toDateString(),
        ]);

        StockTransaction::create([
            'item_id' => $item->id,
            'branch_id' => $this->branch1Id,
            'user_id' => $user->id,
            'transaction_type' => 'stock_out',
            'quantity' => 2,
            'unit_price' => 1000,
            'reference_number' => 'OUT-REF',
            'transaction_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($user)->get(route('transactions.index', ['type' => 'stock_in']));

        $response->assertOk();
        $response->assertSee('IN-REF');
        $response->assertDontSee('OUT-REF');
    }
}
