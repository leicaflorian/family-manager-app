<x-mail::message :why="__('We are writing to you because :inviter added your email to their family.', ['inviter' => $inviter])">
@if ($initials !== [])
<x-mail.faces :initials="$initials" />
@endif

# {{ __('Hello!') }}

{{ __(':inviter invited you to :app, where the family keeps everyone\'s due dates, bills and documents.', ['inviter' => $inviter, 'app' => config('app.name')]) }}

- {{ __('See the family\'s due dates and documents') }}
- {{ __('Upload bills and receipts from your phone') }}
- {{ __('Get a reminder when something is about to expire') }}

<x-mail::button :url="$link">
{{ __('Join the family') }}
</x-mail::button>

<x-mail::panel>
{{ __('The link is valid until :date. If you were not expecting this invitation, you can ignore this email.', ['date' => $expiresOn]) }}
</x-mail::panel>

<x-slot:subcopy>
{{ __('The button does not open? Copy this address into your browser:') }}
[{{ $link }}]({{ $link }})
</x-slot:subcopy>
</x-mail::message>
