<?php

namespace Javaabu\Imports\Tests;

use Javaabu\Forms\FormsServiceProvider;
use Javaabu\Imports\ImportsServiceProvider;
use Javaabu\Imports\Tests\TestSupport\Models\Admin;
use Javaabu\Imports\Tests\TestSupport\Models\User;
use Javaabu\Imports\Tests\TestSupport\Providers\TestServiceProvider;
use Javaabu\Manifest\ManifestServiceProvider;
use Javaabu\Permissions\Models\Permission;
use Javaabu\Permissions\Models\Role;
use Javaabu\Permissions\PermissionsServiceProvider;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ODS\Reader as OdsReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Spatie\Permission\PermissionServiceProvider;
use ZipArchive;

abstract class TestCase extends BaseTestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:yWa/ByhLC/GUvfToOuaPD7zDwB64qkc/QkaQOrT5IpE=');
        $app['config']->set('permission.models.permission', Permission::class);
        $app['config']->set('permission.models.role', Role::class);
        $app['config']->set('permissions.guards', ['web', 'web_admin']);

        $app['config']->set('auth.guards', [
            'web' => [
                'driver' => 'session',
                'provider' => 'users',
            ],
            'web_admin' => [
                'driver' => 'session',
                'provider' => 'admins',
            ],
        ]);

        $app['config']->set('auth.providers', [
            'users' => [
                'driver' => 'eloquent',
                'model' => User::class,
            ],
            'admins' => [
                'driver' => 'eloquent',
                'model' => Admin::class,
            ],
        ]);
    }

    protected function getPackageProviders($app): array
    {
        return [
            PermissionServiceProvider::class,
            PermissionsServiceProvider::class,
            ManifestServiceProvider::class,
            FormsServiceProvider::class,
            ImportsServiceProvider::class,
            TestServiceProvider::class,
        ];
    }

    /**
     * Read every row of a CSV, XLSX or ODS file
     *
     * @return array<int, array<int, mixed>>
     */
    protected function readSpreadsheet(string $path, ?string $extension = null): array
    {
        $reader = $this->makeReader($extension ?? pathinfo($path, PATHINFO_EXTENSION));
        $reader->open($path);

        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }

            break;
        }

        $reader->close();

        return $rows;
    }

    protected function makeReader(string $extension): ReaderInterface
    {
        return match ($extension) {
            'csv' => new CsvReader,
            'ods' => new OdsReader,
            default => new XlsxReader,
        };
    }

    /**
     * Read a file inside an XLSX or ODS archive, e.g. `xl/worksheets/sheet1.xml`
     */
    protected function readFromArchive(string $path, string $file): string
    {
        $zip = new ZipArchive;
        $zip->open($path);

        $contents = (string) $zip->getFromName($file);

        $zip->close();

        return $contents;
    }
}
