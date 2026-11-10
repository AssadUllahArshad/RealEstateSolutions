@extends('print.layout')
@section('content')
    <div class="document-heading"><div><p class="eyebrow">{{ __('Booking confirmation') }}</p><h1>Booking #{{ $record->id }}</h1></div><p class="version">{{ __('Confirmed') }} {{ $record->confirmed_on }}</p></div>
    <section class="status recognized"><h2>{{ __('Booking confirmed') }}</h2><p>Block {{ $record->snapshot['plot']['block']['code'] }} / Plot {{ $record->snapshot['plot']['number'] }} Â· {{ $record->snapshot['plot']['size']['label'] }}</p></section>
    <dl class="details">
        <div><dt>{{ __('Sales contact at confirmation') }}</dt><dd>{{ $record->snapshot['customer_name'] }}</dd></div>
        <div><dt>{{ __('Contract price after discount') }}</dt><dd>{{ \App\Support\Pkr::format($record->net) }}</dd></div>
        <div><dt>{{ __('Gross agreed price') }}</dt><dd>{{ \App\Support\Pkr::format($record->snapshot['terms']['gross']) }}</dd></div>
        <div><dt>{{ __('Admin-authorized discount') }}</dt><dd>{{ \App\Support\Pkr::format($record->snapshot['terms']['discount']) }}</dd></div>
        <div><dt>{{ __('Allocated down payment') }}</dt><dd>{{ \App\Support\Pkr::format($record->down_payment) }}</dd></div>
        <div><dt>{{ __('Original scheduled balance') }}</dt><dd>{{ \App\Support\Pkr::format($record->snapshot['terms']['balance']) }}</dd></div>
    </dl>
    <section class="section"><h2>{{ __('Purchasers on the confirmed booking') }}</h2><table><thead><tr><th scope="col">{{ __('Name at confirmation') }}</th><th scope="col" class="money">{{ __('Ownership share') }}</th></tr></thead><tbody>
        @foreach ($record->snapshot['purchasers'] as $person)<tr><td>{{ $person['name'] }}</td><td class="money">{{ $person['share'] }}%</td></tr>@endforeach
    </tbody></table></section>
    <section class="section"><h2>{{ __('Allocated down payment sources') }}</h2><table><thead><tr><th scope="col">{{ __('Official receipt') }}</th><th scope="col" class="money">{{ __('Allocated amount') }}</th></tr></thead><tbody>
        @foreach ($allocations as $allocation)<tr><td>{{ $allocation->number }}</td><td class="money">{{ \App\Support\Pkr::format($allocation->amount) }}</td></tr>@endforeach
    </tbody></table></section>
    <section class="section"><h2>{{ __('Agreed installment schedule') }}</h2>
        @if (empty($record->snapshot['terms']['schedule']))<p>{{ __('Full-payment plan. No scheduled balance.') }}</p>
        @else<table><thead><tr><th scope="col">Installment</th><th scope="col">{{ __('Due business date') }}</th><th scope="col" class="money">{{ __('Agreed amount') }}</th></tr></thead><tbody>
            @foreach ($record->snapshot['terms']['schedule'] as $row)<tr><td>{{ $row['sequence'] }}</td><td>{{ $row['due_date'] }}</td><td class="money">{{ \App\Support\Pkr::format($row['amount']) }}</td></tr>@endforeach
        </tbody></table>@endif
        <p class="note">{{ __('This is the original agreed schedule, not a current outstanding statement. Later collections, charges or adjustments are not represented here.') }}</p>
    </section>
    <section class="section"><h2>{{ __('Evidence references at confirmation') }}</h2><ul class="evidence">@foreach ($record->snapshot['documents'] as $document)<li>{{ $documentLabels[$document['kind']] ?? 'Supporting document' }} #{{ $document['id'] }}</li>@endforeach</ul><p class="note">{{ __('Document references identify retained office records; they do not reproduce signatures or identity documents.') }}</p></section>
    <p class="note">{{ __('This confirms the recorded booking terms. It is not a registered title, conveyance deed or ownership transfer. Supporting signed documents remain part of the office record.') }}</p>
    <div class="signatures"><div>{{ __('Authorized office representative') }}<span>{{ __('Confirmed by staff') }} #{{ $record->confirmed_by }} Â· signature / date</span></div><div>{{ __('Purchaser acknowledgement') }}<span>{{ __('Signature(s) / date') }}</span></div></div>
@endsection

