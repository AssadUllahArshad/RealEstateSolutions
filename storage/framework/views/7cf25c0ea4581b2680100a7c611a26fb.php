
<?php $__env->startSection('content'); ?>
    <div class="document-heading"><div><p class="eyebrow"><?php echo e(__('Booking confirmation')); ?></p><h1>Booking #<?php echo e($record->id); ?></h1></div><p class="version"><?php echo e(__('Confirmed')); ?> <?php echo e($record->confirmed_on); ?></p></div>
    <section class="status recognized"><h2><?php echo e(__('Booking confirmed')); ?></h2><p>Block <?php echo e($record->snapshot['plot']['block']['code']); ?> / Plot <?php echo e($record->snapshot['plot']['number']); ?> Â· <?php echo e($record->snapshot['plot']['size']['label']); ?></p></section>
    <dl class="details">
        <div><dt><?php echo e(__('Sales contact at confirmation')); ?></dt><dd><?php echo e($record->snapshot['customer_name']); ?></dd></div>
        <div><dt><?php echo e(__('Contract price after discount')); ?></dt><dd><?php echo e(\App\Support\Pkr::format($record->net)); ?></dd></div>
        <div><dt><?php echo e(__('Gross agreed price')); ?></dt><dd><?php echo e(\App\Support\Pkr::format($record->snapshot['terms']['gross'])); ?></dd></div>
        <div><dt><?php echo e(__('Admin-authorized discount')); ?></dt><dd><?php echo e(\App\Support\Pkr::format($record->snapshot['terms']['discount'])); ?></dd></div>
        <div><dt><?php echo e(__('Allocated down payment')); ?></dt><dd><?php echo e(\App\Support\Pkr::format($record->down_payment)); ?></dd></div>
        <div><dt><?php echo e(__('Original scheduled balance')); ?></dt><dd><?php echo e(\App\Support\Pkr::format($record->snapshot['terms']['balance'])); ?></dd></div>
    </dl>
    <section class="section"><h2><?php echo e(__('Purchasers on the confirmed booking')); ?></h2><table><thead><tr><th scope="col"><?php echo e(__('Name at confirmation')); ?></th><th scope="col" class="money"><?php echo e(__('Ownership share')); ?></th></tr></thead><tbody>
        <?php $__currentLoopData = $record->snapshot['purchasers']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $person): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><td><?php echo e($person['name']); ?></td><td class="money"><?php echo e($person['share']); ?>%</td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody></table></section>
    <section class="section"><h2><?php echo e(__('Allocated down payment sources')); ?></h2><table><thead><tr><th scope="col"><?php echo e(__('Official receipt')); ?></th><th scope="col" class="money"><?php echo e(__('Allocated amount')); ?></th></tr></thead><tbody>
        <?php $__currentLoopData = $allocations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $allocation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><td><?php echo e($allocation->number); ?></td><td class="money"><?php echo e(\App\Support\Pkr::format($allocation->amount)); ?></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody></table></section>
    <section class="section"><h2><?php echo e(__('Agreed installment schedule')); ?></h2>
        <?php if(empty($record->snapshot['terms']['schedule'])): ?><p><?php echo e(__('Full-payment plan. No scheduled balance.')); ?></p>
        <?php else: ?><table><thead><tr><th scope="col">Installment</th><th scope="col"><?php echo e(__('Due business date')); ?></th><th scope="col" class="money"><?php echo e(__('Agreed amount')); ?></th></tr></thead><tbody>
            <?php $__currentLoopData = $record->snapshot['terms']['schedule']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><td><?php echo e($row['sequence']); ?></td><td><?php echo e($row['due_date']); ?></td><td class="money"><?php echo e(\App\Support\Pkr::format($row['amount'])); ?></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody></table><?php endif; ?>
        <p class="note"><?php echo e(__('This is the original agreed schedule, not a current outstanding statement. Later collections, charges or adjustments are not represented here.')); ?></p>
    </section>
    <section class="section"><h2><?php echo e(__('Evidence references at confirmation')); ?></h2><ul class="evidence"><?php $__currentLoopData = $record->snapshot['documents']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $document): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($documentLabels[$document['kind']] ?? 'Supporting document'); ?> #<?php echo e($document['id']); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul><p class="note"><?php echo e(__('Document references identify retained office records; they do not reproduce signatures or identity documents.')); ?></p></section>
    <p class="note"><?php echo e(__('This confirms the recorded booking terms. It is not a registered title, conveyance deed or ownership transfer. Supporting signed documents remain part of the office record.')); ?></p>
    <div class="signatures"><div><?php echo e(__('Authorized office representative')); ?><span><?php echo e(__('Confirmed by staff')); ?> #<?php echo e($record->confirmed_by); ?> Â· signature / date</span></div><div><?php echo e(__('Purchaser acknowledgement')); ?><span><?php echo e(__('Signature(s) / date')); ?></span></div></div>
<?php $__env->stopSection(); ?>


<?php echo $__env->make('print.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\Personal\Projects\RealState\resources\views/print/booking.blade.php ENDPATH**/ ?>