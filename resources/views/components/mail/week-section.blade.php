@props(['title', 'rows', 'late' => false])
{{-- A section of the weekly mail: a title and one row per entry (day with weekday, or month for a late one, icon of the area, title and card, person, amount). --}}
<h2 class="section-title{{ $late ? ' section-title-late' : '' }}">{{ $title }}</h2>
<table class="notices" width="100%" cellpadding="0" cellspacing="0" role="presentation">
@foreach ($rows as $row)
<tr>
<td class="week-day" align="center" valign="middle"><span class="week-day-number{{ $late ? ' due-late' : '' }}">{{ $row['day'] }}</span><br><span class="week-day-name{{ $late ? ' due-late' : '' }}">{{ $late ? $row['month'] : $row['weekday'] }}</span></td>
<td class="notice-icon" valign="middle"><x-mail.icon :name="$row['area']" shape="square" /></td>
<td class="notice" valign="middle">
<a href="{{ $row['url'] }}" class="notice-title">{{ $row['title'] }}@if ($row['subtitle'])<span class="notice-subject"> · {{ $row['subtitle'] }}</span>@endif</a>
@if ($row['person'])<br><span class="due">{{ $row['person'] }}</span>@endif
</td>
<td class="notice week-amount" align="right" valign="middle">{{ $row['amount'] }}</td>
</tr>
@endforeach
</table>
