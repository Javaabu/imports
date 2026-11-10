<?php

namespace Javaabu\Imports\Processing;

use DateTimeInterface;
use JsonSerializable;

/**
 * A row that wasn't imported, and why
 */
class RowFailure implements JsonSerializable
{
    /**
     * @param  int  $row  the spreadsheet row number
     * @param  array<string, array<int, string>>  $errors  attribute => messages
     * @param  array<int, mixed>  $values  the row's cells as they were in the file
     */
    public function __construct(
        public readonly int $row,
        public readonly array $errors,
        public readonly array $values = [],
    ) {}

    /**
     * @return array<int, string>
     */
    public function messages(): array
    {
        return array_values(array_merge(...array_values($this->errors ?: [[]])));
    }

    /**
     * @return array{row: int, errors: array<string, array<int, string>>, messages: array<int, string>, values: array<int, mixed>}
     */
    public function toArray(): array
    {
        return [
            'row' => $this->row,
            'errors' => $this->errors,
            'messages' => $this->messages(),
            'values' => array_map(fn (mixed $value) => $value instanceof DateTimeInterface ? $value->format('Y-m-d H:i:s') : $value, $this->values),
        ];
    }

    /**
     * @param  array{row: int, errors?: array<string, array<int, string>>, values?: array<int, mixed>}  $data
     */
    public static function fromArray(array $data): static
    {
        return new static((int) $data['row'], $data['errors'] ?? [], $data['values'] ?? []);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
