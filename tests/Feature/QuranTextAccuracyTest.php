<?php

use App\Models\QuranText;

it('spells these ayahs the way the Tanzil Quran text project does', function () {
    QuranText::insert([
        ['surah_number' => 2, 'ayah_number' => 181, 'text_arabic_simple' => 'فمن بدله بعدما سمعه', 'surah_arabic_ponctuation' => '', 'surah_name_arabic' => 'البقرة', 'surah_name_english' => 'Al-Baqara', 'surah_name_translation' => 'The Cow'],
        ['surah_number' => 5, 'ayah_number' => 31, 'text_arabic_simple' => 'قال يا ويلتا', 'surah_arabic_ponctuation' => '', 'surah_name_arabic' => 'المائدة', 'surah_name_english' => 'Al-Maaida', 'surah_name_translation' => 'The Table'],
        ['surah_number' => 8, 'ayah_number' => 6, 'text_arabic_simple' => 'في الحق بعدما تبين', 'surah_arabic_ponctuation' => '', 'surah_name_arabic' => 'الأنفال', 'surah_name_english' => 'Al-Anfaal', 'surah_name_translation' => 'The Spoils of War'],
        ['surah_number' => 13, 'ayah_number' => 37, 'text_arabic_simple' => 'اهواءهم بعدما جاءك', 'surah_arabic_ponctuation' => '', 'surah_name_arabic' => 'الرعد', 'surah_name_english' => 'Ar-Raad', 'surah_name_translation' => 'The Thunder'],
        ['surah_number' => 17, 'ayah_number' => 32, 'text_arabic_simple' => 'ولا تقربوا الزنا انه', 'surah_arabic_ponctuation' => '', 'surah_name_arabic' => 'الإسراء', 'surah_name_english' => 'Al-Israa', 'surah_name_translation' => 'The Night Journey'],
        ['surah_number' => 39, 'ayah_number' => 56, 'text_arabic_simple' => 'نفس يا حسرتا على', 'surah_arabic_ponctuation' => '', 'surah_name_arabic' => 'الزمر', 'surah_name_english' => 'Az-Zumar', 'surah_name_translation' => 'The Groups'],
    ]);

    $this->artisan('quran:fix-tanzil-spelling');

    expect(QuranText::where('surah_number', 2)->where('ayah_number', 181)->value('text_arabic_simple'))->toContain('بعد ما');
    expect(QuranText::where('surah_number', 5)->where('ayah_number', 31)->value('text_arabic_simple'))->toContain('ويلتى');
    expect(QuranText::where('surah_number', 8)->where('ayah_number', 6)->value('text_arabic_simple'))->toContain('بعد ما');
    expect(QuranText::where('surah_number', 13)->where('ayah_number', 37)->value('text_arabic_simple'))->toContain('بعد ما');
    expect(QuranText::where('surah_number', 17)->where('ayah_number', 32)->value('text_arabic_simple'))->toContain('الزنى');
    expect(QuranText::where('surah_number', 39)->where('ayah_number', 56)->value('text_arabic_simple'))->toContain('حسرتى');
});
