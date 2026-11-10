<?php

namespace App\Domain\Inventory;

use App\Models\Block;
use App\Models\Booking;
use App\Models\InventoryEvent;
use App\Models\Plot;
use App\Models\PlotOwner;
use App\Models\PlotSize;
use App\Models\Society;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class Catalogue
{
    /** @param array<string, mixed> $data */
    public function society(User $actor, array $data): Society
    {
        return $this->save($actor, Society::findOrFail(1), $data, 'configuration.manage', 'society');
    }

    /** @param array<string, mixed> $data */
    public function block(User $actor, array $data, ?Block $block = null): Block
    {
        return $this->save($actor, $block ?? new Block, $data, 'configuration.manage', 'block');
    }

    /** @param array<string, mixed> $data */
    public function size(User $actor, array $data, ?PlotSize $size = null): PlotSize
    {
        return $this->save($actor, $size ?? new PlotSize, $data, 'configuration.manage', 'plot_size');
    }

    /** @param array<string, mixed> $data */
    public function plot(User $actor, array $data, ?Plot $plot = null): Plot
    {
        if (! empty($data['plot_owner_id']) && ($data['owner_percentage'] ?? null) === null) {
            $data['owner_percentage'] = PlotOwner::query()->whereKey($data['plot_owner_id'])->firstOrFail()->default_percentage;
        }
        if (empty($data['plot_owner_id'])) {
            $data['owner_percentage'] = null;
        }

        return $this->save($actor, $plot ?? new Plot, $data, 'inventory.manage', 'plot');
    }

    /**
     * @template T of Model
     *
     * @param  T  $model
     * @param  array<string, mixed>  $data
     * @return T
     */
    private function save(User $actor, Model $model, array $data, string $ability, string $type): Model
    {
        try {
            return DB::transaction(function () use ($actor, $model, $data, $ability, $type) {
                $record = clone $model;
                // One installation-wide catalogue lock serializes setup edits and duplicate creation.
                $society = Society::query()->lockForUpdate()->findOrFail(1);
                Gate::forUser(User::findOrFail($actor->id))->authorize($ability);
                if ($type !== 'society' && $society->name === null) {
                    throw ValidationException::withMessages(['society' => 'Set up the society profile before adding inventory.']);
                }
                $created = ! $record->exists;
                if (! $created) {
                    $record->refresh();
                    if ($type === 'plot' && Booking::where('plot_id', $record->getKey())->lockForUpdate()->first()) {
                        throw ValidationException::withMessages(['number' => 'Booked plot catalogue details are frozen. Use a controlled correction workflow.']);
                    }
                }
                $before = $created ? [] : $record->only(array_keys($data));
                $record->fill($data);
                if (! $created && ! $record->isDirty()) {
                    return $record;
                }
                if ($created) {
                    $record->setAttribute('created_by', $actor->id);
                }
                $record->setAttribute('updated_by', $actor->id);
                $record->save();
                InventoryEvent::create([
                    'actor_id' => $actor->id,
                    'subject_type' => $type,
                    'subject_id' => $record->getKey(),
                    'event' => $type.($created ? '.created' : '.updated'),
                    'before' => $before,
                    'after' => $record->only(array_keys($data)),
                ]);

                return $record;
            }, attempts: 3);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) !== 1062) {
                throw $exception;
            }
            $field = match ($type) {
                'block' => 'code', 'plot_size' => 'label', default => 'number'
            };
            throw ValidationException::withMessages([$field => 'This identifier is already in use. Plot numbers must be unique within their block.']);
        }
    }
}
