@extends('admin.imports.imports')

@section('content')
    @include('admin.imports._queued')
    @include('admin.imports._errors')
    @include('admin.imports._result')

    {!! Form::open(['method' => 'POST', 'route' => 'admin.imports.store', 'files' => true]) !!}
        @include('admin.imports._form')
    {!! Form::close() !!}
@endsection
