<?php

namespace Javaabu\Imports\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Javaabu\Helpers\Media\AllowedMimeTypes;
use Javaabu\Imports\ImportsRepository;

class ImportsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'model' => 'string|required|in:'.implode(',', array_keys(ImportsRepository::getImportablesList($this->user()))),
            'import_file' => AllowedMimeTypes::getValidationRule('excel').'|required_unless:action,download_template',
            'action' => 'string|in:download_template',
            'overwrite_duplicates' => 'boolean',
            'error_handler' => 'in:download,display',
        ];
    }
}
