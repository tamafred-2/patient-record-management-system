@props(['title', 'subtitle' => null, 'back' => null, 'patient', 'visit' => null, 'urgent' => false, 'tag' => null, 'printLabel' => 'Print', 'size' => 'A5'])
@php
    $age = $patient->birth_date ? (int) $patient->birth_date->diffInYears($visit?->visit_date ?? now('Asia/Manila')) : null;
    $address = collect([$patient->barangay, $patient->municipality, $patient->province])->filter()->join(', ');
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · RHU Calasiao</title>
    <style>
        @page { size: {{ $size === 'A4' ? 'A4' : 'A5' }}; margin: 12mm; }
        * { box-sizing: border-box; } body { font: 15px Arial, sans-serif; color: #18352d; margin: 24px; }
        .controls { max-width: {{ $size === 'A4' ? '820px' : '640px' }}; margin: 0 auto 24px; display: flex; gap: 20px; align-items: center; }
        button { background: #065f46; color: white; border: 0; border-radius: 6px; padding: 12px 20px; cursor: pointer; font: inherit; }
        a { color: #065f46; } .sheet { max-width: {{ $size === 'A4' ? '820px' : '640px' }}; border: 1px solid #87948f; padding: 22px; margin: auto; }
        header { display: flex; gap: 14px; align-items: center; border-bottom: 1px solid #ccd6d1; padding-bottom: 14px; }
        img { width: 56px; height: 56px; object-fit: contain; } h1 { font-size: 20px; margin: 0 0 4px; }
        .tag-note { border-color: #b45309 !important; color: #92400e !important; }
        .tag { display: inline-block; margin-top: 6px; border: 1px solid #b91c1c; color: #991b1b; border-radius: 999px; padding: 2px 10px; font-size: 12px; font-weight: bold; text-transform: uppercase; }
        dl { display: grid; grid-template-columns: 1fr 1fr; gap: 0 20px; margin: 0; } dt { color: #50645b; font-size: 12px; margin-top: 12px; } dd { margin: 3px 0 0; overflow-wrap: anywhere; }
        h2 { font-size: 15px; margin: 20px 0 8px; border-bottom: 1px solid #ccd6d1; padding-bottom: 4px; }
        ol, ul { margin: 0; padding-left: 22px; } li { padding: 4px 0; } .text { white-space: pre-wrap; overflow-wrap: anywhere; margin: 0; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; } th, td { text-align: left; padding: 6px 4px; border-bottom: 1px solid #e3e9e6; vertical-align: top; }
        thead { display: table-header-group; } tr { break-inside: avoid; } td, th { overflow-wrap: anywhere; } table { table-layout: fixed; }
        .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 28px; } .line { border-top: 1px solid #18352d; padding-top: 4px; font-size: 13px; margin-top: 34px; }
        .note { margin-top: 18px; font-size: 11px; color: #50645b; }
        @media print { .controls { display: none; } body { margin: 0; } .sheet { border: 0; padding: 0; } }
    </style>
</head>
<body>
    <nav class="controls"><button type="button" onclick="window.print()">{{ $printLabel }}</button>@if($back)<a href="{{ $back }}">Back</a>@endif</nav>
    <main class="sheet">
        <header>
            <img src="{{ asset('images/rhu-logo.jpg') }}" alt="RHU logo">
            <div><h1>RHU Calasiao · {{ $title }}</h1>@if($subtitle)<span>{{ $subtitle }}</span>@endif @if($urgent)<br><span class="tag">Emergency</span>@endif @if($tag)<br><span class="tag tag-note">{{ $tag }}</span>@endif</div>
        </header>
        <dl>
            <div><dt>Patient</dt><dd>{{ $patient->full_name }}</dd></div>
            <div><dt>Patient number</dt><dd>{{ $patient->patient_number }}</dd></div>
            <div><dt>Age / sex</dt><dd>{{ $age !== null ? $age.' years' : 'Not recorded' }} · {{ $patient->sex ?? 'Not recorded' }}</dd></div>
            <div><dt>Address</dt><dd>{{ $address ?: 'Not recorded' }}</dd></div>
            @if($visit)
                <div><dt>Visit</dt><dd>{{ $visit->visit_number }} · {{ $visit->visit_date->format('M j, Y') }}</dd></div>
                <div><dt>Queue number</dt><dd>{{ $visit->queue_reference ?? 'Not recorded' }}</dd></div>
            @endif
        </dl>
        {{ $slot }}
        <p class="note">Printed {{ now('Asia/Manila')->format('M j, Y g:i A') }} from the RHU Calasiao patient records system. This is not an official RHU form layout.</p>
    </main>
</body>
</html>
