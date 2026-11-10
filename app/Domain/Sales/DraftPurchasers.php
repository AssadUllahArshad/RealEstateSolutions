<?php

namespace App\Domain\Sales;

use App\Domain\CRM\Customers;
use App\Models\Booking;
use App\Models\DraftPurchaser;
use App\Models\DraftPurchaserEvent;
use App\Models\User;
use App\Rules\Percentage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class DraftPurchasers
{
    /** @param array<string,mixed> $input */
    public function save(User $actor, int $id, array $input): void
    {
        $data = Validator::make($input, [
            'version' => ['required', 'integer', 'min:0'],
            'purchasers' => ['required', 'array', 'min:1', 'max:20'],
            'purchasers.*' => ['required', 'array:customer_id,share'],
            'purchasers.*.customer_id' => ['required', 'integer', 'distinct'],
            'purchasers.*.share' => ['required', new Percentage],
            'actor_id' => ['prohibited'], 'booking_draft_id' => ['prohibited'],
        ])->validate();
        $rows = [];
        $total = '0.0000';
        foreach ($data['purchasers'] as $row) {
            if (bccomp($row['share'], '0', 4) <= 0) {
                throw ValidationException::withMessages(['purchasers' => 'Every purchaser must have a share greater than zero.']);
            }
            $share = bcadd($row['share'], '0', 4);
            $rows[] = ['customer_id' => (int) $row['customer_id'], 'share' => $share];
            $total = bcadd($total, $share, 4);
        }
        if (bccomp($total, '100', 4) !== 0) {
            throw ValidationException::withMessages(['purchasers' => 'Ownership shares must total exactly 100%.']);
        }
        usort($rows, fn ($a, $b) => $a['customer_id'] <=> $b['customer_id']);
        DB::transaction(function () use ($actor, $id, $data, $rows) {
            $fresh = User::query()->lockForUpdate()->findOrFail($actor->id);
            $drafts = app(BookingDrafts::class);
            $visible = $drafts->query($fresh)->findOrFail($id);
            $ids = array_values(array_unique([$visible->customer_id, ...array_column($rows, 'customer_id')]));
            sort($ids);
            $locked = [];
            foreach ($ids as $customerId) {
                $locked[$customerId] = app(Customers::class)->query($fresh)->whereKey($customerId)->lockForUpdate()->firstOrFail();
            }
            $identities = [];
            foreach ($rows as $row) {
                $customer = $locked[$row['customer_id']];
                if (! filled($customer->cnic) || ! filled($customer->address)) {
                    throw ValidationException::withMessages(['purchasers' => 'Complete CNIC and address for every selected purchaser before saving.']);
                }
                if (in_array($customer->cnic, $identities, true)) {
                    throw ValidationException::withMessages(['purchasers' => 'Two selected customer profiles have the same CNIC. Review the purchaser records.']);
                }
                $identities[] = $customer->cnic;
            }
            $draft = $drafts->query($fresh)->lockForUpdate()->findOrFail($id);
            if (Booking::where('booking_draft_id', $id)->lockForUpdate()->first()) {
                throw new ConflictHttpException('Confirmed purchasers cannot be replaced through a proposal.');
            }
            if ($draft->customer_id !== $visible->customer_id) {
                throw new ConflictHttpException('The sales contact changed while saving. Reload and review the purchaser list.');
            }
            $before = DB::table('draft_purchasers')->where('booking_draft_id', $id)->orderBy('customer_id')->get(['customer_id', 'share'])->map(fn ($row) => ['customer_id' => (int) $row->customer_id, 'share' => $row->share])->all();
            // A repeated successful submission is harmless; stale changed content is rejected.
            if ($before === $rows) {
                return;
            }
            if ($draft->purchasers_version !== (int) $data['version']) {
                throw new ConflictHttpException('Purchasers changed in another session. Reload before replacing them.');
            }
            DB::table('draft_purchasers')->where('booking_draft_id', $id)->delete();
            DB::table('draft_purchasers')->insert(array_map(fn ($row) => ['booking_draft_id' => $id, ...$row], $rows));
            $draft->purchasers_version++;
            $draft->save();
            DraftPurchaserEvent::create(['booking_draft_id' => $id, 'actor_id' => $fresh->id, 'version' => $draft->purchasers_version, 'before' => $before, 'after' => $rows]);
        }, attempts: 3);
    }

    /** @return list<array{customer_id:?int,name:string,share:string,identity_complete:bool}> */
    public function listing(User $actor, int $id): array
    {
        app(BookingDrafts::class)->query($actor)->findOrFail($id);
        $rows = DraftPurchaser::where('booking_draft_id', $id)->orderBy('customer_id')->get(['customer_id', 'share']);
        $customers = app(Customers::class)->query($actor)->whereIn('id', $rows->pluck('customer_id'))->get()->keyBy('id');
        $identities = $customers->pluck('cnic')->filter()->countBy();

        return array_values($rows->map(function ($row) use ($customers, $identities) {
            $customer = $customers->get($row->customer_id);

            return ['customer_id' => $customer?->id, 'name' => $customer->name ?? 'Customer access changed', 'share' => $row->share, 'identity_complete' => $customer && filled($customer->cnic) && filled($customer->address) && $identities->get($customer->cnic) === 1];
        })->all());
    }
}
