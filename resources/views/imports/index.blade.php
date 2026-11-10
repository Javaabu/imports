@extends(config('imports.layout', 'layouts.app'))

@section(config('imports.layout_section', 'content'))
    @manifestRequire('imports::imports')

    <div class="flex flex-col gap-6"
         data-imports
         data-broadcasting="{{ $broadcasting ? 'true' : 'false' }}"
         data-poll-interval="{{ $poll_interval }}"
         data-rows-template="{{ __(':processed of :total rows') }}">
        <h1 class="text-2xl font-semibold">{{ $title }}</h1>

        @include('imports::imports.partials.flash')

        @if($available)
            <x-forms::card :title="__('New import')">
                <x-forms::form method="POST" :action="route($routes['store'])" enctype="multipart/form-data" class="flex flex-wrap items-end gap-4" data-imports-upload>
                    <x-forms::select
                        name="type"
                        :label="__('Import')"
                        :options="collect($available)->pluck('label', 'type')->all()"
                        required />
                    <x-forms::file
                        name="file"
                        :label="__('File')"
                        :mimetypes="explode(',', $accept)"
                        :extensions="array_map(fn ($extension) => ltrim($extension, '.'), explode(',', $accept))"
                        :max-size="(int) config('imports.max_file_size', 51200)"
                        required>
                        <x-slot:help>{{ __('Excel (XLSX), CSV or OpenDocument (ODS), up to :size.', ['size' => \Illuminate\Support\Number::fileSize((int) config('imports.max_file_size', 51200) * 1024)]) }}</x-slot:help>
                    </x-forms::file>
                    <x-forms::submit>{{ __('Upload and map columns') }}</x-forms::submit>
                </x-forms::form>

                <x-slot:footer>
                    <div class="flex flex-col gap-2 text-sm">
                        <span class="text-muted-foreground">{{ __('Templates') }}</span>
                        <ul class="flex flex-wrap gap-x-6 gap-y-1">
                            @foreach($available as $importer)
                                <li class="flex flex-wrap items-center gap-2">
                                    <span class="font-medium">{{ $importer['label'] }}</span>
                                    @foreach($importer['templates'] as $format => $url)
                                        <a href="{{ $url }}" class="text-primary hover:underline" download>{{ strtoupper($format) }}</a>
                                    @endforeach
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </x-slot:footer>
            </x-forms::card>
        @endif

        <x-forms::form method="GET" :action="route($routes['index'])" class="flex flex-wrap items-end gap-4">
            <x-forms::select
                name="status"
                :label="__('Status')"
                :placeholder="__('All statuses')"
                show-placeholder
                :options="collect($statuses)->pluck('label', 'value')->all()"
                :default="$filters['status']" />
            @if($sees_others)
                <x-forms::checkbox name="mine" value="1" :label="__('Only my imports')" :default="$filters['mine']" />
            @endif
            <x-forms::submit color="secondary" :animate="false">{{ __('Filter') }}</x-forms::submit>
        </x-forms::form>

        <x-forms::table model="imports" no-checkbox>
            <x-slot:headers>
                <x-forms::table.heading :label="__('Name')" />
                <x-forms::table.heading :label="__('Status')" />
                <x-forms::table.heading :label="__('Progress')" />
                @if($sees_others)
                    <x-forms::table.heading :label="__('Started by')" />
                @endif
                <x-forms::table.heading :label="__('Created')" />
                <x-forms::table.heading :label="__('Actions')" />
            </x-slot:headers>

            <x-slot:rows>
                @forelse($imports as $import)
                    @include('imports::imports.partials.row', ['import' => $import])
                @empty
                    <tr data-slot="table-row">
                        <td colspan="{{ $sees_others ? 6 : 5 }}" class="p-6 text-center text-muted-foreground">
                            <p class="font-medium">{{ __('No imports yet') }}</p>
                            <p class="text-sm">{{ __('Upload a file to import it.') }}</p>
                        </td>
                    </tr>
                @endforelse
            </x-slot:rows>
        </x-forms::table>

        @if($imports->hasPages())
            <x-forms::pagination :paginator="$imports" />
        @endif
    </div>
@endsection
