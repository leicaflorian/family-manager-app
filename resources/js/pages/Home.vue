<script setup lang="ts">
/**
 * The *Home*: the day and the greeting, the box of the documents to complete
 * (on top, only when files wait for their document, with the button to *To sort*), the blue box with what to pay in the
 * next 30 days and the list of the deadlines, the incomes on their way and,
 * for a new family, *To begin*. One column on the phone, two from the
 * computer: the box and the deadlines on the left, the incomes on the right.
 * *Something is missing* (on the right, only when a card lacks a document,
 * with the page that uploads it). *Mail that arrived* (on the right, between the incomes and
 * what is missing, only when mail with files arrived) lists the last three mails with the card their documents are
 * linked to, each opening *Received mail*. The recently uploaded documents come with their own item.
 * *To begin* counts its steps with a bar; *Hide* hides it for the account, *Not needed* takes away *Add whoever
 * lives with you* or *Invite whoever lives with you* for the family, and an `admin` with the Budget or the Mail off
 * finds at its end the way to *Settings › What you use*.
 *
 * The greeting is the `FmTitle` of the page: on the phone it draws the magnifier and the bell of the notices beside it; on
 * the tablet and the computer they are in the top bar of the layout.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { getActiveLanguage, trans, transChoice } from 'laravel-vue-i18n';
import { ArrowRight, Mail } from '@lucide/vue';
import { computed } from 'vue';
import FmAgendaRow from '@/components/fm/FmAgendaRow.vue';
import FmButton from '@/components/fm/FmButton.vue';
import type { ChecklistStep } from '@/components/fm/FmChecklist.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmChecklist from '@/components/fm/FmChecklist.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmRow from '@/components/fm/FmRow.vue';
import FmSectionLink from '@/components/fm/FmSectionLink.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import FmTotal from '@/components/fm/FmTotal.vue';
import { capitalized } from '@/lib/budget';
import { usePageMeta } from '@/composables/usePageMeta';
import { useFeature } from '@/composables/useFeatures';
import { useWideLayout } from '@/composables/useWideLayout';
import { formatLongDate, relativeDays } from '@/lib/format';
import { agenda, areas, toSort, upload } from '@/routes';
import { create as newCard } from '@/routes/cards';
import { index as receivedMail } from '@/routes/mail';
import { create as invite } from '@/routes/family/invitations';
import { hide, skip } from '@/routes/dashboard/checklist';
import { index as people } from '@/routes/people';
import { edit as editFeatures } from '@/routes/settings/features';

const { home } = defineProps<{
    /** What the Home shows, as the server sends it. */
    home: App.Data.HomeData;
}>();

const day = computed(() => formatLongDate(home.today, getActiveLanguage()));

/** Whether the window has the wide layout: the button of the documents to complete beside the text. */
const wide = useWideLayout();

/**
 * The step of *To begin* for a step of the server: its text and where it leads.
 *
 * @param step - the step, with the names of the people for `people` and the name to invite for `invite`
 * @returns the step for `FmChecklist`
 */
function toStep(step: App.Data.ChecklistStepData): ChecklistStep {
    const { key, done } = step;

    switch (key) {
        case 'people':
            return {
                key,
                title: trans('Add whoever lives with you'),
                subtitle: done
                    ? trans('Done in the Welcome: :names', {
                          names: new Intl.ListFormat(getActiveLanguage(), {
                              type: 'conjunction',
                          }).format(step.names),
                      })
                    : trans('Partner, children and anyone else at home'),
                href: people().url,
                done,
                skippable: true,
            };
        case 'utilities':
            return {
                key,
                title: trans('The bills of the house'),
                subtitle: trans(
                    'Electricity, gas, water and internet: add them as cards of the house',
                ),
                href: areas().url,
                done,
            };
        case 'first_bill':
            return {
                key,
                title: trans('Upload the first document'),
                subtitle: trans('A photo or a PDF: we read it'),
                href: upload().url,
                done,
            };
        case 'card':
            return {
                key,
                title: trans('Create the first card'),
                subtitle: trans(
                    'The car, a policy, the house: each with its deadlines',
                ),
                href: newCard().url,
                done,
            };
        case 'mail':
            return {
                key,
                title: trans('Send the bills to the address of the family'),
                subtitle: trans(
                    'The ones that arrive there upload on their own',
                ),
                href: receivedMail().url,
                done,
            };
        default:
            return {
                key,
                title:
                    step.name === null
                        ? trans('Invite whoever lives with you')
                        : trans('Invite :name', { name: step.name }),
                subtitle: trans(
                    'So they see the documents and get the notices',
                ),
                href: invite().url,
                done,
                skippable: true,
            };
    }
}

