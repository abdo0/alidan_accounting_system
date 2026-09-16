<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    <style>
        body { font-size: 9pt; }
        /* Logical properties are not supported by mPDF, so the direction-specific
           rules live here rather than in the shared stylesheet. */
        .doc-title { font-size: 14pt; font-weight: bold; margin-bottom: 2mm; }
        .doc-meta  { font-size: 8pt; color: #555; margin-bottom: 4mm; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1.6mm 2mm; border-bottom: 0.2mm solid #ddd; }
        th { background: #f2f2f2; font-weight: bold; }
        .num { text-align: {{ $isRtl ? 'left' : 'right' }}; font-family: dejavusans; }
        .txt { text-align: {{ $isRtl ? 'right' : 'left' }}; }
        .total td { border-top: 0.4mm solid #333; font-weight: bold; border-bottom: none; }
    </style>
</head>
<body>
    <div class="doc-title">@yield('title')</div>
    <div class="doc-meta">@yield('meta')</div>
    @yield('content')
</body>
</html>
