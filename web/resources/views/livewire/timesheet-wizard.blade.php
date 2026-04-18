<div x-ignore wire:key="timesheet-wizard-root" class="stack-sm" style="color:var(--fg-2);">
    @if ($step === 1)
        <div class="stack-sm">
            <div class="surface-muted" style="padding:16px;">
                <div class="eyebrow">Upload Source</div>
                <p style="margin-top:6px;font-size:13px;color:var(--fg-3);">Upload an `.xlsx` or `.csv` timesheet. Use the official bi-weekly template or a flat CSV with column mapping.</p>
            </div>

            <div class="field">
                <x-input-label for="timesheet_file" :value="__('Timesheet File')" />
                <input id="timesheet_file" type="file" wire:model="file" accept=".xlsx,.csv,.txt" />
                @error('file')
                    <p class="field-error">{{ $message }}</p>
                @enderror
                <div wire:loading wire:target="file" class="field-help">Reading file…</div>
            </div>

            <div>
                <button type="button" wire:click="uploadFile" wire:loading.attr="disabled" class="btn btn-primary">
                    Parse &amp; Continue
                </button>
            </div>
        </div>
    @endif

    @if ($step === 2)
        @if ($parseFormat === 'flat-csv' && count($builtRows) === 0)
            <div class="stack-sm">
                <div>
                    <div class="eyebrow">Column Mapping</div>
                    <p style="margin-top:6px;font-size:13px;color:var(--fg-3);">Map the flat CSV headers to the fields needed for import preview.</p>
                </div>

                <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));">
                    <label class="field">
                        <span class="eyebrow">Consultant Name</span>
                        <select wire:model.live="mapConsultant">
                            <option value="">—</option>
                            @foreach ($parseColumns as $col)
                                <option value="{{ $col }}">{{ $col }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field">
                        <span class="eyebrow">Pay Period Start</span>
                        <select wire:model.live="mapPayStart">
                            <option value="">—</option>
                            @foreach ($parseColumns as $col)
                                <option value="{{ $col }}">{{ $col }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field">
                        <span class="eyebrow">Pay Period End</span>
                        <select wire:model.live="mapPayEnd">
                            <option value="">—</option>
                            @foreach ($parseColumns as $col)
                                <option value="{{ $col }}">{{ $col }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field">
                        <span class="eyebrow">Total Hours</span>
                        <select wire:model.live="mapTotalHours">
                            <option value="">—</option>
                            @foreach ($parseColumns as $col)
                                <option value="{{ $col }}">{{ $col }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div>
                    <button type="button" wire:click="applyFlatMapping" class="btn btn-secondary">Build Preview</button>
                </div>
            </div>
        @endif

        @if (count($builtRows) > 0)
            <div class="stack-sm">
                <div class="surface-info" style="padding:14px 16px;">
                    <div class="eyebrow" style="color:var(--accent-300);">Preview Ready</div>
                    <p style="margin-top:6px;font-size:13px;color:var(--fg-2);">{{ count($builtRows) }} row(s) are ready to import.</p>
                </div>

                <div class="table-wrap" style="max-height:240px;">
                    <table class="table" style="font-size:12px;">
                        <thead>
                            <tr>
                                <th>Consultant</th>
                                <th>Period</th>
                                <th style="text-align:right;">Reg Hrs</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($builtRows as $i => $br)
                                @php
                                    $c = \App\Models\Consultant::query()->find($br['consultantId'] ?? 0);
                                    $reg = (float) array_sum($br['week1Hours'] ?? []) + (float) array_sum($br['week2Hours'] ?? []);
                                @endphp
                                <tr wire:key="br-{{ $i }}">
                                    <td style="color:var(--fg-1);font-weight:500;">{{ $c?->full_name ?? '—' }}</td>
                                    <td>{{ \App\Support\PayPeriodFormatter::formatRange($br['payPeriodStart'] ?? null, $br['payPeriodEnd'] ?? null) }}</td>
                                    <td class="mono-num" style="text-align:right;">{{ number_format($reg, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div style="display:flex;flex-wrap:wrap;gap:8px;">
                    <button type="button" wire:click="$set('step', 1)" class="btn btn-secondary">Back</button>
                    <button type="button" wire:click="confirmImport" wire:loading.attr="disabled" class="btn btn-primary">Import All</button>
                </div>
            </div>
        @endif
    @endif

    @if ($step === 3)
        <div class="surface-muted" style="padding:16px;">
            <div class="eyebrow">Importing</div>
            <p style="margin-top:6px;font-size:13px;color:var(--fg-2);">Saving timesheets…</p>
            <div wire:loading.delay class="field-help" style="margin-top:6px;">Working</div>
        </div>
    @endif

    @if ($step === 4 && $importResult)
        <div class="stack-sm">
            <div class="flash-banner flash-success">
                <p class="copy-strong">Import finished</p>
                <p style="margin-top:6px;">Saved: {{ $importResult['saved'] }}, Overwrote: {{ $importResult['overwrote'] }}</p>
                @if (! empty($importResult['errors']))
                    <ul style="margin-top:10px;padding-left:18px;font-size:12px;color:var(--warn-400);">
                        @foreach (array_slice($importResult['errors'], 0, 12) as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div>
                <button type="button" wire:click="finishImport" class="btn btn-primary">Done</button>
            </div>
        </div>
    @endif

    @if (count($parseErrors) > 0)
        <div class="flash-banner flash-error">
            <ul style="padding-left:18px;">
                @foreach ($parseErrors as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
