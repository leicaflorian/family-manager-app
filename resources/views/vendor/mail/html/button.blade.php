@props([
    'url',
    'color' => 'primary',
    'align' => 'left',
])
{{-- The one blue button of a mail, on the left as on the boards. --}}
<table class="action" align="{{ $align }}" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="button-cell" bgcolor="#0070e0">
<a href="{{ $url }}" class="button" target="_blank" rel="noopener">{!! $slot !!}</a>
</td>
</tr>
</table>
<br clear="all">
