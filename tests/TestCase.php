<?php

namespace Javaabu\Imports\Tests;

use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Javaabu\Forms\FormsServiceProvider;
use Javaabu\Imports\ImportsServiceProvider;
use Javaabu\Imports\Schema\ModelSchema;
use Javaabu\Imports\Tests\TestSupport\Models\Admin;
use Javaabu\Imports\Tests\TestSupport\Models\User;
use Javaabu\Imports\Tests\TestSupport\Providers\TestServiceProvider;
use Javaabu\Manifest\ManifestServiceProvider;
use Javaabu\Permissions\Models\Permission;
use Javaabu\Permissions\Models\Role;
use Javaabu\Permissions\PermissionsServiceProvider;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\DateTimeCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ODS\Reader as OdsReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\ODS\Writer as OdsWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
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

    /** @var array<int, string> */
    protected array $temporary_files = [];

    protected function setUp(): void
    {
        parent::setUp();

        ModelSchema::flush();
    }

    protected function tearDown(): void
    {
        foreach ($this->temporary_files as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    /**
     * Write rows (the first one being the headings) to a temporary CSV, XLSX or ODS file
     *
     * @param  array<int, array<int, mixed>>  $rows
     */
    protected function spreadsheet(array $rows, string $extension = 'csv'): string
    {
        $path = sys_get_temp_dir() . '/imports-test-' . bin2hex(random_bytes(8)) . '.' . $extension;
        $this->temporary_files[] = $path;

        $writer = match ($extension) {
            'csv' => new CsvWriter,
            'ods' => new OdsWriter,
            default => new XlsxWriter,
        };

        $writer->openToFile($path);

        foreach ($rows as $row) {
            $writer->addRow(new Row(array_map(
                fn (mixed $value) => $value instanceof DateTimeInterface
                    ? new DateTimeCell($value, (new Style)->withFormat('yyyy-mm-dd hh:mm'))
                    : Cell::fromValue($value),
                $row,
            )));
        }

        $writer->close();

        return $path;
    }

    protected function uploadedFile(string $path, ?string $name = null): UploadedFile
    {
        return new UploadedFile($path, $name ?? basename($path), null, null, true);
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
