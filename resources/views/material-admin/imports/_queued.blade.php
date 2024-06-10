@if(session('import_queued'))
    @php
        $count = session('row_count');
        $file_name = session('file_name');
    @endphp
    <div class="card border-warning">
        <div class="card-body">
            <h4 class="card-title text-warning">{{ __('Import Queued!') }}</h4>
            <h6 class="card-subtitle">{{ __('The import file ":file_name" contains :count rows, which is too many to import in one go. The data would be imported in the background and you would be notified via email once the data is imported.', compact('count', 'file_name')) }}</h6>
        </div>
    </div>
@endif





