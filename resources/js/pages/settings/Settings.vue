<script setup lang="ts">
/**
 * The index of the *Settings*: my account, mine only, and the settings that apply
 * to the whole family, and *The app* with the version and what is new in it. In the
 * wide layout the sections stand beside every page of the settings, so the index
 * opens *My account* in its place. The other items of the board (bills, subscription) come with their stages.
 * *Linked banks* is there only while the link to the bank is on for the family.
 */
import { Head, router } from '@inertiajs/vue3';
import {
    Bell,
    CalendarDays,
    FolderTree,
    House,
    Inbox,
    Landmark,
    LayoutGrid,
    LifeBuoy,
    Sparkles,
    Tags,
} from '@lucide/vue';
import FmAvatar from '@/components/fm/FmAvatar.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmRow from '@/components/fm/FmRow.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { watch } from 'vue';
import { useBankFeed } from '@/composables/useBankFeed';
import { useFormat } from '@/composables/useFormat';
import { useWideLayout } from '@/composables/useWideLayout';
import { edit } from '@/routes/account';
import { edit as calendar } from '@/routes/calendar';
import { index as banks } from '@/routes/banks';
import { index } from '@/routes/categories';
import { index as expenseCategories } from '@/routes/expense-categories';
import { edit as mailSettings } from '@/routes/mail-settings';
import { news } from '@/routes/settings';
import { edit as areasSettings } from '@/routes/settings/areas';
import { edit as familyName } from '@/routes/settings/family';
import { edit as notices } from '@/routes/settings/notices';
import { edit as support } from '@/routes/settings/support';

const {
    account,
    mainCategories,
    mainExpenseCategories,
    areaCount,
    hiddenAreas,
    bankCount,
    release,
    releasedOn,
} = defineProps<{
    /** My account. */
    account: App.Data.AccountData;
    /** How many main document categories there are. */
    mainCategories: number;
    /** How many main expense categories there are. */
    mainExpenseCategories: number;
    /** How many areas there are. */
    areaCount: number;
    /** How many areas the family hid. */
    hiddenAreas: number;
    /** How many banks the family linked. */
    bankCount: number;
    /** The version that runs (`dev` in development). */
    release: string;
    /** The day the version came out (`yyyy-mm-dd`), or `null` without notes for it. */
    releasedOn: string | null;
}>();

const { date } = useFormat();

const wide = useWideLayout();

/** The link to the bank: its row is there only while the family has it on. */
const bankFeed = useBankFeed();

// In the wide layout the index would only repeat the sections beside the pages:
// it gives its place in the history to My account, so Back does not come here.
watch(
    wide,
    (isWide) => {
        if (isWide) {
            router.visit(edit().url, { replace: true });
        }
    },
    { immediate: true },
);
</script>

<template>
    <Head :title="$t('Settings')" />

    <div v-if="!wide" class="flex max-w-[720px] flex-col gap-5">
        <FmTitle
            :title="$t('Settings')"
            :subtitle="
                $t(
                    'They apply to the whole :family family; my account is yours only',
                    { family: account.family },
                )
            "
        />
        <FmCard list>
            <FmRow
                :title="$t('My account')"
                :subtitle="`${account.first_name} ${account.last_name} · ${account.role}`"
                :href="edit().url"
            >
                <template #before>
                    <FmAvatar
                        :initials="account.initials"
                        :photo="account.photo"
                        :name="`${account.first_name} ${account.last_name}`"
                    />
                </template>
            </FmRow>
            <FmRow
                :title="$t('Calendar')"
                :subtitle="$t('Reminders and deadlines in Google Calendar')"
                :href="calendar().url"
            >
                <template #before>
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-action-soft text-action-dark"
                    >
                        <FmIcon :icon="CalendarDays" :size="22" />
                    </span>
                </template>
            </FmRow>
        </FmCard>
        <FmCard :title="$t('The family')" list>
            <FmRow
                :title="$t('Family name')"
                :subtitle="account.family"
                :href="familyName().url"
            >
                <template #before>
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-action-soft text-action-dark"
                    >
                        <FmIcon :icon="House" :size="22" />
                    </span>
                </template>
            </FmRow>
            <FmRow
                :title="$t('Notices')"
                :subtitle="$t('How many days before, where and to whom')"
                :href="notices().url"
            >
                <template #before>
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-action-soft text-action-dark"
                    >
                        <FmIcon :icon="Bell" :size="22" />
                    </span>
                </template>
            </FmRow>
            <FmRow
                :title="$t('Address for the bills')"
                :subtitle="$t('The bills arrive by email by themselves')"
                :href="mailSettings().url"
            >
                <template #before>
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-action-soft text-action-dark"
                    >
                        <FmIcon :icon="Inbox" :size="22" />
                    </span>
                </template>
            </FmRow>
            <FmRow
                v-if="bankFeed !== null"
                :title="$t('Linked banks')"
                :subtitle="
                    bankCount > 0
                        ? $tChoice(
                              '{1} :count bank linked|[2,*] :count banks linked',
                              bankCount,
                          )
                        : $t('Receive the transactions by themselves')
                "
                :href="banks().url"
            >
                <template #before>
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-action-soft text-action-dark"
                    >
                        <FmIcon :icon="Landmark" :size="22" />
                    </span>
                </template>
            </FmRow>
            <FmRow
                :title="$t('Document categories')"
                :subtitle="
                    $tChoice(
                        '{0} No main categories|{1} :count main category|[2,*] :count main categories',
                        mainCategories,
                    )
                "
                :href="index().url"
            >
                <template #before>
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-action-soft text-action-dark"
                    >
                        <FmIcon :icon="FolderTree" :size="22" />
                    </span>
                </template>
            </FmRow>
            <FmRow
                :title="$t('Expense categories')"
                :subtitle="
                    $tChoice(
                        '{0} No main categories|{1} :count main category|[2,*] :count main categories',
                        mainExpenseCategories,
                    )
                "
                :href="expenseCategories().url"
            >
                <template #before>
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-action-soft text-action-dark"
                    >
                        <FmIcon :icon="Tags" :size="22" />
                    </span>
                </template>
            </FmRow>
            <FmRow
                :title="$t('Areas')"
                :subtitle="
                    hiddenAreas === 0
                        ? $t(':count areas', { count: String(areaCount) })
                        : $tChoice(
                              '{1} :total areas, 1 hidden|[2,*] :total areas, :count hidden',
                              hiddenAreas,
                              { total: String(areaCount) },
                          )
                "
                :href="areasSettings().url"
            >
                <template #before>
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-action-soft text-action-dark"
                    >
                        <FmIcon :icon="LayoutGrid" :size="22" />
                    </span>
                </template>
            </FmRow>
            <FmRow
                :title="$t('Support')"
                :subtitle="$t('Let us look at the app when you ask for help')"
                :href="support().url"
            >
                <template #before>
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-action-soft text-action-dark"
                    >
                        <FmIcon :icon="LifeBuoy" :size="22" />
                    </span>
                </template>
            </FmRow>
        </FmCard>
        <FmCard :title="$t('The app')" list>
            <FmRow
                :title="$t('What\'s new')"
                :subtitle="
                    releasedOn === null
                        ? release === 'dev'
                            ? $t('Development version')
                            : $t('Version :version', { version: release })
                        : `${$t('Version :version', { version: release })} · ${date(releasedOn)}`
                "
                :href="news().url"
            >
                <template #before>
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-action-soft text-action-dark"
                    >
                        <FmIcon :icon="Sparkles" :size="22" />
                    </span>
                </template>
            </FmRow>
        </FmCard>
    </div>
</template>
