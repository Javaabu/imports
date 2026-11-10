@php($mapped = collect($preview['columns'])->whereNotNull('heading')->values())
<x-forms::card :title="__('Preview')" data-import-preview>
    <p class="text-sm text-muted-foreground">{{ __('The first :count rows, checked with this mapping. Nothing has been saved yet.', ['count' => $preview['counts']['checked']]) }}</p>

    <dl class="mt-4 flex flex-wrap gap-6 text-sm" data-import-preview-counts>
        <div><dt class="text-muted-foreground">{{ __('New') }}</dt><dd class="text-lg font-semibold tabular-nums">{{ number_format($preview['counts']['created']) }}</dd></div>
        <div><dt class="text-muted-foreground">{{ __('Updated') }}</dt><dd class="text-lg font-semibold tabular-nums">{{ number_format($preview['counts']['updated']) }}</dd></div>
        <div><dt class="text-muted-foreground">{{ __('Skipped') }}</dt><dd class="text-lg font-semibold tabular-nums">{{ number_format($preview['counts']['skipped']) }}</dd></div>
        <div><dt class="text-muted-foreground">{{ __('Errors') }}</dt><dd class="text-lg font-semibold tabular-nums text-destructive">{{ number_format($preview['counts']['invalid']) }}</dd></div>
    </dl>

    @if($preview['counts']['invalid'] === 0)
        <x-forms::alert type="success" class="mt-4">{{ __('No problems found in these rows.') }}</x-forms::alert>
    @endif

    <div class="mt-4 overflow-x-auto">
        <table class="w-full text-sm" data-slot="table">
            <thead>
                <tr class="border-b">
                    <th class="p-2 text-start font-medium">{{ __('Row') }}</th>
                    @foreach($mapped as $column)
                        <th class="p-2 text-start font-medium">{{ $column['label'] }}</th>
                    @endforeach
                    <th class="p-2 text-start font-medium">{{ __('Result') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($preview['rows'] as $row)
                    <tr @class(['border-b align-top', 'bg-destructive/5' => $row['errors']]) data-import-preview-row="{{ $row['row'] }}">
                        <td class="p-2 tabular-nums text-muted-foreground">{{ $row['row'] }}</td>
                        @foreach($mapped as $column)
                            <td @class(['p-2', 'text-destructive' => isset($row['errors'][$column['attribute']])]) dir="auto">{{ $row['values'][$column['attribute']] ?? '' }}</td>
                        @endforeach
                        <td class="p-2">
                            @if($row['errors'])
                                <ul class="list-disc ps-4 text-destructive">
                                    @foreach(collect($row['errors'])->flatten() as $message)
                                        <li>{{ $message }}</li>
                                    @endforeach
                                </ul>
                            @else
                                <x-forms::status :color="$row['outcome'] === 'skipped' ? 'secondary' : ($row['outcome'] === 'updated' ? 'info' : 'success')" :label="$row['outcome_label']" />
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-forms::card>
