<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import BarraSotto from './components/BarraSotto.vue'
import Messaggio from './components/Messaggio.vue'

const route = useRoute()
const conBarra = computed(() => !route.meta.senzaBarra)
</script>

<template>
  <div class="mx-auto min-h-dvh max-w-md bg-sfondo" :class="conBarra ? 'pb-28' : ''">
    <RouterView />
    <BarraSotto v-if="conBarra" />
    <Messaggio />
  </div>
</template>
