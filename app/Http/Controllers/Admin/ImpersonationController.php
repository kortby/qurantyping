<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
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

        return redirect('/');
    }

    public function leave(Request $request): RedirectResponse
    {
        abort_unless($request->session()->has('impersonator_id'), 403);

        Auth::guard('web')->loginUsingId($request->session()->pull('impersonator_id'));

        return redirect()->route('admin.users.index');
    }
}
