<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use Carbon\CarbonInterface;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addHour(), function (): string {
            $now = now();

            /** @var list<array{0: string, 1: string, 2: string}> $pages */
            $pages = [
                ['/', '1.0', 'daily'],
                ['/leaderboard', '0.8', 'daily'],
                ['/contest', '0.6', 'weekly'],
                ['/arabic-typing-test', '0.7', 'weekly'],
                ['/quran-memorization', '0.7', 'weekly'],
                ['/privacy-policy', '0.2', 'yearly'],
                ['/terms-of-service', '0.2', 'yearly'],
                ['/data-deletion', '0.2', 'yearly'],
            ];

            $xml = '<?xml version="1.0" encoding="UTF-8"?>';
            $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

            foreach ($pages as [$path, $priority, $frequency]) {
                $xml .= $this->entry(url($path), $now, $frequency, $priority);
            }

            Certificate::query()
                ->select('share_token', 'updated_at')
                ->orderByDesc('updated_at')
                ->chunk(500, function ($certificates) use (&$xml): void {
                    foreach ($certificates as $certificate) {
                        $xml .= $this->entry(
                            url('/c/'.$certificate->share_token),
                            $certificate->updated_at,
                            'monthly',
                            '0.5',
                        );
                    }
                });

            return $xml.'</urlset>';
        });

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    private function entry(string $loc, CarbonInterface $lastmod, string $frequency, string $priority): string
    {
        return '<url>'
            .'<loc>'.e($loc).'</loc>'
            .'<lastmod>'.$lastmod->toAtomString().'</lastmod>'
            .'<changefreq>'.$frequency.'</changefreq>'
            .'<priority>'.$priority.'</priority>'
            .'</url>';
    }
}
