<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Contracts\BankFeed;
use App\Data\BankConnectionData;
use App\Data\BankInstitutionData;
use App\Exceptions\Banks\BankFeedFailed;
use App\Http\Controllers\Controller;
use App\Models\BankConnection;
use App\Services\Banks\BankFeedAvailability;
use App\Support\Banks\BankInstitution;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * *Settings → Linked banks*: the banks the family linked, with how they are doing, and the list of banks to link
 * another. Everyone sees them; only an administrator links, links again and unlinks (checked by the policy).
 */
final class BankController extends Controller
{
    /** How long the list of banks of a country is kept, in hours. */
    private const int INSTITUTIONS_HOURS = 24;

    /**
     * The page. The list of banks comes only when the page asks for it, as the person opens *Link a bank*.
     *
     * @throws NotFoundHttpException while the link to the bank is off for the family
     */
    public function index(BankFeed $feed): Response
    {
        abort_unless(BankFeedAvailability::on(), 404);

        return Inertia::render('settings/Banks', [
            'connections' => BankConnection::query()
                ->with('accounts')
                ->orderBy('institution_name')
                ->orderBy('id')
                ->get()
                ->map(BankConnectionData::fromConnection(...))
                ->all(),
            'can_change' => Gate::allows('create', BankConnection::class),
            'institutions' => Inertia::optional(fn (): ?array => $this->institutions($feed)),
        ]);
    }

    /**
     * The banks of the country of the app, kept for 24 hours so the provider is asked once a day and not at every
     * opening of the panel.
     *
     * @return list<BankInstitutionData>|null null when the provider does not answer: nothing is kept, the next opening asks again
     */
    private function institutions(BankFeed $feed): ?array
    {
        $country = Config::string('bank_feed.country');

        try {
            /** @var list<BankInstitution> $banks */
            $banks = Cache::remember('bank-institutions:'.$country, now()->addHours(self::INSTITUTIONS_HOURS), static fn (): array => $feed->institutions($country));
        } catch (BankFeedFailed $failure) {
            report($failure);

            return null;
        }

        return array_map(BankInstitutionData::fromInstitution(...), $banks);
    }
}
