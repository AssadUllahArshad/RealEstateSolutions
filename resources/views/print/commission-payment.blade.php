@extends('print.layout')
@section('content')
<div class="document-heading"><div><p class="eyebrow">{{ __('Agent commission payment') }}</p><h1>{{ $record->number }}</h1></div><p class="version">{{ __('Approved record') }}</p></div>
<section class="status recognized"><h2>{{ __('Payment authorized') }}</h2><p>{{ __('This voucher records an approved payment against earned agent commission.') }}</p></section>
<section class="amount-box"><p>{{ __('Commission paid') }}</p><strong>{{ \App\Support\Pkr::format($record->amount) }}</strong></section>
<dl class="details"><div><dt>{{ __('Sales agent') }}</dt><dd>{{ $record->agent_snapshot['name'] }}</dd></div><div><dt>{{ __('Business date') }}</dt><dd>{{ $record->business_date }}</dd></div><div><dt>{{ __('Payment account') }}</dt><dd>{{ $record->account_snapshot['code'] }} · {{ $record->account_snapshot['name'] }}</dd></div><div><dt>{{ __('Reference') }}</dt><dd>{{ $record->reference ?: __('Not recorded') }}</dd></div><div><dt>{{ __('Available before request') }}</dt><dd>{{ \App\Support\Pkr::format($record->payable_snapshot) }}</dd></div></dl>
<section class="section"><h2>{{ __('Verification') }}</h2><p>{{ $record->verification_note }}</p><p><strong>{{ __('Admin decision') }}:</strong> {{ $record->decision_note }}</p></section>
<div class="signatures"><div>{{ __('Authorized by') }}<span>{{ __('Admin signature / date') }}</span></div><div>{{ __('Agent acknowledgement') }}<span>{{ __('Signature / date') }}</span></div></div>
@endsection
