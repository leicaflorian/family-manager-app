@props(['url'])
{{-- The logo and the name of the app above the card. --}}
<tr>
<td class="header">
<a href="{{ $url }}"><img src="{{ \App\Support\AppHostUrl::asset('apple-touch-icon.png') }}" class="logo" width="32" height="32" alt="">&nbsp;&nbsp;{!! $slot !!}</a>
</td>
</tr>
