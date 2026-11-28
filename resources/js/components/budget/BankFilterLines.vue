<script setup lang="ts">
/**
 * The two lines that say what a bank filter does, as the boards draw them: *Matches:* (the words as tags, the words
 * left out struck through, the direction, the account) and *Action:* (the name it speaks of in bold). The list of the
 * tab *Filters* and the box of a planned item show them the same way.
 *
 * @group forms
 */
import { actionPieces, criteriaParts } from '@/lib/bankFilters';

defineProps<{
    /** The filter to describe. */
    filter: App.Data.BankFilterData;
}>();
</script>

<template>
    <span class="flex flex-wrap items-center gap-1.5 text-body-sm text-ink">
        <span class="text-meta-strong text-ink-soft">{{ $t('Matches:') }}</span>
        <template v-for="(part, index) in criteriaParts(filter)" :key="index">
            <span v-if="index > 0">{{ ' · ' }}</span>
            <template v-if="part.kind === 'words'">
                {{ part.label }}
                <span
                    v-for="word in part.words"
                    :key="word"
                    :class="
                        part.excluded
                            ? 'inline-flex h-6 items-center rounded-tag bg-card-sunken px-2 text-meta-strong whitespace-nowrap text-ink-soft line-through decoration-ink-faint'
                            : 'inline-flex h-6 items-center rounded-tag bg-card-sunken px-2 text-meta-strong whitespace-nowrap text-ink'
                    "
                    >{{ word }}</span
                >
            </template>
            <template v-else-if="part.kind === 'pattern'">
                {{ part.label }}
                <code
                    class="rounded-tag bg-card-sunken px-2 py-[3px] font-mono text-meta-strong wrap-anywhere"
                    >{{ part.pattern }}</code
                >
            </template>
            <span v-else>{{ part.text }}</span>
        </template>
    </span>
    <span class="flex flex-wrap items-center gap-1.5 text-body-sm text-ink">
        <span class="text-meta-strong text-ink-soft">{{ $t('Action:') }}</span>
        <span
            >{{ actionPieces(filter).before
            }}<strong v-if="actionPieces(filter).name" class="text-label">{{
                actionPieces(filter).name
            }}</strong
            >{{ actionPieces(filter).after }}</span
        >
    </span>
</template>
