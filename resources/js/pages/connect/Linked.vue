<script setup lang="ts">
/**
 * *Phone linked*: who the phone is for, how to put the app on the Home screen and *Take a photo*, which opens
 * the scan. The address also serves the icon of the iPhone, which does not see the cookies of Safari: the
 * secret of the phone is kept after the `#`; opened from the icon, the page goes to the scan if the session is
 * still there, otherwise it sends the secret to enter again.
 */
import { Head, router } from '@inertiajs/vue3';
import { Camera, Check, Share, SquarePlus, Smartphone } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, onMounted } from 'vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import { isIos, isStandalone } from '@/lib/devices';
import {
    cleanAddress,
    keepSecretInAddress,
    secretFromHash,
    withKeptSecret,
} from '@/lib/phoneLink';
import { connect, scan } from '@/routes';
import { resume } from '@/routes/connect';

defineOptions({
    layout: {
        panel: {
            title: trans('Everything about the family, in one place.'),
            text: trans(
                'Link your phone and upload the documents without a password.',
            ),
        },
    },
});

const { name, family, secret } = defineProps<{
    /** The first name of the account of the phone; null when nobody is signed in (the icon, before it enters). */
    name: string | null;
    /** The name of the family; null when nobody is signed in. */
    family: string | null;
    /** The secret of the phone, only right after linking: the page keeps it after the `#`. */
    secret: string | null;
}>();

/** *Take a photo*: the scan, with the secret of the address when there is one (kept by an iPhone in Safari). */
const scanUrl = computed((): string => withKeptSecret(scan().url));

/** The three steps to put the app on the Home screen. */
const steps = [
    { icon: Share, text: trans('Tap Share, at the bottom') },
    { icon: SquarePlus, text: trans('Choose Add to Home Screen') },
    { icon: Check, text: trans('Tap Add, at the top right') },
];

onMounted(() => {
    const installed = isStandalone();

    // Right after linking an iPhone keeps the secret in the address, for the icon to start from it
    if (secret && isIos()) {
        keepSecretInAddress(secret);
    }

    if (installed && name) {
        // the scan keeps the secret: when the session ends there, it enters again with it
        router.visit(withKeptSecret(scan().url));

        return;
    }

    const kept = secretFromHash(location.hash);

    if (installed && kept) {
        // The session is over and the icon has no cookies: the secret of the address enters the phone again
        cleanAddress();
        router.post(
            resume().url,
            { secret: kept },
            // the server opens the scan, which keeps the secret for the next time the session ends
            { onSuccess: () => keepSecretInAddress(kept) },
        );

        return;
    }

    if (!name && !kept) {
        // Nothing to show: not linked
        router.visit(connect().url);

        return;
    }

    cleanAddress();
});
</script>

<template>
    <Head :title="$t('Phone linked')" />

    <div v-if="name" class="flex grow flex-col gap-6">
        <div
            class="flex grow flex-col items-center justify-center gap-[18px] text-center"
        >
            <span
                class="flex size-22 items-center justify-center rounded-full bg-paid-soft text-paid"
            >
                <FmIcon :icon="Smartphone" :size="40" />
            </span>
            <h1 class="m-0 text-title-mobile">
                {{ $t('Phone linked') }}
            </h1>
            <p class="m-0 max-w-[300px] text-body text-ink-soft">
                {{
                    $t(
                        'Hi, :name: from this phone you upload the documents to the :family family without a password.',
                        { name, family: family ?? '' },
                    )
                }}
            </p>
        </div>
        <div class="flex flex-col gap-3.5 rounded-card bg-canvas p-[18px]">
            <span class="text-amount">{{
                $t('Put it on the Home screen')
            }}</span>
            <ol class="m-0 flex list-none flex-col gap-3 p-0">
                <li
                    v-for="step in steps"
                    :key="step.text"
                    class="flex items-center gap-3 text-body-sm"
                >
                    <span
                        class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-card text-action-dark"
                    >
                        <FmIcon :icon="step.icon" :size="20" />
                    </span>
                    <span>{{ step.text }}</span>
                </li>
            </ol>
        </div>
        <FmButton wide :icon="Camera" :href="scanUrl">{{
            $t('Take a photo')
        }}</FmButton>
    </div>
</template>
