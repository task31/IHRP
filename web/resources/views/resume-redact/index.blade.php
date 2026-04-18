<x-app-layout>
    <x-slot name="header">
        <h2 style="font-size:22px;font-weight:700;letter-spacing:-0.01em;color:var(--fg-1);">Resume Redact</h2>
    </x-slot>

    <div style="max-width:768px;">
        <div class="card-base">
            <form method="POST" action="{{ route('resume.redact.process') }}" enctype="multipart/form-data" class="stack">
                @csrf

                <div class="field">
                    <x-input-label for="resume" :value="__('Resume PDF')" />
                    <input id="resume" name="resume" type="file" accept=".pdf" required>
                    @error('resume')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <div class="eyebrow" style="margin-bottom:8px;">Header Mode</div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
                        <label class="card-soft" style="cursor:pointer;">
                            <input type="radio" name="header_mode" value="text" class="mr-2" {{ old('header_mode', 'text') === 'text' ? 'checked' : '' }}>
                            <span class="copy-strong">Text Header</span>
                            <p style="margin-top:8px;font-size:14px;color:var(--brand-300);">MatchPointe Group</p>
                            <p class="field-help">Top-left branded text.</p>
                        </label>

                        @php($hasLogo = trim((string) $logoBase64) !== '')
                        <label
                            class="card-soft" style="{{ $hasLogo ? 'cursor:pointer;' : 'cursor:not-allowed;opacity:0.55;' }}"
                            title="{{ $hasLogo ? 'Use uploaded MPG logo' : 'Upload a logo in Settings -> Logo first' }}"
                        >
                            <input
                                type="radio"
                                name="header_mode"
                                value="logo"
                                class="mr-2"
                                {{ old('header_mode') === 'logo' ? 'checked' : '' }}
                                {{ $hasLogo ? '' : 'disabled' }}
                            >
                            <span class="copy-strong">Logo Header</span>
                            <div style="margin-top:8px;">
                                @if($hasLogo)
                                    <img src="{{ $logoBase64 }}" alt="MPG logo preview" class="max-h-10">
                                @else
                                    <p class="field-help">Upload a logo in Settings → Logo first.</p>
                                @endif
                            </div>
                        </label>
                    </div>
                    @error('header_mode')
                        <p class="field-error" style="margin-top:8px;">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <button type="submit" class="btn btn-primary">Process &amp; Download</button>
                    <p style="margin-top:12px;font-size:13px;color:var(--fg-3);">
                        Contact info (email, phone, address, LinkedIn) will be removed. The candidate's name is kept. Output is a branded PDF ready to send to clients.
                    </p>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
