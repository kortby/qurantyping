<x-mail::message>
    # New Feedback Received

    **Type:** {{ ucfirst($feedback->type) }}
    **User:** {{ $feedback->user->name }} ({{ $feedback->user->email }})

    **Message:**
    {{ $feedback->message }}

    Thanks,<br>
    {{ config('app.name') }}
</x-mail::message>