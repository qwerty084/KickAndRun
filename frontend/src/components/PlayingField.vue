<script setup lang="ts">
import type { Color } from "@/types/Colors";
import type { PlayerColor } from "@/types/Game";

interface Props {
  fieldColor: Color;
  text?: string;
  textColor?: "white" | "black";
  rotationClass?: string;
  position?: string;
  piece?: PlayerColor | null;
  highlighted?: boolean;
  selected?: boolean;
}

withDefaults(defineProps<Props>(), {
  text: "",
  textColor: "black",
  rotationClass: "",
  position: "",
  piece: null,
  highlighted: false,
  selected: false,
});

const emit = defineEmits<{
  fieldClick: [position: string];
}>();
</script>

<template>
  <div
    :class="[
      'playingfield flex justify-center items-center relative',
      fieldColor,
      {
        'cursor-pointer hover:brightness-110': position && (highlighted || selected || piece),
        'ring-2 ring-yellow-400 ring-offset-1 animate-pulse': highlighted,
        'ring-2 ring-blue-500 ring-offset-1': selected,
      },
    ]"
    @click="position ? emit('fieldClick', position) : undefined"
  >
    <!-- Piece token -->
    <div
      v-if="piece"
      :class="[
        'piece-token absolute rounded-full border-2 border-neutral-900',
        `piece-${piece}`,
      ]"
    ></div>
    <span v-else :class="['font-bold field-color', textColor, rotationClass]">{{ text }}</span>
  </div>
</template>

<style>
@reference "tailwindcss";

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

.piece-token {
  width: 65%;
  height: 65%;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);

  &.piece-green {
    @apply bg-green-600;
  }

  &.piece-yellow {
    @apply bg-amber-300;
  }

  &.piece-red {
    @apply bg-red-500;
  }

  &.piece-black {
    @apply bg-neutral-700;
  }
}
</style>
