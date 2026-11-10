<?php

namespace Javaabu\Imports\Tests\Feature;

use Illuminate\Broadcasting\BroadcastManager;
use Javaabu\Imports\Enums\ImportStatuses;
use Javaabu\Imports\Events\ImportProgressed;
use Javaabu\Imports\Support\Broadcasting;
use Javaabu\Imports\Tests\Concerns\InteractsWithImports;
use Javaabu\Imports\Tests\TestCase;
use Javaabu\Imports\Tests\TestSupport\Broadcasting\MemoryBroadcaster;
use Javaabu\Imports\Tests\TestSupport\Models\Admin;
use Javaabu\Imports\Tests\TestSupport\Models\User;
use PHPUnit\Framework\Attributes\Test;

class BroadcastingTest extends TestCase
{
    use InteractsWithImports;

    protected MemoryBroadcaster $broadcaster;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $broadcaster = $this->broadcaster = new MemoryBroadcaster;

        $app->make(BroadcastManager::class)->extend('memory', fn () => $broadcaster);

        $app['config']->set('broadcasting.connections.memory', ['driver' => 'memory']);
        $app['config']->set('broadcasting.default', 'memory');
        $app['config']->set('imports.broadcasting.enabled', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpImports();
    }

    protected function tearDown(): void
    {
        $this->tearDownImports();
        Broadcasting::$reverb_class = 'Laravel\\Reverb\\ReverbServiceProvider';

        parent::tearDown();
    }

    #[Test]
    public function it_broadcasts_progress_on_the_import_and_owner_channels(): void
    {
        config()->set('imports.chunk_size', 2);

        $user = User::factory()->create();
        $import = $this->draftImport($user)->run();

        $events = array_column($this->broadcaster->broadcasts, 'event');

        $this->assertEquals(['import.started', 'import.progressed', 'import.progressed', 'import.completed'], $events);

        $completed = end($this->broadcaster->broadcasts);

        $this->assertEquals([
            "private-imports.{$import->uuid}",
            "private-imports.owner.user.{$user->id}",
        ], $completed['channels']);

        $this->assertEquals($import->uuid, $completed['payload']['id']);
        $this->assertEquals('completed', $completed['payload']['status']);
        $this->assertEquals(100, $completed['payload']['progress']);
        $this->assertEquals(2, $completed['payload']['created_rows']);
        $this->assertEquals(1, $completed['payload']['failed_rows']);
        $this->assertTrue($completed['payload']['has_report']);
    }

    #[Test]
    public function it_broadcasts_cancellation(): void
    {
        $import = $this->draftImport(User::factory()->create());
        $import->forceFill(['status' => ImportStatuses::PROCESSING])->save();

        $import->cancel();

        $this->assertEquals('import.cancelled', $this->broadcaster->broadcasts[0]['event']);
    }

    #[Test]
    public function it_authorizes_the_import_channel_with_the_policy(): void
    {
        $ahmed = User::factory()->create();
        $aisha = User::factory()->create();
        $ahmed->givePermissionTo('view_imports');
        $aisha->givePermissionTo('view_imports');

        $import = $this->draftImport($ahmed);

        $this->assertTrue($this->broadcaster->authorize("imports.{$import->uuid}", $ahmed));
        $this->assertFalse($this->broadcaster->authorize("imports.{$import->uuid}", $aisha));
        $this->assertFalse($this->broadcaster->authorize('imports.missing', $ahmed));
    }

    #[Test]
    public function it_authorizes_the_owner_channel_for_the_owner_only(): void
    {
        $user = User::factory()->create();
        $admin = Admin::factory()->create(['id' => $user->id]);

        $this->assertTrue($this->broadcaster->authorize("imports.owner.user.{$user->id}", $user));
        $this->assertFalse($this->broadcaster->authorize("imports.owner.user.{$user->id}", $admin));
        $this->assertTrue($this->broadcaster->authorize("imports.owner.admin.{$admin->id}", $admin));
    }

    #[Test]
    public function the_api_tells_pages_which_channel_to_listen_on(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view_imports');

        $import = $this->draftImport($user);

        $this->actingAs($user)
            ->getJson("/imports/{$import->uuid}")
            ->assertJsonPath('data.broadcast_channel', "imports.{$import->uuid}")
            ->assertJsonPath('meta.broadcasting', true);
    }

    #[Test]
    public function it_detects_reverb(): void
    {
        config()->set('imports.broadcasting.enabled', 'auto');
        config()->set('broadcasting.default', 'reverb');

        $this->assertTrue(Broadcasting::enabled());

        config()->set('broadcasting.default', 'null');
        $this->assertFalse(Broadcasting::enabled());

        config()->set('broadcasting.default', 'reverb');
        Broadcasting::$reverb_class = 'Missing\\ReverbServiceProvider';
        $this->assertFalse(Broadcasting::enabled());

        config()->set('imports.broadcasting.enabled', true);
        $this->assertTrue(Broadcasting::enabled());

        config()->set('imports.broadcasting.enabled', 'false');
        $this->assertFalse(Broadcasting::enabled());
    }

    #[Test]
    public function imports_without_an_owner_broadcast_on_their_own_channel(): void
    {
        $import = $this->draftImport(null);

        $this->assertNull($import->ownerBroadcastChannel());
        $this->assertEquals(["private-imports.{$import->uuid}"], array_map('strval', (new ImportProgressed($import))->broadcastOn()));
    }
}
