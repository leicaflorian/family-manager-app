<?php

declare(strict_types=1);

namespace App\Services\Banks;

use App\Contracts\BankFeed;
use App\Exceptions\Banks\BankFeedFailed;
use App\Support\Banks\BankInstitution;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

/**
 * The banks of the country of the app, kept for 24 hours so the provider is asked once a day and not at every opening
 * of the panel or every choice of a bank.
 */
final readonly class BankInstitutions
{
    /** How long the list of banks of a country is kept, in hours. */
    private const int HOURS = 24;

    public function __construct(private BankFeed $feed) {}

    /**
     * The banks that can be connected.
     *
     * @return list<BankInstitution>
     *
     * @throws BankFeedFailed when the provider does not answer: nothing is kept, the next ask tries again
     */
    public function all(): array
    {
        $country = Config::string('bank_feed.country');

        // Plain arrays: the cache does not rebuild classes (`serializable_classes` is false), so objects would come back broken
        /** @var list<array{name: string, country: string, logo: string|null, max_consent_days: int}> $rows */
        $rows = Cache::remember('bank-institutions:'.$country, now()->addHours(self::HOURS), fn (): array => array_map(
            static fn (BankInstitution $bank): array => ['name' => $bank->name, 'country' => $bank->country, 'logo' => $bank->logo, 'max_consent_days' => $bank->maxConsentDays],
            $this->feed->institutions($country),
        ));

        return array_map(
            static fn (array $row): BankInstitution => new BankInstitution($row['name'], $row['country'], $row['logo'], $row['max_consent_days']),
            $rows,
        );
    }

    /**
     * A bank by the name the provider knows it by.
     *
     * @param  string  $name  the name of the bank
     * @return BankInstitution|null null when it is not in the list, or the list cannot be read
     */
    public function find(string $name): ?BankInstitution
    {
        try {
            foreach ($this->all() as $bank) {
                if ($bank->name === $name) {
                    return $bank;
                }
            }
        } catch (BankFeedFailed $failure) {
            report($failure);
        }

        return null;
    }
}
