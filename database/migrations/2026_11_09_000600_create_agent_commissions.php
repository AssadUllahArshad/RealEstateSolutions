<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
return new class extends Migration {
 public function up(): void {
  Schema::table('bookings',function(Blueprint $t){$t->foreignId('commission_agent_id')->nullable()->after('confirmed_by')->constrained('users')->restrictOnDelete();$t->decimal('commission_percentage',7,4)->nullable()->after('commission_agent_id');$t->json('commission_policy_snapshot')->nullable()->after('commission_percentage');});
  Schema::create('commission_events',function(Blueprint $t){$t->id();$t->foreignId('booking_id')->constrained()->restrictOnDelete();$t->foreignId('agent_id')->constrained('users')->restrictOnDelete();$t->string('source_kind',32);$t->unsignedBigInteger('source_id');$t->decimal('eligible_amount',18,2);$t->decimal('percentage',7,4);$t->decimal('commission_amount',18,2);$t->date('business_date');$t->json('policy_snapshot');$t->timestamp('created_at')->useCurrent();$t->unique(['source_kind','source_id']);$t->index(['agent_id','business_date']);});
  DB::statement("ALTER TABLE commission_events ADD CONSTRAINT commission_events_valid CHECK (percentage >= 0 AND percentage <= 100 AND ((source_kind IN ('booking_allocation','installment_batch') AND eligible_amount > 0 AND commission_amount >= 0) OR (source_kind = 'customer_refund' AND eligible_amount < 0 AND commission_amount <= 0)))");
  Schema::create('commission_payments',function(Blueprint $t){$t->id();$t->foreignId('agent_id')->constrained('users')->restrictOnDelete();$t->foreignId('financial_account_id')->constrained()->restrictOnDelete();$t->string('number',40)->unique();$t->decimal('amount',18,2);$t->date('business_date');$t->string('state',20);$t->text('reference')->nullable();$t->text('verification_note');$t->json('agent_snapshot');$t->json('account_snapshot');$t->decimal('payable_snapshot',18,2);$t->foreignId('prepared_by')->constrained('users')->restrictOnDelete();$t->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();$t->text('decision_note')->nullable();$t->timestamp('decided_at')->nullable();$t->uuid('command_key');$t->char('payload_hash',64);$t->uuid('decision_key')->nullable()->unique();$t->timestamps();$t->unique(['prepared_by','command_key']);$t->index(['agent_id','state']);});
  DB::statement("ALTER TABLE commission_payments ADD CONSTRAINT commission_payments_valid CHECK (amount > 0 AND state IN ('prepared','approved','rejected'))");
 }
 public function down(): void {Schema::dropIfExists('commission_payments');Schema::dropIfExists('commission_events');Schema::table('bookings',function(Blueprint $t){$t->dropConstrainedForeignId('commission_agent_id');$t->dropColumn(['commission_percentage','commission_policy_snapshot']);});}
};
