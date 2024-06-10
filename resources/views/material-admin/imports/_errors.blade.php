@if($errors->import_errors->isNotEmpty())
    @php
        $import_errors = $errors->import_errors->messages();
    @endphp
    <div class="card">
        <div class="alert alert-danger">
            <h4 class="text-white">{{ __('Import Errors!') }}</h4>
            <h6 class="text-white">{{ __('The import was aborted due to the following errors.') }}</h6>
        </div>
        <div class="card-body">

            <div class="text-danger">
                @foreach($import_errors as $row => $row_errors)
                <div class="{{ ! $loop->last ? ' mb-2' : '' }}">
                    <strong>{{ trans_choice(__('There is an error in row :row|There are errors in row :row', [
                            'row' => $row,
                            'num_errors' => count($row_errors)
                        ]), count($row_errors)) }}
                    </strong>

                    <ul>
                        @foreach($row_errors as $error_message)
                        <li>{{ $error_message }}</li>
                        @endforeach
                    </ul>
                </div>
                @endforeach
            </div>
        </div>
    </div>
@endif





