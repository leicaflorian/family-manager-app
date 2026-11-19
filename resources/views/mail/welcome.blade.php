<x-mail::message :why="__('We are writing to you because a :app account was opened with this email.', ['app' => config('app.name')])">
<x-mail.icon name="welcome" />

# {{ __('Hi :name', ['name' => $name]) }}

{{ __('Welcome to :app: the :family family is ready. Choose your password to sign in.', ['app' => config('app.name'), 'family' => $family]) }}

<x-mail::button :url="$url">
{{ __('Choose your password') }}
</x-mail::button>

<x-mail::panel>
{{ $lasts }} {{ __('Not expecting it? Ignore this email.') }}
</x-mail::panel>

<x-slot:subcopy>
{{ __('The button does not open? Copy this address into your browser:') }}
[{{ $url }}]({{ $url }})
</x-slot:subcopy>
</x-mail::message>
