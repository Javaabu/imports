<div class="card">
    <div class="card-body">
        @if(! empty($card_title))
            <h4 class="card-title">{{ $card_title }}</h4>
        @endif

        <div class="row">
            @if(empty($hide_data_type))
            <div class="col-md-8">
                <div class="form-group">
                    {!! Form::label('model', __('Data Type').' *') !!}
                    {!! Form::select('model', ['' => ''] + $importables, old('model'), [
                        'class' => add_error_class($errors->has('model')).' select2-basic',
                        'data-placeholder' => __('Nothing Selected'),
                        'data-allow-clear' => 'true',
                        'required' => true
                    ]) !!}
                    @include('errors._list', ['error' => $errors->get('model')])
                </div>
            </div>
            @endif

            <div class="col-md-4">
                <div class="button-group inline-btn-group">
                    <button class="btn btn-primary btn--icon-text btn--raised" name="action" value="download_template">
                        <i class="zmdi zmdi-download"></i> {{ __('Download Import Template') }}
                    </button>
                </div>
            </div>
        </div>

        <div class="form-group">
            {!! Form::label('import_file', __('Import File')) !!}
            @component('admin.components.file-input', [
                'file_input_id' => 'import_file',
                'file_url' => null,
                'accept' => \App\Helpers\Media\AllowedMimeTypes::getAllowedMimeTypesString('spreadsheet'),
            ])
            @endcomponent
            @include('errors._list', ['error' => $errors->get('import_file')])
        </div>

        <div class="form-group">
            <div class="checkbox">
                {!! Form::checkbox('overwrite_duplicates', 1, old('overwrite_duplicates'), ['id' => 'overwrite_duplicates-chk']) !!}
                <label for="overwrite_duplicates-chk" class="checkbox__label">{{ __('Overwrite duplicates') }}</label>
            </div>

            @include('errors._list', ['error' => $errors->get('overwrite_duplicates')])
        </div>

        <h3 class="card-body__title">{{ __('What to do in case of validation errors?') }}</h3>

        <div class="form-group">
            <div class="radio">
                {!! Form::radio('error_handler', 'display', old('error_handler', 'display'), ['id' => 'error_handler-display-chk']) !!}
                <label for="error_handler-display-chk" class="radio__label">{{ __('Display errors') }}</label>
            </div>

            <div class="radio">
                {!! Form::radio('error_handler', 'download', old('error_handler'), ['id' => 'error_handler-download-chk']) !!}
                <label for="error_handler-download-chk" class="radio__label">{{ __('Download rows with valid and invalid rows separated') }}</label>
            </div>

            @include('errors._list', ['error' => $errors->get('error_handler')])
        </div>

        <div class="button-group">
            <button class="btn btn-success btn--icon-text btn--raised" data-confirm="">
                <i class="zmdi zmdi-upload"></i> {{ $submit_btn ?? __('Import Data') }}
            </button>
        </div>
    </div>
</div>


