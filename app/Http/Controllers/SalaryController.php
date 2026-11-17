<?php

namespace App\Http\Controllers;

use App\Models\SalaryPayment;
use App\Support\BusinessClock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SalaryController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('finance.salaries.view');
        $month = $request->validate(['month' => ['nullable', 'date_format:Y-m']])['month'] ?? substr(app(BusinessClock::class)->today(), 0, 7);
        $query = SalaryPayment::query()->whereDate('salary_month', $month.'-01');

        return Inertia::render('finance/salaries', [
            'payments' => $query->with('recorder:id,name')->latest('paid_on')->latest('id')->get(),
            'month' => $month,
            'total' => bcadd((string) (clone $query)->sum('amount'), '0', 2),
            'businessDate' => app(BusinessClock::class)->today(),
            'canManage' => $request->user()->can('finance.salaries.manage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('finance.salaries.manage');
        $data = $request->validate([
            'employee_name' => ['required', 'string', 'max:120'],
            'salary_month' => ['required', 'date_format:Y-m'],
            'amount' => ['required', 'decimal:0,2', 'gt:0'],
            'paid_on' => ['required', 'date_format:Y-m-d'],
            'method' => ['required', 'in:cash,bank_transfer,easypaisa,jazzcash,other'],
            'reference' => ['nullable', 'string', 'max:160'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        SalaryPayment::create([
            ...$data,
            'salary_month' => $data['salary_month'].'-01',
            'employee_name' => trim($data['employee_name']),
            'recorded_by' => $request->user()->id,
        ]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Salary payment recorded.']);

        return back();
    }
}
