<?php

namespace Javaabu\Imports\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Javaabu\Imports\Enums\Duplicates;
use Javaabu\Imports\Enums\Formats;
use Javaabu\Imports\Enums\ImportStatuses;
use Javaabu\Imports\Events\ImportCancelled;
use Javaabu\Imports\Events\ImportCompleted;
use Javaabu\Imports\Events\ImportFailed;
use Javaabu\Imports\Events\ImportProgressed;
use Javaabu\Imports\Events\ImportStarted;
use Javaabu\Imports\Exceptions\InvalidMappingException;
use Javaabu\Imports\Jobs\ProcessImport;
use Javaabu\Imports\Models\Import;
use Javaabu\Imports\Notifications\ImportCompletedNotification;
use Javaabu\Imports\Notifications\ImportFailedNotification;
use Javaabu\Imports\Tests\Concerns\InteractsWithImports;
use Javaabu\Imports\Tests\TestCase;
use Javaabu\Imports\Tests\TestSupport\Importers\BrandsImporter;
use Javaabu\Imports\Tests\TestSupport\Importers\FailingImporter;
use Javaabu\Imports\Tests\TestSupport\Importers\ProductsImporter;
use Javaabu\Imports\Tests\TestSupport\Models\Brand;
use Javaabu\Imports\Tests\TestSupport\Models\Product;
use Javaabu\Imports\Tests\TestSupport\Models\User;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

