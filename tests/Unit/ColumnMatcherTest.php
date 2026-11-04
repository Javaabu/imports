<?php

namespace Javaabu\Imports\Tests\Unit;

use Javaabu\Imports\Columns\Column;
use Javaabu\Imports\Mapping\ColumnMatcher;
use Javaabu\Imports\Tests\TestCase;
use Javaabu\Imports\Tests\TestSupport\Models\Brand;
use PHPUnit\Framework\Attributes\Test;

class ColumnMatcherTest extends TestCase
{
    /**
     * @return array<string, Column>
     */
    protected function columns(): array
    {
        return [
            'sku' => Column::make('sku')->label('SKU')->aliases(['item code']),
            'name' => Column::make('name')->label('Product name'),
            'brand_id' => Column::make('brand_id')->lookup(Brand::class),
            'released_on' => Column::make('released_on'),
        ];
    }

    #[Test]
    public function it_matches_attributes_labels_and_aliases_ignoring_case_and_punctuation(): void
    {
        $mapping = (new ColumnMatcher)->match(['Item Code', 'PRODUCT NAME *', 'brand', 'Released-on', 'Notes'], $this->columns());

        $this->assertSame([0 => 'sku', 1 => 'name', 2 => 'brand_id', 3 => 'released_on'], $mapping);
    }

    #[Test]
    public function it_matches_without_spaces_as_a_fallback(): void
    {
        $mapping = (new ColumnMatcher)->match(['productname', 'ReleasedOn', 'brand_id'], $this->columns());

        $this->assertSame([0 => 'name', 1 => 'released_on', 2 => 'brand_id'], $mapping);
    }

    #[Test]
    public function each_column_is_matched_once(): void
    {
        $mapping = (new ColumnMatcher)->match(['SKU', 'sku'], $this->columns());

        $this->assertSame([0 => 'sku'], $mapping);
    }

    #[Test]
    public function it_matches_dhivehi_labels_and_english_headings_in_dhivehi(): void
    {
        app('translator')->addJsonPath(__DIR__ . '/../TestSupport/lang');
        app()->setLocale('dv');

        $mapping = (new ColumnMatcher)->match(['ޕްރޮޑަކްޓްގެ ނަން', 'SKU'], $this->columns());

        $this->assertSame([0 => 'name', 1 => 'sku'], $mapping);
    }
}
