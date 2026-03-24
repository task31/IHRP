<div x-ignore wire:key="timesheet-wizard-root" class="space-y-4 text-sm text-gray-800">
    @if ($step === 1)
        <div>
            <p class="text-gray-600">Upload an .xlsx or .csv timesheet (official bi-weekly template or flat CSV with column mapping).</p>
            <input type="file" wire:model="file" accept=".xlsx,.csv,.txt" class="mt-2 block w-full text-sm" />
            @error('file')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
            <div wire:loading wire:target="file" class="mt-2 text-xs text-gray-500">Reading file…</div>
            <button type="button" wire:click="uploadFile" wire:loading.attr="disabled"
                class="mt-3 rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                Parse &amp; continue
            </button>
        </div>
    @endif

    @if ($step === 2)
        @if ($parseFormat === 'flat-csv' && count($builtRows) === 0)
            <div class="space-y-3">
                <p class="font-medium text-gray-700">Map CSV columns</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block text-xs text-gray-500">Consultant name
                        <select wire:model.live="mapConsultant" class="mt-1 w-full rounded border border-gray-300 px-2 py-1">
                            <option value="">—</option>
                            @foreach ($parseColumns as $col)
                                <option value="{{ $col }}">{{ $col }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-xs text-gray-500">Pay period start
                        <select wire:model.live="mapPayStart" class="mt-1 w-full rounded border border-gray-300 px-2 py-1">
                            <option value="">—</option>
                            @foreach ($parseColumns as $col)
                                <option value="{{ $col }}">{{ $col }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-xs text-gray-500">Pay period end
                        <select wire:model.live="mapPayEnd" class="mt-1 w-full rounded border border-gray-300 px-2 py-1">
                            <option value="">—</option>
                            @foreach ($parseColumns as $col)
                                <option value="{{ $col }}">{{ $col }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-xs text-gray-500">Total hours (optional — distributes across 14 days)
                        <select wire:model.live="mapTotalHours" class="mt-1 w-full rounded border border-gray-300 px-2 py-1">
                            <option value="">—</option>
                            @foreach ($parseColumns as $col)
                                <option value="{{ $col }}">{{ $col }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <button type="button" wire:click="applyFlatMapping"
                    class="rounded bg-gray-800 px-3 py-1.5 text-sm text-white hover:bg-gray-900">
                    Build preview
                </button>
            </div>
        @endif

        @if (count($builtRows) > 0)
            <div>
                <p class="mb-2 font-medium">{{ count($builtRows) }} row(s) ready to import</p>
                <div class="max-h-56 overflow-auto rounded border border-gray-200">
                    <table class="min-w-full text-xs">
                        <thead class="bg-gray-50 text-left">
                            <tr>
                                <th class="px-2 py-1">Consultant</th>
                                <th class="px-2 py-1">Period</th>
                                <th class="px-2 py-1">Reg hrs</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($builtRows as $i => $br)
                                @php
                                    $c = \App\Models\Consultant::query()->find($br['consultantId'] ?? 0);
                                    $reg =
                                        (float) array_sum($br['week1Hours'] ?? []) + (float) array_sum($br['week2Hours'] ?? []);
                                @endphp
                                <tr class="border-t border-gray-100" wire:key="br-{{ $i }}">
                                    <td class="px-2 py-1">{{ $c?->full_name ?? '—' }}</td>
                                    <td class="px-2 py-1">{{ \App\Support\PayPeriodFormatter::formatRange($br['payPeriodStart'] ?? null, $br['payPeriodEnd'] ?? null) }}</td>
                                    <td class="px-2 py-1">{{ number_format($reg, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" wire:click="$set('step', 1)"
                        class="rounded border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-50">Back</button>
                    <button type="button" wire:click="confirmImport" wire:loading.attr="disabled"
                        class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                        Import all
                    </button>
                </div>
            </div>
        @endif
    @endif

    @if ($step === 3)
        <p class="text-gray-600">Saving timesheets…</p>
        <div wire:loading.delay class="text-xs text-gray-500">Working</div>
    @endif

    @if ($step === 4 && $importResult)
        <div class="rounded border border-green-200 bg-green-50 p-3 text-sm text-green-900">
            <p class="font-medium">Import finished</p>
            <p class="mt-1">Saved: {{ $importResult['saved'] }}, Overwrote: {{ $importResult['overwrote'] }}</p>
            @if (! empty($importResult['errors']))
                <ul class="mt-2 list-inside list-disc text-xs text-amber-900">
                    @foreach (array_slice($importResult['errors'], 0, 12) as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
        <button type="button" wire:click="finishImport"
            class="mt-3 rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
            Done
        </button>
    @endif

    @if (count($parseErrors) > 0)
        <div class="rounded border border-red-200 bg-red-50 p-3 text-xs text-red-800">
            <ul class="list-inside list-disc">
                @foreach ($parseErrors as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
