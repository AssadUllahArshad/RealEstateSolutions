<?php

namespace App\Http\Controllers;

use App\Domain\Finance\AgentCommissions;
use App\Models\Booking;
use App\Models\CommissionEvent;
use App\Models\CommissionPayment;
use App\Models\FinancialAccount;
use App\Models\LegacyCommissionAssignment;
use App\Models\User;
use App\Support\BusinessClock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AgentCommissionController extends Controller
{
    public function index(Request $request, AgentCommissions $service): Response
    {
        $user = $request->user();
        abort_unless($user->can('finance.commissions.view'), 403);
        $filters = $request->validate(['agent_id' => ['nullable', 'integer', 'exists:users,id'], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from']]);
        $agents = User::role('sales_agent')->orderBy('name')->get(['id', 'name']);
        $agentCards = $agents->map(fn (User $agent) => ['id' => $agent->id, 'name' => $agent->name, 'payable' => $service->payable($agent->id)]);
        $active = LegacyCommissionAssignment::where('state', 'active')->get();
        $assigned = $active->pluck('booking_id');
        $bookings = Booking::whereIn('id', $assigned)->get()->keyBy('id');
        $legacy = Booking::whereNull('commission_agent_id')->whereNotIn('id', $assigned)->latest('id')->limit(200)->get()->map(fn (Booking $booking) => ['id' => $booking->id, 'label' => '#'.$booking->id.' · '.$booking->snapshot['customer_name'].' · '.$booking->snapshot['plot']['number'], 'confirmed_on' => $booking->confirmed_on]);
        $activeLegacy = $active->map(function (LegacyCommissionAssignment $assignment) use ($bookings, $agents): array {
            $booking = $bookings->get($assignment->booking_id);

            return ['booking_id' => $assignment->booking_id, 'label' => '#'.$assignment->booking_id.' · '.$booking->snapshot['customer_name'].' · '.$booking->snapshot['plot']['number'], 'agent_name' => $agents->firstWhere('id', $assignment->agent_id)?->name, 'percentage' => $assignment->percentage, 'effective_from' => $assignment->effective_from];
        });
        $events = CommissionEvent::query()->when($filters['agent_id'] ?? null, fn ($query, $id) => $query->where('agent_id', $id))->when($filters['from'] ?? null, fn ($query, $date) => $query->where('business_date', '>=', $date))->when($filters['to'] ?? null, fn ($query, $date) => $query->where('business_date', '<=', $date))->latest('id')->limit(200)->get();
        $payments = CommissionPayment::query()->when($filters['agent_id'] ?? null, fn ($query, $id) => $query->where('agent_id', $id))->when($filters['from'] ?? null, fn ($query, $date) => $query->where('business_date', '>=', $date))->when($filters['to'] ?? null, fn ($query, $date) => $query->where('business_date', '<=', $date))->latest('id')->paginate(20)->withQueryString();
        $summary = ['earned' => $this->numeric(CommissionEvent::sum('commission_amount')), 'paid' => $this->numeric(CommissionPayment::where('state', 'approved')->sum('amount')), 'payable' => '0.00', 'recoverable' => '0.00'];
        foreach ($agentCards as $card) {
            $cardPayable = $this->numeric($card['payable']);
            if (bccomp($cardPayable, '0.00', 2) >= 0) {
                $summary['payable'] = bcadd($this->numeric($summary['payable']), $cardPayable, 2);
            } else {
                $summary['recoverable'] = bcadd($this->numeric($summary['recoverable']), bcmul($cardPayable, '-1', 2), 2);
            }
        }
        $corrections = LegacyCommissionAssignment::whereNotNull('supersedes_id')->latest('id')->limit(50)->get()->map(fn (LegacyCommissionAssignment $assignment) => ['id' => $assignment->id, 'booking_id' => $assignment->booking_id, 'agent_name' => $agents->firstWhere('id', $assignment->agent_id)?->name, 'percentage' => $assignment->percentage, 'effective_from' => $assignment->effective_from, 'reason' => $assignment->reason, 'created_at' => $assignment->created_at]);

        return Inertia::render('finance/commissions', [
            'agents' => $agentCards, 'events' => $events, 'payments' => $payments, 'legacyBookings' => $legacy, 'activeLegacyAssignments' => $activeLegacy, 'corrections' => $corrections, 'summary' => $summary,
            'filters' => ['agent_id' => (string) ($filters['agent_id'] ?? ''), 'from' => $filters['from'] ?? '', 'to' => $filters['to'] ?? ''],
            'accounts' => FinancialAccount::where('active', true)->whereIn('type', ['cash', 'bank', 'wallet'])->orderBy('code')->get(['id', 'code', 'name', 'type']),
            'businessDate' => app(BusinessClock::class)->today(), 'commandKey' => (string) Str::uuid(), 'decisionKey' => (string) Str::uuid(),
            'canPrepare' => $user->can('finance.commissions.prepare') && $user->hasRole('accountant'), 'canApprove' => $user->can('finance.commissions.approve') && $user->hasAnyRole(['admin', 'super_admin']),
            'canConfigure' => $user->can('finance.commissions.configure') && $user->hasAnyRole(['admin', 'super_admin']), 'canCorrect' => $user->can('finance.commissions.correct') && $user->hasRole('super_admin'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->can('finance.commissions.view'), 403);
        $filters = $request->validate(['agent_id' => ['nullable', 'integer', 'exists:users,id'], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from']]);
        $agents = User::role('sales_agent')->get(['id', 'name'])->keyBy('id');
        $events = CommissionEvent::query()->when($filters['agent_id'] ?? null, fn ($query, $id) => $query->where('agent_id', $id))->when($filters['from'] ?? null, fn ($query, $date) => $query->where('business_date', '>=', $date))->when($filters['to'] ?? null, fn ($query, $date) => $query->where('business_date', '<=', $date))->orderBy('business_date')->orderBy('id');
        $payments = CommissionPayment::where('state', 'approved')->when($filters['agent_id'] ?? null, fn ($query, $id) => $query->where('agent_id', $id))->when($filters['from'] ?? null, fn ($query, $date) => $query->where('business_date', '>=', $date))->when($filters['to'] ?? null, fn ($query, $date) => $query->where('business_date', '<=', $date))->orderBy('business_date')->orderBy('id');

        return response()->streamDownload(function () use ($events, $payments, $agents): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                throw new \RuntimeException('Unable to open CSV output.');
            }
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Business date', 'Agent', 'Entry type', 'Booking', 'Reference', 'Eligible principal (PKR)', 'Rate (%)', 'Commission earned/reversed (PKR)', 'Payment (PKR)'], ',', '"', '');
            foreach ($events->lazy(500) as $event) {
                fputcsv($stream, [$event->business_date, $this->spreadsheetText($agents->get($event->agent_id)?->name), 'event', $event->booking_id, $event->source_kind.'#'.$event->source_id, $event->eligible_amount, $event->percentage, $event->commission_amount, '0.00'], ',', '"', '');
            }
            foreach ($payments->lazy(500) as $payment) {
                fputcsv($stream, [$payment->business_date, $this->spreadsheetText($agents->get($payment->agent_id)?->name), 'approved_payment', '', $payment->number, '0.00', '', '0.00', $payment->amount], ',', '"', '');
            }
            fclose($stream);
        }, 'estateflow-agent-commissions.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function assignLegacy(Request $request, int $booking, AgentCommissions $service): RedirectResponse
    {
        try {
            $service->assignLegacy($request->user(), $booking, $request->all());
        } catch (ConflictHttpException $exception) {
            return back()->withErrors(['conflict' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Legacy commission assignment recorded and eligible collections calculated.']);

        return back();
    }

    public function correctLegacy(Request $request, int $booking, AgentCommissions $service): RedirectResponse
    {
        try {
            $service->correctLegacy($request->user(), $booking, $request->all());
        } catch (ConflictHttpException $exception) {
            return back()->withErrors(['conflict' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Legacy commission corrected with compensating entries.']);

        return back();
    }

    public function store(Request $request, AgentCommissions $service): RedirectResponse
    {
        try {
            $payment = $service->prepare($request->user(), $request->all());
        } catch (ConflictHttpException $exception) {
            return back()->withErrors(['conflict' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Commission payment '.$payment->number.' prepared.']);

        return back();
    }

    public function decide(Request $request, CommissionPayment $payment, AgentCommissions $service): RedirectResponse
    {
        try {
            $payment = $service->decide($request->user(), $payment, $request->all());
        } catch (ConflictHttpException $exception) {
            return back()->withErrors(['conflict' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Commission payment '.$payment->number.' '.$payment->state.'.']);

        return back();
    }

    /** @return numeric-string */
    private function numeric(mixed $value): string
    {
        return is_numeric((string) $value) ? bcadd((string) $value, '0', 2) : '0.00';
    }

    private function spreadsheetText(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return preg_match('/^[\s\x00-\x20]*[=+@-]|^[\t\r\n]/u', $value) === 1 ? "'".$value : $value;
    }
}
