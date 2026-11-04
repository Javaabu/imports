{{-- The mapping step: choose the field each column fills, preview, then run --}}
@php($can_update = auth()->user()?->can('update', $import))

@if(isset($preview['error']))
    <x-forms::alert type="danger">{{ $preview['error'] }}</x-forms::alert>
@else
    @php($columns = collect($preview['columns']))
    @php($field_options = $columns->mapWithKeys(fn ($column) => [$column['attribute'] => $column['label'] . ($column['required'] ? ' *' : '')])->all())

    <x-forms::form method="POST" :action="route($routes['run'], $import->uuid)" class="flex flex-col gap-6" data-import-mapping>
        <x-forms::card :title="__('Map columns')">
            <p class="text-sm text-muted-foreground">{{ __('Choose the field each column of your file fills. Columns set to "Don\'t import" are ignored.') }}</p>

            @if($preview['missing'])
                <x-forms::alert type="warning" class="mt-4">
                    {{ __('Choose a column for these required fields:') }}
                    {{ $columns->whereIn('attribute', $preview['missing'])->pluck('label')->join(', ') }}
                </x-forms::alert>
            @endif

            @if($errors->has('mapping.*'))
                <x-forms::alert type="danger" class="mt-4">
                    <ul class="list-disc ps-4">
                        @foreach($errors->get('mapping.*') as $messages)
                            @foreach($messages as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        @endforeach
                    </ul>
                </x-forms::alert>
            @endif

            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm" data-slot="table">
                    <thead>
                        <tr class="border-b text-start">
                            <th class="p-2 text-start font-medium">{{ __('Column in your file') }}</th>
                            <th class="p-2 text-start font-medium">{{ __('Sample values') }}</th>
                            <th class="p-2 text-start font-medium">{{ __('Field') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($preview['headings'] as $heading)
                            <tr class="border-b align-top" data-import-heading="{{ $heading['index'] }}">
                                <td class="p-2 font-medium" dir="auto">{{ $heading['heading'] !== '' ? $heading['heading'] : '—' }}</td>
                                <td class="p-2 text-muted-foreground">
                                    @foreach($heading['samples'] as $sample)
                                        <div class="max-w-64 truncate" dir="auto">{{ $sample }}</div>
                                    @endforeach
                                </td>
                                <td class="p-2">
                                    <x-forms::select
                                        :name="'mapping[' . $heading['index'] . ']'"
                                        :label="__('Field')"
                                        :show-label="false"
                                        :placeholder="__('Don\'t import')"
                                        show-placeholder
                                        :options="$field_options"
                                        :default="$heading['attribute']"
                                        :disabled="! $can_update" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-forms::card>

        <x-forms::card>
            <div class="flex flex-wrap items-end gap-6">
                <x-forms::select
                    name="duplicates"
                    :label="__('Existing records')"
                    :options="collect($duplicate_options)->pluck('label', 'value')->all()"
                    :default="$import->duplicates->value" />
                <input type="hidden" name="dry_run" value="0">
                <x-forms::checkbox name="dry_run" value="1" :label="__('Only check the file, don\'t save anything')" :default="$import->dry_run" />
            </div>

            @if($can_update)
                <x-slot:footer>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-forms::submit color="secondary" :animate="false" name="intent" value="preview" data-import-preview-button>
                            {{ __('Preview') }}
                        </x-forms::submit>
                        <x-forms::submit :disabled="! $preview['ready']">{{ __('Start import') }}</x-forms::submit>
                    </div>
                </x-slot:footer>
            @endif
        </x-forms::card>
    </x-forms::form>

    @if($preview['ready'])
        @include('imports::imports.partials.preview')
    @endif
@endif

<div>
    @include('imports::imports.partials.actions', ['import' => $import])
</div>
