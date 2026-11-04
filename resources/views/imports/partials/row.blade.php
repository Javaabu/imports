<x-forms::table.row
    name="imports"
    :model-id="$import->uuid"
    no-checkbox
    data-import-row="{{ $import->uuid }}"
    data-import-url="{{ route($routes['show'], $import->uuid) }}"
    data-import-channel="{{ $broadcasting ? $import->broadcastChannel() : '' }}"
    data-import-finished="{{ $import->status->isFinished() || $import->isDraft() ? 'true' : 'false' }}">
    <x-forms::table.cell name="name" :label="__('Name')">
        <a href="{{ route($routes['show'], $import->uuid) }}" class="font-medium hover:underline">{{ $import->name }}</a>
        <div class="text-sm text-muted-foreground" dir="auto">{{ $import->filename }}</div>
    </x-forms::table.cell>
    <x-forms::table.cell name="status" :label="__('Status')">
        <span data-import-status>
            <x-forms::status :color="$import->status->color()" :label="$import->status->label()" />
        </span>
    </x-forms::table.cell>
    <x-forms::table.cell name="progress" :label="__('Progress')">
        @include('imports::imports.partials.progress', ['import' => $import])
    </x-forms::table.cell>
    @if($sees_others)
        <x-forms::table.cell name="user" :label="__('Started by')">{{ $import->user?->getAttribute('name') }}</x-forms::table.cell>
    @endif
    <x-forms::table.cell name="created_at" :label="__('Created')">
        <time datetime="{{ $import->created_at->toIso8601String() }}">{{ $import->created_at->diffForHumans() }}</time>
    </x-forms::table.cell>
    <x-forms::table.cell name="actions" :label="__('Actions')">
        @include('imports::imports.partials.actions', ['import' => $import, 'compact' => true])
    </x-forms::table.cell>
</x-forms::table.row>
