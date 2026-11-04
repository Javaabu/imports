@extends(config('imports.layout', 'layouts.app'))

@section(config('imports.layout_section', 'content'))
    @manifestRequire('imports::imports')

    <div class="flex flex-col gap-6"
         data-imports
         data-broadcasting="{{ $broadcasting ? 'true' : 'false' }}"
         data-poll-interval="{{ $poll_interval }}"
         data-rows-template="{{ __(':processed of :total rows') }}">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold">{{ $title }}</h1>
                <p class="text-sm text-muted-foreground" dir="auto">{{ $import->filename }}</p>
            </div>
            <x-forms::link-button :url="route($routes['index'])" color="secondary" size="sm" start-icon="chevron-left">
                {{ __('Back to imports') }}
            </x-forms::link-button>
        </div>

        @include('imports::imports.partials.flash')

        @if($import->isDraft())
            @include('imports::imports.partials.mapping')
        @else
            @include('imports::imports.partials.results')
        @endif
    </div>
@endsection
