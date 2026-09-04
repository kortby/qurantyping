<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Certificate accuracy bar
    |--------------------------------------------------------------------------
    |
    | Every ayah of a surah must have been typed at least this accurately
    | (in any test — practice or hifz) before a completion certificate is
    | issued for that surah.
    |
    */

    'certificate_accuracy' => (float) env('HIFZ_CERTIFICATE_ACCURACY', 95),

];
