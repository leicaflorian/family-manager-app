@props(['title', 'rows', 'late' => false])
{{-- A section of the weekly mail: a title and one row per entry (day, icon of the area, title and card, person, amount). --}}
<h2 class="section-title{{ $late ? ' section-title-late' : '' }}">{{ $title }}</h2>
<table class="notices" width="100%" cellpadding="0" cellspacing="0" role="presentation">
@foreach ($rows as $row)
<tr>
<td class="week-day" valign="middle"><span class="{{ $late ? 'due-late' : 'due' }}">{{ $row['day'] }}</span></td>
<td class="notice-icon" valign="middle"><x-mail.icon :name="$row['area']" shape="square" /></td>
<td class="notice" valign="middle">
<a href="{{ $row['url'] }}" class="notice-title">{{ $row['title'] }}@if ($row['subtitle'])<span class="notice-subject"> · {{ $row['subtitle'] }}</span>@endif</a>
@if ($row['person'])<br><span class="due">{{ $row['person'] }}</span>@endif
</td>
@if ($row['amount'])
<td class="notice week-amount" align="right" valign="middle">{{ $row['amount'] }}</td>
@endif
</tr>
@endforeach
</table>
