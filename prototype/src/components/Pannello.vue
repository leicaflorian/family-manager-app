<script setup lang="ts">
// Il pannello che sale dal basso, sopra la pagina; si chiude toccando fuori
const aperto = defineModel<boolean>({ required: true })
defineProps<{ titolo: string }>()
const id = `pannello-${Math.random().toString(36).slice(2, 8)}`
</script>

<template>
  <Transition name="pannello">
    <div v-if="aperto" class="fixed inset-0 z-30 mx-auto flex max-w-md flex-col justify-end bg-testo/45" @click.self="aperto = false">
      <div role="dialog" aria-modal="true" :aria-labelledby="id" class="flex flex-col gap-4 rounded-t-3xl bg-white px-5 pt-3 pb-7">
        <span class="h-1.25 w-10 self-center rounded-full bg-[#D5DBE3]" />
        <h2 :id="id" class="m-0 text-[22px] font-extrabold">{{ titolo }}</h2>
        <slot />
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.pannello-enter-active,
.pannello-leave-active {
  transition: opacity 0.2s ease;
}
.pannello-enter-from,
.pannello-leave-to {
  opacity: 0;
}
</style>
