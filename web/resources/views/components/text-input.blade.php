@props(['disabled' => false])

<input
    @disabled($disabled)
    {{ $attributes->class(['field-control']) }}
    style="width:100%;"
>
