<?php

namespace Javaabu\Imports\Tests\Http;

use Inertia\ServiceProvider as InertiaServiceProvider;
use Inertia\Testing\AssertableInertia;
use Javaabu\Imports\Tests\Concerns\InteractsWithImports;
use Javaabu\Imports\Tests\TestCase;
use Javaabu\Imports\Tests\TestSupport\Models\User;
use PHPUnit\Framework\Attributes\Test;

class InertiaPagesTest extends TestCase
{
    use InteractsWithImports;

    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), InertiaServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('imports.views', 'inertia');
        $app['config']->set('inertia.testing.ensure_pages_exist', false);
    }

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
    public function the_index_renders_the_inertia_page(): void
    {
        app()->setLocale('dv');

        $user = $this->importer();
        $import = $this->draftImport($user);

        $this->actingAs($user)
            ->get('/imports')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('imports::Imports/Index')
                ->where('title', 'އިމްޕޯޓްތައް')
                ->where('imports.data.0.id', $import->uuid)
                ->where('imports.data.0.is_draft', true)
                ->where('available.0.type', 'products')
                ->where('accept', '.xlsx,.csv,.ods,.txt')
                ->where('max_file_size', 51200)
                ->where('statuses.0.value', 'draft')
                ->where('sees_others', false)
                ->where('broadcasting', false)
                ->where('routes.store', 'imports.store')
                ->where('urls.store', url('/imports'))
                ->where('strings.upload', 'އަޕްލޯޑްކޮށް ކޮލަމްތައް ގުޅާ')
                ->has('imports.meta.last_page'));
    }

    #[Test]
    public function a_draft_renders_with_its_preview(): void
    {
        $user = $this->importer();
        $import = $this->draftImport($user);

        $this->actingAs($user)
            ->get("/imports/{$import->uuid}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('imports::Imports/Show')
                ->where('import.id', $import->uuid)
                ->where('import.is_draft', true)
                ->where('import.links.run', url("/imports/{$import->uuid}/run"))
                ->where('preview.ready', true)
                ->where('preview.headings.0.attribute', 'sku')
                ->where('preview.counts.invalid', 1)
                ->where('duplicate_options.1.value', 'update')
                ->where('strings.map_columns', 'Map columns')
                ->where('failures', []));
    }

    #[Test]
    public function a_finished_import_renders_its_results(): void
    {
        $user = $this->importer();
        $import = $this->completedImport($user);

        $this->actingAs($user)
            ->get("/imports/{$import->uuid}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('imports::Imports/Show')
                ->where('import.status', 'completed')
                ->where('import.has_report', true)
                ->where('preview', null)
                ->where('failures.0.row', 3));
    }

    #[Test]
    public function the_pages_exist_where_manifest_resolves_them(): void
    {
        $this->assertFileExists(__DIR__ . '/../../resources/js/Pages/Imports/Index.tsx');
        $this->assertFileExists(__DIR__ . '/../../resources/js/Pages/Imports/Show.tsx');
    }
}
