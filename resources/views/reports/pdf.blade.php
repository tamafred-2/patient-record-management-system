<!doctype html>
<html><head><meta charset="utf-8"><title>RHU Service activity report</title>
@include('reports.styles')
<style>@page { margin: 30px 32px 42px; } body { font-family: DejaVu Sans, sans-serif; } .service-report { font-size: 9px; } h1 { font-size: 20px; margin: 0 0 6px; } .logo { float: right; width: 48px; height: 48px; } .service-report h2 { page-break-after: avoid; } .service-report th, .service-report td { padding: 6px; }</style>
</head><body class="service-report">
@if(is_file(public_path('images/rhu-logo.jpg')))<img class="logo" alt="RHU logo" src="data:image/jpeg;base64,{{ base64_encode(file_get_contents(public_path('images/rhu-logo.jpg'))) }}">@endif
<h1>RHU Calasiao</h1><p><strong>Service activity report</strong></p>
@include('reports.content')
</body></html>
