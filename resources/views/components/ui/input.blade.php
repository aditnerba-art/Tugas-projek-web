@props([
    'id',
    'label',
    'type' => 'text',
    'help' => null,
])

<div class="ui-field">
    <label class="ui-label" for="{{ $id }}">{{ $label }}</label>
    <input
        id="{{ $id }}"
        type="{{ $type }}"
        @if ($help) aria-describedby="{{ $id }}-help" @endif
        {{ $attributes->class(['ui-input']) }}
    >
    @if ($help)
        <p id="{{ $id }}-help" class="ui-help">{{ $help }}</p>
    @endif
</div>
