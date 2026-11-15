{{-- The technical part of a report. Everything here comes from the browser of a person: printed escaped only. --}}
@php
    use App\Models\FeedbackReport;
    use Carbon\Carbon;

    /** @var FeedbackReport $report */
    $report = $getRecord();
    $details = $report->diagnostics ?? [];
    $viewport = $details['viewport'] ?? [];
    $moment = static function (mixed $at): string {
        try {
            return is_string($at) && $at !== '' ? Carbon::parse($at)->timezone('Europe/Rome')->format('d/m/Y H:i:s') : '—';
        } catch (Throwable) {
            return '—';
        }
    };
    $yesNo = static fn (mixed $value): string => $value === null ? '—' : ($value ? __('Yes') : __('No'));
    $build = $details['client_build'] ?? null;
    // The build the owner runs now, not the one stored at sending: a page that was left open can be older
    $server = config('app.version');
@endphp
<div class="space-y-6 text-sm">
    @if ($details !== [])
        <dl class="grid gap-x-6 gap-y-2 sm:grid-cols-3">
            <div><dt class="font-medium">{{ __('Screen') }}</dt><dd>{{ ($viewport['width'] ?? '—') . ' × ' . ($viewport['height'] ?? '—') }}@if (! empty($viewport['dpr'])) @ {{ $viewport['dpr'] }}x @endif</dd></div>
            <div><dt class="font-medium">{{ __('Orientation') }}</dt><dd>{{ $viewport['orientation'] ?? '—' }}</dd></div>
            <div><dt class="font-medium">{{ __('Opened as') }}</dt><dd>{{ ($details['standalone'] ?? null) === null ? '—' : (($details['standalone'] ?? false) ? __('Installed app') : __('Browser')) }}</dd></div>
            <div><dt class="font-medium">{{ __('Phone') }}</dt><dd>{{ $yesNo($details['phone'] ?? null) }}</dd></div>
            <div><dt class="font-medium">{{ __('iPhone or iPad') }}</dt><dd>{{ $yesNo($details['ios'] ?? null) }}</dd></div>
            <div><dt class="font-medium">{{ __('Theme') }}</dt><dd>{{ $details['theme'] ?? '—' }}</dd></div>
            <div><dt class="font-medium">{{ __('Language') }}</dt><dd>{{ $details['locale'] ?? '—' }}</dd></div>
            <div><dt class="font-medium">{{ __('Online') }}</dt><dd>{{ $yesNo($details['online'] ?? null) }}</dd></div>
            <div><dt class="font-medium">{{ __('Connection') }}</dt><dd>{{ $details['connection'] ?? '—' }}</dd></div>
            <div><dt class="font-medium">{{ __('Seconds on the page') }}</dt><dd>{{ $details['page_age_seconds'] ?? '—' }}</dd></div>
            <div><dt class="font-medium">{{ __('Component') }}</dt><dd>{{ $details['component'] ?? '—' }}</dd></div>
            <div>
                <dt class="font-medium">{{ __('App version') }}</dt>
                <dd>
                    {{ $build ?? '—' }} / {{ $server ?? '—' }}
                    @if ($build !== null && $server !== null && $build !== $server)
                        <span class="font-medium text-warning-600">{{ __('Different from the server') }}</span>
                    @endif
                </dd>
            </div>
        </dl>

        @if (($details['pages'] ?? []) !== [])
            <div>
                <h4 class="mb-1 font-semibold">{{ __('Last pages') }}</h4>
                <ul class="space-y-1">
                    @foreach ($details['pages'] as $page)
                        <li>{{ $moment($page['at'] ?? null) }} · {{ $page['path'] ?? '' }}@if (! empty($page['component'])) ({{ $page['component'] }})@endif</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div>
            <h4 class="mb-1 font-semibold">{{ __('Page errors') }} ({{ $report->pageErrorCount() }})</h4>
            <ul class="space-y-1">
                @foreach ($details['console'] ?? [] as $line)
                    <li>{{ $moment($line['at'] ?? null) }} · {{ $line['level'] ?? '' }} · {{ $line['message'] ?? '' }}@if (! empty($line['component'])) ({{ $line['component'] }})@endif</li>
                @endforeach
            </ul>
        </div>

        <div>
            <h4 class="mb-1 font-semibold">{{ __('Failed requests') }} ({{ $report->failedRequestCount() }})</h4>
            <ul class="space-y-1">
                @foreach ($details['requests'] ?? [] as $request)
                    <li>{{ $moment($request['at'] ?? null) }} · {{ ($request['method'] ?? '') . ' ' . ($request['path'] ?? '') . ' · ' . ($request['status'] ?? '—') }}@if (! empty($request['request_id'])) · {{ $request['request_id'] }}@endif</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($report->server_errors !== null)
        <div>
            <h4 class="mb-1 font-semibold">{{ __('Server errors') }} ({{ $report->serverErrorCount() }})</h4>
            <ul class="space-y-2">
                @foreach ($report->server_errors as $error)
                    <li>
                        {{ $moment($error['at'] ?? null) }} · {{ ($error['method'] ?? '') . ' ' . ($error['path'] ?? '') }}@if (! empty($error['request_id'])) · {{ $error['request_id'] }}@endif<br>
                        <strong>{{ $error['class'] ?? '' }}</strong>: {{ $error['message'] ?? '' }}<br>
                        <span class="text-gray-500">{{ ($error['file'] ?? '') . ':' . ($error['line'] ?? '') }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
