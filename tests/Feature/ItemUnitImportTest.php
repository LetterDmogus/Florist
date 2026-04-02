<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ItemCategory;
use App\Models\ItemUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ItemUnitImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_import_inventory_supply_file(): void
    {
        $admin = User::factory()->create();
        $this->assignInventoryAdmin($admin);

        $category = ItemCategory::create([
            'name' => 'Supply',
            'slug' => 'supply',
        ]);

        $upload = $this->createImportUpload([
            ['SUP-001', 'White Cherry Pink', 27000, '20lbr/pack', '56x56cm', 2],
            ['SUP-002', 'Big Love Red', 28000, '10lbr/pack', '60x60cm', 5],
        ]);

        $previewResponse = $this->actingAs($admin)->post(route('item-units.import'), [
            'category_id' => $category->id,
            'file' => $upload,
        ]);

        $previewResponse->assertRedirect(route('item-units.index'));
        $token = session('item_unit_import_preview.token');
        $this->assertIsString($token);

        $commitResponse = $this->actingAs($admin)->post(route('item-units.import.commit'), [
            'token' => $token,
        ]);

        $commitResponse->assertRedirect(route('item-units.index'));

        $this->assertDatabaseHas('item_units', [
            'category_id' => $category->id,
            'serial_number' => 'SUP-001',
            'name' => 'White Cherry Pink',
            'price' => 27000.00,
            'individual' => '20lbr/pack',
            'description' => 'Size: 56x56cm',
            'stock' => 2,
        ]);

        $this->assertDatabaseHas('item_units', [
            'category_id' => $category->id,
            'serial_number' => 'SUP-002',
            'name' => 'Big Love Red',
            'price' => 28000.00,
            'individual' => '10lbr/pack',
            'description' => 'Size: 60x60cm',
            'stock' => 5,
        ]);
    }

    public function test_import_updates_existing_inventory_by_serial_number(): void
    {
        $admin = User::factory()->create();
        $this->assignInventoryAdmin($admin);

        $category = ItemCategory::create([
            'name' => 'Supply',
            'slug' => 'supply',
        ]);

        $item = ItemUnit::create([
            'category_id' => $category->id,
            'serial_number' => 'SUP-001',
            'name' => 'Old Name',
            'price' => 10000,
            'individual' => 'unit',
            'description' => 'Old desc',
            'stock' => 1,
        ]);

        $upload = $this->createImportUpload([
            ['SUP-001', 'Updated Name', 33000, '20lbr/pack', '70x70cm', 9],
        ]);

        $previewResponse = $this->actingAs($admin)->post(route('item-units.import'), [
            'category_id' => $category->id,
            'file' => $upload,
        ]);
        $previewResponse->assertRedirect(route('item-units.index'));

        $token = session('item_unit_import_preview.token');
        $this->assertIsString($token);

        $this->actingAs($admin)->post(route('item-units.import.commit'), [
            'token' => $token,
        ])->assertRedirect(route('item-units.index'));

        $item->refresh();

        $this->assertSame('Updated Name', $item->name);
        $this->assertSame('33000.00', (string) $item->price);
        $this->assertSame('20lbr/pack', $item->individual);
        $this->assertSame('Size: 70x70cm', $item->description);
        $this->assertSame(9, $item->stock);
    }

    private function createImportUpload(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sheet1');
        $sheet->setCellValue('A1', 'DAFTAR PRODUK');

        $headers = ['Kode', 'Name', 'Harga Jual', 'Sheets', 'Size', 'Stock Awal', 'Stok Masuk', 'Stok Keluar', 'Sisa'];
        foreach ($headers as $index => $header) {
            $sheet->setCellValueByColumnAndRow($index + 1, 3, $header);
        }

        $rowIndex = 4;
        foreach ($rows as $row) {
            [$kode, $name, $harga, $sheets, $size, $sisa] = $row;
            $sheet->setCellValueByColumnAndRow(1, $rowIndex, $kode);
            $sheet->setCellValueByColumnAndRow(2, $rowIndex, $name);
            $sheet->setCellValueByColumnAndRow(3, $rowIndex, $harga);
            $sheet->setCellValueByColumnAndRow(4, $rowIndex, $sheets);
            $sheet->setCellValueByColumnAndRow(5, $rowIndex, $size);
            $sheet->setCellValueByColumnAndRow(9, $rowIndex, $sisa);
            $rowIndex++;
        }

        $path = tempnam(sys_get_temp_dir(), 'inventory-import-');
        if ($path === false) {
            throw new \RuntimeException('Gagal membuat file temporary import.');
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        return UploadedFile::fake()->createWithContent('inventory-import.xlsx', (string) file_get_contents($path));
    }

    private function assignInventoryAdmin(User $user): void
    {
        Permission::create([
            'name' => 'inventory.view',
            'guard_name' => 'web',
        ]);

        $role = Role::create([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        $role->givePermissionTo('inventory.view');
        $user->assignRole($role);
    }
}
