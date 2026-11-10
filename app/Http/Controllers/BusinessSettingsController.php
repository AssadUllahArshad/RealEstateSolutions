<?php

namespace App\Http\Controllers;

use App\Domain\Configuration\BusinessPolicies;
use App\Domain\Configuration\PolicyDefinitions;
use App\Models\BusinessPolicy;
use App\Models\BusinessPolicyEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class BusinessSettingsController extends Controller
{
    public function index(Request $request, BusinessPolicies $policies, string $section = 'booking'): Response
    {
        $policies->authorize($request->user());
        $definitions = PolicyDefinitions::all();
        abort_unless(isset($definitions[$section]), 404);
        $records = BusinessPolicy::all()->keyBy('key');
        $sections = [];
        foreach ($definitions as $key => $definition) {
            $record = $records->get($key);
            $values = $record->values ?? $policies->defaults($key);
            $sections[] = ['key' => $key, 'title' => $definition['title'], 'saved' => $record !== null, 'missing' => $policies->missing($key, $values)];
        }
        $record = $records->get($section);
        $values = $record->values ?? $policies->defaults($section);

        return Inertia::render('business-settings/index', ['section' => $section, 'definition' => $definitions[$section], 'sections' => $sections,
            'values' => $values, 'version' => $record->version ?? 0, 'missing' => $policies->missing($section, $values),
            'history' => BusinessPolicyEvent::where('policy_key', $section)->latest('version')->paginate(10),
        ]);
    }

    public function update(Request $request, BusinessPolicies $policies, string $section): RedirectResponse
    {
        try {
            $policies->save($request->user(), $section, $request->all());
        } catch (ConflictHttpException $exception) {
            return back()->withErrors(['conflict' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Business settings saved.']);

        return to_route('business-settings.section', $section);
    }
}
