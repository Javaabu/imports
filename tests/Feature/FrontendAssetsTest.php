<?php

namespace Javaabu\Imports\Tests\Feature;

use Javaabu\Imports\Tests\TestCase;
use Javaabu\Manifest\Manifest;
use PHPUnit\Framework\Attributes\Test;

class FrontendAssetsTest extends TestCase
{
    #[Test]
    public function it_registers_its_pages_and_scripts_with_manifest(): void
    {
        $manifest = app(Manifest::class);

        $this->assertTrue($manifest->has('imports::imports'));

        $package = $manifest->package('imports');

        $this->assertEquals('@javaabu/imports', $package->importAlias());
        $this->assertEquals('js/Pages', $package->pagesPath());
        $this->assertArrayHasKey('@javaabu/forms', $package->npmDependencies());
        $this->assertArrayHasKey('react', $package->npmPeerDependencies());

        $this->artisan('manifest:list', ['package' => 'imports'])->assertSuccessful();
    }
}
