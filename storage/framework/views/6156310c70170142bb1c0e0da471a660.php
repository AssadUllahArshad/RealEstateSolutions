
<?php $__env->startSection('content'); ?>
    <div class="document-heading"><div><p class="eyebrow"><?php echo e(__('Customer receipt')); ?></p><h1><?php echo e($record->number); ?></h1></div><p class="version"><?php echo e(__('Record version')); ?> <?php echo e($record->version); ?></p></div>
    <section class="status <?php echo e($record->state === 'reversed' ? 'reversed' : ($recognized ? 'recognized' : 'pending')); ?>" aria-label="<?php echo e(__('Receipt status')); ?>">
        <h2><?php echo e(__($status)); ?></h2>
        <?php if($record->state === 'reversed'): ?>
            <p><?php echo e(__('Reversed on :date. The original face amount below is retained for reference. This copy provides no valid payment credit and does not confirm a cash refund.', ['date' => $record->reversed_on])); ?></p>
        <?php elseif(!$recognized): ?>
            <p><?php echo e(__('The cheque has been received for processing. Funds are not recognized and cannot support a booking payment until the required bank clearance is recorded.')); ?></p>
        <?php elseif($record->method === 'cheque' && $record->bank_stage !== 'cleared'): ?>
            <p><?php echo e(__('Recognized on deposit under the policy captured at issue. Bank clearance has not been recorded.')); ?></p>
        <?php else: ?>
            <p><?php echo e(__('Payment recognition recorded on :date.', ['date' => $record->recognized_on])); ?><?php echo e($record->method === 'cheque' ? ' '.__('Bank clearance recorded on :date.', ['date' => $record->cleared_on]) : ''); ?></p>
        <?php endif; ?>
    </section>
    <section class="amount-box"><p><?php echo e($record->state === 'reversed' ? __('Original face amount') : __('Receipt amount')); ?></p><strong><?php echo e(\App\Support\Pkr::format($record->amount)); ?></strong></section>
    <dl class="details">
        <div><dt><?php echo e(__('Received from')); ?></dt><dd><?php echo e($record->snapshot['customer_name']); ?></dd></div>
        <div><dt><?php echo e(__('Accepted business date')); ?></dt><dd><?php echo e($record->accepted_on); ?></dd></div>
        <div><dt><?php echo e(__('Payment method')); ?></dt><dd><?php echo e(__($method)); ?></dd></div>
        <div><dt><?php echo e(__('Destination account at issue')); ?></dt><dd><?php echo e($record->snapshot['posted_account']['code']); ?> · <?php echo e($record->snapshot['posted_account']['name']); ?></dd></div>
        <div><dt><?php echo e(__('External reference')); ?></dt><dd><?php echo e($record->reference ?: __('Not provided / cash')); ?></dd></div>
        <div><dt><?php echo e(__('Bank stage')); ?></dt><dd><?php echo e($record->method === 'cheque' ? ($record->bank_stage === 'cleared' ? 'Cleared' : 'Deposited — not bank-cleared') : __('Not applicable')); ?></dd></div>
    </dl>
    <section class="section"><h2><?php echo e(__('Payment credit at preview')); ?></h2><table><tbody>
        <tr><th scope="row"><?php echo e(__('Recognized credit')); ?></th><td class="money"><?php echo e(\App\Support\Pkr::format($recognized ? $record->amount : '0.00')); ?></td></tr>
        <tr><th scope="row"><?php echo e(__('Allocated to bookings and installments')); ?></th><td class="money"><?php echo e(\App\Support\Pkr::format($allocated)); ?></td></tr>
        <tr><th scope="row"><?php echo e(__('Unallocated customer credit')); ?></th><td class="money"><?php echo e(\App\Support\Pkr::format($unallocated)); ?></td></tr>
    </tbody></table><p class="note"><?php echo e(__('This receipt alone does not confirm a plot booking, ownership, a paid-in-full contract or an account balance.')); ?></p></section>
    <?php if(empty($issuer)): ?><p class="note"><?php echo e(__('Office profile details were not captured at issue for this receipt.')); ?></p><?php endif; ?>
    <div class="signatures"><div><?php echo e(__('Received / checked by')); ?><span><?php echo e(__('Authorized staff')); ?> #<?php echo e($record->accepted_by); ?></span></div><div><?php echo e(__('Customer acknowledgement')); ?><span><?php echo e(__('Signature / date')); ?></span></div></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('print.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\Personal\Projects\RealState\resources\views/print/receipt.blade.php ENDPATH**/ ?>