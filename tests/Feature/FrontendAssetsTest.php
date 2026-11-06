<?php

namespace Javaabu\Imports\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Javaabu\Imports\Tests\TestCase;
use Javaabu\Manifest\Enums\NpmScopes;
use Javaabu\Manifest\Manifest;
use PHPUnit\Framework\Attributes\Test;
use stdClass;

class FrontendAssetsTest extends TestCase
{
    /** What only the Inertia pages and the @javaabu/imports components need */
    protected const array PAGES_ONLY = ['@javaabu/forms', '@javaabu/onu', 'react', 'react-dom', '@inertiajs/react'];

    protected function tearDown(): void
    {
        File::delete(base_path('package.json'));
        File::deleteDirectory(base_path('node_modules'));

        parent::tearDown();
    }

    #[Test]
    public function it_registers_its_pages_and_scripts_with_manifest(): void
    {
        $manifest = app(Manifest::class);

        $this->assertTrue($manifest->has('imports::imports'));

        $package = $manifest->package('imports');

        $this->assertEquals('@javaabu/imports', $package->importAlias());
        $this->assertEquals('js/Pages', $package->pagesPath());

        $this->artisan('manifest:list', ['package' => 'imports'])->assertSuccessful();
    }

    #[Test]
    public function it_asks_only_inertia_apps_for_the_react_packages(): void
    {
        $package = app(Manifest::class)->package('imports');

        // The Blade script imports nothing from npm
        $this->assertSame([], $package->npmDependencies(NpmScopes::ASSETS));
        $this->assertSame([], $package->npmPeerDependencies(NpmScopes::ASSETS));

        $this->assertEqualsCanonicalizing(['@javaabu/forms', '@javaabu/onu'], array_keys($package->npmDependencies(NpmScopes::PAGES)));
        $this->assertEqualsCanonicalizing(['react', 'react-dom', '@inertiajs/react'], array_keys($package->npmPeerDependencies(NpmScopes::PAGES)));
    }

    #[Test]
    public function manifest_check_passes_in_a_blade_app_without_the_react_packages(): void
    {
        // A Blade app that installs everything the registered assets need, and nothing else
        $this->installNpmPackages([]);
        $this->installNpmPackages($this->check());

        $rows = $this->check();

        // javaabu/forms' Blade assets still need @javaabu/onu, but nothing asks for React
        $this->assertNotEmpty($rows);
        $this->assertEmpty(array_intersect(['@javaabu/forms', 'react', 'react-dom', '@inertiajs/react'], array_keys($rows)));
        $this->assertSame(['laravel-vite-plugin', 'vite'], $this->neededByImports($rows));

        $this->artisan('manifest:check')
            ->expectsOutputToContain('Everything the packages need is installed.')
            ->assertSuccessful();
    }

    #[Test]
    public function manifest_check_asks_an_inertia_app_for_the_react_packages(): void
    {
        $this->installNpmPackages(['@inertiajs/react' => ['constraints' => ['^2.0']]]);

        $rows = $this->check();

        foreach (self::PAGES_ONLY as $name) {
            $this->assertArrayHasKey($name, $rows);
            $this->assertContains('imports', $rows[$name]['needed_by']);
            $this->assertContains('pages', $rows[$name]['scopes']);
        }

        $this->assertSame('ok', $rows['@inertiajs/react']['status']);
        $this->assertSame('missing', $rows['react']['status']);

        $this->artisan('manifest:check')->assertFailed();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function check(): array
    {
        Artisan::call('manifest:check', ['--json' => true]);

        return collect(json_decode(Artisan::output(), true))->keyBy('name')->all();
    }

    /**
     * @param  array<string, array<string, mixed>>  $rows
     * @return array<int, string>
     */
    protected function neededByImports(array $rows): array
    {
        $names = array_keys(array_filter($rows, fn (array $row) => in_array('imports', $row['needed_by'], true)));
        sort($names);

        return $names;
    }

    /**
     * Declares the packages in package.json and puts a version that satisfies
     * their first constraint in node_modules
     *
     * @param  array<string, array<string, mixed>>  $packages  name => manifest:check row
     */
    protected function installNpmPackages(array $packages): void
    {
        $declared = [];

        foreach ($packages as $name => $row) {
            $constraint = $row['constraints'][0] ?? '*';
            $version = preg_match('/(\d+)(?:\.(\d+))?(?:\.(\d+))?/', $constraint, $matches)
                ? sprintf('%d.%d.%d', $matches[1], $matches[2] ?? 0, $matches[3] ?? 0)
                : '1.0.0';

            $declared[$name] = $constraint;

            File::ensureDirectoryExists(base_path("node_modules/{$name}"));
            File::put(base_path("node_modules/{$name}/package.json"), json_encode(['name' => $name, 'version' => $version]));
        }

        File::put(base_path('package.json'), json_encode(['devDependencies' => $declared ?: new stdClass]));
    }
}
