<script setup lang="ts">
import TheBoard from "./components/TheBoard.vue";
</script>

<template>
  <main class="mt-4 p-2 bg-amber-200 mx-auto">
    <div class="outer p-4 h-full">
      <TheBoard />
    </div>
  </main>
</template>

<style>
main {
  border: 3px solid red;
  @apply rounded;
  max-width: 800px;
}

.outer {
  border: 2px solid #000;
  @apply rounded-sm;
}
</style>
