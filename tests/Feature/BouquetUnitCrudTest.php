<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BouquetCategory;
use App\Models\BouquetType;
use App\Models\BouquetUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BouquetUnitCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_store_bouquet_unit_with_money_bouquet_alias(): void
    {
        $user = User::factory()->create();
        $this->assignAdminRole($user);

        $category = BouquetCategory::create([
            'name' => 'Graduation',
            'slug' => 'graduation',
        ]);

        $type = BouquetType::create([
            'category_id' => $category->id,
            'name' => 'Premium',
            'is_custom' => false,
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('bouquet-units.store'), [
                'type_id' => $type->id,
                'serial_number' => 'BQT-1001',
                'name' => 'Bouquet Gold',
                'description' => 'Fresh flower set',
                'money_bouquet' => 210000,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('bouquet_units', [
            'serial_number' => 'BQT-1001',
            'price' => 210000.00,
        ]);
    }

    public function test_admin_can_update_bouquet_unit_with_money_bouquet_alias(): void
    {
        $user = User::factory()->create();
        $this->assignAdminRole($user);

        $category = BouquetCategory::create([
            'name' => 'Wedding',
            'slug' => 'wedding',
        ]);

        $type = BouquetType::create([
            'category_id' => $category->id,
            'name' => 'Classic',
            'is_custom' => false,
        ]);

        $unit = BouquetUnit::create([
            'type_id' => $type->id,
            'serial_number' => 'BQT-2001',
            'name' => 'Bouquet White',
            'price' => 180000,
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('bouquet-units.update', $unit), [
                '_method' => 'PUT',
                'type_id' => $type->id,
                'serial_number' => 'BQT-2001',
                'name' => 'Bouquet White Updated',
                'money_bouquet' => 225000,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('bouquet_units', [
            'id' => $unit->id,
            'name' => 'Bouquet White Updated',
            'price' => 225000.00,
        ]);
    }

    private function assignAdminRole(User $user): void
    {
        $role = Role::create([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        $user->assignRole($role);
    }
}
