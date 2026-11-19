@props(['lines'])
{{-- The notices of a mail: icon of the area, title and thing, when it is due, and the Open link. --}}
<table class="notices" width="100%" cellpadding="0" cellspacing="0" role="presentation">
@foreach ($lines as $line)
<tr>
<td class="notice-icon" valign="middle"><x-mail.icon :name="$line['area']" shape="square" /></td>
<td class="notice">
<span class="notice-title">{{ $line['title'] }}@if ($line['subject'])<span class="notice-subject"> · {{ $line['subject'] }}</span>@endif</span><br>
<span class="due{{ $line['late'] ? ' due-late' : '' }}">{{ $line['when'] }}</span>
</td>
<td class="notice" align="right" valign="middle"><a href="{{ $line['url'] }}" class="notice-open">{{ __('Open') }}</a></td>
</tr>
@endforeach
</table>
