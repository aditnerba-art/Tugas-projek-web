@props([
    'tone' => 'error',
])

<div {{ $attributes->class(['ui-alert', 'ui-alert--'.$tone]) }}>
    {{ $slot }}
</div>
