<script setup lang="ts">
/**
 * The wait while a file is read: a line that runs, «Reading the document…», a
 * note that it takes a few seconds and, on the computer, the name of the file
 * with its pages and the lines found so far (who it is from, the amount…).
 * Used on the page of the reading and on the rows of the files being read.
 *
 * @group basics
 */
import { computed } from 'vue';
import { trans, transChoice } from 'laravel-vue-i18n';

const { name, pages } = defineProps<{
    /** The name of the file. */
    name: string;
    /** The pages of the file; missing when they are not known (a PDF not counted yet). */
    pages?: number | null;
    /** What the reading found so far, one line each. */
    found?: App.Data.Reading.FoundLineData[];
}>();

/** The name with the pages, «bolletta.pdf · 1 page», or the name alone. */
const caption = computed(() =>
    pages
        ? trans(':name · :pages', {
              name,
              pages: transChoice('{1} :count page|[2,*] :count pages', pages, {
                  count: String(pages),
              }),
          })
        : name,
);
</script>

<template>
    <div role="status" class="flex flex-col gap-3">
        <div
            class="relative h-1.5 overflow-hidden rounded-full bg-action/15"
            aria-hidden="true"
        >
            <span
                class="reading-run absolute inset-y-0 w-1/3 rounded-full bg-action"
            />
        </div>
        <div class="flex flex-col gap-0.5">
            <p class="text-base font-semibold">
                {{ $t('Reading the document…') }}
            </p>
            <p class="text-sm text-ink-soft">
                {{ $t('It takes a few seconds') }}
            </p>
        </div>
        <p class="hidden truncate text-sm text-ink-soft lg:block">
            {{ caption }}
        </p>
        <dl
            v-if="found && found.length > 0"
            v-auto-animate
            class="hidden flex-col gap-1 text-sm lg:flex"
        >
            <div
                v-for="line in found"
                :key="line.label"
                class="flex justify-between gap-3"
            >
                <dt class="text-ink-soft">{{ line.label }}</dt>
                <dd class="font-medium">{{ line.value }}</dd>
            </div>
        </dl>
    </div>
</template>

<style scoped>
@media (prefers-reduced-motion: no-preference) {
    .reading-run {
        animation: reading-run 1.4s ease-in-out infinite;
    }
}

@keyframes reading-run {
    from {
        left: -33%;
    }
    to {
        left: 100%;
    }
}
</style>
