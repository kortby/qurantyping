<?php

namespace App\Http\Controllers;

use App\Mail\FeedbackReceived;
use App\Models\Feedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class FeedbackController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'message' => 'required|string|max:5000',
            'type' => 'required|string|in:bug,suggestion,other',
        ]);

        $feedback = Feedback::create([
            'user_id' => $request->user()->id,
            'message' => $request->message,
            'type' => $request->type,
        ]);

        $admins = config('admin.super_admins', []);
        if (! empty($admins)) {
            Mail::to($admins)->send(new FeedbackReceived($feedback));
        }

        return back()->with('flash', [
            'banner' => 'Thank you for your feedback! It has been sent to our team.',
            'bannerStyle' => 'success',
        ]);
    }
}
