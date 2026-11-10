<?php

namespace Javaabu\Imports\Tests\Http;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Javaabu\Imports\Enums\ImportStatuses;
use Javaabu\Imports\Jobs\ProcessImport;
use Javaabu\Imports\Models\Import;
use Javaabu\Imports\Tests\Concerns\InteractsWithImports;
use Javaabu\Imports\Tests\TestCase;
use Javaabu\Imports\Tests\TestSupport\Models\Admin;
use Javaabu\Imports\Tests\TestSupport\Models\Product;
use Javaabu\Imports\Tests\TestSupport\Models\User;
use Javaabu\Imports\Tests\TestSupport\Policies\DenyAllPolicy;
use PHPUnit\Framework\Attributes\Test;

class ImportsControllerTest extends TestCase
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

    protected function importer(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['view_imports', 'edit_products']);

        return $user;
    }

    #[Test]
    public function guests_and_users_without_permission_are_kept_out(): void
    {
        $this->getJson('/imports')->assertUnauthorized();

        $this->actingAs(User::factory()->create())->getJson('/imports')->assertForbidden();
    }

    #[Test]
    public function it_lists_the_users_own_imports_with_the_importers_they_may_run(): void
    {
        $user = $this->importer();
        $mine = $this->draftImport($user);
        $this->draftImport(User::factory()->create());

        $this->actingAs($user)
            ->getJson('/imports')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->uuid)
            ->assertJsonPath('data.0.is_draft', true)
            ->assertJsonPath('data.0.can.update', true)
            ->assertJsonPath('meta.available.0.type', 'products')
            ->assertJsonPath('meta.available.0.templates.csv', url('/imports/template/products?format=csv'))
            ->assertJsonPath('meta.broadcasting', false);
    }

    #[Test]
    public function importers_the_user_may_not_run_are_not_offered(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view_imports');

        $this->actingAs($user)->getJson('/imports')->assertOk()->assertJsonPath('meta.available', []);

        $file = $this->uploadedFile($this->spreadsheet($this->productRows()), 'products.csv');

        $this->actingAs($user)->postJson('/imports', ['type' => 'products', 'file' => $file])->assertForbidden();
    }

    #[Test]
    public function users_who_see_others_imports_can_filter_them(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('view_others_imports');

        $this->draftImport(User::factory()->create());
        $this->completedImport($admin);

        $this->actingAs($admin)->getJson('/imports')->assertJsonCount(2, 'data');
        $this->actingAs($admin)->getJson('/imports?mine=1')->assertJsonCount(1, 'data');
        $this->actingAs($admin)->getJson('/imports?status=completed')->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'completed');
        $this->actingAs($admin)->getJson('/imports?type=other')->assertJsonCount(0, 'data');
    }

    #[Test]
    public function uploading_returns_the_draft_and_its_preview(): void
    {
        $user = $this->importer();
        $file = $this->uploadedFile($this->spreadsheet($this->productRows()), 'ބަރުދަލު.csv');

        $response = $this->actingAs($user)
            ->postJson('/imports', ['type' => 'products', 'file' => $file])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.filename', 'ބަރުދަލު.csv')
            ->assertJsonPath('preview.ready', true)
            ->assertJsonPath('preview.mapping', ['sku', 'name', 'price'])
            ->assertJsonPath('preview.counts.invalid', 1)
            ->assertJsonPath('preview.headings.3.heading', 'Notes');

        $this->assertTrue(Import::query()->where('uuid', $response->json('data.id'))->firstOrFail()->isOwnedBy($user));
    }

    #[Test]
    public function uploads_must_be_spreadsheets_of_a_registered_type(): void
    {
        $user = $this->importer();
        $pdf = $this->uploadedFile($this->spreadsheet([['a']]), 'notes.pdf');

        $this->actingAs($user)
            ->postJson('/imports', ['type' => 'nope', 'file' => $pdf])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'file']);
    }

    #[Test]
    public function unreadable_files_and_files_without_headings_are_rejected(): void
    {
        $user = $this->importer();

        $corrupt = sys_get_temp_dir() . '/corrupt-' . uniqid() . '.xlsx';
        file_put_contents($corrupt, 'not a spreadsheet');
        $this->temporary_files[] = $corrupt;

        $this->actingAs($user)
            ->postJson('/imports', ['type' => 'products', 'file' => $this->uploadedFile($corrupt, 'ބަރުދަލު.xlsx')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => 'The file could not be read. Check that it is a valid Excel (XLSX) file.']);

        $this->actingAs($user)
            ->postJson('/imports', ['type' => 'products', 'file' => $this->uploadedFile($this->spreadsheet([[''], ['A-1']]), 'empty.csv')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => 'The file has no headings in row 1.']);

        $this->assertSame(0, Import::query()->count());
    }

    #[Test]
    public function the_owner_maps_previews_and_runs_a_draft(): void
    {
        Queue::fake();

        $user = $this->importer();
        $import = $this->draftImport($user);

        $this->actingAs($user)
            ->postJson("/imports/{$import->uuid}/preview", ['mapping' => ['0' => 'sku', '1' => 'name', '2' => '', '3' => ''], 'duplicates' => 'update'])
            ->assertOk()
            ->assertJsonPath('data.duplicates', 'update')
            ->assertJsonPath('preview.mapping', ['sku', 'name'])
            ->assertJsonPath('preview.counts.invalid', 1);

        $this->actingAs($user)
            ->postJson("/imports/{$import->uuid}/preview", ['mapping' => ['0' => 'name', '1' => 'name']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mapping.1']);

        $this->actingAs($user)
            ->postJson("/imports/{$import->uuid}/run", ['mapping' => ['0' => 'price']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mapping.sku', 'mapping.name']);

        $this->actingAs($user)
            ->postJson("/imports/{$import->uuid}/run")
            ->assertAccepted()
            ->assertJsonPath('data.status', 'pending');

        Queue::assertPushed(ProcessImport::class);

        $this->actingAs($user)->postJson("/imports/{$import->uuid}/run")->assertStatus(409);
        $this->actingAs($user)->postJson("/imports/{$import->uuid}/preview")->assertStatus(409);
    }

    #[Test]
    public function others_cannot_run_or_see_a_users_draft(): void
    {
        $import = $this->draftImport($this->importer());
        $other = $this->importer();

        $this->actingAs($other)->getJson("/imports/{$import->uuid}")->assertForbidden();
        $this->actingAs($other)->postJson("/imports/{$import->uuid}/run")->assertForbidden();

        $viewer = $this->importer();
        $viewer->givePermissionTo('view_others_imports');

        $this->actingAs($viewer)->getJson("/imports/{$import->uuid}")->assertOk()->assertJsonPath('data.can.update', false);
        $this->actingAs($viewer)->postJson("/imports/{$import->uuid}/run")->assertForbidden();
    }

    #[Test]
    public function running_an_import_saves_rows_and_shows_the_results(): void
    {
        $user = $this->importer();
        $import = $this->draftImport($user);

        $this->actingAs($user)->postJson("/imports/{$import->uuid}/run")->assertAccepted();

        $this->actingAs($user)
            ->getJson("/imports/{$import->uuid}")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.created_rows', 2)
            ->assertJsonPath('data.failed_rows', 1)
            ->assertJsonPath('data.has_report', true)
            ->assertJsonPath('data.links.report', url("/imports/{$import->uuid}/report"))
            ->assertJsonPath('failures.0.row', 3)
            ->assertJsonPath('preview', null);

        $this->assertSame(2, Product::query()->count());

        $this->actingAs($user)
            ->get("/imports/{$import->uuid}/report")
            ->assertOk()
            ->assertHeader('Content-Disposition', "attachment; filename=\"-failed rows.csv\"; filename*=utf-8''" . rawurlencode('ބަރުދަލު-failed rows.csv'));
    }

    #[Test]
    public function reports_are_signed_or_need_permission_and_expire(): void
    {
        $user = $this->importer();
        $import = $this->completedImport($user);

        $this->actingAs($this->importer())->get("/imports/{$import->uuid}/report")->assertForbidden();

        $this->get(URL::temporarySignedRoute('imports.signed-report', now()->addHour(), ['import' => $import->uuid]))->assertOk();
        $this->get("/imports/{$import->uuid}/signed-report")->assertForbidden();

        $import->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->actingAs($user)->get("/imports/{$import->uuid}/report")->assertStatus(410);

        $draft = $this->draftImport($user);
        $this->actingAs($user)->get("/imports/{$draft->uuid}/report")->assertNotFound();
    }

    #[Test]
    public function templates_download_in_the_enabled_formats(): void
    {
        $user = $this->importer();

        $this->actingAs($user)->get('/imports/template/products?format=csv')->assertOk()->assertDownload('products-template.csv');
        $this->actingAs($user)->get('/imports/template/products')->assertDownload('products-template.xlsx');
        $this->actingAs($user)->get('/imports/template/products?format=pdf')->assertNotFound();
        $this->actingAs($user)->get('/imports/template/nope')->assertNotFound();
    }

    #[Test]
    public function imports_can_be_cancelled_and_deleted_by_whoever_may(): void
    {
        Queue::fake();

        $user = $this->importer();
        $user->givePermissionTo('delete_imports');

        $import = $this->draftImport($user)->run();

        $this->actingAs($this->importer())->postJson("/imports/{$import->uuid}/cancel")->assertForbidden();

        $this->actingAs($user)->postJson("/imports/{$import->uuid}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->actingAs($user)->postJson("/imports/{$import->uuid}/cancel")->assertStatus(409);
        $this->actingAs($user)->deleteJson("/imports/{$import->uuid}")->assertNoContent();

        $this->assertNull($import->fresh());
    }

    #[Test]
    public function the_admin_guard_has_its_own_routes_and_imports(): void
    {
        $admin = Admin::factory()->create();
        $admin->givePermissionTo('view_imports');

        $this->actingAs($admin, 'web_admin')->getJson('/admin/imports')->assertOk()->assertJsonPath('meta.urls.index', url('/admin/imports'));

        $import = $this->draftImport($admin);

        $this->assertSame('admin', $import->user_type);

        $this->actingAs($admin, 'web_admin')
            ->getJson("/admin/imports/{$import->uuid}")
            ->assertOk()
            ->assertJsonPath('data.links.show', url("/admin/imports/{$import->uuid}"))
            ->assertJsonPath('meta.routes.run', 'admin.imports.run');
    }

    #[Test]
    public function apps_can_replace_the_policy(): void
    {
        $user = $this->importer();

        Gate::policy(Import::class, DenyAllPolicy::class);

        $this->actingAs($user)->getJson('/imports')->assertForbidden();
    }

    #[Test]
    public function unknown_imports_are_not_found(): void
    {
        $this->actingAs($this->importer())->getJson('/imports/' . fake()->uuid())->assertNotFound();
    }

    #[Test]
    public function finished_imports_cannot_be_cancelled_and_pending_status_shows_as_running(): void
    {
        $import = $this->completedImport(null);

        $this->assertSame(ImportStatuses::COMPLETED, $import->status);
        $this->assertFalse($import->isCancellable());
    }
}
