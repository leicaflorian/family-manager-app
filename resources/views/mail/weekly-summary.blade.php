<x-mail::message :why="__('You get this email every Monday because you are an admin of the family.')">
# {{ __('Good morning :name', ['name' => $name]) }}

{{ __('Here is what to pay and to do this week.') }}

@if ($total)
<table class="total" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="total-label" valign="middle">{{ __('To pay') }}</td>
<td class="total-amount" align="right" valign="middle">{{ $estimated ? __('About :amount', ['amount' => $total]) : $total }}</td>
</tr>
</table>
@endif

@if ($late !== [])
<x-mail.week-section :title="__('Late')" :rows="$late" :late="true" />
@endif

@if ($toPay !== [])
<x-mail.week-section :title="__('To pay this week')" :rows="$toPay" />
@endif

@if ($tasks !== [])
<x-mail.week-section :title="__('Reminders and things to do')" :rows="$tasks" />
@endif

@if ($noticeLines !== [])
<h2 class="section-title">{{ __('Today\'s notices') }}</h2>
<x-mail.notice-list :lines="$noticeLines" />
@endif

<x-mail::button :url="$agenda">
{{ __('Open the Agenda') }}
</x-mail::button>
</x-mail::message>
