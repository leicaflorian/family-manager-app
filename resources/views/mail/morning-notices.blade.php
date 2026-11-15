<x-mail::message :why="__('You get this email because email reminders are on. You can change them in Settings › Notices.')">
# {{ __('Good morning :name', ['name' => $name]) }}

{{ __('Here is what is due in the next few days.') }} {{ __('Mark it as done when it is sorted.') }}

<table class="notices" width="100%" cellpadding="0" cellspacing="0" role="presentation">
@foreach ($lines as $line)
<tr>
<td class="notice">
<span class="notice-title">{{ $line['title'] }}@if ($line['subject'])<span class="notice-subject"> · {{ $line['subject'] }}</span>@endif</span><br>
<span class="due{{ $line['late'] ? ' due-late' : '' }}">{{ $line['when'] }}</span>
</td>
<td class="notice" align="right" valign="middle"><a href="{{ $line['url'] }}" class="notice-open">{{ __('Open') }}</a></td>
</tr>
@endforeach
</table>

<x-mail::button :url="$agenda">
{{ __('Open the Agenda') }}
</x-mail::button>
</x-mail::message>
