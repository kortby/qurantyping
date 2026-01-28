<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use Illuminate\Http\Request;

use App\Mail\FeedbackReceived;
use Illuminate\Support\Facades\Mail;

class FeedbackController extends Controller
{
    public function store(Request $request)
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

        // Send Email
        Mail::to(['hasbellaoui.faycal@gmail.com', 'kortby@gmail.com'])
            ->send(new FeedbackReceived($feedback));

        return back()->with('flash', [
            'banner' => 'Thank you for your feedback! It has been sent to our team.',
            'bannerStyle' => 'success',
        ]);
    }
}
