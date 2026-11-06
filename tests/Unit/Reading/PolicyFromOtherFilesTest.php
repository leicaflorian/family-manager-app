<?php

declare(strict_types=1);

use App\Data\Reading\PolicyGiverData;
use App\Services\Reading\PolicyFromOtherFiles;

/**
 * A giver, the contract of a car policy of Prima Assicurazioni by default.
 *
 * @param  array<string, mixed>  $attributes
 */
function giver(array $attributes = []): PolicyGiverData
{
    return new PolicyGiverData(...($attributes + [
        'key' => 'file:1',
        'fileName' => 'Contratto polizza PRP730628468-1.pdf',
        'number' => 'PRP730628468',
        'creates' => true,
        'organizationId' => 58,
        'organizationName' => 'Prima Assicurazioni',
        'personId' => 1,
        'type' => 'car',
    ]));
}

/**
 * What an insurance paper without a policy number asks for.
 *
 * @param  array<string, mixed>  $attributes
 * @return array{organizationId: ?int, organizationName: ?string, personId: ?int, type: ?string}
 */
function policyNeed(array $attributes = []): array
{
    return $attributes + ['organizationId' => 58, 'organizationName' => 'Prima Assicurazioni', 'personId' => 1, 'type' => 'car'];
}

describe('policyFromOtherFiles', function (): void {
    beforeEach(function (): void {
        $this->from = new PolicyFromOtherFiles;
        $this->contract = giver();
        $this->second = giver(['key' => 'file:2', 'fileName' => 'Contratto polizza PRP111.pdf', 'number' => 'PRP111']);
    });

    test('the same company, person and type: the contract gives its policy', function (): void {
        expect($this->from->matching(policyNeed(), [$this->contract]))->toEqual([$this->contract]);
    });

    test('another company, another person or another type: nothing', function (): void {
        expect($this->from->matching(policyNeed(['organizationId' => 12, 'organizationName' => 'Allianz']), [$this->contract]))->toBe([])
            ->and($this->from->matching(policyNeed(['personId' => 2]), [$this->contract]))->toBe([])
            ->and($this->from->matching(policyNeed(['type' => 'life']), [$this->contract]))->toBe([]);
    });

    test('a person or a type missing on one side does not count', function (): void {
        expect($this->from->matching(policyNeed(['personId' => null]), [$this->contract]))->toEqual([$this->contract])
            ->and($this->from->matching(policyNeed(['type' => null]), [$this->contract]))->toEqual([$this->contract]);
    });

    test('a company created in the same round is compared by its name, written in any way', function (): void {
        $created = giver(['organizationId' => null, 'organizationName' => 'Prima Assicurazioni S.p.A.']);

        expect($this->from->matching(policyNeed(['organizationId' => null, 'organizationName' => 'PRIMA ASSICURAZIONI']), [$created]))->toEqual([$created]);
    });

    test('no company on either side: nothing', function (): void {
        $none = giver(['organizationId' => null, 'organizationName' => null]);

        expect($this->from->matching(policyNeed(['organizationId' => null, 'organizationName' => null]), [$none]))->toBe([]);
    });

    test('one file fits: taken by itself; two: only the one picked by hand', function (): void {
        expect($this->from->chosen(policyNeed(), [$this->contract], 'auto'))->toEqual($this->contract)
            ->and($this->from->chosen(policyNeed(), [$this->contract, $this->second], 'auto'))->toBeNull()
            ->and($this->from->chosen(policyNeed(), [$this->contract, $this->second], 'file:2'))->toEqual($this->second);
    });

    test('"none", or a file that does not fit: no policy', function (): void {
        $allianz = giver(['key' => 'file:2', 'organizationId' => 12, 'organizationName' => 'Allianz']);

        expect($this->from->chosen(policyNeed(), [$this->contract], 'none'))->toBeNull()
            ->and($this->from->chosen(policyNeed(), [$this->contract, $allianz], 'file:2'))->toBeNull();
    });

    test('the files giving a policy are confirmed first, the others keep their order', function (): void {
        expect($this->from->confirmOrder([10, 20, 30], fn (int $id): bool => $id === 20))->toBe([20, 10, 30]);
    });
});
