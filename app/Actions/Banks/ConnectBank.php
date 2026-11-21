<?php

declare(strict_types=1);

namespace App\Actions\Banks;

use App\Contracts\BankFeed;
use App\Exceptions\Banks\BankFeedFailed;
use App\Support\Banks\BankInstitution;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

/**
 * The first step of connecting a bank: asks the permission and gives the address of the bank where the person goes to
 * give it. The secret that must come back is kept in the session.
 */
final readonly class ConnectBank
{
    public function __construct(private BankFeed $feed) {}

    /**
     * @param  BankInstitution  $institution  the bank chosen
     * @param  Request  $request  the request, for its session
     * @return string where to send the person
     *
     * @throws BankFeedFailed
     */
    public function handle(BankInstitution $institution, Request $request): string
    {
        $state = Str::random(40);
        $days = min(Config::integer('bank_feed.consent_days'), $institution->maxConsentDays);

        $url = $this->feed->start($institution, $state, route('banks.callback'), CarbonImmutable::now()->addDays($days));

        // Kept only once the provider has answered: a failed start leaves no secret waiting
        $request->session()->put('bank_connect', ['state' => $state, 'institution' => $institution->name]);

        return $url;
    }
}
