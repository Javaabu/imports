<?php

namespace Javaabu\Imports\Tests\Http;

use Javaabu\Imports\Enums\ImportStatuses;
use Javaabu\Imports\Imports;
use Javaabu\Imports\Models\Import;
use Javaabu\Imports\Tests\Concerns\InteractsWithImports;
use Javaabu\Imports\Tests\TestCase;
use Javaabu\Imports\Tests\TestSupport\Models\User;
use Javaabu\Manifest\Facades\Manifest;
use PHPUnit\Framework\Attributes\Test;

class BladePagesTest extends TestCase
{
    use InteractsWithImports;

    protected function setUp(): void
    {
        parent::setUp();

        Manifest::fake();

        $this->setUpImports();
    }

    protected function tearDown(): void
    {
        $this->tearDownImports();

        parent::tearDown();
    }

    protected function importer(): User
    {
        $user = User::factory()->create(['name' => 'އަހުމަދު']);
        $user->givePermissionTo(['view_imports', 'delete_imports', 'edit_products']);

        return $user;
    }

    #[Test]
    public function the_index_has_the_upload_form_templates_and_the_imports(): void
    {
        $user = $this->importer();
        $draft = $this->draftImport($user);
        $running = $this->draftImport($user);
        $running->forceFill(['status' => ImportStatuses::PROCESSING, 'total_rows' => 200, 'processed_rows' => 50])->save();

        $this->actingAs($user)
            ->get('/imports')
            ->assertOk()
            ->assertSee('New import')
            ->assertSee('Upload and map columns')
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee(url('/imports/template/products?format=xlsx'), false)
            ->assertSee('ބަރުދަލު.csv')
            ->assertSee("data-import-row=\"{$running->uuid}\"", false)
            ->assertSee('data-import-finished="false"', false)
            ->assertSee('inline-size: 25%', false)
            ->assertSee('50 of 200 rows')
            ->assertSee('Map columns')
            ->assertSee(url("/imports/{$draft->uuid}"), false)
            ->assertSee(url("/imports/{$running->uuid}/cancel"), false)
            ->assertSee('data-poll-interval="3000"', false)
            ->assertDontSee('Started by');

        Manifest::assertAssetRequired('imports::imports');
    }

    #[Test]
    public function the_index_shows_an_empty_state_and_no_form_without_importers(): void
    {
        Imports::forget();

        $user = $this->importer();

        $this->actingAs($user)
            ->get('/imports')
            ->assertOk()
            ->assertSee('No imports yet')
            ->assertDontSee('Upload and map columns');
    }

    #[Test]
    public function uploading_redirects_to_the_mapping_step(): void
    {
        $user = $this->importer();
        $file = $this->uploadedFile($this->spreadsheet($this->productRows()), 'ބަރުދަލު.csv');

        $response = $this->actingAs($user)->post('/imports', ['type' => 'products', 'file' => $file]);

        $import = Import::query()->firstOrFail();
        $response->assertRedirect(url("/imports/{$import->uuid}"));
    }

    #[Test]
    public function the_mapping_step_shows_headings_samples_fields_and_the_preview(): void
    {
        $user = $this->importer();
        $import = $this->draftImport($user);

        $this->actingAs($user)
            ->get("/imports/{$import->uuid}")
            ->assertOk()
            ->assertSee('Map columns')
            ->assertSee('Column in your file')
            ->assertSee('Notes')
            ->assertSee('ފަންކާ')
            ->assertSee('name="mapping[0]"', false)
            ->assertSee('SKU *')
            ->assertSee('Don&#039;t import', false)
            ->assertSee('Existing records')
            ->assertSee('Update existing records')
            ->assertSee('name="intent" value="preview"', false)
            ->assertSee('Start import')
            ->assertSee('data-import-preview-row="3"', false)
            ->assertSee('The Name field is required.')
            ->assertSee('New');
    }

    #[Test]
    public function the_mapping_step_lists_required_fields_that_are_not_mapped(): void
    {
        $user = $this->importer();
        $import = $this->draftImport($user, [['Code', 'Notes'], ['A-1', 'x']]);

        $this->actingAs($user)
            ->get("/imports/{$import->uuid}")
            ->assertOk()
            ->assertSee('Choose a column for these required fields:')
            ->assertDontSee('data-import-preview-row', false);
    }

    #[Test]
    public function previewing_and_running_from_the_form(): void
    {
        $user = $this->importer();
        $import = $this->draftImport($user);

        $this->actingAs($user)
            ->from("/imports/{$import->uuid}")
            ->post("/imports/{$import->uuid}/run", ['intent' => 'preview', 'mapping' => ['sku', 'name', '', ''], 'duplicates' => 'update', 'dry_run' => '0'])
            ->assertRedirect(url("/imports/{$import->uuid}"));

        $this->assertSame([0 => 'sku', 1 => 'name'], $import->fresh()->mapping);

        $this->actingAs($user)
            ->from("/imports/{$import->uuid}")
            ->post("/imports/{$import->uuid}/run", ['mapping' => ['', 'name', '', '']])
            ->assertRedirect(url("/imports/{$import->uuid}"))
            ->assertSessionHasErrors('mapping.sku');

        $this->actingAs($user)
            ->post("/imports/{$import->uuid}/run", ['mapping' => ['sku', 'name', '', '']])
            ->assertRedirect(url("/imports/{$import->uuid}"))
            ->assertSessionHas('success');

        $this->actingAs($user)
            ->get("/imports/{$import->uuid}")
            ->assertOk()
            ->assertSee('Completed')
            ->assertSee('data-import-count="created_rows"', false)
            ->assertSee('These rows were not imported.')
            ->assertSee('Download failed rows')
            ->assertSee('The Name field is required.');
    }

    #[Test]
    public function pages_render_in_dhivehi_right_to_left(): void
    {
        app()->setLocale('dv');

        $user = $this->importer();
        $import = $this->completedImport($user);

        $this->actingAs($user)
            ->get("/imports/{$import->uuid}")
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('އިމްޕޯޓްތަކަށް އެނބުރި')
            ->assertSee('ނާކާމިޔާބު ރޯތައް ޑައުންލޯޑު');
    }
}
