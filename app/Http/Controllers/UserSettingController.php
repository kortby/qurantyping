<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

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
}
