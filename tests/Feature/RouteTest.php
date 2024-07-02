<?php

namespace Javaabu\Imports\Tests\Feature;

use Javaabu\Imports\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
