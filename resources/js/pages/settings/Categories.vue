<script setup lang="ts">
/**
 * *Document categories*: the tree with the number of documents of each one. An
 * administrator adds, renames, moves and deletes in the panel; a category with
 * documents or subcategories cannot be deleted.
 */
import { Head, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import { useSaveShortcut } from '@/composables/useSaveShortcut';
import FmBottomSheet from '@/components/fm/FmBottomSheet.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmCategoryTree from '@/components/fm/FmCategoryTree.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { errors } from '@/lib/errors';
import { settings } from '@/routes';
import { destroy, store, update } from '@/routes/categories';

const { categories, canEdit } = defineProps<{
    /** The tree, from the main ones. */
    categories: App.Data.CategoryData[];
    /** Whether who looks can change the categories (only an administrator). */
    canEdit: boolean;
}>();

/** The category in the panel: null for a new one. */
const selected = ref<App.Data.CategoryData | null>(null);
const open = ref(false);
const form = useForm({ name: '', parent_id: '' });
// The deletion sends only the address; the server answers with the `category` error.
const deletion = useForm<{ category?: string }>({});

/**
 * All the categories in a row, with the path by name («Home and utilities › Electricity»).
 *
 * @param branches - a level of the tree
 * @param before - the path of the level above
 */
function flatten(
    branches: App.Data.CategoryData[],
    before = '',
): { category: App.Data.CategoryData; path: string }[] {
    return branches.flatMap((category) => {
        const path = before ? `${before} › ${category.name}` : category.name;

        return [{ category, path }, ...flatten(category.children, path)];
    });
}

/**
 * The ids of a category and of those below it: it cannot be moved there.
 *
 * @param category - the category to move
 */
function withDescendants(category: App.Data.CategoryData): number[] {
    return [category.id, ...category.children.flatMap(withDescendants)];
}

/** The places where the category of the panel can stay. */
const places = computed(() => {
    const excluded = selected.value ? withDescendants(selected.value) : [];

    return [
        { value: '', label: trans('None: it is a main one') },
        ...flatten(categories)
            .filter(({ category }) => !excluded.includes(category.id))
            .map(({ category, path }) => ({
                value: String(category.id),
                label: path,
            })),
    ];
});

/** Why the category of the panel can be deleted or not. */
const note = computed(() => {
    const category = selected.value;

    if (category === null) {
        return '';
    }

    if (category.documents_count > 0) {
        return transChoice(
            '{1} There is 1 document in this category: to delete it, first move it to another one.|[2,*] There are :count documents in this category: to delete it, first move them to another one.',
            category.documents_count,
        );
    }

    if (category.children.length > 0) {
        return trans(
            'It has subcategories: to delete it, first move or delete them.',
        );
    }

    return trans('No document is in this category: it can be deleted.');
});

const deletable = computed(
    () =>
        selected.value !== null &&
        selected.value.documents_count === 0 &&
        selected.value.children.length === 0,
);

/**
 * Opens the panel for a category, or for a new one.
 *
 * @param category - the category to edit, null for a new one
 */
function openSheet(category: App.Data.CategoryData | null): void {
    selected.value = category;
    form.clearErrors();
    deletion.clearErrors();
    form.name = category?.name ?? '';
    form.parent_id = category?.parent_id ? String(category.parent_id) : '';
    open.value = true;
}

/**
 * Saves the category and closes the panel.
 */
function save(): void {
    form.transform((data) => ({
        ...data,
        parent_id: data.parent_id === '' ? null : Number(data.parent_id),
    })).submit(selected.value ? update(selected.value.id) : store(), {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    });
}

// Only the open panel has a form to save
useSaveShortcut(save, () => open.value && !form.processing);

/**
 * Deletes the category of the panel.
 */
function deleteCategory(): void {
    if (selected.value !== null) {
        deletion.submit(destroy(selected.value.id), {
            preserveScroll: true,
            onSuccess: () => (open.value = false),
        });
    }
}
</script>

<template>
    <Head :title="$t('Document categories')" />

    <div class="flex max-w-[720px] flex-col gap-5">
        <FmTitle
            :title="$t('Document categories')"
            :back="settings().url"
            :back-label="$t('Settings')"
            :subtitle="
                $t(
                    'They say what kind of document it is; whose it is and what it refers to is said by the links',
                )
            "
        >
            <template v-if="canEdit" #actions>
                <FmButton :icon="Plus" @click="openSheet(null)">{{
                    $t('New category')
                }}</FmButton>
            </template>
        </FmTitle>

        <FmCard list>
            <FmCategoryTree
                :categories="categories"
                :selectable="canEdit"
                @select="openSheet"
            />
        </FmCard>

        <FmBottomSheet
            v-model:open="open"
            :title="selected ? selected.name : $t('New category')"
        >
            <form class="flex flex-col gap-3" novalidate @submit.prevent="save">
                <FormKit
                    v-model="form.name"
                    type="text"
                    :label="$t('Name')"
                    name="name"
                    :errors="errors(form.errors.name)"
                />
                <FormKit
                    v-model="form.parent_id"
                    type="dropdown"
                    :label="$t('Inside')"
                    name="parent_id"
                    :options="places"
                    :errors="errors(form.errors.parent_id)"
                />
                <p v-if="note" class="m-0 text-[13px] text-ink-soft">
                    {{ note }}
                </p>
                <FmNotice v-if="deletion.errors.category" tone="warning">{{
                    deletion.errors.category
                }}</FmNotice>
                <div
                    class="flex flex-wrap items-center justify-between gap-2.5 pt-2"
                >
                    <FmButton
                        v-if="deletable"
                        variant="danger"
                        :icon="Trash2"
                        :disabled="deletion.processing"
                        @click="deleteCategory"
                        >{{ $t('Delete') }}</FmButton
                    >
                    <span v-else />
                    <div class="flex gap-2.5">
                        <FmButton variant="text" @click="open = false">{{
                            $t('Cancel')
                        }}</FmButton>
                        <FmButton type="submit" :disabled="form.processing">{{
                            $t('Save')
                        }}</FmButton>
                    </div>
                </div>
            </form>
        </FmBottomSheet>
    </div>
</template>
