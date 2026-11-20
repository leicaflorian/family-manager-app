<script setup lang="ts">
/**
 * The choices of the scanner of *Scan the sheets*: the scanner, the resolution (with how many sides fit), the colour,
 * where the sheets come from and *On a single page*. Before the first scan they are the whole column on the right
 * (board `Scanner-vuoto-pc`); then they go in the tab *Scanner* (board `Scanner-pc`), where `next` says they count
 * for the sheets added from now on.
 */
import { computed } from 'vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import type { UseScanner } from '@/composables/useScanner';
import type { Colour, Source } from '@/lib/scan/agent';
import { colourName, sidesText, sourceName } from '@/lib/scan/choices';

const { scanner, next = false } = defineProps<{
    /** The scanner of the page, with its choices. */
    scanner: UseScanner;
    /** There are sides already: the choices count for the next sheets. */
    next?: boolean;
}>();

/** How many sides fit at the resolution chosen. */
const fitText = computed((): string | undefined =>
    scanner.fit.value ? sidesText(scanner.fit.value) : undefined,
);
const colourOptions = computed(() =>
    scanner.colours.value.map((value: Colour) => ({
        label: colourName(value),
        value,
    })),
);
const sourceOptions = computed(() =>
    scanner.sources.value.map((value: Source) => ({
        label: sourceName(value),
        value,
    })),
);
</script>

<template>
    <div class="flex flex-col gap-[18px]">
        <FmNotice v-if="scanner.missingScanner.value" tone="warning">
            {{
                $t(':name cannot be seen: it is off or not on the network.', {
                    name: scanner.missingScanner.value,
                })
            }}
        </FmNotice>

        <FormKit
            :classes="{ outer: 'mb-0!' }"
            type="dropdown"
            name="scanner"
            :label="$t('Scanner')"
            :model-value="scanner.scannerId.value"
            :options="scanner.scannerOptions.value"
            :placeholder="
                scanner.scanners.value.length
                    ? $t('Choose the scanner')
                    : $t('No scanner')
            "
            :disabled="
                scanner.scanning.value || scanner.scanners.value.length === 0
            "
            @update:model-value="
                (value: unknown) =>
                    scanner.chooseScanner(
                        typeof value === 'string' ? value : null,
                    )
            "
        />
        <template v-if="scanner.ready.value">
            <FormKit
                :classes="{ outer: 'mb-0!' }"
                v-if="scanner.resolutionOptions.value.length > 0"
                type="dropdown"
                name="resolution"
                :label="$t('Resolution')"
                :model-value="scanner.resolution.value"
                :options="scanner.resolutionOptions.value"
                :help="fitText"
                :disabled="scanner.scanning.value"
                @update:model-value="
                    (value: unknown) =>
                        (scanner.resolution.value =
                            typeof value === 'number' ? value : null)
                "
            />
            <FormKit
                :classes="{ outer: 'mb-0!' }"
                type="togglebuttons"
                enforced
                name="colour"
                :label="$t('Colour')"
                :model-value="scanner.colour.value"
                :options="colourOptions"
                :disabled="scanner.scanning.value"
                @update:model-value="
                    (value: unknown) => (scanner.colour.value = value as Colour)
                "
            />
            <FormKit
                :classes="{ outer: 'mb-0!' }"
                v-if="scanner.sources.value.length > 1"
                type="togglebuttons"
                enforced
                name="source"
                :label="$t('Where from')"
                :model-value="scanner.source.value"
                :options="sourceOptions"
                :disabled="scanner.scanning.value"
                @update:model-value="
                    (value: unknown) => scanner.chooseSource(value as Source)
                "
            />
            <FormKit
                :classes="{ outer: 'mb-0!' }"
                type="toggle"
                name="one_sheet"
                :label="$t('On a single page')"
                :help="
                    $t(
                        'Front and back of a card on one page, in colour and at high resolution',
                    )
                "
                :model-value="scanner.oneSheet.value"
                :disabled="scanner.scanning.value"
                @update:model-value="
                    (value: unknown) => scanner.setOneSheet(value === true)
                "
            />
        </template>
        <p v-if="next" class="m-0 text-meta text-ink-soft">
            {{ $t('They apply to the next sheets you add.') }}
        </p>
    </div>
</template>
