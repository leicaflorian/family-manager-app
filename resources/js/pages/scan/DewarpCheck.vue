<script setup lang="ts">
/**
 * *Straighten check*: a page only for the development machine, to time
 * *Raddrizza* on a real phone. The person picks a photo of a sheet, the page
 * takes the whole photo as the sheet, downloads the model once (and says how
 * long it took, with the engine) and lays the sheet flat (and says how long
 * that page took), then shows the result. Nothing is saved or sent.
 */
import { Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { onBeforeUnmount, ref } from 'vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { readPhoto, toObjectUrl } from '@/lib/scan/canvas';
import { WHOLE, warp, LONG_SIDE } from '@/lib/scan/crop';
import { dewarp, isDewarpAvailable, loadDewarp } from '@/lib/scan/dewarp';
import { usePageTrail } from '@/composables/usePageTrail';
import { toSort } from '@/routes';

const available = isDewarpAvailable();
const working = ref(false);
const timing = ref<string | null>(null);
const error = ref<string | null>(null);
const flatUrl = ref<string | null>(null);

/**
 * Seconds with one decimal.
 *
 * @param from - a `performance.now()` value
 * @returns the seconds since then
 */
function secondsSince(from: number): string {
    return ((performance.now() - from) / 1000).toFixed(1);
}

/** Forgets the picture shown. */
function clearPicture(): void {
    if (flatUrl.value) {
        URL.revokeObjectURL(flatUrl.value);
    }

    flatUrl.value = null;
}

/**
 * Lays the chosen photo flat and times the two steps: the model (download and
 * engine, the first time only) and the page.
 *
 * @param event - the change of the file field
 */
async function start(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (!file) {
        return;
    }

    working.value = true;
    error.value = null;
    timing.value = null;
    clearPicture();

    try {
        const sheet = warp(await readPhoto(file), WHOLE, LONG_SIDE);

        const modelStart = performance.now();
        await loadDewarp();
        const model = secondsSince(modelStart);

        const pageStart = performance.now();
        const flat = await dewarp(sheet);
        const page = secondsSince(pageStart);

        timing.value = trans('Model: :model s · Page: :page s', {
            model,
            page,
        });
        flatUrl.value = await toObjectUrl(flat);
    } catch (caught) {
        error.value = caught instanceof Error ? caught.message : String(caught);
    } finally {
        working.value = false;
        input.value = '';
    }
}

onBeforeUnmount(clearPicture);

usePageTrail(() => [
    { label: trans('To sort'), href: toSort().url },
    { label: trans('Straighten check') },
]);
</script>

<template>
    <Head :title="$t('Straighten check')" />

    <div class="flex max-w-[640px] flex-col gap-4">
        <FmTitle
            :title="$t('Straighten check')"
            :subtitle="
                $t(
                    'Pick a photo of a folded sheet or a receipt: the whole photo is taken as the sheet.',
                )
            "
        />

        <FmNotice v-if="!available" tone="warning">
            {{ $t('Straighten is not available on this phone') }}
        </FmNotice>

        <label class="flex flex-col gap-2 text-body-sm">
            {{ $t('Photo of a sheet') }}
            <input
                type="file"
                accept="image/*"
                :disabled="!available || working"
                class="text-body-sm"
                @change="start"
            />
        </label>

        <p v-if="working" role="status">
            {{ $t('Straightening…') }}
        </p>
        <p v-if="timing" class="text-subtitle" role="status">
            {{ timing }}
        </p>
        <FmNotice v-if="error" tone="danger">{{ error }}</FmNotice>

        <img
            v-if="flatUrl"
            :src="flatUrl"
            :alt="$t('The flat sheet')"
            class="w-full rounded-card border border-border"
        />
    </div>
</template>
