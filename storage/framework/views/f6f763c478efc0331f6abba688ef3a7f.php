<!DOCTYPE html>
<html lang="<?php echo e(app()->getLocale()); ?>" dir="<?php echo e(app()->getLocale() === 'ur' ? 'rtl' : 'ltr'); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title><?php echo e($title); ?> Â· Data Real Estate</title>
    <link rel="stylesheet" href="/branding/documents.css">
    <script src="/branding/documents.js" defer></script>
</head>
<body>
    <nav class="toolbar" aria-label="<?php echo e(__('Document controls')); ?>">
        <a href="<?php echo e($backUrl); ?>"><?php echo e(__('Back to record')); ?></a>
        <span><?php echo e(__('Choose your printer or Save as PDF in the print dialog.')); ?></span>
        <button type="button" id="print-document"><?php echo e(__('Print / Save PDF')); ?></button>
    </nav>
    <main class="document">
        <header class="letterhead">
            <img src="/branding/data-real-estate.svg" width="90" height="96" alt="Data Real Estate">
            <div><p class="company">Data Real Estate</p><p class="society">Green Vista Housing Scheme</p></div>
            <img src="/branding/green-vista.svg" width="135" height="96" alt="Green Vista Housing Scheme">
        </header>
        <?php if(!empty($issuer['name']) || !empty($issuer['office_name'])): ?>
            <div class="issuer">
                <strong><?php echo e(__('Issuer on record:')); ?></strong> <?php echo e($issuer['office_name'] ?? ''); ?><?php echo e(!empty($issuer['office_name']) && !empty($issuer['name']) ? ' Â· ' : ''); ?><?php echo e($issuer['name'] ?? ''); ?>

                <?php if(!empty($issuer['address'])): ?><div><?php echo e($issuer['address']); ?></div><?php endif; ?>
                <?php if(!empty($issuer['phone']) || !empty($issuer['email'])): ?><div><?php echo e($issuer['phone'] ?? ''); ?> <?php echo e($issuer['email'] ?? ''); ?></div><?php endif; ?>
            </div>
        <?php endif; ?>
        <?php echo $__env->yieldContent('content'); ?>
        <footer class="document-footer">
            <p><?php echo e(__('Current status copy')); ?> Â· <?php echo e(__('Generated')); ?> <?php echo e($generatedAt); ?></p>
            <p><?php echo e(__('Amounts are in Pakistani rupees. Business dates use Asia/Karachi. Check the CRM record for later changes.')); ?></p>
        </footer>
    </main>
</body>
</html>

<?php /**PATH D:\Personal\Projects\RealState\resources\views/print/layout.blade.php ENDPATH**/ ?>