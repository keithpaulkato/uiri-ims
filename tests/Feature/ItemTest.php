<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ItemTest extends TestCase
{
    use RefreshDatabase;

    protected int $branch1Id;

    protected int $branch2Id;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        // Branches are seeded by name (BranchSeeder); look them up rather
        // than assuming fixed ids, since ids are not stable across test runs.
        $this->branch1Id = Branch::where('name', 'UIRI Nakawa')->firstOrFail()->id;
        $this->branch2Id = Branch::where('name', 'UIRI Namanve')->firstOrFail()->id;
    }

    public function test_index_is_viewable_by_any_authenticated_user(): void
    {
        $user = User::factory()->create(['role' => 'Staff', 'branch_id' => $this->branch1Id]);

        $response = $this->actingAs($user)->get(route('items.index'));

        $response->assertOk();
    }

    public function test_index_only_shows_items_of_the_users_active_branch(): void
    {
        $category = Category::first();

        InventoryItem::create([
            'branch_id' => $this->branch1Id,
            'category_id' => $category->id,
            'item_code' => 'BR1-001',
            'name' => 'Branch One Item',
            'unit_price' => 1000,
            'current_stock' => 10,
            'minimum_stock' => 2,
        ]);

        InventoryItem::create([
            'branch_id' => $this->branch2Id,
            'category_id' => $category->id,
            'item_code' => 'BR2-001',
            'name' => 'Branch Two Item',
            'unit_price' => 1000,
            'current_stock' => 10,
            'minimum_stock' => 2,
        ]);

        $manager = User::factory()->create(['role' => 'Store Manager', 'branch_id' => $this->branch1Id]);

        $response = $this->actingAs($manager)->get(route('items.index'));

        $response->assertOk();
        $response->assertSee('Branch One Item');
        $response->assertDontSee('Branch Two Item');
    }

    public function test_admin_can_store_a_new_item_with_image(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'Administrator', 'branch_id' => $this->branch1Id]);
        $category = Category::first();
        $supplier = Supplier::first();
        $image = UploadedFile::fake()->image('item.jpg');

        $response = $this->actingAs($admin)->post(route('items.store'), [
            'category_id' => $category->id,
            'supplier_id' => $supplier->id,
            'item_code' => 'TST-001',
            'name' => 'Test Laptop',
            'description' => 'A test laptop',
            'unit' => 'piece',
            'unit_price' => 2500000,
            'current_stock' => 5,
            'minimum_stock' => 2,
            'image' => $image,
        ]);

        $response->assertRedirect(route('items.index'));

        $this->assertDatabaseHas('inventory_items', [
            'item_code' => 'TST-001',
            'name' => 'Test Laptop',
            'branch_id' => $this->branch1Id,
            'created_by' => $admin->id,
        ]);

        $item = InventoryItem::where('item_code', 'TST-001')->firstOrFail();
        $this->assertNotNull($item->image);
        Storage::disk('public')->assertExists($item->image);
    }

    public function test_store_manager_can_store_a_new_item(): void
    {
        $manager = User::factory()->create(['role' => 'Store Manager', 'branch_id' => $this->branch1Id]);
        $category = Category::first();

        $response = $this->actingAs($manager)->post(route('items.store'), [
            'category_id' => $category->id,
            'item_code' => 'TST-002',
            'name' => 'Test Chair',
            'unit_price' => 100000,
            'current_stock' => 3,
            'minimum_stock' => 1,
        ]);

        $response->assertRedirect(route('items.index'));
        $this->assertDatabaseHas('inventory_items', ['item_code' => 'TST-002']);
    }

    public function test_staff_without_permission_is_forbidden_from_storing(): void
    {
        $staff = User::factory()->create(['role' => 'Staff', 'branch_id' => $this->branch1Id]);
        $category = Category::first();

        $response = $this->actingAs($staff)->post(route('items.store'), [
            'category_id' => $category->id,
            'item_code' => 'TST-003',
            'name' => 'Forbidden Item',
            'unit_price' => 1000,
            'current_stock' => 1,
            'minimum_stock' => 1,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('inventory_items', ['item_code' => 'TST-003']);
    }

    public function test_admin_can_update_an_item(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator', 'branch_id' => $this->branch1Id]);
        $category = Category::first();

        $item = InventoryItem::create([
            'branch_id' => $this->branch1Id,
            'category_id' => $category->id,
            'item_code' => 'TST-004',
            'name' => 'Old Name',
            'unit_price' => 1000,
            'current_stock' => 5,
            'minimum_stock' => 2,
        ]);

        $response = $this->actingAs($admin)->put(route('items.update', $item), [
            'category_id' => $category->id,
            'item_code' => 'TST-004',
            'name' => 'New Name',
            'unit_price' => 1500,
            'current_stock' => 8,
            'minimum_stock' => 2,
        ]);

        $response->assertRedirect(route('items.index'));
        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'name' => 'New Name',
            'unit_price' => 1500.00,
        ]);
    }

    public function test_destroy_deactivates_rather_than_deletes(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator', 'branch_id' => $this->branch1Id]);
        $category = Category::first();

        $item = InventoryItem::create([
            'branch_id' => $this->branch1Id,
            'category_id' => $category->id,
            'item_code' => 'TST-005',
            'name' => 'To Deactivate',
            'unit_price' => 1000,
            'current_stock' => 5,
            'minimum_stock' => 2,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->delete(route('items.destroy', $item));

        $response->assertRedirect(route('items.index'));
        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'is_active' => false,
        ]);
    }

    public function test_low_stock_filter_returns_only_low_stock_items(): void
    {
        $category = Category::first();

        InventoryItem::create([
            'branch_id' => $this->branch1Id,
            'category_id' => $category->id,
            'item_code' => 'LOW-001',
            'name' => 'Low Stock Item',
            'unit_price' => 1000,
            'current_stock' => 1,
            'minimum_stock' => 5,
        ]);

        InventoryItem::create([
            'branch_id' => $this->branch1Id,
            'category_id' => $category->id,
            'item_code' => 'OK-001',
            'name' => 'Healthy Stock Item',
            'unit_price' => 1000,
            'current_stock' => 50,
            'minimum_stock' => 5,
        ]);

        $manager = User::factory()->create(['role' => 'Store Manager', 'branch_id' => $this->branch1Id]);

        $response = $this->actingAs($manager)->get(route('items.index', ['low_stock' => 1]));

        $response->assertOk();
        $response->assertSee('Low Stock Item');
        $response->assertDontSee('Healthy Stock Item');
    }
}
