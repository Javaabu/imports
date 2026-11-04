<?php

namespace Javaabu\Imports\Tests\Unit;

use DateTimeImmutable;
use Javaabu\Imports\Columns\Column;
use Javaabu\Imports\Columns\ValueConverter;
use Javaabu\Imports\Tests\TestCase;
use Javaabu\Imports\Tests\TestSupport\Enums\ProductStatuses;
use PHPUnit\Framework\Attributes\Test;

class ValueConverterTest extends TestCase
{
    protected ValueConverter $converter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->converter = new ValueConverter;
    }

    #[Test]
    public function it_trims_text_and_turns_blanks_into_null(): void
    {
        $this->assertSame('ފެން', $this->converter->normalize('  ފެން '));
        $this->assertNull($this->converter->normalize('   '));
        $this->assertSame(' a ', $this->converter->normalize(' a ', trim: false));
        $this->assertSame(5, $this->converter->normalize(5));
    }

    #[Test]
    public function it_reads_booleans(): void
    {
        $column = Column::make('active')->boolean();

        foreach (['Yes', 'TRUE', 'y', '1', 1, true, 'on'] as $value) {
            $this->assertTrue($this->converter->convert($column, $value), var_export($value, true));
        }

        foreach (['No', 'false', 'N', '0', 0, false, 'off'] as $value) {
            $this->assertFalse($this->converter->convert($column, $value), var_export($value, true));
        }

        $this->assertSame('maybe', $this->converter->convert($column, 'maybe'));
    }

    #[Test]
    public function it_reads_numbers_with_thousands_separators(): void
    {
        $this->assertSame(1250.5, $this->converter->convert(Column::make('price')->number(), '1,250.50'));
        $this->assertSame(1250, $this->converter->convert(Column::make('stock')->integer(), '1,250'));
        $this->assertSame(3, $this->converter->convert(Column::make('stock')->integer(), 3.0));
        $this->assertSame('ten', $this->converter->convert(Column::make('stock')->integer(), 'ten'));
    }

    #[Test]
    public function it_reads_dates_from_cells_serial_numbers_and_text(): void
    {
        $date = Column::make('released_on')->date();
        $datetime = Column::make('published_at')->datetime();

        $this->assertSame('2025-03-01', $this->converter->convert($date, new DateTimeImmutable('2025-03-01 10:00')));
        $this->assertSame('2025-03-01', $this->converter->convert($date, 45717));
        $this->assertSame('2025-03-01 12:00:00', $this->converter->convert($datetime, 45717.5));
        $this->assertSame('2025-03-01', $this->converter->convert($date, '1 March 2025'));
        $this->assertSame('not a date', $this->converter->convert($date, 'not a date'));
    }

    #[Test]
    public function it_reads_enums_by_value_name_or_label(): void
    {
        $column = Column::make('status')->enum(ProductStatuses::class);

        $this->assertSame('published', $this->converter->convert($column, 'published'));
        $this->assertSame('published', $this->converter->convert($column, 'PUBLISHED'));
        $this->assertSame('archived', $this->converter->convert($column, 'Archived'));
        $this->assertSame('archived', $this->converter->convert($column, ProductStatuses::ARCHIVED));
        $this->assertSame('gone', $this->converter->convert($column, 'gone'));
    }

    #[Test]
    public function it_writes_numbers_as_text_for_string_columns(): void
    {
        $this->assertSame('7701234', $this->converter->convert(Column::make('phone')->string(), 7701234));
    }
}
