<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold" style="color:var(--fg-1)">Placements</h2>
    </x-slot>

    <div class="py-4">
        @livewire('placement-manager')
    </div>
</x-app-layout>
