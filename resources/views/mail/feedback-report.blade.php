<x-mail::message>
# {{ $report->kind->label() }}

{{ __(':name (:email), :family family, wrote on :date:', ['name' => $sender, 'email' => $senderEmail, 'family' => $family, 'date' => $sentAt]) }}

<x-mail::panel>
{!! $message !!}
</x-mail::panel>

- {{ __('Page') }}: {!! $page !!}
- {{ __('Layout') }}: {{ $report->layout ?? '-' }}
- {{ __('Device') }}: {!! $device !!}

{{ __('Answer this email to write to them.') }}

Family Manager
</x-mail::message>
