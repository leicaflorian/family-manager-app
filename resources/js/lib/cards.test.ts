import { Car, FileText, Wrench } from '@lucide/vue';
import { describe, expect, it } from 'vite-plus/test';
import {
    employmentFields,
    employmentTitleParts,
    enrollmentFields,
    enrollmentTitleParts,
    utilityFormFields,
    utilityTitleParts,
} from '@/test/fixtures';
import {
    cardIcon,
    composeTitle,
    fieldInput,
    isMenuField,
    newCardUrl,
} from './cards';

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

describe('composeTitle', () => {
    it('names a utility by its type and supplier, the supplier winning over the residence', () => {
        expect(
            composeTitle(utilityTitleParts, utilityFormFields, {
                type: 'electricity',
                organization_id: '1',
                home_id: '1',
            }),
        ).toBe('Electricity - Edison');
    });

    it('names a utility with no supplier by its type and residence', () => {
        expect(
            composeTitle(utilityTitleParts, utilityFormFields, {
                type: 'gas',
                organization_id: null,
                home_id: '1',
            }),
        ).toBe('Gas - Casa di via Roma');
    });

    it('names a utility with neither by its type only', () => {
        expect(
            composeTitle(utilityTitleParts, utilityFormFields, {
                type: 'gas',
                organization_id: '',
                home_id: null,
            }),
        ).toBe('Gas');
    });

    it('names an employment by the first name of the person and the employer', () => {
        expect(
            composeTitle(employmentTitleParts, employmentFields, {
                person_id: '1',
                organization_id: '5',
            }),
        ).toBe('Marco - Studio Rinaldi');
    });

    it('names an enrollment by first name, type and organization', () => {
        expect(
            composeTitle(enrollmentTitleParts, enrollmentFields, {
                person_id: '2',
                type: 'sport',
                organization_id: '6',
            }),
        ).toBe('Giulia - Sport - Scuola Passo a Due');
    });

    it('gives nothing while nothing is chosen, or for a type with no composed name', () => {
        expect(composeTitle(enrollmentTitleParts, enrollmentFields, {})).toBe(
            '',
        );
        expect(
            composeTitle([], utilityFormFields, { type: 'electricity' }),
        ).toBe('');
    });

    it('reads an id given as a number like its text option', () => {
        expect(
            composeTitle(employmentTitleParts, employmentFields, {
                person_id: 3,
                organization_id: 4,
            }),
        ).toBe('Laura - Comune di Bologna');
    });
});
