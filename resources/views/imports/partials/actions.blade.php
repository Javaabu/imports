@php($user = auth()->user())
<div class="flex flex-wrap items-center gap-2" data-import-actions>
    @if(! empty($compact))
        <x-forms::link-button :url="route($routes['show'], $import->uuid)" color="secondary" size="sm">
            {{ $import->isDraft() && $user?->can('update', $import) ? __('Map columns') : __('View') }}
        </x-forms::link-button>
    @endif

    @if($import->hasReport() && $user?->can('download', $import))
        <x-forms::link-button :url="route($routes['report'], $import->uuid)" size="sm" start-icon="download">
            {{ __('Download failed rows') }}
        </x-forms::link-button>
    @endif

    @if(empty($compact) && in_array($import->status, [\Javaabu\Imports\Enums\ImportStatuses::FAILED, \Javaabu\Imports\Enums\ImportStatuses::CANCELLED], true) && $user?->can('update', $import))
        <x-forms::form method="POST" :action="route($routes['run'], $import->uuid)">
            <x-forms::submit color="secondary" size="sm" :animate="false">{{ __('Try again') }}</x-forms::submit>
        </x-forms::form>
    @endif

    @if($import->isCancellable() && $user?->can('cancel', $import))
        <x-forms::form method="POST" :action="route($routes['cancel'], $import->uuid)" data-confirm="{{ __('Cancel this import? Rows already saved stay saved.') }}">
            <x-forms::submit color="secondary" size="sm" :animate="false">{{ __('Cancel') }}</x-forms::submit>
        </x-forms::form>
    @endif

    @if($user?->can('delete', $import))
        <x-forms::form method="DELETE" :action="route($routes['destroy'], $import->uuid)" data-confirm="{{ __('Delete this import and its files?') }}">
            <x-forms::submit color="danger" size="sm" :animate="false">{{ __('Delete') }}</x-forms::submit>
        </x-forms::form>
    @endif
</div>
