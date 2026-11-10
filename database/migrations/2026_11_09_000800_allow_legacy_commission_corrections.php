<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE commission_events DROP CHECK commission_events_valid');
        DB::statement("ALTER TABLE commission_events ADD CONSTRAINT commission_events_valid CHECK (percentage >= 0 AND percentage <= 100 AND ((source_kind IN ('booking_allocation','installment_batch') AND eligible_amount > 0 AND commission_amount >= 0) OR (source_kind = 'customer_refund' AND eligible_amount < 0 AND commission_amount <= 0) OR (source_kind IN ('legacy_correction','legacy_correction_debit','legacy_correction_credit') AND eligible_amount = 0)))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE commission_events DROP CHECK commission_events_valid');
        DB::statement("ALTER TABLE commission_events ADD CONSTRAINT commission_events_valid CHECK (percentage >= 0 AND percentage <= 100 AND ((source_kind IN ('booking_allocation','installment_batch') AND eligible_amount > 0 AND commission_amount >= 0) OR (source_kind = 'customer_refund' AND eligible_amount < 0 AND commission_amount <= 0) OR (source_kind = 'legacy_correction' AND eligible_amount = 0)))");
    }
};
