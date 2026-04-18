@props(['value'])

<label {{ $attributes->merge(['class' => 'eyebrow']) }}>
    {{ $value ?? $slot }}
</label>
