<script setup lang="ts">
/**
 * A document as the board *Un documento* shows it: the preview of the file on
 * grey and, beside it, its data, the linked records (one type at a time) and
 * the notes. On its own page the buttons are next to the title of the page; in
 * the panel beside the list it is one card, with the title, the date and the
 * buttons (`FmDocumentActions`) on top.
 * Used by *A document* and, on the computer, on the right of *Documents*.
 */
import { Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useFormat } from '@/composables/useFormat';
import { cn } from '@/lib/utils';
import { show as showCard } from '@/routes/cards';
import { edit, show } from '@/routes/documents';
import FmAvatar from './FmAvatar.vue';
import FmButton from './FmButton.vue';
import FmDocumentActions from './FmDocumentActions.vue';
import FmFilePanel from './FmFilePanel.vue';
import FmIcon from './FmIcon.vue';

const { document, compact = false } = defineProps<{
    /** The document. */
    document: App.Data.DocumentDetailData;
    /**
     * The panel beside the list: one card, with the title and the date on top
     * and, on their right, *Download* and the ⋯ menu, icons only.
     */
    compact?: boolean;
}>();

const { date } = useFormat();

/** The colours of the badge of each status. */
const statusColor: Record<string, string> = {
    active: cn('bg-[#e6f4ee] text-paid'),
    expired: cn('bg-overdue/10 text-overdue'),
    archived: cn('bg-divider text-ink-soft'),
};

/** The linked records grouped by type, in the order they come. */
const linkGroups = computed(() => {
    const groups = new Map<
        string,
        { type: string; label: string; links: App.Data.DocumentLinkData[] }
    >();

    for (const link of document.links) {
        const group = groups.get(link.type) ?? {
            type: link.type,
            label: link.type_label,
            links: [],
        };
        group.links.push(link);
        groups.set(link.type, group);
    }

    return [...groups.values()];
});

/** The type of the linked records shown, the first one until another is chosen. */
const chosenType = ref<string | null>(null);

/** The group of linked records shown. */
const shownGroup = computed(
    () =>
        linkGroups.value.find((group) => group.type === chosenType.value) ??
        linkGroups.value[0],
);
</script>

