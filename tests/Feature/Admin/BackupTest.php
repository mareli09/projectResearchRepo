<?php

use App\Domain\Admin\BackupRestorer;
use App\Domain\Admin\Backups;
use App\Domain\Audit\Activity;
use App\Jobs\RestoreBackupToStaging;
use App\Jobs\RunBackup;
use App\Models\User;
use App\Notifications\WorkflowNotice;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Backup\BackupDestination\BackupDestination;
use Spatie\Backup\Events\BackupHasFailed;

/* M9 Backups (CLAUDE.md §7 M9, §19): encrypted backups, off-server copy, restore to staging first. */
beforeEach(function () {
    seedBasics();
    $this->admin = userWithRole(User::ROLE_SYSADMIN);
    // Its own folder per test: on Windows a just-deleted shared fake disk can stay locked for a moment.
    $this->backupRoot = storage_path('framework/testing/backups-'.Str::lower(Str::random(10)));
    config(['filesystems.disks.backups.root' => $this->backupRoot, 'backup.backup.password' => 'test-archive-password']);
    Storage::forgetDisk('backups');
});

afterEach(function () {
    File::deleteDirectory($this->backupRoot);
});

/** A backup zip like spatie/laravel-backup makes: a database dump plus research files, AES-256. */
function fakeBackup(string $file = '2026-11-27-02-00-00.zip', ?string $password = 'test-archive-password'): string
{
    $dir = Storage::disk('backups')->path(Backups::NAME);
    File::ensureDirectoryExists($dir);
    $zip = new ZipArchive;
    expect($zip->open($dir.'/'.$file, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();
    $entries = ['db-dumps/mysql-tua_repository.sql' => "-- dump\nSELECT 1;\n", 'research/2024/a.pdf' => '%PDF-1.4 a', 'research/2024/b.pdf' => '%PDF-1.4 b'];
    foreach ($entries as $name => $body) {
        $zip->addFromString($name, $body);
        if ($password !== null) {
            $zip->setEncryptionName($name, ZipArchive::EM_AES_256, $password);
        }
    }
    $zip->close();

    return $file;
}

it('refuses to back up without an archive password', function () {
    config(['backup.backup.password' => null]);
    Queue::fake();

    $this->actingAs($this->admin)->get('/admin/backups')->assertOk()
        ->assertSee('Backups are off.')->assertSee('No backups yet')->assertSee('No off-server copy.')
        ->assertDontSee('data-preview', false);
    $this->actingAs($this->admin)->post('/admin/backups')->assertSessionHasErrors('backup');
    Queue::assertNothingPushed();
});

it('lists real backups with what is inside them', function () {
    fakeBackup();
    fakeBackup('2026-11-26-02-00-00.zip', password: null);

    $this->actingAs($this->admin)->get('/admin/backups')->assertOk()
        ->assertSee('2026-11-27-02-00-00.zip')->assertSee('2026-11-26-02-00-00.zip')
        ->assertDontSee('No backups yet');

    $list = collect(Backups::all())->keyBy('file');
    expect($list['2026-11-27-02-00-00.zip']['encrypted'])->toBeTrue()
        ->and($list['2026-11-27-02-00-00.zip']['files'])->toBe(2)
        ->and($list['2026-11-26-02-00-00.zip']['encrypted'])->toBeFalse()
        ->and($list['2026-11-27-02-00-00.zip']['offsite'])->toBeFalse();
});

it('queues Back up now on the long queue and logs it', function () {
    Queue::fake();

    $this->actingAs($this->admin)->post('/admin/backups')->assertSessionHas('status');
    Queue::assertPushed(RunBackup::class, fn (RunBackup $job) => $job->by?->is($this->admin) && $job->queue === 'long');
    expect(Activity::where('event', 'backup')->where('description', 'Backup started from the Backups page')->exists())->toBeTrue();
});

it('logs a failed backup and emails the System administrators', function () {
    Notification::fake();

    event(new BackupHasFailed(new Exception('mysqldump: Access denied'), BackupDestination::create('backups', Backups::NAME)));

    expect(Activity::where('event', 'backup')->latest('id')->first()->description)->toContain('Backup failed on this server: mysqldump: Access denied');
    Notification::assertSentTo($this->admin, WorkflowNotice::class);
    $this->actingAs($this->admin)->get('/admin/backups')->assertSee('Recent failures')->assertSee('Access denied');
    $this->actingAs($this->admin)->get('/admin/health')->assertSee('Failed');
});

it('restores a backup to staging, passing the password only in the environment', function () {
    $file = fakeBackup();
    config(['tua.backup.staging_database' => 'tua_repository_test_staging']); // never a real staging database
    $staging = BackupRestorer::stagingDatabase();
    config(['database.connections.backup_admin' => config('database.connections.mysql')]);
    DB::connection('backup_admin')->statement("CREATE DATABASE IF NOT EXISTS `{$staging}`");
    $dbPassword = (string) config('database.connections.mysql.password');

    // The mysql client is faked: "importing" copies the checked tables into staging.
    Process::fake(function (PendingProcess $p) use ($staging) {
        config(['database.connections.staging' => array_merge(config('database.connections.mysql'), ['database' => $staging])]);
        $live = config('database.connections.mysql.database');
        foreach ([...BackupRestorer::CHECK_TABLES, 'migrations'] as $t) {
            DB::connection('backup_admin')->statement("DROP TABLE IF EXISTS `{$staging}`.`{$t}`");
            DB::connection('backup_admin')->statement("CREATE TABLE `{$staging}`.`{$t}` LIKE `{$live}`.`{$t}`");
        }

        return Process::result();
    });

    $this->actingAs($this->admin)->post("/admin/backups/{$file}/restore")->assertSessionHas('status');

    Process::assertRan(fn (PendingProcess $p) => str_ends_with((string) $p->command[0], 'mysql')
        && end($p->command) === $staging
        && collect($p->command)->every(fn ($arg) => ! str_starts_with((string) $arg, '--password') && ! str_starts_with((string) $arg, '-p'))
        && ($p->environment['MYSQL_PWD'] ?? '') === $dbPassword);

    $last = Backups::lastRestore();
    expect($last['ok'])->toBeTrue()->and($last['file'])->toBe($file)->and($last['files_in_backup'])->toBe(2)
        ->and(array_keys($last['tables']))->toBe(BackupRestorer::CHECK_TABLES);
    expect(Activity::where('event', 'backup')->where('description', "Restore to staging passed: {$file}")->exists())->toBeTrue();
    $this->actingAs($this->admin)->get('/admin/backups')->assertSee('Passed')->assertSee('research_records');

    DB::connection('backup_admin')->statement("DROP DATABASE IF EXISTS `{$staging}`");
});

it('fails the restore check with a wrong password and tells the administrators', function () {
    Notification::fake();
    Process::fake();
    $file = fakeBackup(password: 'another-password');

    $result = app(BackupRestorer::class)->toStaging($file, $this->admin);

    expect($result['ok'])->toBeFalse()->and($result['error'])->toContain('could not be decrypted');
    Process::assertNothingRan();
    Notification::assertSentTo($this->admin, WorkflowNotice::class);
});

it('never restores into the live database by mistake', function () {
    config(['tua.backup.staging_database' => config('database.connections.mysql.database')]);

    expect(fn () => BackupRestorer::stagingDatabase())->toThrow(RuntimeException::class, 'cannot be the live database');
});

it('promotes only a backup that passed the staging check', function () {
    $file = fakeBackup();

    $this->artisan('backup:promote', ['file' => $file, '--force' => true])->assertFailed()
        ->expectsOutputToContain('Restore '.$file.' to staging first');
});

it('rejects restore requests for files that are not backups', function () {
    Queue::fake();
    $this->actingAs($this->admin)->post('/admin/backups/missing.zip/restore')->assertNotFound();
    Queue::assertNotPushed(RestoreBackupToStaging::class);
});

it('is for the System administrator only', function () {
    $this->actingAs(userWithRole(User::ROLE_URDC))->get('/admin/backups')->assertForbidden();
    $this->actingAs(userWithRole(User::ROLE_URDC))->post('/admin/backups')->assertForbidden();
});
