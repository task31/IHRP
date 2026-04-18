@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['style' => 'background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius-md);padding:9px 12px;color:var(--fg-1);font-family:var(--font-sans);font-size:var(--fs-sm);outline:none;width:100%;transition:border-color 120ms,box-shadow 120ms;']) }}>
