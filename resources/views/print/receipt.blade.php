@extends('print.layout')
@section('content')
    <div class="document-heading"><div><p class="eyebrow">{{ __('Customer receipt') }}</p><h1>{{ $record->number }}</h1></div><p class="version">{{ __('Record version') }} {{ $record->version }}</p></div>
    <section class="status {{ $record->state === 'reversed' ? 'reversed' : ($recognized ? 'recognized' : 'pending') }}" aria-label="{{ __('Receipt status') }}">
        <h2>{{ __($status) }}</h2>
        @if ($record->state === 'reversed')
            <p>{{ __('Reversed on :date. The original face amount below is retained for reference. This copy provides no valid payment credit and does not confirm a cash refund.', ['date' => $record->reversed_on]) }}</p>
        @elseif (!$recognized)
            <p>{{ __('The cheque has been received for processing. Funds are not recognized and cannot support a booking payment until the required bank clearance is recorded.') }}</p>
        @elseif ($record->method === 'cheque' && $record->bank_stage !== 'cleared')
            <p>{{ __('Recognized on deposit under the policy captured at issue. Bank clearance has not been recorded.') }}</p>
        @else
            <p>{{ __('Payment recognition recorded on :date.', ['date' => $record->recognized_on]) }}{{ $record->method === 'cheque' ? ' '.__('Bank clearance recorded on :date.', ['date' => $record->cleared_on]) : '' }}</p>
        @endif
    </section>
    <section class="amount-box"><p>{{ $record->state === 'reversed' ? __('Original face amount') : __('Receipt amount') }}</p><strong>{{ \App\Support\Pkr::format($record->amount) }}</strong></section>
    <dl class="details">
        <div><dt>{{ __('Received from') }}</dt><dd>{{ $record->snapshot['customer_name'] }}</dd></div>
        <div><dt>{{ __('Accepted business date') }}</dt><dd>{{ $record->accepted_on }}</dd></div>
        <div><dt>{{ __('Payment method') }}</dt><dd>{{ __($method) }}</dd></div>
        <div><dt>{{ __('Destination account at issue') }}</dt><dd>{{ $record->snapshot['posted_account']['code'] }} · {{ $record->snapshot['posted_account']['name'] }}</dd></div>
        <div><dt>{{ __('External reference') }}</dt><dd>{{ $record->reference ?: __('Not provided / cash') }}</dd></div>
        <div><dt>{{ __('Bank stage') }}</dt><dd>{{ $record->method === 'cheque' ? ($record->bank_stage === 'cleared' ? 'Cleared' : 'Deposited — not bank-cleared') : __('Not applicable') }}</dd></div>
    </dl>
    <section class="section"><h2>{{ __('Payment credit at preview') }}</h2><table><tbody>
        <tr><th scope="row">{{ __('Recognized credit') }}</th><td class="money">{{ \App\Support\Pkr::format($recognized ? $record->amount : '0.00') }}</td></tr>
        <tr><th scope="row">{{ __('Allocated to bookings and installments') }}</th><td class="money">{{ \App\Support\Pkr::format($allocated) }}</td></tr>
        <tr><th scope="row">{{ __('Unallocated customer credit') }}</th><td class="money">{{ \App\Support\Pkr::format($unallocated) }}</td></tr>
    </tbody></table><p class="note">{{ __('This receipt alone does not confirm a plot booking, ownership, a paid-in-full contract or an account balance.') }}</p></section>
    @if (empty($issuer))<p class="note">{{ __('Office profile details were not captured at issue for this receipt.') }}</p>@endif
    <div class="signatures"><div>{{ __('Received / checked by') }}<span>{{ __('Authorized staff') }} #{{ $record->accepted_by }}</span></div><div>{{ __('Customer acknowledgement') }}<span>{{ __('Signature / date') }}</span></div></div>
@endsection
