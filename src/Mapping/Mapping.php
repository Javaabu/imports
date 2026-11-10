<?php

namespace Javaabu\Imports\Mapping;

use Javaabu\Imports\Columns\Column;
use Javaabu\Imports\Exceptions\InvalidMappingException;

/**
 * Which column of the file fills which attribute: heading index => attribute
 *
 * Built from what people chose in the mapping step, or from code, where headings can be
 * given by their text: `['Product name' => 'name', 'Code' => 'sku', 'Notes' => null]`.
 */
class Mapping
{
    /**
     * @param  array<int, string>  $map  heading index => attribute
     */
    final public function __construct(
        protected array $map = [],
    ) {}

    /**
     * Suggest a mapping from the headings
     *
     * @param  array<int, string>  $headings
     * @param  array<string, Column>  $columns
     */
    public static function suggest(array $headings, array $columns): static
    {
        return new static((new ColumnMatcher)->match($headings, $columns));
    }

    /**
     * @param  array<int|string, string|null>  $mapping  heading index or heading text => attribute (null or '' to skip)
     * @param  array<int, string>  $headings
     * @param  array<string, Column>  $columns
     *
     * @throws InvalidMappingException when a heading or attribute doesn't exist, or an attribute is used twice
     */
    public static function fromArray(array $mapping, array $headings, array $columns): static
    {
        $map = [];
        $errors = [];
        $matcher = new ColumnMatcher;

        foreach ($mapping as $heading => $attribute) {
            if ($attribute === null || $attribute === '') {
                continue;
            }

            $index = is_int($heading) ? $heading : static::findHeading($heading, $headings, $matcher);

            if ($index === null || ! array_key_exists($index, $headings)) {
                $errors["mapping.{$heading}"][] = __('The file has no column :heading.', ['heading' => $heading]);

                continue;
            }

            if (! isset($columns[$attribute])) {
                $errors["mapping.{$index}"][] = __('Unknown field :field.', ['field' => $attribute]);

                continue;
            }

            if (in_array($attribute, $map, true)) {
                $errors["mapping.{$index}"][] = __(':field is chosen for more than one column.', ['field' => $columns[$attribute]->getLabel()]);

                continue;
            }

            $map[$index] = $attribute;
        }

        if ($errors) {
            throw InvalidMappingException::fromErrors($errors);
        }

        ksort($map);

        return new static($map);
    }

    /**
     * @param  array<int, string>  $headings
     */
    protected static function findHeading(string $heading, array $headings, ColumnMatcher $matcher): ?int
    {
        $wanted = $matcher->normalize($heading);

        foreach ($headings as $index => $text) {
            if ($matcher->normalize($text) === $wanted) {
                return $index;
            }
        }

        return null;
    }

    /**
     * The heading index an attribute is read from, or null when it isn't mapped
     */
    public function indexOf(string $attribute): ?int
    {
        $index = array_search($attribute, $this->map, true);

        return $index === false ? null : $index;
    }

    public function isMapped(string $attribute): bool
    {
        return $this->indexOf($attribute) !== null;
    }

    /**
     * Required columns that aren't mapped and have no default
     *
     * @param  array<string, Column>  $columns
     * @return array<int, string> attributes
     */
    public function missingRequired(array $columns): array
    {
        $missing = [];

        foreach ($columns as $attribute => $column) {
            if ($column->isRequired() && ! $column->hasDefault() && ! $this->isMapped($attribute)) {
                $missing[] = $attribute;
            }
        }

        return $missing;
    }

    /**
     * @param  array<string, Column>  $columns
     *
     * @throws InvalidMappingException
     */
    public function ensureComplete(array $columns): static
    {
        $missing = $this->missingRequired($columns);

        if ($missing === []) {
            return $this;
        }

        $errors = [];

        foreach ($missing as $attribute) {
            $errors["mapping.{$attribute}"] = [__('Choose a column for :field.', ['field' => $columns[$attribute]->getLabel()])];
        }

        throw InvalidMappingException::fromErrors($errors);
    }

    public function isEmpty(): bool
    {
        return $this->map === [];
    }

    /**
     * @return array<int, string>
     */
    public function toArray(): array
    {
        return $this->map;
    }
}
