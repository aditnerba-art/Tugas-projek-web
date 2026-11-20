@if (file_exists(public_path('hot')) || file_exists(public_path('build/manifest.json')))
    @vite('resources/js/app.js')
@endif
