<script setup lang="ts">
/**
 * The *Home*: the day and the greeting, the files waiting for their document
 * (on top, only when there are some), the blue box with what to pay in the
 * next 30 days and the list of the deadlines, the incomes on their way and,
 * for a new family, *To begin*. One column on the phone, two from the
 * computer: the box and the deadlines on the left, the incomes on the right.
 * *Something is missing* (on the right, only when a card lacks a document,
 * with the page that uploads it) and the bell of the notices beside the
 * greeting. The recently uploaded documents and the mail come with their own
 * items.
 */
import { Head, usePage } from '@inertiajs/vue3';
import { getActiveLanguage, trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import FmAgendaRow from '@/components/fm/FmAgendaRow.vue';
import type { ChecklistStep } from '@/components/fm/FmChecklist.vue';
import FmBell from '@/components/fm/FmBell.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmChecklist from '@/components/fm/FmChecklist.vue';
import FmFileTile from '@/components/fm/FmFileTile.vue';
import FmRow from '@/components/fm/FmRow.vue';
import FmSectionLink from '@/components/fm/FmSectionLink.vue';
import FmTotal from '@/components/fm/FmTotal.vue';
import { formatLongDate, formatSize } from '@/lib/format';
import { agenda, areas, upload } from '@/routes';
import { create as newCard } from '@/routes/cards';
import { create as invite } from '@/routes/family/invitations';

const { home } = defineProps<{
    /** What the Home shows, as the server sends it. */
    home: App.Data.HomeData;
}>();

const page = usePage();

/** How many notices the account has not seen yet, for the dot of the bell. */
const unreadNotices = computed((): number => page.props.unread_notices ?? 0);

const day = computed(() => formatLongDate(home.today, getActiveLanguage()));

/** The files waiting that the section does not show. */
const morePending = computed(() => home.pending_count - home.pending.length);

/**
 * The step of *To begin* for a step of the server: its text and where it leads.
 *
 * @param step - the step, with the name to invite for `invite`
 * @returns the step for `FmChecklist`
 */
function toStep(step: App.Data.ChecklistStepData): ChecklistStep {
    const done = step.done;

    switch (step.key) {
        case 'utilities':
            return {
                title: trans('The bills of the house'),
                subtitle: trans(
                    'Electricity, gas, water and internet are already in the areas',
                ),
                href: areas().url,
                done,
            };
        case 'first_bill':
            return {
                title: trans('Upload the first bill'),
                subtitle: trans('Take a photo or choose the PDF'),
                href: upload().url,
                done,
            };
        case 'card':
            return {
                title: trans('Add the car, a policy or a subscription'),
                subtitle: trans('Every item is a card, with its deadlines'),
                href: newCard().url,
                done,
            };
        default:
            return {
                title:
                    step.name === null
                        ? trans('Invite a family member')
                        : trans('Invite :name', { name: step.name }),
                subtitle: trans('So they get the alerts too'),
                href: invite().url,
                done,
            };
    }
}

/** The steps of *To begin*; empty when all are done. */
const steps = computed(() => home.checklist.map(toStep));

/** Whether the family is still beginning: the incomes tell how they will show. */
const beginning = computed(() => steps.value.length > 0);

/** Whether the right column has something to show; without it the left one takes the whole width. */
const hasRightColumn = computed(
    () =>
        home.incoming.length > 0 || home.missing.length > 0 || beginning.value,
);
</script>

<template>
    <Head :title="$t('Home')" />

    <div class="flex flex-col gap-5">
        <div class="flex items-center justify-between gap-3">
            <div class="flex flex-col gap-1">
                <span class="text-sm text-ink-soft lg:text-[15px]">{{
                    day
                }}</span>
                <h1 class="m-0 text-[28px] font-extrabold lg:text-[32px]">
                    {{ $t('Hi, :name', { name: home.first_name }) }}
                </h1>
            </div>
            <FmBell :count="unreadNotices" class="rounded-full bg-white" />
        </div>

        <FmCard v-if="home.pending_count > 0" :title="$t('Waiting')" list>
            <template #actions>
                <FmSectionLink :href="upload().url">{{
                    $t('Finish')
                }}</FmSectionLink>
            </template>
            <FmRow
                v-for="file in home.pending"
                :key="file.id"
                :title="file.title"
                :subtitle="formatSize(file.size)"
                :arrow="false"
            >
                <template #before>
                    <FmFileTile />
                </template>
            </FmRow>
            <p
                v-if="morePending > 0"
                class="m-0 px-4 py-3.5 text-[15px] text-ink-soft"
            >
                {{
                    $tChoice(
                        '{1} +:count more|[2,*] +:count more',
                        morePending,
                        { count: String(morePending) },
                    )
                }}
            </p>
        </FmCard>

        <div class="grid gap-5 lg:grid-cols-5 lg:items-start lg:gap-6">
            <div
                class="flex flex-col gap-5"
                :class="hasRightColumn ? 'lg:col-span-3' : 'lg:col-span-5'"
            >
                <FmTotal
                    :total="home.total"
                    :payments="home.payments"
                    :late="home.late"
                />

                <FmCard
                    v-if="home.entries.length > 0"
                    :title="$t('The deadlines')"
                    list
                >
                    <FmAgendaRow
                        v-for="entry in home.entries"
                        :key="entry.key"
                        :entry="entry"
                        layout="home"
                    />
                    <template v-if="home.more" #actions>
                        <FmSectionLink :href="agenda().url">{{
                            $t('See everything in the Agenda')
                        }}</FmSectionLink>
                    </template>
                </FmCard>

                <FmChecklist v-if="beginning" :steps="steps" />
            </div>

            <div
                v-if="hasRightColumn"
                class="flex flex-col gap-5 lg:col-span-2"
                :class="{
                    'max-lg:hidden':
                        home.incoming.length === 0 && home.missing.length === 0,
                }"
            >
                <FmCard
                    v-if="home.incoming.length > 0"
                    :title="$t('Incoming')"
                    list
                >
                    <FmAgendaRow
                        v-for="entry in home.incoming"
                        :key="entry.key"
                        :entry="entry"
                        layout="home"
                    />
                </FmCard>
                <FmCard v-else :title="$t('Incoming')" class="max-lg:hidden">
                    <p class="m-0 text-[15px] text-ink-soft">
                        {{
                            $t(
                                'Salaries and other incomes show here when you add the card of a job.',
                            )
                        }}
                    </p>
                </FmCard>
                <FmCard
                    v-if="home.missing.length > 0"
                    :title="$t('Something is missing')"
                    list
                >
                    <FmRow
                        v-for="item in home.missing"
                        :key="item.key"
                        :title="item.document"
                        :subtitle="item.card"
                        :href="item.href"
                        tone="warning"
                    />
                </FmCard>
            </div>
        </div>
    </div>
</template>
