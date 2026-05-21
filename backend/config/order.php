<?php

return [

    /*
    |--------------------------------------------------------------------------
    | COD processing grace period
    |--------------------------------------------------------------------------
    |
    | Minimum minutes after the order reaches "confirmed" before admin may
    | move it to "processing". Measured from the first confirmed timeline entry.
    |
    */
    'cod_processing_grace_minutes' => (int) env('ORDER_COD_PROCESSING_GRACE_MINUTES', 30),

];
