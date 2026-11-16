import { describe, expect, it } from 'vite-plus/test';
import { replaceableFor } from './policies';

/** An old policy the form can replace. */
function policy(
    id: number,
    overrides: Partial<App.Data.ReplaceablePolicyData> = {},
): App.Data.ReplaceablePolicyData {
    return {
        id,
        label: `Policy ${id}`,
        expiry_date: '2026-11-21',
        type: 'car',
        vehicle_id: 1,
        home_id: null,
        employment_id: null,
        ...overrides,
    };
}

describe('replaceableFor', () => {
    it('offers the policies of the same vehicle and type', () => {
        const policies = [
            policy(1),
            policy(2, { vehicle_id: 2 }),
            policy(3, { type: 'home', vehicle_id: null, home_id: 1 }),
        ];

        expect(
            replaceableFor(
                policies,
                { type: 'car', vehicle_id: '1' },
                [],
            ).map((item) => item.id),
        ).toEqual([1]);
    });

    it('offers nothing when the type has no covered card', () => {
        const policies = [policy(1, { type: 'travel' })];

        expect(
            replaceableFor(
                policies,
                { type: 'travel', vehicle_id: '1' },
                [],
            ),
        ).toEqual([]);
        expect(
            replaceableFor(
                [policy(1)],
                { type: 'car', vehicle_id: null },
                [],
            ),
        ).toEqual([]);
    });

    it('keeps the policy to replace whatever the covered card', () => {
        const policies = [policy(1), policy(7, { type: 'home', home_id: 4 })];

        expect(
            replaceableFor(
                policies,
                { type: 'car', vehicle_id: '1' },
                [7],
            ).map((item) => item.id),
        ).toEqual([1, 7]);
    });
});
