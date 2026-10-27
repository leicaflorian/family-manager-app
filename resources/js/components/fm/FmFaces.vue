<script setup lang="ts">
/**
 * The faces of the family, one over the other: the circles with the initials,
 * as in the *Invitation accepted* board.
 */
import { computed } from 'vue';
import { listNames } from '@/lib/names';
import FmAvatar from './FmAvatar.vue';

const { people, size = 48 } = defineProps<{
    /** The people to show. */
    people: App.Data.PersonSummaryData[];
    /** The side of each circle, in pixels. */
    size?: number;
}>();

/** The colours of the circles, in turn. */
const colors = ['bg-action', 'bg-paid', 'bg-warning', 'bg-ink'];

const names = computed(() => listNames(people.map((p) => p.first_name)));
</script>

<template>
    <ul :aria-label="names" class="m-0 flex list-none p-0">
        <li
            v-for="(person, i) in people"
            :key="person.id"
            aria-hidden="true"
            class="flex rounded-full shadow-[0_0_0_3px_#fff] not-first:-ml-2.5"
        >
            <FmAvatar
                :initials="person.initials"
                :size="size"
                :color="colors[i % colors.length]"
            />
        </li>
    </ul>
</template>
