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

        Auth::guard('web')->login($user);
        $this->syncSessionPasswordHash($request, $user);

        return redirect('/');
    }

    public function leave(Request $request): RedirectResponse
    {
        abort_unless($request->session()->has('impersonator_id'), 403);

        $original = Auth::guard('web')->loginUsingId($request->session()->pull('impersonator_id'));

        if ($original) {
            $this->syncSessionPasswordHash($request, $original);
        }

        return redirect()->route('admin.users.index');
    }

    /**
     * Realign the session's stored password hash with the now-active user.
     *
     * Jetstream's AuthenticateSession middleware logs the session out on the
     * next request when this hash no longer matches the authenticated user, so
     * after switching users programmatically it must be refreshed by hand.
     */
    protected function syncSessionPasswordHash(Request $request, Authenticatable $user): void
    {
        $request->session()->put('password_hash_web', $user->getAuthPassword());
    }
}
