<script setup lang="ts">
import { messaggio } from '../messaggio'
</script>

<template>
  <div class="pointer-events-none fixed inset-x-0 bottom-28 z-20 mx-auto flex max-w-md justify-center px-5" v-auto-animate>
    <p v-if="messaggio.testo" :key="messaggio.testo" role="status" class="m-0 rounded-2xl bg-testo px-4 py-3 text-[15px] font-medium text-white shadow-lg">
      {{ messaggio.testo }}
    </p>
  </div>
</template>
