<x-mail::message>
<x-mail.icon name="mail" />

# {{ $name !== '' ? __('Hi :name', ['name' => $name]) : __('Hello!') }}

{{ __('Confirm that this is your email: this way the reminders reach you and, if needed, the link to change your password.') }}

<x-mail::button :url="$url">
{{ __('Confirm your email') }}
</x-mail::button>

<x-mail::panel>
{{ $lasts }} {{ __('If you did not sign up, ignore this email.') }}
</x-mail::panel>

<x-slot:subcopy>
{{ __('The button does not open? Copy this address into your browser:') }}
[{{ $url }}]({{ $url }})
</x-slot:subcopy>
</x-mail::message>
