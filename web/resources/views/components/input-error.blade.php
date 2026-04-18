@props(['messages'])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'field-error stack-xs']) }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
