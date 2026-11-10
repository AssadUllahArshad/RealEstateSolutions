@extends('print.layout')
@section('content')
    <div class="document-heading"><div><p class="eyebrow">{{ __('Current principal statement') }}</p><h1>{{ __('Booking') }} #{{ $record->id }}</h1></div><p class="version">{{ __('As of') }} {{ $state['businessDate'] }} Asia/Karachi / {{ __('Ledger version') }} {{ $state['version'] }}</p></div>
    <section class="status recognized"><h2>{{ __('Live principal balances') }}</h2><p>{{ $record->snapshot['customer_name'] }} / {{ __('Plot') }} {{ $record->snapshot['plot']['number'] }}</p></section>
    <dl class="details">
        <div><dt>{{ __('Contract price') }}</dt><dd>{{ \App\Support\Pkr::format($record->net) }}</dd></div>
        <div><dt>{{ __('Paid principal including down payment') }}</dt><dd>{{ \App\Support\Pkr::format($state['paidPrincipal']) }}</dd></div>
        <div><dt>{{ __('Remaining principal') }}</dt><dd>{{ \App\Support\Pkr::format($state['outstanding']) }}</dd></div>
        <div><dt>{{ __('Overdue principal') }}</dt><dd>{{ \App\Support\Pkr::format($state['overdue']) }}</dd></div>
    </dl>
    <p class="note">{{ __("This statement covers this booking's principal only. It excludes unapplied customer credit, charges, refunds and adjustments. Due dates use Asia/Karachi business dates; overdue means unpaid before today. It is not a new receipt or a legal title document.") }}</p>
    <section class="section"><h2>{{ __('Current installment balances') }}</h2>
        @if (empty($state['schedule']))<p>{{ __('Full-payment plan. No scheduled balance.') }}</p>
        @else<table><thead><tr><th scope="col">{{ __('No. / Due date') }}</th><th scope="col" class="money">{{ __('Agreed') }}</th><th scope="col" class="money">{{ __('Paid') }}</th><th scope="col" class="money">{{ __('Remaining') }}</th><th scope="col">{{ __('Status') }}</th></tr></thead><tbody>
            @foreach ($state['schedule'] as $row)<tr><td>{{ $row['sequence'] }} / {{ $row['due_date'] }}</td><td class="money">{{ \App\Support\Pkr::format($row['amount']) }}</td><td class="money">{{ \App\Support\Pkr::format($row['paid']) }}</td><td class="money">{{ \App\Support\Pkr::format($row['remaining']) }}</td><td>{{ __($row['status']) }}</td></tr>@endforeach
        </tbody></table>@endif
    </section>
    <section class="section"><h2>{{ __('Down payment allocated at confirmation') }} ({{ $record->confirmed_on }})</h2><table><thead><tr><th scope="col">{{ __('Receipt') }}</th><th scope="col" class="money">{{ __('Applied principal') }}</th></tr></thead><tbody>
        @foreach ($downPayments as $payment)<tr><td>{{ $payment->number }}</td><td class="money">{{ \App\Support\Pkr::format($payment->amount) }}</td></tr>@endforeach
    </tbody></table></section>
    <section class="section"><h2>{{ __('Complete installment allocation history') }}</h2><p class="note">{{ __('Dates below are allocation dates, which may differ from receipt recognition dates. Amounts apply only to this booking.') }}</p>
        @if ($payments->isEmpty())<p>{{ __('No installment allocations recorded.') }}</p>
        @else<table><thead><tr><th scope="col">{{ __('Version / Business date') }}</th><th scope="col">{{ __('Receipt') }}</th><th scope="col" class="money">{{ __('Applied principal') }}</th></tr></thead><tbody>
            @foreach ($payments as $payment)<tr><td>{{ $payment->version }} / {{ $payment->business_date }}</td><td>{{ $payment->number }}</td><td class="money">{{ \App\Support\Pkr::format($payment->amount) }}</td></tr>@endforeach
        </tbody></table>@endif
    </section>
    @if ($adjustments->isNotEmpty())
        <section class="section"><h2>{{ __('Admin-approved allocation reversals') }}</h2><table><thead><tr><th scope="col">{{ __('Date / Correction') }}</th><th scope="col">{{ __('Installment / Receipt') }}</th><th scope="col">{{ __('Admin') }}</th><th scope="col" class="money">{{ __('Restored customer credit') }}</th></tr></thead><tbody>
            @foreach ($adjustments as $adjustment)<tr><td>{{ $adjustment->business_date }} / #{{ $adjustment->id }}</td><td>{{ $adjustment->sequence }} / {{ $adjustment->number }}</td><td>{{ $adjustment->decider_name }}</td><td class="money">{{ \App\Support\Pkr::format($adjustment->amount) }}</td></tr>@endforeach
        </tbody></table></section>
    @endif
@endsection
