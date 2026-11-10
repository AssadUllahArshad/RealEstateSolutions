<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ur' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>{{ $title }} Â· Data Real Estate</title>
    <link rel="stylesheet" href="/branding/documents.css">
    <script src="/branding/documents.js" defer></script>
</head>
<body>
    <nav class="toolbar" aria-label="{{ __('Document controls') }}">
        <a href="{{ $backUrl }}">{{ __('Back to record') }}</a>
        <span>{{ __('Choose your printer or Save as PDF in the print dialog.') }}</span>
        <button type="button" id="print-document">{{ __('Print / Save PDF') }}</button>
    </nav>
    <main class="document">
        <header class="letterhead">
            <img src="/branding/data-real-estate.svg" width="90" height="96" alt="Data Real Estate">
            <div><p class="company">Data Real Estate</p><p class="society">Green Vista Housing Scheme</p></div>
            <img src="/branding/green-vista.svg" width="135" height="96" alt="Green Vista Housing Scheme">
        </header>
        @if (!empty($issuer['name']) || !empty($issuer['office_name']))
            <div class="issuer">
                <strong>{{ __('Issuer on record:') }}</strong> {{ $issuer['office_name'] ?? '' }}{{ !empty($issuer['office_name']) && !empty($issuer['name']) ? ' Â· ' : '' }}{{ $issuer['name'] ?? '' }}
                @if (!empty($issuer['address']))<div>{{ $issuer['address'] }}</div>@endif
                @if (!empty($issuer['phone']) || !empty($issuer['email']))<div>{{ $issuer['phone'] ?? '' }} {{ $issuer['email'] ?? '' }}</div>@endif
            </div>
        @endif
        @yield('content')
        <footer class="document-footer">
            <p>{{ __('Current status copy') }} Â· {{ __('Generated') }} {{ $generatedAt }}</p>
            <p>{{ __('Amounts are in Pakistani rupees. Business dates use Asia/Karachi. Check the CRM record for later changes.') }}</p>
        </footer>
    </main>
</body>
</html>