/** Whether the family has the mail for the bills. */
const mail = useFeature('mail');

/**
 * Hides *To begin* for the account.
 */
function hideChecklist(): void {
    router.post(hide().url, {}, { preserveScroll: true });
}

/**
 * Takes a step of *To begin* away for the whole family (*Not needed*).
 *
 * @param step - the key of the step, `people` or `invite`
 */
function skipStep(step: string): void {
    router.post(skip().url, { step }, { preserveScroll: true });
}

/** Whether the family has the Budget: without it the Home shows no money. */
const budget = useFeature('budget');

/** The steps of *To begin*; empty when all are done. */
const steps = computed(() => home.checklist.map(toStep));

/** Whether the family is still beginning: the incomes tell how they will show. */
const beginning = computed(() => steps.value.length > 0);

/** *To begin* says how many steps there are, in words, as the board does. */
const checklistIntro = computed(() =>
    transChoice(
        '{1} One thing to get started. Tick it off when it is done.|{2} Two things to get started. Tick them off as you go.|{3} Three things to get started. Tick them off as you go.|{4} Four things to get started. Tick them off as you go.|{5} Five things to get started. Tick them off as you go.|[6,*] Six things to get started. Tick them off as you go.',
        steps.value.length,
    ),
);

/** Whether *To begin* ends with the way to *Settings › What you use*: only for whoever can turn on a feature that is off. */
const featuresLink = computed(
    () => home.can_change_features && (!budget.value || !mail.value),
);

/** Whether the right column has something to show; without it the left one takes the whole width. */
const hasRightColumn = computed(
    () =>
        (budget.value && home.incoming.length > 0) ||
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

const { title, head } = usePageMeta({
    info: 'home',
    title: () => trans('Hi, :name', { name: home.first_name }),
    head: () => trans('Home'),
});
</script>

<template>
    <Head :title="head" />

    <div class="flex flex-col gap-5">
        <FmTitle :title="title">
            <template #overline>
                <span class="text-body-sm text-ink-soft">{{ day }}</span>
            </template>
        </FmTitle>

        <section
            v-if="home.pending_count > 0"
            :aria-label="$t('Documents to complete')"
            class="flex gap-4 rounded-card bg-card shadow-card"
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

        <div class="grid gap-5 wide:grid-cols-5 wide:items-start wide:gap-6">
            <div
                class="flex min-w-0 flex-col gap-5"
                :class="hasRightColumn ? 'wide:col-span-3' : 'wide:col-span-5'"
            >
                <FmTotal
                    v-if="home.total !== null"
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

                <FmChecklist
                    v-if="beginning"
                    :steps="steps"
                    :intro="checklistIntro"
                    @hide="hideChecklist"
                    @skip="skipStep"
                >
                    <template v-if="featuresLink" #footer>
                        {{
                            $t(
                                'Do you also need the Budget or the Mail for the bills?',
                            )
                        }}
                        <Link
                            :href="editFeatures().url"
                            class="text-meta-strong text-action no-underline hover:text-action-dark"
                            >{{
                                $t('Turn them on from Settings › What you use.')
                            }}</Link
                        >
                    </template>
                </FmChecklist>
            </div>

            <div
                v-if="hasRightColumn"
                class="flex min-w-0 flex-col gap-5 wide:col-span-2"
                :class="{
                    'not-wide:hidden':
                        home.incoming.length === 0 &&
                        home.missing.length === 0 &&
                        home.mails.length === 0,
                }"
            >
                <FmCard
                    v-if="budget && home.incoming.length > 0"
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
                <FmCard
                    v-else-if="budget"
                    :title="$t('Incoming')"
                    class="not-wide:hidden"
                >
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