class QueuedImportsTest extends TestCase
{
    use InteractsWithImports;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpImports();
    }

    protected function tearDown(): void
    {
        $this->tearDownImports();

        parent::tearDown();
    }

    #[Test]
    public function uploading_stores_a_draft_with_headings_and_a_suggested_mapping(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $import = $this->draftImport($user);

        $this->assertSame(ImportStatuses::DRAFT, $import->status);
        $this->assertSame('products', $import->type);
        $this->assertSame('Products', $import->name);
        $this->assertSame('ބަރުދަލު.csv', $import->filename);
        $this->assertSame(Formats::CSV, $import->format);
        $this->assertSame(['SKU', 'Name', 'Price', 'Notes'], $import->headings);
        $this->assertSame([0 => 'sku', 1 => 'name', 2 => 'price'], $import->mapping);
        $this->assertTrue($import->isOwnedBy($user));
        $this->assertTrue($import->expires_at->isFuture());
        Storage::disk('imports')->assertExists($import->path);
        $this->assertSame("imports/{$import->uuid}/ބަރުދަލު.csv", $import->path);
        Queue::assertNothingPushed();
    }

    #[Test]
    public function a_draft_can_be_mapped_previewed_and_run(): void
    {
        Event::fake([ImportStarted::class, ImportProgressed::class, ImportCompleted::class]);
        Notification::fake();

        $user = User::factory()->create();
        $import = $this->draftImport($user);

        $import->configure(['SKU' => 'sku', 'Name' => 'name'], Duplicates::UPDATE);

        $this->assertSame([0 => 'sku', 1 => 'name'], $import->fresh()->mapping);
        $this->assertSame(Duplicates::UPDATE, $import->fresh()->duplicates);

        $preview = $import->preview();
        $this->assertSame(1, $preview->invalidCount());

        $import->run();
        $import->refresh();

        $this->assertSame(ImportStatuses::COMPLETED, $import->status);
        $this->assertSame([3, 3, 2, 1], [$import->total_rows, $import->processed_rows, $import->created_rows, $import->failed_rows]);
        $this->assertSame(100, $import->progress());
        $this->assertSame(3, $import->failures[0]['row']);
        $this->assertTrue($import->hasReport());
        Storage::disk('imports')->assertExists("imports/{$import->uuid}/failures.csv");
        $this->assertSame('ބަރުދަލު-failed rows.csv', $import->reportFilename());
        $this->assertSame(['A-1', 'A-3'], Product::query()->orderBy('sku')->pluck('sku')->all());

        Event::assertDispatched(ImportStarted::class);
        Event::assertDispatched(ImportProgressed::class);
        Event::assertDispatched(ImportCompleted::class);
        Notification::assertSentTo($user, ImportCompletedNotification::class);
    }

    #[Test]
    public function queueing_dispatches_the_job_on_the_configured_queue(): void
    {
        Queue::fake();
        config()->set('imports.queue.queue', 'imports');

        $import = ProductsImporter::make()->queue($this->uploadedFile($this->spreadsheet($this->productRows())), User::factory()->create());

        $this->assertSame(ImportStatuses::PENDING, $import->status);
        Queue::assertPushedOn('imports', ProcessImport::class, fn (ProcessImport $job) => $job->import->is($import));
    }

    #[Test]
    public function it_will_not_run_without_the_required_columns(): void
    {
        Queue::fake();

        $import = $this->draftImport(null, [['Code', 'Notes'], ['A-1', 'x']]);

        $this->expectException(InvalidMappingException::class);

        try {
            $import->run();
        } finally {
            $this->assertSame(ImportStatuses::DRAFT, $import->fresh()->status);
            Queue::assertNothingPushed();
        }
    }

    #[Test]
    public function a_started_import_cannot_run_again(): void
    {
        Queue::fake();

        $import = $this->draftImport(null)->run();

        $this->expectException(RuntimeException::class);

        $import->run();
    }

    #[Test]
    public function the_job_keeps_the_importer_and_its_constructor_arguments(): void
    {
        $path = $this->spreadsheet([['Name', 'Code'], ['LG', 'L']]);

        BrandsImporter::make('ބ-')->queue($path);

        $this->assertTrue(Brand::query()->where('name', 'ބ-LG')->exists());
    }

    #[Test]
    public function a_dry_run_counts_without_saving(): void
    {
        $import = $this->draftImport(null);
        $import->configure(dry_run: true);
        $import->run();

        $import->refresh();
        $this->assertTrue($import->dry_run);
        $this->assertSame([2, 1], [$import->created_rows, $import->failed_rows]);
        $this->assertSame(0, Product::query()->count());
    }

    #[Test]
    public function a_failing_import_is_marked_failed_and_the_user_told(): void
    {
        Event::fake([ImportFailed::class]);
        Notification::fake();

        $user = User::factory()->create();
        $import = FailingImporter::make()->upload($this->uploadedFile($this->spreadsheet($this->productRows())), $user);

        try {
            $import->run();
        } catch (RuntimeException) {
            // the sync queue rethrows after failing the job
        }

        $import->refresh();
        $this->assertSame(ImportStatuses::FAILED, $import->status);
        $this->assertSame('The warehouse is closed.', $import->error);
        Event::assertDispatched(ImportFailed::class);
        Notification::assertSentTo($user, ImportFailedNotification::class);
    }

    #[Test]
    public function a_cancelled_import_stops_before_its_next_chunk(): void
    {
        Event::fake([ImportCancelled::class]);
        config()->set('imports.chunk_size', 1);

        $import = $this->draftImport(null);

        Event::listen(ImportProgressed::class, fn (ImportProgressed $event) => $event->import->processed_rows === 1 ? $event->import->cancel() : null);

        $import->run();
        $import->refresh();

        $this->assertSame(ImportStatuses::CANCELLED, $import->status);
        $this->assertSame(1, $import->processed_rows);
        $this->assertSame(['A-1'], Product::query()->pluck('sku')->all());
        $this->assertFalse($import->cancel());
        Event::assertDispatched(ImportCancelled::class);
    }

    #[Test]
    public function the_job_skips_imports_that_are_not_pending(): void
    {
        $import = $this->draftImport(null);

        (new ProcessImport($import))->handle();

        $this->assertSame(ImportStatuses::DRAFT, $import->fresh()->status);
    }

    #[Test]
    public function it_imports_xlsx_files_and_writes_xlsx_reports(): void
    {
        $import = $this->draftImport(null, extension: 'xlsx')->run()->refresh();

        $this->assertSame(ImportStatuses::COMPLETED, $import->status);
        $this->assertStringEndsWith('failures.xlsx', $import->report_path);

        $local = sys_get_temp_dir() . '/report-' . uniqid() . '.xlsx';
        $this->temporary_files[] = $local;
        file_put_contents($local, Storage::disk('imports')->get($import->report_path));

        $rows = $this->readSpreadsheet($local);
        $this->assertSame(['Row', 'SKU', 'Name', 'Price', 'Notes', 'Errors'], $rows[0]);
        $this->assertSame(3, $rows[1][0]);
    }

    #[Test]
    public function failed_imports_keep_the_rows_already_saved_and_can_run_again(): void
    {
        $import = $this->draftImport(null);
        $import->forceFill(['status' => ImportStatuses::FAILED])->save();

        $import->run()->refresh();

        $this->assertSame(ImportStatuses::COMPLETED, $import->status);
    }

    #[Test]
    public function deleting_an_import_deletes_its_files(): void
    {
        $import = $this->completedImport(null);

        $import->delete();

        Storage::disk('imports')->assertMissing($import->path);
        Storage::disk('imports')->assertMissing($import->report_path);
    }

    #[Test]
    public function the_uploaded_file_must_still_exist(): void
    {
        $import = $this->draftImport(null);
        Storage::disk('imports')->delete($import->path);

        $this->expectExceptionMessage('The uploaded file is no longer available.');

        $import->preview();
    }

    #[Test]
    public function completed_notifications_link_to_the_failure_report_when_the_route_exists(): void
    {
        $user = User::factory()->create();
        $import = $this->completedImport($user);

        $mail = (new ImportCompletedNotification($import))->toMail($user);

        $this->assertSame('Your Products import finished with 1 failed rows', $mail->subject);
        $this->assertStringContainsString('/imports/' . $import->uuid . '/signed-report', $mail->actionUrl);
        $this->assertSame($import->uuid, (new ImportCompletedNotification($import))->toArray($user)['import']);
        $this->assertSame('Your Products import failed', (new ImportFailedNotification($import))->toArray($user)['message']);
    }

    #[Test]
    public function prune_deletes_expired_drafts_and_the_files_of_expired_imports(): void
    {
        $draft = $this->draftImport(null);
        $done = $this->completedImport(null);
        $fresh = $this->completedImport(null);

        $draft->forceFill(['expires_at' => now()->subMinute()])->save();
        $done->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->artisan('imports:prune', ['--pretend' => true])->assertSuccessful();
        $this->assertNotNull($draft->fresh());

        $this->artisan('imports:prune')->assertSuccessful();

        $this->assertNull($draft->fresh());
        $this->assertNotNull($done->fresh());
        $this->assertNull($done->fresh()->path);
        $this->assertNull($done->fresh()->report_path);
        Storage::disk('imports')->assertMissing("imports/{$done->uuid}/failures.csv");
        Storage::disk('imports')->assertExists($fresh->report_path);

        $fresh->forceFill(['created_at' => now()->subDays(40)])->save();
        $this->artisan('imports:prune', ['--days' => 30])->assertSuccessful();
        $this->assertNull(Import::query()->find($fresh->id));
    }
}
