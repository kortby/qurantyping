<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialiteController extends Controller
{
    /**
     * Redirect to the provider.
     */
    public function redirect(string $provider)
    {
        if ($provider === 'github') {
            return Socialite::driver($provider)->scopes(['user:email'])->redirect();
        }

        return Socialite::driver($provider)->redirect();
    }

    /**
     * Handle the provider callback.
     */
    public function callback(string $provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            return redirect()->route('login')->with('error', 'Authentication failed.');
        }

        $email = $socialUser->getEmail();

        if (!$email) {
            return redirect()->route('login')->with('error', 'We could not get your email from ' . ucfirst($provider) . '. Please ensure your email is public in your account settings.');
        }

        $user = User::where('oauth_provider', $provider)
            ->where('oauth_id', $socialUser->getId())
            ->first();

        if (!$user) {
            // Check if user already exists with this email
            $user = User::where('email', $email)->first();

            if ($user) {
                // Link account
                $user->update([
                    'oauth_id' => $socialUser->getId(),
                    'oauth_provider' => $provider,
                ]);
            } else {
                // Create new user
                $user = User::create([
                    'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? 'User',
                    'email' => $email,
                    'password' => Hash::make(Str::random(24)),
                    'oauth_id' => $socialUser->getId(),
                    'oauth_provider' => $provider,
                    'email_verified_at' => now(),
                ]);
            }
        }

        Auth::login($user);

        return redirect()->intended(route('dashboard'));
    }
}
