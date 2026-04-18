<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Resume Redact</h2>
    </x-slot>

    <div class="mx-auto max-w-3xl">
        <div class="card-base">
            <form method="POST" action="{{ route('resume.redact.process') }}" enctype="multipart/form-data" class="stack">
                @csrf

                <div>
                    <label for="resume" class="mb-2 block text-sm font-medium text-gray-700">Resume PDF</label>
                    <input
                        id="resume"
                        name="resume"
                        type="file"
                        accept=".pdf"
                        required
                        style="background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:8px 10px;color:var(--fg-1);font-size:13px;outline:none;width:100%;"
                    >
                    @error('resume')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <p class="mb-2 block text-sm font-medium text-gray-700">Header Mode</p>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <label class="cursor-pointer rounded-lg border border-gray-300 p-4 hover:border-gray-400">
                            <input type="radio" name="header_mode" value="text" class="mr-2" {{ old('header_mode', 'text') === 'text' ? 'checked' : '' }}>
                            <span class="font-medium text-gray-900">Text Header</span>
                            <p class="mt-2 text-sm text-[#c0392b]">MatchPointe Group</p>
                            <p class="text-xs text-gray-500">Top-left branded text</p>
                        </label>

                        @php($hasLogo = trim((string) $logoBase64) !== '')
                        <label
                            class="card-base" style="{{ $hasLogo ? 'cursor:pointer' : 'cursor:not-allowed;opacity:0.55' }}"
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
                            <span class="font-medium text-gray-900">Logo Header</span>
                            <div class="mt-2">
                                @if($hasLogo)
                                    <img src="{{ $logoBase64 }}" alt="MPG logo preview" class="max-h-10">
                                @else
                                    <p class="text-xs text-gray-500">Upload a logo in Settings -> Logo first</p>
                                @endif
                            </div>
                        </label>
                    </div>
                    @error('header_mode')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800"
                    >
                        Process &amp; Download
                    </button>
                    <p class="mt-3 text-sm text-gray-600">
                        Contact info (email, phone, address, LinkedIn) will be removed. The candidate's name is kept. Output is a branded PDF ready to send to clients.
                    </p>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
