<script setup lang="ts">
/**
 * The link to *To sort* that sits beside the title of *Documents* and
 * *Upload*: a soft button that says how many files wait there. With none it is
 * not shown.
 *
 * @group navigation
 */
import { toSort } from '@/routes';
import FmButton from './FmButton.vue';

const { count } = defineProps<{
    /** How many files wait, copies left out. */
    count: number;
}>();
</script>

<template>
    <FmButton v-if="count > 0" variant="soft" :href="toSort().url">
        {{
            $tChoice(
                '{1} :count file to sort|[2,*] :count files to sort',
                count,
                { count: String(count) },
            )
        }}
    </FmButton>
</template>
