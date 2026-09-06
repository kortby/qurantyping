<?php

namespace App\Http\Controllers;

use App\Services\BadgeService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BadgeController extends Controller
{
    public function __construct(private readonly BadgeService $service) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Badges/Index', [
            'badges' => fn () => $this->service->forUser($request->user()),
        ]);
    }
}
