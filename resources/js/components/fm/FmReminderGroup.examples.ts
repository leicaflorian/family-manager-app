/**
 * The cases of `FmReminderGroup` for the component catalogue: a group with
 * its kinds, the default group, and one that whoever is looking cannot edit.
 */
import { defineExamples } from '@/catalog/examples';
import type { ReminderGroupItem, RulePerson } from '@/lib/reminders';
import FmReminderGroup from './FmReminderGroup.vue';

/** The people with an account. */
const people: RulePerson[] = [
    { id: 1, name: 'Marco', initials: 'MB', photo: null },
    { id: 2, name: 'Laura', initials: 'LB', photo: null },
];

/** The group of the vehicles. */
const group: ReminderGroupItem = {
    name: 'Veicoli',
    is_default: false,
    kinds: ['Bollo', 'Revisione'],
    rules: [
        { days: 45, timing: 'before', channels: 'both', recipients: ['all'] },
        { days: 15, timing: 'before', channels: 'app', recipients: [1] },
        { days: 0, timing: 'before', channels: 'both', recipients: [1, 2] },
    ],
};

export default defineExamples(FmReminderGroup, [
    { name: 'With kinds', props: { group, people, canEdit: true } },
    {
        name: 'Default',
        props: {
            group: {
                ...group,
                name: 'Predefinito',
                is_default: true,
                kinds: [],
            },
            people,
            canEdit: true,
        },
    },
    { name: 'Cannot edit', props: { group, people, canEdit: false } },
]);
