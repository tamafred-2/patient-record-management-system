@php
    // DomPDF has remote loading disabled and no CSS grid support, so images are embedded and layout uses tables.
    $image = fn (string $file, string $type) => is_file(public_path('images/'.$file)) ? 'data:image/'.$type.';base64,'.base64_encode(file_get_contents(public_path('images/'.$file))) : null;
    $seal = $image('calasiao-seal.png', 'png');
    $logo = $image('certificate-mho-logo.jpg', 'jpeg');
    $measurements = array_filter(\Illuminate\Support\Arr::only($content, ['blood_pressure', 'pulse_rate', 'respiratory_rate', 'temperature']), fn ($value) => $value !== null && $value !== '');
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Medical certificate #{{ $certificate->id }}</title>
    <style>
        @page { margin: 14mm 18mm; }
        body { font: 12pt/1.4 'Times New Roman', serif; color: #111; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; }
        .header td { text-align: center; vertical-align: middle; }
        .header img { width: 23mm; height: 23mm; }
        .header p { margin: 0; line-height: 1.3; }
        h1 { text-align: center; font-size: 21pt; line-height: 1.2; margin: 16px 0 0; }
        h2 { text-align: center; text-decoration: underline; font-size: 17pt; margin: 0 0 20px; }
        .salutation { font-weight: bold; margin-bottom: 14px; }
        .body-text { white-space: pre-wrap; text-align: justify; text-indent: 36px; margin: 14px 0; }
        .findings, .remarks { white-space: pre-wrap; }
        .findings { margin-top: 16px; }
        .vitals { margin: 8px 0 16px; }
        .bottom { margin-top: 18px; page-break-inside: avoid; }
        .signature { width: 80mm; text-align: center; padding-top: 30px; }
        .signature strong { display: block; border-top: 1px solid #222; padding-top: 5px; }
        .seal-note { margin-top: 15px; font-size: 9pt; font-weight: bold; }
    </style>
</head>
<body>
    <table class="header"><tr>
        <td style="width: 26mm">@if($seal)<img src="{{ $seal }}" alt="Municipality of Calasiao seal">@endif</td>
        <td><p>Republic of the Philippines</p><p>Province of Pangasinan</p><p>Municipality of Calasiao</p></td>
        <td style="width: 26mm">@if($logo)<img src="{{ $logo }}" alt="Municipal Health Office Calasiao logo">@endif</td>
    </tr></table>
    <h1>MUNICIPAL HEALTH OFFICE</h1><h2>MEDICAL CERTIFICATE</h2>
    <p class="salutation">TO WHOM IT MAY CONCERN:</p>
    <p class="body-text">{{ $content['body'] }}</p>
    <p class="body-text">{{ $content['request_text'] }}</p>
    @if(!empty($content['findings']))<div class="findings"><strong>Findings:</strong> {{ $content['findings'] }}</div>@endif
    @if($certificate->template === 'examination' && $measurements)
        <div class="vitals"><strong>Examination findings:</strong><br>
            @foreach(['blood_pressure' => ['Blood Pressure', 'mmHg'], 'pulse_rate' => ['Pulse Rate', '/ minute'], 'respiratory_rate' => ['Respiratory Rate', '/ minute'], 'temperature' => ['Temperature', '°C']] as $field => [$label, $unit])
                @if(array_key_exists($field, $measurements)){{ $label }}: {{ $measurements[$field] }} {{ $unit }}<br>@endif
            @endforeach
        </div>
    @endif
    <table class="bottom"><tr>
        <td><strong>REMARKS:</strong><div class="remarks">{{ $content['remarks'] ?? '' }}</div></td>
        <td class="signature"><strong>{{ $content['signatory_name'] }}</strong><div>{{ $content['signatory_title'] }}</div><div>License No.: {{ $content['license_number'] }}</div></td>
    </tr></table>
    <p class="seal-note">NOT VALID WITHOUT SEAL</p>
</body>
</html>
