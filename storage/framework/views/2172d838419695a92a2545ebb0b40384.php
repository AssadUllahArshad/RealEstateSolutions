<?php $__env->startSection('content'); ?>
    <div class="document-heading"><div><p class="eyebrow"><?php echo e(__('Current principal statement')); ?></p><h1><?php echo e(__('Booking')); ?> #<?php echo e($record->id); ?></h1></div><p class="version"><?php echo e(__('As of')); ?> <?php echo e($state['businessDate']); ?> Asia/Karachi / <?php echo e(__('Ledger version')); ?> <?php echo e($state['version']); ?></p></div>
    <section class="status recognized"><h2><?php echo e(__('Live principal balances')); ?></h2><p><?php echo e($record->snapshot['customer_name']); ?> / <?php echo e(__('Plot')); ?> <?php echo e($record->snapshot['plot']['number']); ?></p></section>
    <dl class="details">
        <div><dt><?php echo e(__('Contract price')); ?></dt><dd><?php echo e(\App\Support\Pkr::format($record->net)); ?></dd></div>
        <div><dt><?php echo e(__('Paid principal including down payment')); ?></dt><dd><?php echo e(\App\Support\Pkr::format($state['paidPrincipal'])); ?></dd></div>
        <div><dt><?php echo e(__('Remaining principal')); ?></dt><dd><?php echo e(\App\Support\Pkr::format($state['outstanding'])); ?></dd></div>
        <div><dt><?php echo e(__('Overdue principal')); ?></dt><dd><?php echo e(\App\Support\Pkr::format($state['overdue'])); ?></dd></div>
    </dl>
    <p class="note"><?php echo e(__("This statement covers this booking's principal only. It excludes unapplied customer credit, charges, refunds and adjustments. Due dates use Asia/Karachi business dates; overdue means unpaid before today. It is not a new receipt or a legal title document.")); ?></p>
    <section class="section"><h2><?php echo e(__('Current installment balances')); ?></h2>
        <?php if(empty($state['schedule'])): ?><p><?php echo e(__('Full-payment plan. No scheduled balance.')); ?></p>
        <?php else: ?><table><thead><tr><th scope="col"><?php echo e(__('No. / Due date')); ?></th><th scope="col" class="money"><?php echo e(__('Agreed')); ?></th><th scope="col" class="money"><?php echo e(__('Paid')); ?></th><th scope="col" class="money"><?php echo e(__('Remaining')); ?></th><th scope="col"><?php echo e(__('Status')); ?></th></tr></thead><tbody>
            <?php $__currentLoopData = $state['schedule']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><td><?php echo e($row['sequence']); ?> / <?php echo e($row['due_date']); ?></td><td class="money"><?php echo e(\App\Support\Pkr::format($row['amount'])); ?></td><td class="money"><?php echo e(\App\Support\Pkr::format($row['paid'])); ?></td><td class="money"><?php echo e(\App\Support\Pkr::format($row['remaining'])); ?></td><td><?php echo e(__($row['status'])); ?></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody></table><?php endif; ?>
    </section>
    <section class="section"><h2><?php echo e(__('Down payment allocated at confirmation')); ?> (<?php echo e($record->confirmed_on); ?>)</h2><table><thead><tr><th scope="col"><?php echo e(__('Receipt')); ?></th><th scope="col" class="money"><?php echo e(__('Applied principal')); ?></th></tr></thead><tbody>
        <?php $__currentLoopData = $downPayments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><td><?php echo e($payment->number); ?></td><td class="money"><?php echo e(\App\Support\Pkr::format($payment->amount)); ?></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody></table></section>
    <section class="section"><h2><?php echo e(__('Complete installment allocation history')); ?></h2><p class="note"><?php echo e(__('Dates below are allocation dates, which may differ from receipt recognition dates. Amounts apply only to this booking.')); ?></p>
        <?php if($payments->isEmpty()): ?><p><?php echo e(__('No installment allocations recorded.')); ?></p>
        <?php else: ?><table><thead><tr><th scope="col"><?php echo e(__('Version / Business date')); ?></th><th scope="col"><?php echo e(__('Receipt')); ?></th><th scope="col" class="money"><?php echo e(__('Applied principal')); ?></th></tr></thead><tbody>
            <?php $__currentLoopData = $payments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><td><?php echo e($payment->version); ?> / <?php echo e($payment->business_date); ?></td><td><?php echo e($payment->number); ?></td><td class="money"><?php echo e(\App\Support\Pkr::format($payment->amount)); ?></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody></table><?php endif; ?>
    </section>
    <?php if($adjustments->isNotEmpty()): ?>
        <section class="section"><h2><?php echo e(__('Admin-approved allocation reversals')); ?></h2><table><thead><tr><th scope="col"><?php echo e(__('Date / Correction')); ?></th><th scope="col"><?php echo e(__('Installment / Receipt')); ?></th><th scope="col"><?php echo e(__('Admin')); ?></th><th scope="col" class="money"><?php echo e(__('Restored customer credit')); ?></th></tr></thead><tbody>
            <?php $__currentLoopData = $adjustments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $adjustment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><td><?php echo e($adjustment->business_date); ?> / #<?php echo e($adjustment->id); ?></td><td><?php echo e($adjustment->sequence); ?> / <?php echo e($adjustment->number); ?></td><td><?php echo e($adjustment->decider_name); ?></td><td class="money"><?php echo e(\App\Support\Pkr::format($adjustment->amount)); ?></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody></table></section>
    <?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('print.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\Personal\Projects\RealState\resources\views/print/statement.blade.php ENDPATH**/ ?>