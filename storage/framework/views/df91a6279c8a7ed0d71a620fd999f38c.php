<?php $__env->startSection('content'); ?>
    <div class="document-heading"><div><p class="eyebrow"><?php echo e(__('Office expense voucher')); ?></p><h1><?php echo e($record->number); ?></h1></div><p class="version"><?php echo e(__('Posted record')); ?></p></div>
    <section class="status recognized" aria-label="<?php echo e(__('Expense status')); ?>">
        <h2><?php echo e(__('Expense posted')); ?></h2>
        <p><?php echo e(__('This immutable operational expense was posted on the Karachi business date shown below.')); ?></p>
    </section>
    <section class="amount-box"><p><?php echo e(__('Expense amount')); ?></p><strong><?php echo e(\App\Support\Pkr::format($record->amount)); ?></strong></section>
    <dl class="details">
        <div><dt><?php echo e(__('Business date')); ?></dt><dd><?php echo e($record->business_date); ?></dd></div>
        <div><dt><?php echo e(__('Expense category')); ?></dt><dd><?php echo e($record->category); ?></dd></div>
        <div><dt><?php echo e(__('Paid to')); ?></dt><dd><?php echo e($record->payee ?: __('Not recorded')); ?></dd></div>
        <div><dt><?php echo e(__('Payment account at posting')); ?></dt><dd><?php echo e($record->account_snapshot['code']); ?> · <?php echo e($record->account_snapshot['name']); ?></dd></div>
        <div><dt><?php echo e(__('Reference')); ?></dt><dd><?php echo e($record->reference ?: __('Not recorded')); ?></dd></div>
        <div><dt><?php echo e(__('Posted by')); ?></dt><dd><?php echo e($record->poster->name); ?></dd></div>
    </dl>
    <section class="section"><h2><?php echo e(__('Verification note')); ?></h2><p><?php echo e($record->note); ?></p>
        <p class="note"><?php echo e(__('This voucher records an operational cashbook outflow. It is not a tax invoice, bank statement, general ledger entry or proof of the recipient’s acknowledgement.')); ?></p>
    </section>
    <div class="signatures"><div><?php echo e(__('Prepared / checked by')); ?><span><?php echo e($record->poster->name); ?></span></div><div><?php echo e(__('Recipient acknowledgement')); ?><span><?php echo e(__('Signature / date')); ?></span></div></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('print.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\Personal\Projects\RealState\resources\views/print/expense.blade.php ENDPATH**/ ?>