<?php

namespace App\Domain\Inventory;

use App\Models\Booking;
use App\Models\InventoryEvent;
use App\Models\Plot;
use App\Models\PlotHold;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class Holds
{
    public function execute(User $actor, int $plotId, string $operation, string $key, string $reason, ?int $holdId = null): PlotHold
    {
        // The domain entry point validates too: jobs/CLI callers cannot bypass HTTP rules.
        $key = strtolower($key);
        $reason = trim($reason);
        Validator::make(compact('operation', 'key', 'reason', 'holdId'), [
            'operation' => ['required', Rule::in(['reservation', 'block', 'release'])],
            'key' => ['required', 'uuid'],
            'reason' => ['required', 'string', 'max:500'],
            'holdId' => [Rule::requiredIf($operation === 'release'), 'nullable', 'integer', 'min:1'],
        ])->validate();
        $hash = hash('sha256', json_encode([$operation, $plotId, $holdId, $reason], JSON_THROW_ON_ERROR));

        try {
            return DB::transaction(function () use ($actor, $plotId, $operation, $key, $reason, $holdId, $hash) {
                // Common plot lock protects all occupancy kinds; catalogue takes society then plot.
                Plot::query()->lockForUpdate()->findOrFail($plotId);
                $freshActor = User::findOrFail($actor->id);
                Gate::forUser($freshActor)->authorize('inventory.view');
                Gate::forUser($freshActor)->authorize('inventory.'.$operation);
                if ($operation !== 'reservation') {
                    abort_unless($freshActor->hasAnyRole(['super_admin', 'admin']), 403);
                }
                $command = DB::table('inventory_commands')->where('actor_id', $actor->id)->where('key', $key)->first();
                if ($command) {
                    if ($command->payload_hash !== $hash) {
                        throw new ConflictHttpException('This request key was already used for different details. Start a new action.');
                    }

                    return PlotHold::whereKey($command->plot_hold_id)->firstOrFail();
                }
                if ($operation === 'release') {
                    $hold = PlotHold::where('plot_id', $plotId)->whereKey($holdId)->lockForUpdate()->firstOrFail();
                    if ($hold->state === 'active') {
                        $hold->fill(['state' => 'released', 'released_by' => $actor->id, 'released_at' => now(), 'release_reason' => $reason])->save();
                        $this->audit($actor, $hold, 'released');
                    }
                } else {
                    if (Booking::where('plot_id', $plotId)->lockForUpdate()->first()) {
                        throw new ConflictHttpException('This plot has a confirmed booking and cannot be held or blocked.');
                    }
                    if (PlotHold::where('active_plot_id', $plotId)->exists()) {
                        throw new ConflictHttpException('This plot already has an active hold or block. Refresh to see its current state.');
                    }
                    $hold = PlotHold::create(['plot_id' => $plotId, 'kind' => $operation, 'state' => 'active', 'purpose' => $reason, 'created_by' => $actor->id]);
                    $this->audit($actor, $hold, 'created');
                }
                DB::table('inventory_commands')->insert(['actor_id' => $actor->id, 'key' => $key, 'payload_hash' => $hash, 'plot_hold_id' => $hold->id, 'created_at' => now()]);

                return $hold;
            }, attempts: 3);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                throw new ConflictHttpException('The plot or request key changed concurrently. Refresh and retry with the same request key.', $exception);
            }

            throw $exception;
        }
    }

    private function audit(User $actor, PlotHold $hold, string $event): void
    {
        InventoryEvent::create([
            'actor_id' => $actor->id, 'subject_type' => 'plot_hold', 'subject_id' => $hold->id,
            'event' => $hold->kind.'.'.$event,
            'before' => $event === 'released' ? ['state' => 'active'] : [],
            'after' => $hold->only(['plot_id', 'kind', 'state', 'purpose', 'created_by', 'released_by', 'release_reason', 'released_at']),
        ]);
    }
}
