<x-mail::message>
# We replied to your feedback

You wrote to {{ config('app.name') }}:

> {{ $feedback->message }}

**Our reply:**

{{ $feedback->admin_response }}

Thank you for helping us improve,<br>
{{ config('app.name') }}
</x-mail::message>
