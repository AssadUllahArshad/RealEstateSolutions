<?php

namespace App\Http\Controllers;

use App\Domain\Finance\OfficeExpenses;
use App\Models\FinancialAccount;
use App\Support\BusinessClock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class OfficeExpenseController extends Controller
{
    public function index(Request $request, OfficeExpenses $expenses): Response
    {
        $filters = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d']]);
        $query = $expenses->query($request->user())
            ->when($filters['from'] ?? null, fn ($builder, $date) => $builder->whereDate('business_date', '>=', $date))
            ->when($filters['to'] ?? null, fn ($builder, $date) => $builder->whereDate('business_date', '<=', $date));
        $total = (clone $query)->selectRaw('COALESCE(SUM(amount), 0) as total')->value('total');
        $totalValue = is_string($total) && is_numeric($total)
            ? bcadd($total, '0', 2)
            : (is_int($total) || is_float($total) ? number_format($total, 2, '.', '') : '0.00');

        return Inertia::render('finance/expenses', [
            'expenses' => $query->with('poster:id,name')->latest('business_date')->latest('id')->paginate(20)->withQueryString(),
            'total' => $totalValue, 'filters' => $filters,
            'accounts' => FinancialAccount::where('active', true)->orderBy('code')->get(['id', 'code', 'name', 'type']),
            'businessDate' => app(BusinessClock::class)->today(), 'commandKey' => (string) Str::uuid(),
            'canPost' => $request->user()->can('finance.expenses.post') && $request->user()->hasAnyRole(['super_admin', 'admin', 'accountant']),
        ]);
    }

    public function store(Request $request, OfficeExpenses $expenses): RedirectResponse
    {
        try {
            $record = $expenses->post($request->user(), $request->all());
        } catch (ConflictHttpException $exception) {
            return back()->withErrors(['conflict' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Office expense '.$record->number.' posted.']);

        return back();
    }
}
