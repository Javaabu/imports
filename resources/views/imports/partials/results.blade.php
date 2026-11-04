<x-forms::card
    data-import-row="{{ $import->uuid }}"
    data-import-url="{{ route($routes['show'], $import->uuid) }}"
    data-import-channel="{{ $broadcasting ? $import->broadcastChannel() : '' }}"
    data-import-finished="{{ $import->status->isFinished() ? 'true' : 'false' }}">
    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-center gap-6">
            <span data-import-status><x-forms::status :color="$import->status->color()" :label="$import->status->label()" /></span>
            <div class="min-w-64 grow">@include('imports::imports.partials.progress', ['import' => $import])</div>
        </div>

        @if($import->dry_run)
            <x-forms::alert type="info">{{ __('This was a dry run: nothing was saved.') }}</x-forms::alert>
        @endif

        <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            @foreach(['created_rows' => __('New'), 'updated_rows' => __('Updated'), 'skipped_rows' => __('Skipped'), 'failed_rows' => __('Failed')] as $count => $label)
                <div>
                    <dt class="text-sm text-muted-foreground">{{ $label }}</dt>
                    <dd @class(['text-2xl font-semibold tabular-nums', 'text-destructive' => $count === 'failed_rows' && $import->failed_rows > 0]) data-import-count="{{ $count }}">{{ number_format($import->{$count}) }}</dd>
                </div>
            @endforeach
        </dl>

        @if($import->error)
            <div>
                <p class="text-sm text-muted-foreground">{{ __('Error') }}</p>
                <p class="text-destructive">{{ $import->error }}</p>
            </div>
        @endif
    </div>

    <x-slot:footer>
        @include('imports::imports.partials.actions', ['import' => $import])
    </x-slot:footer>
</x-forms::card>

@if($failures)
    <x-forms::card :title="__('Failed')" data-import-failures>
        <p class="text-sm text-muted-foreground">{{ __('These rows were not imported. Download the report, fix the rows and import it again.') }}</p>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-sm" data-slot="table">
                <thead>
                    <tr class="border-b">
                        <th class="p-2 text-start font-medium">{{ __('Row') }}</th>
                        <th class="p-2 text-start font-medium">{{ __('Errors') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($failures as $failure)
                        <tr class="border-b align-top">
                            <td class="p-2 tabular-nums text-muted-foreground">{{ $failure['row'] }}</td>
                            <td class="p-2">
                                <ul class="list-disc ps-4">
                                    @foreach($failure['messages'] as $message)
                                        <li>{{ $message }}</li>
                                    @endforeach
                                </ul>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-forms::card>
@endif
