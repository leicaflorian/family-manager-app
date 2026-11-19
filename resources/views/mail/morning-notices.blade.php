<x-mail::message :why="__('You get this email because email reminders are on. You can change them in Settings › Notices.')">
# {{ __('Good morning :name', ['name' => $name]) }}

{{ __('Here is what is due in the next few days.') }} {{ __('Mark it as done when it is sorted.') }}

<x-mail.notice-list :lines="$lines" />

<x-mail::button :url="$agenda">
{{ __('Open the Agenda') }}
</x-mail::button>
</x-mail::message>
