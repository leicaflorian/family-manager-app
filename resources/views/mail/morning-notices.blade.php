<x-mail::message>
# {{ __('Hello!') }}

@foreach ($lines as $line)
**{{ $line['title'] }}**@if ($line['subject']) · {{ $line['subject'] }}@endif

{{ $line['when'] }} · [{{ __('Open') }}]({{ $line['url'] }})

@endforeach
{{ __('Mark it as done when it is sorted.') }}

Family Manager
</x-mail::message>
