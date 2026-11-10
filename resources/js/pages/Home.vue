<script setup lang="ts">
/**
 * The *Home*: the day and the greeting, the box of the documents to complete
 * (on top, only when files wait for their document, with the button to *To sort*), the blue box with what to pay in the
 * next 30 days and the list of the deadlines, the incomes on their way and,
 * for a new family, *To begin*. One column on the phone, two from the
 * computer: the box and the deadlines on the left, the incomes on the right.
 * *Something is missing* (on the right, only when a card lacks a document,
 * with the page that uploads it) and the bell of the notices beside the
 * greeting, whose menu loads the notices when it opens. *Mail that arrived* (on the right, between the incomes and
 * what is missing, only when mail with files arrived) lists the last three mails with the card their documents are
 * linked to, each opening *Received mail*. The recently uploaded documents come with their own item.
 *
 * The search of the whole app: on the computer the box beside the greeting
 * (300 px, 420 px while it has the focus or some text) with the menu of the
 * first results under it, and the square bell; ⌘K, Ctrl K or «/» put the focus
 * in the box. On the phone a round magnifier before the round bell opens the
 * *Search* page.
 */
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { getActiveLanguage, trans } from 'laravel-vue-i18n';
import { ArrowRight, Mail, Search } from '@lucide/vue';
import { computed, ref, useTemplateRef } from 'vue';
import FmAgendaRow from '@/components/fm/FmAgendaRow.vue';
import FmButton from '@/components/fm/FmButton.vue';
import type { ChecklistStep } from '@/components/fm/FmChecklist.vue';
import type { BellNotices } from '@/components/fm/FmBell.vue';
import FmBell from '@/components/fm/FmBell.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmChecklist from '@/components/fm/FmChecklist.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmRow from '@/components/fm/FmRow.vue';
import FmSearchField from '@/components/fm/FmSearchField.vue';
import FmSearchMenu from '@/components/fm/FmSearchMenu.vue';
import FmSectionLink from '@/components/fm/FmSectionLink.vue';
import FmTotal from '@/components/fm/FmTotal.vue';
import { capitalized } from '@/lib/budget';
import { SEARCH_MENU_ID, useSearchBox } from '@/composables/useSearchBox';
import { useSearchShortcut } from '@/composables/useSearchShortcut';
import { useWideLayout } from '@/composables/useWideLayout';
import { formatLongDate, relativeDays } from '@/lib/format';
import { agenda, areas, search, toSort, upload } from '@/routes';
import { create as newCard } from '@/routes/cards';
import { index as receivedMail } from '@/routes/mail';
import { create as invite } from '@/routes/family/invitations';

const { home } = defineProps<{
    /** What the Home shows, as the server sends it. */
    home: App.Data.HomeData;
}>();

const page = usePage();

/** The notices of the menu of the bell: the server sends them only when the menu asks. */
const notices = computed((): BellNotices | undefined => page.props.bell_notices);

/** How many notices the account has not seen yet, for the dot of the bell. */
const unreadNotices = computed((): number => page.props.unread_notices ?? 0);

/** Loads the notices of the bell when its menu opens, without changing the address. */
function loadNotices(): void {
    router.reload({ only: ['bell_notices'], preserveUrl: true });
}

const day = computed(() => formatLongDate(home.today, getActiveLanguage()));

/**
 * Whether the window has the wide layout: the search box instead of the
 * magnifier, and the button of the documents to complete beside the text.
 */
const wide = useWideLayout();

/** The text in the search box. */
const text = ref('');

/** The search box of the computer, to focus it with the shortcut. */
const field = useTemplateRef<InstanceType<typeof FmSearchField>>('field');
/** The menu of the first results under the box, for the arrows and Enter. */
const menu = useTemplateRef<InstanceType<typeof FmSearchMenu>>('menu');

/** Searches the text of the box on the *Search* page. */
function submit(): void {
    const q = text.value.trim();

    router.visit(q === '' ? search.url() : search.url({ query: { q } }));
}

// The box grows to 420 px while it has the focus or some text
const {
    menuResults,
    enter,
    onKeydown,
    closeMenu,
    focused,
    onFocusIn,
    onFocusOut,
} = useSearchBox(text, menu, submit);

// On the phone there is no box: the keys open the Search page
useSearchShortcut(() =>
    field.value ? field.value.focus() : router.visit(search.url()),
);

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
        home.incoming.length > 0 ||
        home.missing.length > 0 ||
        home.mails.length > 0 ||
        beginning.value,
);

/**
 * The second line of a mail of *Mail that arrived*: the day it arrived and, when its documents are linked to cards, which.
 *
 * @param mail - the mail, as the server sends it
 * @returns «Yesterday · linked to Gas - Enel Energia», or only the day
 */
function mailLine(mail: App.Data.HomeMailData): string {
    const day = capitalized(relativeDays(mail.received_at));

    return mail.links.length === 0
        ? day
        : trans(':day · linked to :cards', {
              day,
              cards: mail.links.join(', '),
          });
}

/**
 * The first line of a mail of *Mail that arrived*: who wrote and the subject, or only who wrote.
 *
 * @param mail - the mail, as the server sends it
 * @returns «Enel Energia · La tua bolletta»
 */
