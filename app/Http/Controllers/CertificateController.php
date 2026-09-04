<?php

namespace App\Http\Controllers;

use App\Services\CertificateService;
use Illuminate\Http\Request;
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
            ]),
        ]);
    }
}
