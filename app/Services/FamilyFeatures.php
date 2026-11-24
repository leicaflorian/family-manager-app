<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\BankFeed;
use App\Enums\Feature;
use App\Enums\UtilityType;
use App\Models\DocumentCategory;
use App\Models\Family;
use App\Models\Utility;
use App\Support\CurrentFamily;

/**
 * Which parts of the app a family has, and the pause of its Budget (the window whose unpaid due dates are waived).
 * The one place that answers, for the pages, the routes, the jobs and the menus. Scoped: one per request and per test, remembering what it read of each family by its id.
 */
final class FamilyFeatures
{
    /** @var array<int, array{budget: bool, mail: bool, bank_feed: bool, budget_paused_on: ?string, budget_started_on: ?string}> the switches of the family, by family id */
    private array $switches = [];

    /**
     * Whether the current family has a feature. Without a family (nobody signed in) nothing is on.
     *
     * @param  Feature  $feature  the feature
     */
    public function on(Feature $feature): bool
    {
        $id = CurrentFamily::id();

        if ($id === null) {
            return false;
        }

        return $this->resolve($feature, $id, fn (): array => $this->switches($id));
    }

    /**
     * Whether a given family has a feature, whoever is signed in (the queue, a mail before its family is set).
     *
     * @param  Family  $family  the family
     * @param  Feature  $feature  the feature
     */
    public function for(Family $family, Feature $feature): bool
    {
        return $this->resolve($feature, $family->id, fn (): array => $this->switchesOf($family));
    }

    /**
     * Every feature, by value, for the family given or the current one.
     *
     * @param  Family|null  $family  the family, null for the current one
     * @return array<string, bool>
     */
    public function all(?Family $family = null): array
    {
        $all = [];

        foreach (Feature::cases() as $feature) {
            $all[$feature->value] = $family instanceof Family ? $this->for($family, $feature) : $this->on($feature);
        }

        return $all;
    }

    /**
     * The pause of the Budget of the current family, `yyyy-mm-dd`: from the first day it was turned off (`from`,
     * included) to the day it was last turned on again (`until`, excluded). The due dates of planned expenses and
     * incomes that fell in it, unpaid, are neither late nor to pay. Null when the Budget was never turned on again
     * after a pause, or without a family.
     *
     * @return array{from: string, until: string}|null
     */
    public function budgetPause(): ?array
    {
        $id = CurrentFamily::id();

        if ($id === null) {
            return null;
        }

        ['budget_paused_on' => $from, 'budget_started_on' => $until] = $this->switches($id);

        return $from === null || $until === null ? null : ['from' => $from, 'until' => $until];
    }

    /**
     * Forgets what was read of a family, after it was saved.
     *
     * @param  Family  $family  the family
     */
    public function forget(Family $family): void
    {
        unset($this->switches[$family->id]);
    }

    /**
     * The domain the mail of the families arrives on, trimmed, null while it is not set (the mail is off).
     */
    public function mailDomain(): ?string
    {
        $domain = config('services.mailgun.inbox_domain');

        return is_string($domain) && trim($domain) !== '' ? trim($domain) : null;
    }

    /**
     * Whether the mail for the bills can be offered on this server: only with the domain its addresses live on. The
     * one answer for the Welcome, *What you use* and *To begin*; without it they do not show the mail at all.
     */
    public function mailAvailable(): bool
    {
        return $this->mailDomain() !== null;
    }

    /**
     * The switches of a family, read once from `families` and remembered.
     *
     * @param  int  $id  the family id
     * @return array{budget: bool, mail: bool, bank_feed: bool, budget_paused_on: ?string, budget_started_on: ?string}
     */
    private function switches(int $id): array
    {
        if (! isset($this->switches[$id])) {
            $row = Family::query()->whereKey($id)->first(['budget_enabled', 'mail_enabled', 'bank_feed_enabled', 'budget_paused_on', 'budget_started_on']);

            $this->switches[$id] = $this->switchesOf($row);
        }

        return $this->switches[$id];
    }

    /**
     * The five switches of a family, off when there is no family.
     *
     * @param  Family|null  $family  the family, or null when it does not exist
     * @return array{budget: bool, mail: bool, bank_feed: bool, budget_paused_on: ?string, budget_started_on: ?string}
     */
    private function switchesOf(?Family $family): array
    {
        return [
            'budget' => (bool) $family?->budget_enabled,
            'mail' => (bool) $family?->mail_enabled,
            'bank_feed' => (bool) $family?->bank_feed_enabled,
            'budget_paused_on' => $family?->budget_paused_on?->toDateString(),
            'budget_started_on' => $family?->budget_started_on?->toDateString(),
        ];
    }

    /**
     * Decides a feature for a family id. The data of the family is read as that family, whoever is signed in.
     *
     * @param  Feature  $feature  the feature
     * @param  int  $id  the family id
     * @param  callable(): array{budget: bool, mail: bool, bank_feed: bool, budget_paused_on: ?string, budget_started_on: ?string}  $switches  reads the switches of the family
     */
    private function resolve(Feature $feature, int $id, callable $switches): bool
    {
        return match ($feature) {
            Feature::Budget => $switches()['budget'],
            Feature::Mail => $switches()['mail'] && $this->mailAvailable(),
            // The link to the bank and the consumption live in the Budget: with it off they are off
            Feature::BankFeed => $switches()['budget'] && resolve(BankFeed::class)->isActive() && $switches()['bank_feed'],
            // An electricity, gas or water utility: the Consumption page exists only then
            Feature::Consumption => $switches()['budget'] && CurrentFamily::using($id, static fn (): bool => Utility::query()->whereIn('type', UtilityType::measured())->exists()),
            // A gallery category: the sidebar shows *Drawings and crafts* under *Documents*
            Feature::Gallery => CurrentFamily::using($id, static fn (): bool => DocumentCategory::query()->where('gallery', true)->exists()),
        };
    }
}
