<script setup lang="ts">
/**
 * *Settings → Support*: the family lets support look at the app as one of its
 * accounts for 24 hours. Everyone sees whether the permission is on; only an
 * administrator gives it or takes it back.
 */
import { Head, router } from '@inertiajs/vue3';
import { Clock, LifeBuoy, Lock } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import { formatDateTime } from '@/lib/format';
import { settings } from '@/routes';
import { destroy, store } from '@/routes/settings/support';
import { usePageTrail } from '@/composables/usePageTrail';

const props = defineProps<{
    /** Until when support can come in (ISO with offset), or `null` without the permission. */
    support_until: string | null;
    /** Whether who looks can give and take back the permission: an administrator. */
    can_change: boolean;
    /** The label of the level that can give the permission (administrator). */
    admin_level: string;
}>();

/** Marks a place in a translated sentence, so the value that goes there can be bold. */
const MARK = '\u0000';

/**
 * Splits a translated sentence around one placeholder, so the value can be written in bold.
 *
 * @param key - the English text with the placeholder
 * @param name - the placeholder, without the colon
 * @returns the text before and after the value
 */
function around(key: string, name: string): [string, string] {
    const [before, after = ''] = trans(key, { [name]: MARK }).split(MARK);

    return [before, after];
}

/** The note under the button, around the level that can give the permission. */
const note = computed(() =>
    around(
        'The permission ends by itself after 24 hours, and you can take it back sooner. Only someone with :level can give it.',
        'level',
    ),
);

/** The line of the permission, around the date. */
const until = computed(() => around('Support can come in until :date', 'date'));

/**
 * Lets support in for 24 hours.
 */
function allow(): void {
    router.post(store.url(), {}, { preserveScroll: true });
}

/**
 * Takes the permission back.
 */
function takeBack(): void {
    router.delete(destroy.url(), { preserveScroll: true });
}

usePageTrail(() => [
    { label: trans('Settings'), href: settings().url },
    { label: trans('Support') },
]);
</script>

<template>
    <Head :title="$t('Support')" />

    <div class="flex max-w-[720px] flex-col gap-5">
        <FmTitle
            :title="$t('Support')"
            :back="settings().url"
            :back-label="$t('Settings')"
        />
        <FmCard>
            <div class="flex flex-col gap-4">
                <div class="flex items-start gap-3.5">
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-action-soft text-action-dark"
                    >
                        <FmIcon :icon="LifeBuoy" :size="22" />
                    </span>
                    <p class="text-body">
                        {{
                            $t(
                                'If you ask us for help, we can look at Family Manager as one of your accounts. We only look, we change nothing, and only while you allow it.',
                            )
                        }}
                    </p>
                </div>
                <template v-if="props.support_until === null">
                    <FmNotice :icon="Lock">{{
                        $t('Support cannot come in right now.')
                    }}</FmNotice>
                    <template v-if="props.can_change">
                        <FmButton wide @click="allow">{{
                            $t('Let support in for 24 hours')
                        }}</FmButton>
                        <p class="text-meta text-ink-soft">
                            {{ note[0]
                            }}<strong class="text-meta-strong">{{
                                admin_level
                            }}</strong
                            >{{ note[1] }}
                        </p>
                    </template>
                </template>
            </div>
        </FmCard>
        <FmCard
            v-if="props.support_until !== null"
            :title="$t('With the permission')"
        >
            <div class="flex flex-col gap-3">
                <FmNotice tone="ok" :icon="Clock">
                    {{ until[0]
                    }}<strong class="text-label">{{
                        formatDateTime(props.support_until)
                    }}</strong
                    >{{ until[1] }}
                </FmNotice>
                <div v-if="props.can_change">
                    <FmButton variant="danger" @click="takeBack">{{
                        $t('Take the permission back')
                    }}</FmButton>
                </div>
            </div>
        </FmCard>
    </div>
</template>
