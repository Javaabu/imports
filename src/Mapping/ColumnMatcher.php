<?php

namespace Javaabu\Imports\Mapping;

use Illuminate\Support\Str;
use Javaabu\Imports\Columns\Column;

/**
 * Suggests which column each heading in a file fills
 *
 * Headings are compared with each column's attribute (`brand_id`, `brand`), its label in the
 * current locale and in English, and its aliases, ignoring case, punctuation, spacing and
 * the `*` that marks required columns in templates. So `Brand`, `brand_id`, `BRAND *` and
 * `ބްރޭންޑް` (with a Dhivehi label) all match `brand_id`.
 */
class ColumnMatcher
{
    /**
     * @param  array<int, string>  $headings
     * @param  array<string, Column>  $columns
     * @return array<int, string> heading index => attribute
     */
    public function match(array $headings, array $columns): array
    {
        $names = [];

        foreach ($columns as $attribute => $column) {
            $names[$attribute] = $this->candidates($column);
        }

        $mapping = [];

        foreach ([false, true] as $loose) {
            foreach ($headings as $index => $heading) {
                if (isset($mapping[$index])) {
                    continue;
                }

                $normalized = $this->normalize($heading, $loose);

                if ($normalized === '') {
                    continue;
                }

                foreach ($names as $attribute => $candidates) {
                    if (in_array($attribute, $mapping, true)) {
                        continue;
                    }

                    $candidates = array_map(fn (string $name) => $this->normalize($name, $loose), $candidates);

                    if (in_array($normalized, $candidates, true)) {
                        $mapping[$index] = $attribute;

                        break;
                    }
                }
            }
        }

        ksort($mapping);

        return $mapping;
    }

    /**
     * Lower case, without `*`, punctuation or repeated spaces. Loose matching drops spaces too.
     */
    public function normalize(string $value, bool $loose = false): string
    {
        $value = Str::lower(str_replace('*', '', $value));
        $value = trim((string) preg_replace('/[^\pL\pN\pM]+/u', ' ', $value));

        return $loose ? str_replace(' ', '', $value) : $value;
    }

    /**
     * @return array<int, string>
     */
    protected function candidates(Column $column): array
    {
        $names = [
            $column->attribute(),
            $column->baseName(),
            $column->getLabel(),
            Str::headline($column->baseName()),
            ...$column->getAliases(),
        ];

        if (($english = $column->getLabelIn('en')) !== null) {
            $names[] = $english;
        }

        return array_values(array_unique($names));
    }
}
