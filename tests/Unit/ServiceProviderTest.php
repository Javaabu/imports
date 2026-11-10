<?php

namespace Javaabu\Imports\Tests\Unit;

use Javaabu\Imports\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ServiceProviderTest extends TestCase
{
    #[Test]
    public function it_merges_the_package_config(): void
    {
        $this->assertSame(500, config('imports.chunk_size'));
        $this->assertSame(1, config('imports.heading_row'));
    }
}
