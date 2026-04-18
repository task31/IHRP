@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-nav-link active'
            : 'inline-nav-link';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
