<?php

namespace Javaabu\Imports\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Javaabu\Imports\Enums\Formats;
use Javaabu\Imports\Imports;

/**
 * Uploading a file for a registered importer: `type` (its registered name) and `file`
 */
class UploadImportRequest extends FormRequest
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
            'type' => ['required', 'string', Rule::in(array_keys(Imports::registered()))],
            'file' => [
                'required',
                'file',
                'extensions:' . implode(',', Formats::extensions()),
                'max:' . (int) config('imports.max_file_size', 51200),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => __('import'),
            'file' => __('file'),
        ];
    }
}
