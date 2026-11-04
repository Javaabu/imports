<?php

namespace Javaabu\Imports\Enums;

use ValueError;

/**
 * The spreadsheet formats that can be imported, and that templates and failure
 * reports can be written in
 */
enum Formats: string
{
    case CSV = 'csv';
    case XLSX = 'xlsx';
    case ODS = 'ods';

    public function extension(): string
    {
        return $this->value;
    }

    public function mimeType(): string
    {
        return match ($this) {
            self::CSV => 'text/csv',
            self::XLSX => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            self::ODS => 'application/vnd.oasis.opendocument.spreadsheet',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::CSV => __('CSV'),
            self::XLSX => __('Excel (XLSX)'),
            self::ODS => __('OpenDocument (ODS)'),
        };
    }

    /**
     * The format for a value, or from a file name's extension (`txt` counts as CSV)
     */
    public static function resolve(self|string|null $format = null, ?string $filename = null): self
    {
        if ($format instanceof self) {
            return $format;
        }

        if ($format) {
            return self::tryFrom(strtolower($format))
                ?? throw new ValueError(__('Unsupported file format: :format', ['format' => $format]));
        }

        $extension = $filename ? strtolower(pathinfo($filename, PATHINFO_EXTENSION)) : '';

        if ($extension === 'txt') {
            return self::CSV;
        }

        return self::tryFrom($extension)
            ?? throw new ValueError(__('Unsupported file format: :format', ['format' => $extension ?: (string) $filename]));
    }

    /**
     * The formats enabled in `config('imports.formats')`, in that order
     *
     * @return array<int, self>
     */
    public static function enabled(): array
    {
        $enabled = config('imports.formats', [self::XLSX->value, self::CSV->value, self::ODS->value]);

        return array_values(array_filter(array_map(
            fn (string $format) => self::tryFrom(strtolower($format)),
            array_unique($enabled),
        )));
    }

    /**
     * File extensions accepted for upload
     *
     * @return array<int, string>
     */
    public static function extensions(): array
    {
        $extensions = array_map(fn (self $format) => $format->extension(), self::enabled());

        if (in_array(self::CSV, self::enabled(), true)) {
            $extensions[] = 'txt';
        }

        return $extensions;
    }
}
