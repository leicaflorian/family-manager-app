/**
 * The cases of `FmNotificationRule` for the component catalogue: days before
 * for everyone, the day itself for two people, days after only by email.
 */
import { defineExamples } from '@/catalog/examples';
import type { RulePerson } from '@/lib/reminders';
import FmNotificationRule from './FmNotificationRule.vue';

/** The people with an account. */
const people: RulePerson[] = [
    { id: 1, name: 'Marco', initials: 'MB', photo: null },
    { id: 2, name: 'Laura', initials: 'LB', photo: null },
];

export default defineExamples(FmNotificationRule, [
    {
        name: 'Days before',
        props: {
            people,
            modelValue: {
                days: 45,
                timing: 'before',
                channels: 'both',
                recipients: ['all'],
            },
        },
    },
    {
        name: 'On the day, two people',
        props: {
            people,
            modelValue: {
                days: 0,
                timing: 'before',
                channels: 'app',
                recipients: [1, 2],
            },
        },
    },
    {
        name: 'Days after, email',
        props: {
            people,
            modelValue: {
                days: 7,
                timing: 'after',
                channels: 'email',
                recipients: ['all'],
            },
        },
    },
]);
