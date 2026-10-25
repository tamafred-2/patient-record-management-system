<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Medical certificate · RHU Calasiao</title>
    <style>
        @page { size: A4 landscape; margin: 14mm 18mm; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #eef2f1; color: #111; }
        .controls { max-width: 1050px; margin: 24px auto; padding: 0 20px; font: 14px/1.5 Arial, sans-serif; }
        .actions { display: flex; flex-wrap: wrap; gap: 20px; align-items: center; }
        button { border: 0; border-radius: 8px; padding: 12px 20px; background: #065f46; color: white; cursor: pointer; font: inherit; }
        a { color: #065f46; }
        .paper { background: white; width: 1123px; max-width: 100%; min-height: 794px; margin: 0 auto 32px; padding: 53px 68px; font: 17px/1.55 'Times New Roman', serif; box-shadow: 0 4px 24px #0001; }
        header { display: grid; grid-template-columns: 100px 1fr 100px; gap: 24px; align-items: center; text-align: center; }
        header img { width: 90px; height: 90px; object-fit: contain; }
        header p { margin: 0; line-height: 1.3; }
        h1 { text-align: center; font-size: 28px; line-height: 1.2; margin: 20px 0 0; }
        h2 { text-align: center; text-decoration: underline; font-size: 23px; margin: 0 0 27px; }
        .body-text { white-space: pre-wrap; overflow-wrap: anywhere; text-align: justify; text-indent: 36px; margin: 22px 0; }
        .salutation { font-weight: bold; margin-bottom: 18px; }
        .findings { white-space: pre-wrap; overflow-wrap: anywhere; margin-top: 20px; }
        .vitals { margin: 8px 0 20px; line-height: 1.45; }
        .bottom { display: grid; grid-template-columns: 1fr 330px; gap: 35px; margin-top: 25px; break-inside: avoid; }
        .remarks { white-space: pre-wrap; overflow-wrap: anywhere; }
        .signature { text-align: center; padding-top: 42px; overflow-wrap: anywhere; }
        .signature strong { display: block; border-top: 1px solid #222; padding-top: 5px; }
        .seal-note { margin-top: 22px; font-size: 12px; font-weight: bold; }
        @media screen and (max-width: 650px) { .paper { padding: 24px; font-size: 15px; } header { grid-template-columns: 60px 1fr 60px; gap: 10px; } header img { width: 60px; height: 60px; } h1 { font-size: 22px; } .bottom { grid-template-columns: 1fr; } }
        @media print { body { background: white; } .controls { display: none; } .paper { width: auto; max-width: none; min-height: 0; padding: 0; margin: 0; box-shadow: none; font-size: 12pt; line-height: 1.4; } header { grid-template-columns: 24mm 1fr 24mm; } header img { width: 23mm; height: 23mm; } h1 { font-size: 21pt; } h2 { font-size: 17pt; } .bottom { grid-template-columns: 1fr 80mm; margin-top: 18px; } h2 { margin-bottom: 20px; } .body-text { margin: 14px 0; } .signature { padding-top: 30px; } .seal-note { margin-top: 15px; } }
    </style>
    @if($preview ?? false)<style>body { overflow: hidden; } .paper { margin: 0; min-height: 0; padding-bottom: 28px; }</style>@endif
</head>
<body>
    @unless($preview ?? false)
    <nav class="controls"><div class="actions"><button type="button" onclick="window.print()">Print / Save as PDF</button><a href="{{ route('certificates.index', [$patient, $visit]) }}">Back to certificates</a></div><p>Copy #{{ $certificate->id }} · {{ $patient->patient_number }} · {{ $visit->visit_number }}. Prepared for manual signature and seal; printing does not record issuance. Choose a printer or Save as PDF in the print dialog. Use A4 landscape, 100% scale, and turn off browser headers/footers. Review Print Preview for longer text.</p></nav>
    @endunless
    <main class="paper">
        <header><img src="{{ asset('images/calasiao-seal.png') }}" alt="Municipality of Calasiao seal"><div><p>Republic of the Philippines</p><p>Province of Pangasinan</p><p>Municipality of Calasiao</p></div><img src="{{ asset('images/certificate-mho-logo.jpg') }}" alt="Municipal Health Office Calasiao logo"></header>
        <h1>MUNICIPAL HEALTH OFFICE</h1><h2>MEDICAL CERTIFICATE</h2>
        <p class="salutation">TO WHOM IT MAY CONCERN:</p>
        <p class="body-text" data-certificate-field="body">{{ $content['body'] }}</p>
        <p class="body-text" data-certificate-field="request_text">{{ $content['request_text'] }}</p>
        @if(($preview ?? false) || !empty($content['findings']))<div class="findings" data-certificate-findings @if(empty($content['findings'])) hidden @endif><strong>Findings:</strong> <span data-certificate-field="findings">{{ $content['findings'] ?? '' }}</span></div>@endif
        @php($measurements = array_filter(\Illuminate\Support\Arr::only($content, ['blood_pressure', 'pulse_rate', 'respiratory_rate', 'temperature']), fn ($value) => $value !== null && $value !== ''))
        @if((($preview ?? false) || $certificate->template === 'examination') && $measurements)
            <div class="vitals" data-certificate-vitals @if($certificate->template !== 'examination') hidden @endif><strong>Examination findings:</strong><br>
                @foreach(['blood_pressure' => ['Blood Pressure', 'mmHg'], 'pulse_rate' => ['Pulse Rate', '/ minute'], 'respiratory_rate' => ['Respiratory Rate', '/ minute'], 'temperature' => ['Temperature', '°C']] as $field => [$label, $unit])
                    @if(array_key_exists($field, $measurements)){{ $label }}: {{ $measurements[$field] }} {{ $unit }}<br>@endif
                @endforeach
            </div>
        @endif
        <div class="bottom"><div><strong>REMARKS:</strong><div class="remarks" data-certificate-field="remarks">{{ $content['remarks'] ?? '' }}</div></div><div class="signature"><strong data-certificate-field="signatory_name">{{ $content['signatory_name'] }}</strong><div data-certificate-field="signatory_title">{{ $content['signatory_title'] }}</div><div>License No.: <span data-certificate-field="license_number">{{ $content['license_number'] }}</span></div></div></div>
        <p class="seal-note">NOT VALID WITHOUT SEAL</p>
    </main>
</body>
</html>
