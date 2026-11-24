<script setup lang="ts">
/**
 * *Settings → What you use*: two cards, one for the Budget and one for the mail for the bills, each with its
 * switch and the lists of what changes when it is turned on and off. Each switch saves at once. A member sees the
 * page with the switches locked and a note that says who can change them.
 */
import { Head, router } from '@inertiajs/vue3';
import { Check, Inbox, Lock, Minus, Wallet } from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { ref, watch } from 'vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import FmSettingsPage from '@/components/fm/FmSettingsPage.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { usePageMeta } from '@/composables/usePageMeta';
import { update } from '@/routes/settings/features';

const props = defineProps<{
    /** The switches of the family and whether who looks can change them. */
    settings: App.Data.FeatureSettingsData;
}>();

/** The switches as the person sees them: they follow a click before the server answers. */
const budget = ref(props.settings.budget);
const mail = ref(props.settings.mail);

/**
 * Puts the switches back to what the server has: after a save that failed (the props did not change) and after
 * every save.
 */
function resync(): void {
    budget.value = props.settings.budget;
    mail.value = props.settings.mail;
}

watch(() => props.settings, resync);

/** One card: the feature, its switch and what changes. */
type Card = {
    key: 'budget' | 'mail';
    title: string;
    description: string;
    icon: LucideIcon;
    tile: string;
    onTitle: string;
    on: string[];
    offTitle: string;
    off: string[];
};

/** The two cards, in order. */
const cards: Card[] = [
    {
        key: 'budget',
        title: trans('Budget'),
        description: trans(
            'Expenses, income and deadlines to pay, month by month.',
        ),
        icon: Wallet,
        tile: 'bg-action-soft text-action-dark',
        onTitle: trans('When you turn it on'),
        on: [
            trans(
                'It shows in the menu and on the Home, with the deadlines to pay.',
            ),
            trans(
                'Import offers to record expenses and income from your documents.',
            ),
            trans('You can link the bank, which marks the payments by itself.'),
        ],
        offTitle: trans('When you turn it off'),
        off: [
            trans(
                'It disappears from the menu and the Home, together with Linked banks and Expense categories.',
            ),
            trans(
                'Expenses and income already recorded stay saved and come back if you turn it on again.',
            ),
        ],
    },
    {
        key: 'mail',
        title: trans('Mail for the bills'),
        description: trans(
            'An email address for the family: the bills you forward to it are read by themselves.',
        ),
        icon: Inbox,
        tile: 'bg-home-soft text-home-text',
        onTitle: trans('When you turn the mail on'),
        on: [
            trans(
                'The family gets its email address and the Mail shows in the menu.',
            ),
            trans('Forwarded bills are read by themselves and go to To sort.'),
        ],
        offTitle: trans('When you turn the mail off'),
        off: [
            trans(
                'The Mail disappears from the menu and the emails that reach the address are no longer saved.',
            ),
            trans(
                'The documents that already arrived stay. If you turn it on again, the address is the same.',
            ),
        ],
    },
];

/**
 * Saves the switches as they are now, with the answer of the server as the truth afterwards.
 */
function save(): void {
    router.put(
        update.url(),
        { budget: budget.value, mail: mail.value },
        { preserveScroll: true, onError: resync, onFinish: resync },
    );
}

/**
 * Sets one switch and saves.
 *
 * @param key - the feature
 * @param value - the new state
 */
function change(key: 'budget' | 'mail', value: unknown): void {
    const on = value === true;

    if (
        !props.settings.can_change ||
        (key === 'budget' ? budget : mail).value === on
    ) {
        return;
    }

    (key === 'budget' ? budget : mail).value = on;
    save();
}

const { title, head } = usePageMeta({
    info: 'settings-features',
    title: () => trans('What you use'),
    parent: 'settings',
});
</script>

<template>
    <Head :title="head" />

    <FmSettingsPage current="features">
        <template #title>
            <FmTitle
                :title="title"
                :subtitle="
                    $t(
                        'Documents, cards and the agenda are always there. You turn these on only if you need them.',
                    )
                "
            />
        </template>
        <FmNotice v-if="!props.settings.can_change" :icon="Lock">{{
            $t('Only whoever manages the family can change them.')
        }}</FmNotice>
        <FmCard v-for="card in cards" :key="card.key">
            <div class="flex flex-col gap-4">
                <div class="flex items-center gap-3.5">
                    <span
                        :class="[
                            'flex size-11 shrink-0 items-center justify-center rounded-lg',
                            card.tile,
                        ]"
                    >
                        <FmIcon :icon="card.icon" :size="22" />
                    </span>
                    <span class="flex min-w-0 grow flex-col gap-0.5">
                        <span class="text-row">{{ card.title }}</span>
                        <span class="text-body-sm text-ink-soft">{{
                            card.description
                        }}</span>
                    </span>
                    <FormKit
                        type="toggle"
                        :name="card.key"
                        :aria-label="card.title"
                        :disabled="!props.settings.can_change"
                        :model-value="card.key === 'budget' ? budget : mail"
                        @update:model-value="
                            (value: unknown) => change(card.key, value)
                        "
                    />
                </div>
                <div class="flex flex-col gap-4 border-t border-divider pt-4">
                    <div
                        v-for="list in [
                            {
                                title: card.onTitle,
                                items: card.on,
                                icon: Check,
                                tone: 'text-paid',
                            },
                            {
                                title: card.offTitle,
                                items: card.off,
                                icon: Minus,
                                tone: 'text-ink-soft',
                            },
                        ]"
                        :key="list.title"
                        class="flex min-w-0 flex-col gap-2.5"
                    >
                        <h3 class="m-0 text-label">{{ list.title }}</h3>
                        <ul class="m-0 flex list-none flex-col gap-2 p-0">
                            <li
                                v-for="item in list.items"
                                :key="item"
                                class="flex items-start gap-2.5 text-body-sm"
                            >
                                <span
                                    :class="['mt-px flex shrink-0', list.tone]"
                                >
                                    <FmIcon :icon="list.icon" :size="18" />
                                </span>
                                <span>{{ item }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </FmCard>
    </FmSettingsPage>
</template>
