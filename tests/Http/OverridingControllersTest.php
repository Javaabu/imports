<?php

namespace Javaabu\Imports\Tests\Http;

use Javaabu\Imports\Http\Controllers\ImportsController;
use Javaabu\Imports\Tests\Concerns\InteractsWithImports;
use Javaabu\Imports\Tests\TestCase;
use Javaabu\Imports\Tests\TestSupport\Http\CustomImportsController;
use Javaabu\Imports\Tests\TestSupport\Models\User;
use PHPUnit\Framework\Attributes\Test;

class OverridingControllersTest extends TestCase
{
    use InteractsWithImports;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpImports();
    }

    protected function defineRoutes($router): void
    {
        $router->middleware('web')->group(function ($router) {
            $router->imports(['prefix' => 'option', 'name' => 'option', 'controller' => CustomImportsController::class]);
            $router->imports(['prefix' => 'default', 'name' => 'default']);
            $router->imports(['prefix' => 'unsigned', 'name' => 'unsigned', 'signed' => false]);
        });
    }

    #[Test]
    public function the_route_option_replaces_the_controller(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/option')
            ->assertOk()
            ->assertJson(['custom' => true]);
    }

    #[Test]
    public function the_container_replaces_the_controller(): void
    {
        $this->app->bind(ImportsController::class, CustomImportsController::class);

        $this->actingAs(User::factory()->create())
            ->getJson('/default')
            ->assertJson(['custom' => true]);
    }

    #[Test]
    public function the_signed_route_can_be_left_out(): void
    {
        $this->assertTrue(app('router')->has('default.signed-report'));
        $this->assertFalse(app('router')->has('unsigned.signed-report'));
        $this->assertTrue(app('router')->has('unsigned.report'));
    }
}
