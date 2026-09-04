<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    public function start(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 403);
        abort_if($user->isSuperAdmin(), 403);
        abort_if($request->session()->has('impersonator_id'), 403);

        $request->session()->put('impersonator_id', $request->user()->id);

        $this->switchTo($request, $user);

        return redirect('/');
    }

    public function leave(Request $request): RedirectResponse
    {
        abort_unless($request->session()->has('impersonator_id'), 403);

        $original = User::find($request->session()->pull('impersonator_id'));

        if ($original) {
            $this->switchTo($request, $original);
        } else {
            Auth::guard('web')->logout();
        }

        return redirect()->route('admin.users.index');
    }

    /**
     * Log the session in as the given user and leave the auth state consistent.
     *
     * After the session login this also (1) forgets the already-resolved guard
     * instances so the rest of this request — including Jetstream's
     * AuthenticateSession terminating callback — resolves the new user instead
     * of the one sanctum cached before the switch, and (2) drops the stale
     * session password-hash fingerprints (web + sanctum, since the route runs
     * behind auth:sanctum) so they are rewritten for the new user. Without this
     * the first navigation after switching users is logged straight out to
     * /login.
     */
    protected function switchTo(Request $request, Authenticatable $user): void
    {
        Auth::guard('web')->login($user);
        Auth::forgetGuards();

        $request->session()->forget(['password_hash_web', 'password_hash_sanctum']);
    }
}
