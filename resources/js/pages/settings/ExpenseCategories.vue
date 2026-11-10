<script setup lang="ts">
/**
 * *Expense categories*: the tree with the card each category refers to. An
 * administrator taps a main category to choose its *Linked card* in the panel;
 * a subcategory takes the one of its main category. Nothing is added, renamed
 * or deleted here.
 */
import { Head, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import { useSaveShortcut } from '@/composables/useSaveShortcut';
import FmBottomSheet from '@/components/fm/FmBottomSheet.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmCategoryTree from '@/components/fm/FmCategoryTree.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { errors } from '@/lib/errors';
import { settings } from '@/routes';
import { update } from '@/routes/expense-categories';
import { useUnsavedChanges } from '@/composables/useUnsavedChanges';
import { usePageTrail } from '@/composables/usePageTrail';

const { cardTypes } = defineProps<{
    /** The tree, from the main ones. */
    categories: App.Data.ExpenseCategoryData[];
    /** The 13 card types to choose from. */
    cardTypes: App.Data.OptionData[];
    /** Whether who looks can change the categories (only an administrator). */
    canEdit: boolean;
}>();

/** The main category in the panel. */
const selected = ref<App.Data.ExpenseCategoryData | null>(null);
const open = ref(false);
// The dropdown has no null value: the empty text is «No card»
const form = useForm({ card_type: '' });

/** Closing the panel with the × asks first once something changed. */
const changes = useUnsavedChanges(() => form.data(), {
    active: () => open.value,
});

/** The card types, after «No card». */
const options = computed(() => [
    { value: '', label: trans('No card') },
    ...cardTypes,
]);

/**
 * Opens the panel for a main category.
 *
 * @param category - the category tapped
 */
function openSheet(category: App.Data.ExpenseCategoryData): void {
    selected.value = category;
    form.clearErrors();
    form.card_type = category.card_type ?? '';
    changes.reset();
    open.value = true;
}

/**
 * Saves the card type and closes the panel.
 */
function save(): void {
    if (selected.value === null) {
        return;
    }

    form.transform((data) => ({
        card_type: data.card_type === '' ? null : data.card_type,
    })).submit(update(selected.value.id), {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    });
}

// Only the open panel has a form to save
useSaveShortcut(save, () => open.value && !form.processing);

usePageTrail(() => [
    { label: trans('Settings'), href: settings().url },
    { label: trans('Expense categories') },
]);
</script>

<template>
    <Head :title="$t('Expense categories')" />

    <div class="flex max-w-[720px] flex-col gap-5">
        <FmTitle
            :title="$t('Expense categories')"
            :back="settings().url"
            :back-label="$t('Settings')"
            :subtitle="
                $t(
                    'The card a category refers to is chosen on its main category',
                )
            "
        />

        <FmCard list>
            <FmCategoryTree
                :categories="categories"
                :selectable="canEdit"
                main-only
                @select="openSheet"
            >
                <template #after="{ category }">
                    <span
                        v-if="category.card_label"
                        class="text-meta text-ink-soft"
                        >{{ category.card_label }}</span
                    >
                </template>
            </FmCategoryTree>
        </FmCard>

        <FmBottomSheet
            v-model:open="open"
            :before-close="changes.confirmClose"
            :title="selected?.name ?? ''"
        >
            <form class="flex flex-col gap-3" novalidate @submit.prevent="save">
                <FormKit
                    v-model="form.card_type"
                    type="dropdown"
                    :label="$t('Linked card')"
                    name="card_type"
                    :options="options"
                    :help="
                        $t(
                            'The expenses of this category and of its subcategories refer to a card of this type',
                        )
                    "
                    :errors="errors(form.errors.card_type)"
                />
                <div class="flex justify-end gap-2.5 pt-2">
                    <FmButton variant="text" @click="open = false">{{
                        $t('Cancel')
                    }}</FmButton>
                    <FmButton type="submit" :disabled="form.processing">{{
                        $t('Save')
                    }}</FmButton>
                </div>
            </form>
        </FmBottomSheet>
    </div>
</template>
