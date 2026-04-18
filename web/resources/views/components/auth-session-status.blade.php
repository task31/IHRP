@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'flash-banner flash-success']) }}>
        {{ $status }}
    </div>
@endif
