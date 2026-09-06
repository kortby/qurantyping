<?php

use App\Models\QuranText;

it('falls back to the simple text when the punctuated column is empty', function () {
    $rows = [];
    for ($a = 1; $a <= 4; $a++) {
        $rows[] = [
            'surah_number' => 108, 'ayah_number' => $a, 'juz' => 30, 'hizb_quarter' => 240, 'page' => 602,
            'text_arabic_simple' => 'إنا أعطيناك الكوثر فصل لربك وانحر',
            'surah_arabic_ponctuation' => null,
            'surah_name_arabic' => 'الكوثر', 'surah_name_english' => 'Al-Kawthar', 'surah_name_translation' => 'Al-Kawthar',
            'created_at' => now(), 'updated_at' => now(),
        ];
    }
    QuranText::insert($rows);

    $data = $this->getJson('/api/test/text?surah_number=108&start_ayah=1&end_ayah=4')
        ->assertOk()
        ->json();

    expect($data['text_punctuated'])->toBe($data['text_simple'])
        ->and($data['text_punctuated'])->toContain('الكوثر');
});
