@if (file_exists(public_path('hot')) || file_exists(public_path('build/manifest.json')))
    @if ($withStyles ?? false)
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        @vite('resources/js/app.js')
    @endif
@endif
