@props([
    /** @var 'employees'|'monthly'|'yearly' $active */
    'active' => 'employees',
])

@php
    $tabClass = fn (bool $on) => $on
        ? 'border-indigo-600 text-indigo-600'
        : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700';
@endphp

<nav class="-mb-px mb-6 flex flex-wrap gap-4 border-b border-gray-200 text-sm font-medium" aria-label="Call report views">
    <a
        href="{{ route('calls.report') }}"
        @class(['border-b-2 pb-3', $tabClass($active === 'employees')])
    >By employee</a>
    <a
        href="{{ route('calls.report.monthly') }}"
        @class(['border-b-2 pb-3', $tabClass($active === 'monthly')])
    >By month</a>
    <a
        href="{{ route('calls.report.yearly') }}"
        @class(['border-b-2 pb-3', $tabClass($active === 'yearly')])
    >By year</a>
</nav>
