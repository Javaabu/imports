<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'dv' ? 'rtl' : 'ltr' }}">
<head><title>{{ $title ?? '' }}</title></head>
<body>
@yield('content')
</body>
</html>
