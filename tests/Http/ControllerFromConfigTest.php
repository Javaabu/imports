<?php

namespace Javaabu\Imports\Tests\Http;

use Javaabu\Imports\Tests\Concerns\InteractsWithImports;
use Javaabu\Imports\Tests\TestCase;
use Javaabu\Imports\Tests\TestSupport\Http\CustomImportsController;
use Javaabu\Imports\Tests\TestSupport\Models\User;
use PHPUnit\Framework\Attributes\Test;

class ControllerFromConfigTest extends TestCase
{
    use InteractsWithImports;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('imports.controllers.imports', CustomImportsController::class);
    }

    #[Test]
    public function the_config_replaces_the_controller(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/imports')
            ->assertJson(['custom' => true]);
    }
}
