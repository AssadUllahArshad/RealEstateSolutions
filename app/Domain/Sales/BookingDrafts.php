<?php

namespace App\Domain\Sales;

use App\Domain\CRM\Customers;
use App\Models\Booking;
use App\Models\BookingDraft;
use App\Models\BookingDraftEvent;
use App\Models\Plot;
use App\Models\User;
use App\Rules\PositiveDecimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class BookingDrafts
{
    /** @return Builder<BookingDraft> */
    public function query(User $actor): Builder
    {
        Gate::forUser($actor)->authorize('sales.drafts');
        Gate::forUser($actor)->authorize('inventory.view');

        return BookingDraft::whereIn('customer_id', app(Customers::class)->query($actor)->select('id'));
    }

    /** @param array<string,mixed> $input */
    public function save(User $actor, array $input, ?int $id = null): BookingDraft
    {
        return DB::transaction(function () use ($actor, $input, $id) {
            $fresh = User::query()->lockForUpdate()->findOrFail($actor->id);
            Gate::forUser($fresh)->authorize('sales.drafts');
            Gate::forUser($fresh)->authorize('inventory.view');
            $data = Validator::make($input, [
                'customer_id' => ['required', 'integer'], 'plot_id' => ['required', 'integer'],
                'quoted_price' => ['required', new PositiveDecimal], 'notes' => ['nullable', 'string', 'max:2000'],
                'creation_key' => [$id ? 'prohibited' : 'required', 'uuid'], 'version' => [$id ? 'required' : 'prohibited', 'integer', 'min:1'],
                'created_by' => ['prohibited'], 'catalogue_price_snapshot' => ['prohibited'], 'status' => ['prohibited'],
            ])->validate();
            $customer = app(Customers::class)->query($fresh)->whereKey($data['customer_id'])->lockForUpdate()->firstOrFail();
            $plot = Plot::query()->whereKey($data['plot_id'])->lockForUpdate()->firstOrFail();
            $values = ['customer_id' => $customer->id, 'plot_id' => $plot->id, 'quoted_price' => bcadd($data['quoted_price'], '0', 2), 'notes' => $data['notes'] ?? null];
            $hash = hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));
            if (! $id) {
                $existing = BookingDraft::where('created_by', $fresh->id)->where('creation_key', $data['creation_key'])->first();
                if ($existing) {
                    $this->query($fresh)->findOrFail($existing->id);
                    if ($existing->creation_hash !== $hash) {
                        throw new ConflictHttpException('This form was already saved with different details. Start a new draft.');
                    }

                    return $existing;
                }
            }
            $draft = $id ? $this->query($fresh)->lockForUpdate()->findOrFail($id) : new BookingDraft;
            if ($id && Booking::where('booking_draft_id', $id)->lockForUpdate()->first()) {
                throw new ConflictHttpException('This proposal is confirmed and cannot be edited.');
            }
            if ($id && $draft->version !== (int) $data['version']) {
                throw new ConflictHttpException('Someone updated this draft. Reload to review the latest version before editing.');
            }
            // Snapshot only when selecting a different plot; never silently reprice an existing draft.
            if (! $id || $draft->plot_id !== $plot->id) {
                $values['catalogue_price_snapshot'] = $plot->list_price;
            }
            $draft->fill($values);
            if ($id && ! $draft->isDirty()) {
                return $draft;
            }
            $draft->version = $id ? $draft->version + 1 : 1;
            if (! $id) {
                $draft->fill(['created_by' => $fresh->id, 'creation_key' => $data['creation_key'], 'creation_hash' => $hash]);
            }
            $draft->save();
            BookingDraftEvent::create(['booking_draft_id' => $draft->id, 'actor_id' => $fresh->id, 'version' => $draft->version,
                'details' => ['customer_id' => $draft->customer_id, 'plot_id' => $draft->plot_id, 'quoted_price' => $draft->quoted_price, 'catalogue_price_snapshot' => $draft->catalogue_price_snapshot, 'notes_changed' => $draft->wasChanged('notes')]]);

            return $draft;
        }, attempts: 3);
    }
}
