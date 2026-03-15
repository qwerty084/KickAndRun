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
  kicked?: boolean;
}

withDefaults(defineProps<Props>(), {
  text: "",
  textColor: "black",
  rotationClass: "",
  position: "",
  piece: null,
  highlighted: false,
  selected: false,
  kicked: false,
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
        'ring-[3px] ring-white ring-offset-[2px] ring-offset-blue-500 selected-glow': selected,
      },
    ]"
    @click="position ? emit('fieldClick', position) : undefined"
  >
    <!-- Piece token -->
    <Transition :name="kicked ? 'piece-kicked' : 'piece'" appear>
      <div
        v-if="piece"
        :key="piece"
        :class="[
          'piece-token absolute rounded-full border-2',
          `piece-${piece}`,
        ]"
      ></div>
    </Transition>
    <span v-if="!piece" :class="['font-bold field-color', textColor, rotationClass]">{{ text }}</span>
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
    @apply bg-green-400 border-green-900;
  }

  &.piece-yellow {
    @apply bg-amber-200 border-amber-700;
  }

  &.piece-red {
    @apply bg-red-300 border-red-900;
  }

  &.piece-black {
    @apply bg-neutral-950 border-neutral-400;
  }
}

.selected-glow {
  animation: selected-pulse 1s ease-in-out infinite;
}

@keyframes selected-pulse {
  0%, 100% {
    box-shadow: 0 0 0 2px #3b82f6, 0 0 8px 3px rgba(59, 130, 246, 0.6);
  }
  50% {
    box-shadow: 0 0 0 2px #3b82f6, 0 0 16px 6px rgba(59, 130, 246, 0.9);
  }
}

/* Piece enter/leave transitions */
.piece-enter-active {
  animation: piece-pop-in 0.25s ease-out;
}

.piece-leave-active {
  animation: piece-pop-out 0.2s ease-in forwards;
}

.piece-kicked-leave-active {
  animation: piece-shake-out 0.35s ease-in forwards;
}

@keyframes piece-pop-in {
  0% {
    transform: scale(0);
    opacity: 0;
  }
  60% {
    transform: scale(1.15);
  }
  100% {
    transform: scale(1);
    opacity: 1;
  }
}

@keyframes piece-pop-out {
  0% {
    transform: scale(1);
    opacity: 1;
  }
  100% {
    transform: scale(0);
    opacity: 0;
  }
}

@keyframes piece-shake-out {
  0% {
    transform: translateX(0) scale(1);
    opacity: 1;
  }
  15% {
    transform: translateX(-3px) scale(1);
  }
  30% {
    transform: translateX(3px) scale(1);
  }
  45% {
    transform: translateX(-2px) scale(0.9);
  }
  60% {
    transform: translateX(2px) scale(0.8);
    opacity: 0.7;
  }
  100% {
    transform: translateX(0) scale(0);
    opacity: 0;
  }
}
</style>
