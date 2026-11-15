@props(['class' => ''])
{{-- A grey box for a note (how long a link lasts) or for a message quoted in the mail. --}}
<table class="panel {{ $class }}" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="panel-content" bgcolor="#f8fafc">
{{ Illuminate\Mail\Markdown::parse($slot) }}
</td>
</tr>
</table>
