@props(['name'])
@php
    $paths = [
        'analytics' => 'M4 3v17h17 M8 15v-4 M13 15V7 M18 15V4',
        'visits' => 'M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z M16 3v4 M8 3v4 M3 11h18',
        'dashboard' => 'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',
        'patients' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M16 3a4 4 0 0 1 0 8 M22 21v-2a4 4 0 0 0-3-3.87 M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
        'staff' => 'M8 3h8v4H8z M8 5H5v16h14V5h-3 M8 12h8 M8 16h5',
        'collapse' => 'M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z M9 3v18',
        'logout' => 'M9 5H4v14h5 M9 12h12 M17 8l4 4-4 4',
    ];
@endphp
<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 shrink-0"><path d="{{ $paths[$name] ?? $paths['dashboard'] }}" /></svg>
