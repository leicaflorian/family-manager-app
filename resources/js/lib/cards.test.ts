import { Car, FileText, Wrench } from '@lucide/vue';
import { expect, it } from 'vite-plus/test';
import { cardIcon, newCardUrl } from './cards';

it('gives every card type its icon, and a sheet to an unknown one', () => {
    expect(cardIcon('vehicle')).toBe(Car);
    expect(cardIcon('maintenance_record')).toBe(Wrench);
    expect(cardIcon('spaceship')).toBe(FileText);
});

it('opens the form when an area has one card type, the choice when it has more', () => {
    const types = [
        {
            type: 'home',
            label: 'Residence',
            description: '',
            area: 'home',
            area_label: '',
        },
        {
            type: 'utility',
            label: 'Utility',
            description: '',
            area: 'home',
            area_label: '',
        },
        {
            type: 'vehicle',
            label: 'Vehicle',
            description: '',
            area: 'vehicles',
            area_label: '',
        },
    ];

    expect(newCardUrl(types, 'home')).toBe('/cards/create?area=home');
    expect(newCardUrl(types, 'vehicles')).toBe('/cards/create?type=vehicle');
    expect(newCardUrl([], 'work')).toBe('/cards/create?area=work');
});
