<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Midwife monthly report {{ $start->format('F Y') }} · RHU Calasiao</title>
    @vite(['resources/css/app.css'])
    <style>
        @page { size: A4; margin: 14mm; }
        @media print { .no-print { display: none; } body { background: white; } .report-card { break-inside: avoid; border-color: #cbd5e1; } }
    </style>
</head>
<body class="bg-stone-50 p-6 text-slate-900">
    <main class="mx-auto max-w-4xl">
        <nav class="no-print mb-6 flex items-center gap-4"><x-button type="button" onclick="window.print()">Print report</x-button><a href="{{ route('midwife-report.index', ['month' => $month]) }}" class="text-emerald-800 underline">Back</a></nav>
        <header class="flex items-center gap-4 border-b border-slate-300 pb-4">
            <img src="{{ asset('images/rhu-logo.jpg') }}" alt="RHU logo" class="h-14 w-14 object-contain">
            <div><h1 class="text-xl font-bold">RHU Calasiao · Midwife monthly report</h1><p class="text-slate-600">{{ $start->format('F Y') }} · counts only</p></div>
        </header>
        @include('midwife-report.tables')
        <p class="mt-6 text-xs text-slate-500">Printed {{ now('Asia/Manila')->format('M j, Y g:i A') }} by {{ auth()->user()->name }} from the RHU Calasiao patient records system. This is not an official DOH/FHSIS report form.</p>
    </main>
</body>
</html>
