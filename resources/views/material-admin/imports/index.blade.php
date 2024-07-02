@extends('imports::material-admin.imports.imports')

@section('content')
    @include('imports::material-admin.imports._queued')
    @include('imports::material-admin.imports._errors')
    @include('imports::material-admin.imports._result')

    <x-forms::form method="POST" action="{{ $store_route_url }}" :files="true">
        @include('imports::material-admin.imports._form')
    </x-forms::form>
@endsection
