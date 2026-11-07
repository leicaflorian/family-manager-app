import { Car, FileText, Wrench } from '@lucide/vue';
import { expect, it } from 'vite-plus/test';
import { cardIcon, fieldInput, isMenuField, newCardUrl } from './cards';

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
            plural_label: 'Residences',
            description: '',
            area: 'home',
            area_label: '',
        },
        {
            type: 'utility',
            label: 'Utility',
            plural_label: 'Utilities',
            description: '',
            area: 'home',
            area_label: '',
        },
        {
            type: 'vehicle',
            label: 'Vehicle',
            plural_label: 'Vehicles',
            description: '',
            area: 'vehicles',
            area_label: '',
        },
    ];

    expect(newCardUrl(types, 'home')).toBe('/cards/create?area=home');
    expect(newCardUrl(types, 'vehicles')).toBe('/cards/create?card=vehicle');
    expect(newCardUrl([], 'work')).toBe('/cards/create?area=work');
});

it('draws each kind of field with its FormKit input, and text for an unknown kind', () => {
    expect(fieldInput('date')).toBe('datepicker');
    expect(fieldInput('dropdown')).toBe('autocomplete');
    expect(fieldInput('choice')).toBe('togglebuttons');
    expect(fieldInput('currency')).toBe('currency');
    expect(fieldInput('spaceship')).toBe('text');
});

it('knows the fields that are a menu that can be left empty', () => {
    expect(isMenuField('dropdown')).toBe(true);
    expect(isMenuField('person')).toBe(true);
    expect(isMenuField('choice')).toBe(false);
    expect(isMenuField('text')).toBe(false);
});
