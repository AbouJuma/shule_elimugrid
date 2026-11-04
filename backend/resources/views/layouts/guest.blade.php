<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'ElimuGrid')</title>
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height:100vh; background:#0a1f44;">
    <div class="w-100" style="max-width: 420px;">
        @yield('content')
    </div>
</body>
</html>
