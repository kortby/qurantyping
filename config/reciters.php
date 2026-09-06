<?php

return [
    /*
     * Reciter key used when a user has not chosen one.
     */
    'default' => 'alafasy',

    /*
     * Available reciters. `folder` is the everyayah.com data directory; audio is
     * streamed from https://everyayah.com/data/<folder>/<SSS><AAA>.mp3
     */
    'list' => [
        'alafasy' => ['name' => 'Mishary Rashid Alafasy', 'folder' => 'Alafasy_128kbps'],
        'husary' => ['name' => 'Mahmoud Khalil Al-Husary', 'folder' => 'Husary_128kbps'],
        'minshawi' => ['name' => 'Mohamed Siddiq El-Minshawi', 'folder' => 'Minshawy_Murattal_128kbps'],
        'abdulbasit' => ['name' => 'Abdul Basit (Murattal)', 'folder' => 'Abdul_Basit_Murattal_192kbps'],
        'sudais' => ['name' => 'Abdurrahmaan As-Sudais', 'folder' => 'Abdurrahmaan_As-Sudais_192kbps'],
        'shuraim' => ['name' => 'Saud Ash-Shuraim', 'folder' => 'Saood_ash-Shuraym_128kbps'],
    ],
];
