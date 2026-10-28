import { Car, FileText, Wrench } from '@lucide/vue';
import { expect, it } from 'vite-plus/test';
import { cardIcon } from './cards';

it('gives every card type its icon, and a sheet to an unknown one', () => {
    expect(cardIcon('vehicle')).toBe(Car);
    expect(cardIcon('maintenance_record')).toBe(Wrench);
    expect(cardIcon('spaceship')).toBe(FileText);
});
