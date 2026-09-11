<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->admin = User::factory()->create();
        Permission::findOrCreate('logs.view', 'web');
        $this->admin->givePermissionTo('logs.view');
    }

    public function test_admin_can_view_backups_page(): void
    {
        $response = $this->actingAs($this->admin)->get(route('backups.index'));

        $response->assertOk();
    }

    public function test_admin_can_create_sqlite_backup(): void
    {
        // Setup a dummy sqlite database file for the test
        $tempDb = tempnam(sys_get_temp_dir(), 'test_sqlite');
        file_put_contents($tempDb, 'SQLite format 3' . str_repeat('0', 100));
        config(['database.connections.sqlite.database' => $tempDb]);

        $response = $this->actingAs($this->admin)->post(route('backups.create'));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $disk = Storage::disk('local');
        $files = $disk->files('backups');
        $this->assertNotEmpty($files);
        $this->assertTrue(str_ends_with($files[0], '.sqlite'));

        @unlink($tempDb);
    }

    public function test_admin_can_download_backup_file(): void
    {
        $disk = Storage::disk('local');
        $disk->put('backups/test.sqlite', 'sample sqlite content');

        $response = $this->actingAs($this->admin)->get(route('backups.download', ['path' => 'backups/test.sqlite']));

        $response->assertOk();
    }

    public function test_admin_can_delete_backup_file(): void
    {
        $disk = Storage::disk('local');
        $disk->put('backups/test.sqlite', 'sample content');

        $response = $this->actingAs($this->admin)->delete(route('backups.destroy'), [
            'path' => 'backups/test.sqlite',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $disk->assertMissing('backups/test.sqlite');
    }
}

