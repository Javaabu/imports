<div class="card">
    <div class="card-body">
        @if(! empty($card_title))
            <h4 class="card-title">{{ $card_title }}</h4>
        @endif

        <div class="row">
            @if(empty($hide_data_type))
                <div class="col-md-8">
                    <div class="form-group">
                        <x-forms::select2
                            name="model"
                            label="{{ __('Data Type') }}"
                            :options="$importables"
                            placeholder="{{ __('Nothing Selected') }}"
                            required="required"
                        />
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
            <x-forms::file
                name="import_file"
                label="{{ __('Import File') }}"
                accept="{{ \Javaabu\Helpers\Media\AllowedMimeTypes::getAllowedMimeTypesString('spreadsheet') }}"
                />
        </div>

        <div class="form-group">
            <div class="checkbox">
                <x-forms::checkbox
                    name="overwrite_duplicates"
                    label="{{ __('Overwrite duplicates') }}"
                    id="overwrite_duplicates-chk"
                />
            </div>
        </div>

        <h3 class="card-body__title">{{ __('What to do in case of validation errors?') }}</h3>

        <div class="form-group">
            <x-forms::radio
                name="error_handler"
                label="{{ __('Display errors') }}"
                id="error_handler-display-chk"
                value="display"
                checked="checked"
                :show-label="false"
            />

            <x-forms::radio
                name="error_handler"
                label="{{ __('Download rows with valid and invalid rows separated') }}"
                id="error_handler-download-chk"
                value="download"
                :show-label="false"
            />
        </div>

        <div class="button-group">
            <x-forms::button type="submit"
                             data-confirm=""
                             class="btn btn-success btn--icon-text btn--raised">
                <i class="zmdi zmdi-upload"></i>
                {{  __('Import Data') }}
            </x-forms::button>
        </div>
    </div>
</div>


