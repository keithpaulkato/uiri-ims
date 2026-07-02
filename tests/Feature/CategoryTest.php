<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_index_is_viewable_by_any_authenticated_user(): void
    {
        $user = User::factory()->create(['role' => 'Staff']);

        $response = $this->actingAs($user)->get(route('categories.index'));

        $response->assertOk();
    }

    public function test_admin_can_store_a_new_category(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);

        $response = $this->actingAs($admin)->post(route('categories.store'), [
            'name' => 'ICT Equipment',
            'description' => 'Computers, printers, networking devices',
        ]);

        $response->assertRedirect(route('categories.index'));
        $this->assertDatabaseHas('categories', [
            'name' => 'ICT Equipment',
            'description' => 'Computers, printers, networking devices',
        ]);
    }

    public function test_user_without_permission_is_forbidden_from_storing(): void
    {
        $staff = User::factory()->create(['role' => 'Staff']);

        $response = $this->actingAs($staff)->post(route('categories.store'), [
            'name' => 'ICT Equipment',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('categories', ['name' => 'ICT Equipment']);
    }

    public function test_admin_can_update_a_category(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $category = Category::create(['name' => 'Furniture', 'description' => 'Desks and chairs']);

        $response = $this->actingAs($admin)->put(route('categories.update', $category), [
            'name' => 'Furniture & Fittings',
            'description' => 'Desks, chairs and fittings',
        ]);

        $response->assertRedirect(route('categories.index'));
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Furniture & Fittings',
        ]);
    }

    public function test_admin_can_delete_a_category(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $category = Category::create(['name' => 'Vehicles', 'description' => 'Cars and trucks']);

        $response = $this->actingAs($admin)->delete(route('categories.destroy', $category));

        $response->assertRedirect(route('categories.index'));
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_deleting_a_category_with_items_is_blocked(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $branch = Branch::create([
            'name' => 'Test Branch',
            'location' => 'Test Location',
            'address' => 'Test Address',
            'phone' => '+256 700 000000',
            'email' => 'test@example.com',
            'is_headquarters' => false,
        ]);
        $category = Category::create(['name' => 'Machinery In Use', 'description' => 'Has items']);

        InventoryItem::create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'item_code' => 'GRD-001',
            'name' => 'Guarded Item',
            'unit_price' => 1000,
            'current_stock' => 5,
            'minimum_stock' => 1,
        ]);

        $response = $this->actingAs($admin)->delete(route('categories.destroy', $category));

        $response->assertRedirect(route('categories.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_index_supports_search_filter_on_name(): void
    {
        $user = User::factory()->create(['role' => 'Staff']);
        Category::create(['name' => 'Laboratory Equipment', 'description' => 'Lab tools']);
        Category::create(['name' => 'Office Supplies', 'description' => 'Stationery']);

        $response = $this->actingAs($user)->get(route('categories.index', ['search' => 'Laboratory']));

        $response->assertOk();
        $response->assertSee('Laboratory Equipment');
        $response->assertDontSee('Office Supplies');
    }
}
