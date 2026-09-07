<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Services\CertificateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Inertia\Inertia;
use Inertia\Response;

class CertificateController extends Controller
{
    public function index(Request $request, CertificateService $certificates): Response
    {
        return Inertia::render('Certificates/Index', [
            'certificates' => $certificates->forUser($request->user())->map(fn ($c) => [
                'surah_number' => $c->surah_number,
                'surah_name_english' => $c->surah_name_english,
                'surah_name_arabic' => $c->surah_name_arabic,
                'ayah_count' => $c->ayah_count,
                'accuracy' => (float) $c->accuracy,
                'issued_at' => $c->issued_at->toDateString(),
                'holder' => $request->user()->name,
                'share_url' => url('/c/'.$c->share_token),
            ]),
        ]);
    }

    public function show(string $token): Response
    {
        $certificate = Certificate::with('user:id,name')->where('share_token', $token)->firstOrFail();

        $accuracy = rtrim(rtrim(number_format((float) $certificate->accuracy, 2, '.', ''), '0'), '.');

        View::share('meta', [
            'type' => 'article',
            'url' => url('/c/'.$certificate->share_token),
            'title' => "{$certificate->user->name} completed Surah {$certificate->surah_name_english} · QuranTyping",
            'description' => "{$accuracy}% accuracy over {$certificate->ayah_count} ayahs, typed letter by letter. Earn your own certificate — free at qurantyping.com.",
            'image' => asset('images/certificate-og.png'),
            'image_alt' => "QuranTyping certificate of completion for Surah {$certificate->surah_name_english}",
        ]);

        return Inertia::render('Certificates/Show', [
            'certificate' => [
                'surah_number' => $certificate->surah_number,
                'surah_name_english' => $certificate->surah_name_english,
                'surah_name_arabic' => $certificate->surah_name_arabic,
                'ayah_count' => $certificate->ayah_count,
                'accuracy' => (float) $certificate->accuracy,
                'issued_at' => $certificate->issued_at->toDateString(),
                'holder' => $certificate->user->name,
            ],
        ]);
    }
}
