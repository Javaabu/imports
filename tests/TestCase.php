<?php

namespace Javaabu\Imports\Tests;

use Illuminate\Support\Facades\View;
use Javaabu\Forms\FormsServiceProvider;
use Javaabu\Helpers\HelpersServiceProvider;
use Javaabu\Imports\ImportsServiceProvider;
use Javaabu\Settings\SettingsServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Javaabu\Imports\Tests\TestSupport\Providers\TestServiceProvider;

abstract class TestCase extends BaseTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('app.key', 'base64:yWa/ByhLC/GUvfToOuaPD7zDwB64qkc/QkaQOrT5IpE=');

        $this->app['config']->set('session.serialization', 'php');

        View::addLocation(__DIR__.'/TestSupport/views');
    }

    protected function getPackageProviders($app)
    {
        return [
            HelpersServiceProvider::class,
            SettingsServiceProvider::class,
            ImportsServiceProvider::class,
            TestServiceProvider::class,
            FormsServiceProvider::class,
        ];
    }
}