function mailTitle(mail: App.Data.HomeMailData): string {
    return mail.subject === null
        ? mail.sender
        : `${mail.sender} · ${mail.subject}`;
}
</script>

<template>
    <Head :title="$t('Home')" />

    <div class="flex flex-col gap-5">
        <div
            class="flex justify-between gap-3"
            :class="wide ? 'items-end' : 'items-center'"
        >
            <div class="flex flex-col gap-1">
                <span class="text-body-sm text-ink-soft lg:text-body-sm">{{
                    day
                }}</span>
                <h1 class="m-0 text-title-mobile lg:text-title">
                    {{ $t('Hi, :name', { name: home.first_name }) }}
                </h1>
            </div>
            <div
                class="flex shrink-0 items-center"
                :class="wide ? 'gap-2.5' : 'gap-2'"
            >
                <div
                    v-if="wide"
                    class="relative max-w-full transition-[width] duration-200 motion-reduce:transition-none"
                    :class="focused || text !== '' ? 'w-105' : 'w-75'"
                    @focusin="onFocusIn"
                    @focusout="onFocusOut"
                >
                    <FmSearchField
                        ref="field"
                        v-model="text"
                        :label="$t('Search in Family Manager')"
                        shortcut
                        clearable
                        :controls="SEARCH_MENU_ID"
                        :expanded="menuResults !== null"
                        :active-descendant="
                            menuResults ? menu?.activeId : undefined
                        "
                        @submit="enter"
                        @escape="closeMenu"
                        @keydown="onKeydown"
                    />
                    <FmSearchMenu
                        v-if="menuResults"
                        :id="SEARCH_MENU_ID"
                        ref="menu"
                        :results="menuResults"
                        :query="menuResults.query"
                        class="max-w-none"
                        @mousedown.prevent
                    />
                </div>
                <Link
                    v-else
                    :href="search.url()"
                    :aria-label="$t('Search in Family Manager')"
                    class="flex size-11 items-center justify-center rounded-full bg-card text-ink"
                >
                    <FmIcon :icon="Search" :size="22" />
                </Link>
                <FmBell
                    :count="unreadNotices"
                    :notices="notices"
                    :class="
                        wide
                            ? 'rounded-lg border border-edge-soft bg-card'
                            : 'rounded-full bg-card'
                    "
                    @open="loadNotices"
                />
            </div>
        </div>

        <section
            v-if="home.pending_count > 0"
            :aria-label="$t('Documents to complete')"
            class="flex gap-4 rounded-card bg-card"
            :class="wide ? 'items-center px-5 py-4.5' : 'flex-col gap-3.5 p-4'"
        >
            <div
                class="flex min-w-0 grow items-center"
                :class="wide ? 'gap-4' : 'gap-3.5'"
            >
                <span
                    aria-hidden="true"
                    class="relative size-15 shrink-0 rounded-xl bg-action-soft"
                >
                    <span
                        class="absolute top-2.25 left-3.5 h-9.5 w-7.5 -translate-x-1.25 translate-y-0.5 -rotate-12 rounded-sm border border-edge bg-card"
                    />
                    <span
                        class="absolute top-2.25 left-3.5 h-9.5 w-7.5 translate-x-1.25 translate-y-px rotate-10 rounded-sm border border-edge bg-card"
                    />
                    <span
                        class="absolute top-2.25 left-3.5 flex h-9.5 w-7.5 items-center justify-center rounded-sm border border-action bg-card text-stat text-action-dark"
                        >{{ home.pending_count }}</span
                    >
                </span>
                <span class="flex min-w-0 grow flex-col gap-0.75">
                    <span class="text-section text-ink">{{
                        $tChoice(
                            '{1} :count document to complete|[2,*] :count documents to complete',
                            home.pending_count,
                        )
                    }}</span>
                    <span class="text-meta text-ink-soft">{{
                        $t('Check what I read and save them')
                    }}</span>
                </span>
            </div>
            <FmButton :href="toSort().url" :wide="!wide">
                {{ $t('Complete') }}
                <FmIcon :icon="ArrowRight" :size="20" />
            </FmButton>
        </section>

        <div class="grid gap-5 lg:grid-cols-5 lg:items-start lg:gap-6">
            <div
                class="flex min-w-0 flex-col gap-5"
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
                class="flex min-w-0 flex-col gap-5 lg:col-span-2"
                :class="{
                    'max-lg:hidden':
                        home.incoming.length === 0 &&
                        home.missing.length === 0 &&
                        home.mails.length === 0,
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
                    <p class="m-0 text-body-sm text-ink-soft">
                        {{
                            $t(
                                'Salaries and other incomes show here when you add the card of a job.',
                            )
                        }}
                    </p>
                </FmCard>
                <FmCard
                    v-if="home.mails.length > 0"
                    :title="$t('Mail that arrived')"
                    list
                >
                    <FmRow
                        v-for="mail in home.mails"
                        :key="mail.id"
                        :title="mailTitle(mail)"
                        :subtitle="mailLine(mail)"
                        :href="receivedMail().url"
                    >
                        <template #before>
                            <span
                                aria-hidden="true"
                                class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-action-soft text-action-dark"
                            >
                                <FmIcon :icon="Mail" :size="20" />
                            </span>
                        </template>
                    </FmRow>
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
