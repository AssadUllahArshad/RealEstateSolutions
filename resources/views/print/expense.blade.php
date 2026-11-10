@extends('print.layout')
@section('content')
    <div class="document-heading"><div><p class="eyebrow">{{ __('Office expense voucher') }}</p><h1>{{ $record->number }}</h1></div><p class="version">{{ __('Posted record') }}</p></div>
    <section class="status recognized" aria-label="{{ __('Expense status') }}">
        <h2>{{ __('Expense posted') }}</h2>
        <p>{{ __('This immutable operational expense was posted on the Karachi business date shown below.') }}</p>
    </section>
    <section class="amount-box"><p>{{ __('Expense amount') }}</p><strong>{{ \App\Support\Pkr::format($record->amount) }}</strong></section>
    <dl class="details">
        <div><dt>{{ __('Business date') }}</dt><dd>{{ $record->business_date }}</dd></div>
        <div><dt>{{ __('Expense category') }}</dt><dd>{{ $record->category }}</dd></div>
        <div><dt>{{ __('Paid to') }}</dt><dd>{{ $record->payee ?: __('Not recorded') }}</dd></div>
        <div><dt>{{ __('Payment account at posting') }}</dt><dd>{{ $record->account_snapshot['code'] }} · {{ $record->account_snapshot['name'] }}</dd></div>
        <div><dt>{{ __('Reference') }}</dt><dd>{{ $record->reference ?: __('Not recorded') }}</dd></div>
        <div><dt>{{ __('Posted by') }}</dt><dd>{{ $record->poster->name }}</dd></div>
    </dl>
    <section class="section"><h2>{{ __('Verification note') }}</h2><p>{{ $record->note }}</p>
        <p class="note">{{ __('This voucher records an operational cashbook outflow. It is not a tax invoice, bank statement, general ledger entry or proof of the recipient’s acknowledgement.') }}</p>
    </section>
    <div class="signatures"><div>{{ __('Prepared / checked by') }}<span>{{ $record->poster->name }}</span></div><div>{{ __('Recipient acknowledgement') }}<span>{{ __('Signature / date') }}</span></div></div>
@endsection
