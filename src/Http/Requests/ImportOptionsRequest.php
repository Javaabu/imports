<?php

namespace Javaabu\Imports\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Javaabu\Imports\Enums\Duplicates;

/**
 * The mapping step: `mapping` (heading index => attribute, empty to skip a column),
 * `duplicates` and `dry_run`
 */
class ImportOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mapping' => ['nullable', 'array'],
            'mapping.*' => ['nullable', 'string', 'max:255'],
            'duplicates' => ['nullable', Rule::enum(Duplicates::class)],
            'dry_run' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'mapping' => __('columns'),
            'duplicates' => __('existing records'),
            'dry_run' => __('dry run'),
        ];
    }

    /**
     * @return array<int, string|null>|null
     */
    public function mapping(): ?array
    {
        if (! $this->has('mapping')) {
            return null;
        }

        $mapping = [];

        foreach ((array) $this->validated('mapping', []) as $index => $attribute) {
            if (is_numeric($index)) {
                $mapping[(int) $index] = $attribute ?: null;
            }
        }

        return $mapping;
    }
}
