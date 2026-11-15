<x-mail::message>
# {{ $name !== '' ? __('Hi :name', ['name' => $name]) : __('Hello!') }}

{{ __('You asked to change your Family Manager password. Tap the button to choose a new one.') }}

<x-mail::button :url="$url">
{{ __('Choose a new password') }}
</x-mail::button>

<x-mail::panel>
{{ $lasts }} {{ __('If you did not ask for it, ignore this email: your password stays the same.') }}
</x-mail::panel>

<x-slot:subcopy>
{{ __('The button does not open? Copy this address into your browser:') }}
[{{ $url }}]({{ $url }})
</x-slot:subcopy>
</x-mail::message>
