<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    use RefreshDatabase;

    protected array $connectionsToTransact = [];

    protected static string $sharedSqlitePath;

    protected User $superAdmin;

    protected function beforeRefreshingDatabase(): void
    {
        RefreshDatabaseState::$migrated = false;

        $directory = storage_path('framework/testing');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        self::$sharedSqlitePath = $directory.'/database-backup-test.sqlite';
        @unlink(self::$sharedSqlitePath);
        touch(self::$sharedSqlitePath);

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => self::$sharedSqlitePath,
        ]);

        DB::purge('sqlite');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->superAdmin = User::factory()->create([
            'email' => 'super@example.com',
            'password' => Hash::make('password'),
            'type' => 'super_admin',
            'status' => 'active',
            'can_login' => true,
            'company_id' => null,
            'branch_id' => null,
        ]);
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$sharedSqlitePath) && is_file(self::$sharedSqlitePath)) {
            @unlink(self::$sharedSqlitePath);
        }

        parent::tearDownAfterClass();
    }

    public function test_super_admin_can_view_database_backups_page(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('database-backups.index'))
            ->assertOk()
            ->assertSee('Database Backups')
            ->assertSee('Create backup');
    }

    public function test_non_super_admin_cannot_access_database_backups(): void
    {
        $user = User::factory()->create([
            'type' => 'company_admin',
            'status' => 'active',
            'can_login' => true,
        ]);

        $this->actingAs($user)
            ->get(route('database-backups.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_create_and_download_sqlite_backup(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('database-backups.store'))
            ->assertRedirect(route('database-backups.index'))
            ->assertSessionHas('success');

        $filename = app(DatabaseBackupService::class)->listBackups()->first()['filename'];

        $this->actingAs($this->superAdmin)
            ->get(route('database-backups.download', $filename))
            ->assertOk();
    }

    public function test_super_admin_can_restore_sqlite_backup(): void
    {
        User::factory()->create([
            'email' => 'tenant@example.com',
            'type' => 'company_admin',
            'status' => 'active',
            'can_login' => true,
        ]);

        $this->actingAs($this->superAdmin)
            ->post(route('database-backups.store'))
            ->assertRedirect(route('database-backups.index'));

        $filename = app(DatabaseBackupService::class)->listBackups()->first()['filename'];

        User::query()->where('email', 'tenant@example.com')->forceDelete();
        $this->assertDatabaseMissing('users', ['email' => 'tenant@example.com']);

        $this->actingAs($this->superAdmin)
            ->post(route('database-backups.restore', $filename), [
                'confirm_restore' => 'RESTORE',
            ])
            ->assertRedirect(route('database-backups.index'))
            ->assertSessionHas('success');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->assertDatabaseHas('users', ['email' => 'tenant@example.com']);
        $this->assertGreaterThan(1, app(DatabaseBackupService::class)->listBackups()->count());
    }

    public function test_super_admin_can_upload_sql_gz_backup_when_using_mysql(): void
    {
        config(['database.default' => 'mysql']);

        $sql = "-- MySQL dump\nCREATE TABLE `users` (`id` bigint unsigned NOT NULL);\n";
        $file = UploadedFile::fake()->createWithContent('downloaded-backup.sql.gz', gzencode($sql));

        $this->actingAs($this->superAdmin)
            ->post(route('database-backups.upload'), [
                'backup_file' => $file,
            ])
            ->assertRedirect(route('database-backups.index'))
            ->assertSessionHas('success');

        $backup = app(DatabaseBackupService::class)->listBackups()->first();
        $this->assertNotNull($backup);
        $this->assertStringContainsString('uploaded', $backup['filename']);
        $this->assertSame('Uploaded', $backup['method']);
        $this->assertStringEndsWith('.sql.gz', $backup['filename']);
    }

    public function test_upload_rejects_invalid_backup_on_sqlite_driver(): void
    {
        $sql = "CREATE TABLE `users` (`id` bigint unsigned NOT NULL);\n";
        $file = UploadedFile::fake()->createWithContent('downloaded-backup.sql.gz', gzencode($sql));

        $this->actingAs($this->superAdmin)
            ->post(route('database-backups.upload'), [
                'backup_file' => $file,
            ])
            ->assertRedirect(route('database-backups.index'))
            ->assertSessionHas('error');

        $this->assertTrue(app(DatabaseBackupService::class)->listBackups()->isEmpty());
    }

    public function test_upload_rejects_non_sql_gz_file(): void
    {
        config(['database.default' => 'mysql']);

        $file = UploadedFile::fake()->createWithContent('backup.txt.gz', gzencode('not sql'));

        $this->actingAs($this->superAdmin)
            ->post(route('database-backups.upload'), [
                'backup_file' => $file,
            ])
            ->assertRedirect(route('database-backups.index'))
            ->assertSessionHas('error');
    }

    public function test_non_super_admin_cannot_upload_backup(): void
    {
        $user = User::factory()->create([
            'type' => 'company_admin',
            'status' => 'active',
            'can_login' => true,
        ]);

        $sql = "CREATE TABLE `users` (`id` bigint unsigned NOT NULL);\n";
        $file = UploadedFile::fake()->createWithContent('downloaded-backup.sql.gz', gzencode($sql));

        $this->actingAs($user)
            ->post(route('database-backups.upload'), [
                'backup_file' => $file,
            ])
            ->assertForbidden();
    }
}
