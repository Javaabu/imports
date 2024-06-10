@if($result = session('import_result'))
    @php
        $num_imported = $result['num_imported'];
        $num_duplicates = $result['num_duplicates'];
        $overwrite = $result['overwrite'];
        $duplicates = $result['duplicates'];
    @endphp

    <div class="card">
        <div class="alert alert-success">
            <h4 class="text-white">{{ __('Import Result') }}</h4>
        </div>
        <div class="card-body">

            <div class="row">
                <div class="col-md-6">
                    <div class="quick-stats__item bg-blue">
                        <div class="quick-stats__info">
                            <h2>{{ $num_imported }}</h2>
                            <small>{{ __('Records Imported') }}</small>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="quick-stats__item bg-blue">
                        <div class="quick-stats__info">
                            <h2>{{ $num_duplicates }}</h2>
                            <small>{{ $overwrite ? __('Duplicates Overwritten') : __('Duplicates Skipped') }}</small>
                        </div>
                    </div>
                </div>
            </div>

            @if($duplicates)
                <h5 class="card-body__title">{{ __('Duplicate Rows') }}</h5>

                <ul class="list-unstyled">
                    @foreach($duplicates as $row)
                        <li><i class="zmdi zmdi-check mr-2"></i>{{ __('Row :row', compact('row')) }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endif


