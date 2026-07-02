<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierTest extends TestCase
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

        $response = $this->actingAs($user)->get(route('suppliers.index'));

        $response->assertOk();
    }

    public function test_store_manager_can_store_a_new_supplier(): void
    {
        $manager = User::factory()->create(['role' => 'Store Manager']);

        $response = $this->actingAs($manager)->post(route('suppliers.store'), [
            'company_name' => 'CompuTech Uganda Ltd',
            'contact_person' => 'David Mukasa',
            'email' => 'sales@computech.co.ug',
            'phone' => '+256 414 123 456',
            'address' => 'Plot 5, Kampala Road, Kampala',
            'tin_number' => '1001234567',
        ]);

        $response->assertRedirect(route('suppliers.index'));
        $this->assertDatabaseHas('suppliers', [
            'company_name' => 'CompuTech Uganda Ltd',
            'email' => 'sales@computech.co.ug',
        ]);
    }

    public function test_user_without_permission_is_forbidden_from_storing(): void
    {
        $staff = User::factory()->create(['role' => 'Staff']);

        $response = $this->actingAs($staff)->post(route('suppliers.store'), [
            'company_name' => 'CompuTech Uganda Ltd',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('suppliers', ['company_name' => 'CompuTech Uganda Ltd']);
    }

    public function test_invalid_email_fails_validation(): void
    {
        $manager = User::factory()->create(['role' => 'Store Manager']);

        $response = $this->actingAs($manager)->post(route('suppliers.store'), [
            'company_name' => 'Bad Email Co',
            'email' => 'not-an-email',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('suppliers', ['company_name' => 'Bad Email Co']);
    }

    public function test_store_manager_can_update_a_supplier(): void
    {
        $manager = User::factory()->create(['role' => 'Store Manager']);
        $supplier = Supplier::create([
            'company_name' => 'Labtech East Africa',
            'contact_person' => 'Sarah Namutebi',
            'email' => 'info@labtech.co.ug',
            'phone' => '+256 772 987 654',
            'address' => 'Industrial Area, Kampala',
            'tin_number' => '1009876543',
            'is_active' => true,
        ]);

        $response = $this->actingAs($manager)->put(route('suppliers.update', $supplier), [
            'company_name' => 'Labtech East Africa Ltd',
            'contact_person' => 'Sarah Namutebi',
            'email' => 'info@labtech.co.ug',
        ]);

        $response->assertRedirect(route('suppliers.index'));
        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'company_name' => 'Labtech East Africa Ltd',
        ]);
    }

    public function test_destroy_deactivates_supplier_instead_of_deleting(): void
    {
        $manager = User::factory()->create(['role' => 'Store Manager']);
        $supplier = Supplier::create([
            'company_name' => 'Office World Uganda',
            'is_active' => true,
        ]);

        $response = $this->actingAs($manager)->delete(route('suppliers.destroy', $supplier));

        $response->assertRedirect(route('suppliers.index'));
        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'is_active' => false,
        ]);
    }

    public function test_index_supports_search_filter_on_company_name(): void
    {
        $user = User::factory()->create(['role' => 'Staff']);
        Supplier::create(['company_name' => 'National Enterprises Ltd', 'is_active' => true]);
        Supplier::create(['company_name' => 'Office World Uganda', 'is_active' => true]);

        $response = $this->actingAs($user)->get(route('suppliers.index', ['search' => 'National']));

        $response->assertOk();
        $response->assertSee('National Enterprises Ltd');
        $response->assertDontSee('Office World Uganda');
    }
}
