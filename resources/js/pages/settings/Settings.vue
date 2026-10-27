<script setup lang="ts">
/**
 * The index of the *Settings*: my account, mine only, and the settings that apply
 * to the whole family. The other items of the board (alerts, bills, calendar,
 * subscription) come with their stages.
 */
import { Head } from '@inertiajs/vue3';
import { FolderTree } from '@lucide/vue';
import FmAvatar from '@/components/fm/FmAvatar.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmRow from '@/components/fm/FmRow.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { edit } from '@/routes/account';
import { index } from '@/routes/categories';

const { account, mainCategories } = defineProps<{
    /** My account. */
    account: App.Data.AccountData;
    /** How many main document categories there are. */
    mainCategories: number;
}>();
</script>

<template>
    <Head :title="$t('Settings')" />

    <div class="flex max-w-[720px] flex-col gap-5">
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
        </FmCard>
        <FmCard :title="$t('The family')" list>
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
                        class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-action-soft text-action-dark"
                    >
                        <FmIcon :icon="FolderTree" :size="22" />
                    </span>
                </template>
            </FmRow>
        </FmCard>
    </div>
</template>
