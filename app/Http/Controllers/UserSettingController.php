<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserSettingController extends Controller
{
    public function updateErrorSound(Request $request): RedirectResponse
    {
        $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        // Since we added it to the user table directly
        $user->forceFill([
            'error_sound' => $request->boolean('enabled'),
        ])->save();

        return back();
    }

    public function updateDailyGoal(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'daily_goal_chars' => ['required', 'integer', 'min:50', 'max:10000'],
        ]);

        $request->user()->forceFill([
            'daily_goal_chars' => $validated['daily_goal_chars'],
        ])->save();

        return back();
    }

    public function updateAutoAdvance(Request $request): RedirectResponse
    {
        $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $request->user()->forceFill([
            'auto_advance' => $request->boolean('enabled'),
        ])->save();

        return back();
    }

    public function updateHifzDailyNew(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'hifz_daily_new' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        $request->user()->forceFill([
            'hifz_daily_new' => $validated['hifz_daily_new'],
        ])->save();

        return back();
    }
}