<template>
    <div
        :data-card="compact || undefined"
        :class="
            cn(
                'flex flex-col gap-4',
                compact && 'rounded-[20px] bg-white p-5.5',
            )
        "
    >
        <div
            v-if="compact"
            class="flex flex-nowrap items-start justify-between gap-3"
        >
            <div class="flex min-w-0 flex-1 flex-col gap-1">
                <h2 class="m-0 text-xl font-extrabold">
                    <Link
                        :href="show.url(document.id)"
                        class="text-ink underline decoration-ink/30 decoration-2 underline-offset-4 hover:decoration-ink"
                        >{{ document.title }}</Link
                    >
                </h2>
                <span v-if="document.date" class="text-sm text-ink-soft">{{
                    $t('Arrived on :date', { date: date(document.date) })
                }}</span>
            </div>
            <FmDocumentActions :document="document" compact />
        </div>

        <div
            :class="
                cn(
                    'flex flex-col gap-4',
                    !compact &&
                        'gap-6 lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(0,1.55fr)] lg:items-start',
                )
            "
        >
            <FmFilePanel :file="document.file" :compact="compact" />

            <div class="flex min-w-0 flex-col gap-4">
                <div
                    :class="compact ? 'pt-1' : 'rounded-[20px] bg-white p-5.5'"
                >
                    <dl
                        class="m-0 grid grid-cols-[max-content_minmax(0,1fr)] items-baseline gap-x-4 gap-y-2.5 text-[15px]"
                    >
                        <dt class="text-ink-soft">
                            {{ $t('What kind it is') }}
                        </dt>
                        <dd class="m-0 font-semibold">
                            {{ document.category ?? '—' }}
                        </dd>
                        <dt class="text-ink-soft">{{ $t('Whose it is') }}</dt>
                        <dd class="m-0 font-semibold">
                            <span
                                v-if="document.person"
                                class="inline-flex items-center gap-2"
                            >
                                <FmAvatar
                                    v-if="document.person_initials"
                                    :initials="document.person_initials"
                                    :photo="document.person_photo"
                                    :name="document.person ?? ''"
                                    :size="22"
                                />
                                {{ document.person }}
                            </span>
                            <template v-else>—</template>
                        </dd>
                        <dt class="text-ink-soft">
                            {{ $t('Who it is from') }}
                        </dt>
                        <dd class="m-0 font-semibold">
                            {{ document.organization ?? '—' }}
                        </dd>
                        <dt class="text-ink-soft">{{ $t('Date') }}</dt>
                        <dd class="m-0 font-semibold">
                            {{ date(document.date) || '—' }}
                        </dd>
                        <dt class="text-ink-soft">{{ $t('Expiry') }}</dt>
                        <dd class="m-0 font-semibold">
                            <span v-if="document.expiry_date">{{
                                date(document.expiry_date)
                            }}</span>
                            <span v-else class="font-normal text-ink-soft">{{
                                $t('No expiry')
                            }}</span>
                        </dd>
                        <dt class="text-ink-soft">{{ $t('Status') }}</dt>
                        <dd class="m-0">
                            <span
                                class="inline-flex rounded-lg px-2 py-0.5 text-[13px] font-semibold whitespace-nowrap"
                                :class="statusColor[document.status]"
                                >{{ document.status_label }}</span
                            >
                        </dd>
                        <dt class="text-ink-soft">{{ $t('Tags') }}</dt>
                        <dd class="m-0 font-semibold">
                            {{ document.tags.join(', ') || '—' }}
                        </dd>
                        <template v-if="compact && document.links.length > 0">
                            <dt class="text-ink-soft">{{ $t('Links') }}</dt>
                            <dd class="m-0 flex flex-col gap-0.5 font-semibold">
                                <Link
                                    v-for="link in document.links"
                                    :key="`${link.type}:${link.id}`"
                                    :href="
                                        showCard({
                                            type: link.type,
                                            id: link.id,
                                        }).url
                                    "
                                    >{{ link.label }}</Link
                                >
                            </dd>
                        </template>
                        <template v-if="compact && document.notes">
                            <dt class="text-ink-soft">{{ $t('Notes') }}</dt>
                            <dd class="m-0 whitespace-pre-line text-ink-soft">
                                {{ document.notes }}
                            </dd>
                        </template>
                    </dl>
                </div>

                <section
                    v-if="!compact && (shownGroup || document.can_edit)"
                    class="flex flex-col gap-2"
                >
                    <h2 class="m-0 text-[17px] font-bold">{{ $t('Links') }}</h2>
                    <div class="flex flex-col rounded-2xl bg-white px-4 pt-1">
                        <div
                            class="flex items-center gap-4.5 border-b border-edge"
                        >
                            <button
                                v-for="group in linkGroups"
                                :key="group.type"
                                type="button"
                                :aria-pressed="group.type === shownGroup?.type"
                                class="flex h-10 items-center gap-1.5 border-0 border-b-2 bg-transparent px-1 text-[15px]"
                                :class="
                                    group.type === shownGroup?.type
                                        ? 'border-action font-bold text-action-dark'
                                        : 'border-transparent font-medium text-[#4A5565]'
                                "
                                @click="chosenType = group.type"
                            >
                                {{ group.label }}
                                <span class="font-semibold text-ink-soft">{{
                                    group.links.length
                                }}</span>
                            </button>
                            <span class="grow" />
                            <FmButton
                                v-if="document.can_edit"
                                :href="edit.url(document.id)"
                                :icon="Plus"
                                :label="$t('Add a link')"
                                xsmall
                            />
                        </div>
                        <p
                            v-if="!shownGroup"
                            class="m-0 py-3.5 text-[15px] text-ink-soft"
                        >
                            {{ $t('Nothing linked yet.') }}
                        </p>
                        <div v-else class="-mx-4 flex flex-col">
                            <Link
                                v-for="link in shownGroup.links"
                                :key="`${link.type}:${link.id}`"
                                :href="
                                    showCard({ type: link.type, id: link.id })
                                        .url
                                "
                                class="flex flex-col gap-0.5 px-4 py-3 text-ink no-underline"
                            >
                                <span class="text-base font-semibold">{{
                                    link.label
                                }}</span>
                                <span class="text-[13px] text-ink-soft">{{
                                    link.type_label
                                }}</span>
                            </Link>
                        </div>
                    </div>
                </section>

                <section
                    v-if="!compact && document.notes"
                    class="flex flex-col gap-2"
                >
                    <h2 class="m-0 text-[17px] font-bold">{{ $t('Notes') }}</h2>
                    <p
                        class="m-0 rounded-2xl bg-white px-4 py-3.5 text-[15px] leading-normal whitespace-pre-line text-[#4A5565]"
                    >
                        {{ document.notes }}
                    </p>
                </section>
            </div>
        </div>
    </div>
</template>
