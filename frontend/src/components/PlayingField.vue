<script setup lang="ts">
import type { Color } from "@/types/Colors";

interface Props {
  fieldColor: Color;
  text?: string;
  textColor?: "white" | "black";
  rotationClass?: string;
}

withDefaults(defineProps<Props>(), {
  text: "",
  textColor: "black",
  rotationClass: "",
});
</script>

<template>
  <div :class="['playingfield flex justify-center items-center', fieldColor]">
    <span :class="['font-bold field-color', textColor, rotationClass]">{{ text }}</span>
  </div>
</template>

<style>
.playingfield {
  border-radius: 50%;
  width: 1.4rem;
  height: 1.4rem;
  border: 0.188rem solid #000;

  @apply sm:w-[2.3rem] sm:h-[2.3rem] md:w-[3.125rem] md:h-[3.125rem];

  &.green {
    @apply bg-green-700;
  }

  &.yellow {
    @apply bg-amber-400;
  }

  &.black {
    @apply bg-black;
  }

  &.red {
    @apply bg-red-600;
  }

  &.white {
    @apply bg-slate-50;
  }
}

.field-color {
  &.white {
    @apply text-white;
  }

  &.black {
    @apply text-black;
  }
}
</style>
