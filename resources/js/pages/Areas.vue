<script setup lang="ts">
/**
 * *Areas* page: the cards (home, vehicles, policies…) come with the item of the
 * cards. Meanwhile the page says so and leads to the documents.
 */
import { Head } from '@inertiajs/vue3';
import { Shapes } from '@lucide/vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmEmpty from '@/components/fm/FmEmpty.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { index as documents } from '@/routes/documents';
</script>

<template>
    <Head :title="$t('Areas')" />

    <FmTitle :title="$t('Areas')" />
    <FmEmpty
        :icon="Shapes"
        :title="$t('The cards are coming soon')"
        :text="
            $t(
                'Here you will find the home, the vehicles, the policies and the other things of the family, each with its documents.',
            )
        "
    >
        <FmButton variant="outline" :href="documents().url">{{
            $t('Go to the documents')
        }}</FmButton>
    </FmEmpty>
</template>
