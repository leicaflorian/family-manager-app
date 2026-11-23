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
 * Which parts of the app a family has. The one place that answers, for the pages, the routes, the jobs and the
 * menus. Scoped: one per request and per test, remembering what it read of each family by its id.
 */
final class FamilyFeatures
{
    /** @var array<int, bool> whether the owner switched the link to the bank on, by family id */
    private array $bankSwitch = [];

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

        return $this->resolve($feature, $id, fn (): bool => $this->bankSwitch[$id] ??= (bool) Family::query()->whereKey($id)->value('bank_feed_enabled'));
    }

    /**
     * Whether a given family has a feature, whoever is signed in (the queue, a mail before its family is set).
     *
     * @param  Family  $family  the family
     * @param  Feature  $feature  the feature
     */
    public function for(Family $family, Feature $feature): bool
    {
        return $this->resolve($feature, $family->id, static fn (): bool => $family->bank_feed_enabled);
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
     * Forgets what was read of a family, after it was saved.
     *
     * @param  Family  $family  the family
     */
    public function forget(Family $family): void
    {
        unset($this->bankSwitch[$family->id]);
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
     * Decides a feature for a family id. The data of the family is read as that family, whoever is signed in.
     *
     * @param  Feature  $feature  the feature
     * @param  int  $id  the family id
     * @param  callable(): bool  $bankSwitch  reads whether the owner switched the link to the bank on
     */
    private function resolve(Feature $feature, int $id, callable $bankSwitch): bool
    {
        return match ($feature) {
            Feature::BankFeed => resolve(BankFeed::class)->isActive() && $bankSwitch(),
            // An electricity, gas or water utility: the Consumption page exists only then
            Feature::Consumption => CurrentFamily::using($id, static fn (): bool => Utility::query()->whereIn('type', UtilityType::measured())->exists()),
            // A gallery category: the sidebar shows *Drawings and crafts* under *Documents*
            Feature::Gallery => CurrentFamily::using($id, static fn (): bool => DocumentCategory::query()->where('gallery', true)->exists()),
        };
    }
}
