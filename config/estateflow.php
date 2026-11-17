<?php

return [
    'business_timezone' => 'Asia/Karachi',
    'currency' => 'PKR',
    // The company records the agreed society schedule but collects only through
    // the down payment. Keep the legacy ledger code available for historical
    // data without exposing post-booking installment collection workflows.
    'post_booking_installments' => (bool) env('POST_BOOKING_INSTALLMENTS_ENABLED', false),
];
