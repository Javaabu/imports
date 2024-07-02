<?php

namespace Javaabu\Imports\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Javaabu\Imports\Tests\TestCase;
use Javaabu\Imports\Tests\TestSupport\Models\User;

class RouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_visit_imports_index_view()
    {
        $response = $this->get(route('imports.index'));
        $response->assertStatus(200);
    }

    public function test_imports_store_route()
    {
        $response = $this->post(route('imports.store'));
        $response->assertSessionHasErrors(['model']);
    }
}
