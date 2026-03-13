<script setup lang="ts">
import type { Color } from "@/types/Colors";
import PlayingField from "./PlayingField.vue";

interface Props {
  color: Color;
  rotationClass?: string;
}

withDefaults(defineProps<Props>(), {
  rotationClass: "",
});
</script>

<template>
  <div class="grid h-fit">
    <div class="flex flex-col gap-4">
      <PlayingField :fieldColor="$props.color" />
      <PlayingField :fieldColor="$props.color" />
    </div>
    <div :class="['flex h-full justify-center items-center mx-auto font-bold text-2xl', rotationClass]">B</div>
    <div class="flex flex-col gap-4">
      <PlayingField :fieldColor="$props.color" />
      <PlayingField :fieldColor="$props.color" />
    </div>
  </div>
</template>

<style scoped>
.grid {
  grid-template-columns: 1fr 30px 1fr;
}
</style>
