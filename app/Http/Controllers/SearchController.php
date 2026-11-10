<?php

namespace App\Http\Controllers;

use App\Domain\CRM\Customers;
use App\Models\Plot;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function __invoke(Request $request, Customers $customers): Response
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:160']]);
        $q = trim($data['q'] ?? '');
        $literal = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q).'%';

        return Inertia::render('search', ['q' => $q,
            'plots' => $q !== '' && $request->user()->can('inventory.view') ? Plot::with(['block:id,code', 'size:id,label', 'activeHold:id,plot_id,kind'])->whereRaw("number LIKE ? ESCAPE '!'", [$literal])->orderBy('number')->limit(20)->get() : [],
            'customers' => $q !== '' && $request->user()->can('customers.view') ? $customers->query($request->user())->whereRaw("name LIKE ? ESCAPE '!'", [$literal])->orderBy('name')->limit(20)->get(['id', 'name', 'phone']) : [],
        ]);
    }
}
