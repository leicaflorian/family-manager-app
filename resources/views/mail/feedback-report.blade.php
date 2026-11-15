<x-mail::message :why="__('You get this email because you look after Family Manager. You also find it in the panel, under Reports.')">
<x-mail.icon name="alert" />

# {{ $report->kind->label() }}

{{ __(':name (:email), :family family, wrote on :date:', ['name' => $sender, 'email' => $senderEmail, 'family' => $family, 'date' => $sentAt]) }}

<x-mail::panel class="quote">
{!! $message !!}
</x-mail::panel>

<table class="details" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr><td class="detail-name">{{ __('Page') }}</td><td>{!! $page !!}</td></tr>
<tr><td class="detail-name">{{ __('Layout') }}</td><td>{{ $report->layout ?? '-' }}</td></tr>
<tr><td class="detail-name">{{ __('Device') }}</td><td>{!! $device !!}</td></tr>
@if ($fileName !== null)
<tr><td class="detail-name">{{ __('File') }}</td><td>{!! __('Attached: :name', ['name' => $fileName]) !!}</td></tr>
@endif
</table>

@if ($senderEmail !== '')
<x-mail::button :url="'mailto:'.$senderEmail">
{{ __('Reply to :name', ['name' => $senderFirstName]) }}
</x-mail::button>
@endif

{{ __('Answer this email to write to them.') }}
</x-mail::message>
