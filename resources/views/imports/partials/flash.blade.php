@if(session('success'))
    <x-forms::alert type="success">{{ session('success') }}</x-forms::alert>
@endif

@if(session('error'))
    <x-forms::alert type="danger">{{ session('error') }}</x-forms::alert>
@endif
