<x-mail::message :why="__('We are writing to you because you are an admin of the family and the bank link is yours to keep.')">
<x-mail.icon name="banks" />

# {{ __('Hi :name', ['name' => $name]) }}

{{ $text }}

<x-mail::button :url="$url">
{{ __('Connect again') }}
</x-mail::button>

<x-mail::panel>
{{ __('It takes a minute: you choose the bank and say yes, as the first time.') }}
</x-mail::panel>
</x-mail::message>
