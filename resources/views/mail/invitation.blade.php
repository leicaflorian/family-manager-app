<x-mail::message>
# {{ __('Hello!') }}

{{ __(':inviter invited you to Family Manager, where the family keeps everyone\'s due dates, bills and documents.', ['inviter' => $inviter]) }}

<x-mail::button :url="$link">
{{ __('Join the family') }}
</x-mail::button>

{{ __('The link is valid until :date. If you were not expecting this invitation, you can ignore this email.', ['date' => $expiresOn]) }}

Family Manager
</x-mail::message>
