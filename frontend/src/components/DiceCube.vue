<script setup lang="ts">
import { computed, ref, watch } from "vue";

interface Props {
  value: number | null;
  rolling?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  rolling: false,
});

// Pip layouts for each face (1-6) using a 3x3 grid
// Each pip position: TL=0, TC=1, TR=2, ML=3, MC=4, MR=5, BL=6, BC=7, BR=8
const pipPositions: Record<number, number[]> = {
  1: [4],
  2: [2, 6],
  3: [2, 4, 6],
  4: [0, 2, 6, 8],
  5: [0, 2, 4, 6, 8],
  6: [0, 2, 3, 5, 6, 8],
};

// Rotation to show each face (value 1-6)
const faceRotations: Record<number, string> = {
  1: "rotateX(0deg) rotateY(0deg)",
  2: "rotateX(0deg) rotateY(-90deg)",
  3: "rotateX(-90deg) rotateY(0deg)",
  4: "rotateX(90deg) rotateY(0deg)",
  5: "rotateX(0deg) rotateY(90deg)",
  6: "rotateX(0deg) rotateY(180deg)",
};

// Extra full spins to make the tumble dramatic
const rollCount = ref(0);
const showResult = ref(false);
const previousValue = ref<number | null>(null);

watch(
  () => props.rolling,
  (isRolling) => {
    if (isRolling) {
      showResult.value = false;
      rollCount.value++;
    } else {
      // Small delay so the CSS transition plays the final snap
      showResult.value = true;
      previousValue.value = props.value;
    }
  },
);

watch(
  () => props.value,
  (newVal) => {
    if (newVal !== null && !props.rolling) {
      showResult.value = true;
      previousValue.value = newVal;
    }
  },
);

const cubeTransform = computed(() => {
  if (props.rolling) {
    return ""; // handled by CSS animation class
  }
  const val = props.value ?? previousValue.value;
  if (val && val >= 1 && val <= 6) {
    return faceRotations[val];
  }
  return "rotateX(-20deg) rotateY(30deg)"; // idle angle showing corner
});

function hasPip(face: number, position: number): boolean {
  return pipPositions[face]?.includes(position) ?? false;
}
</script>

<template>
  <div class="dice-scene" aria-label="Dice result">
    <div
      class="dice-cube"
      :class="{ 'dice-tumble': rolling }"
      :style="!rolling ? { transform: cubeTransform } : undefined"
    >
      <!-- Face 1 - Front -->
      <div class="dice-face dice-face--front">
        <div v-for="i in 9" :key="i" class="pip-cell">
          <span v-if="hasPip(1, i - 1)" class="pip"></span>
        </div>
      </div>
      <!-- Face 2 - Right -->
      <div class="dice-face dice-face--right">
        <div v-for="i in 9" :key="i" class="pip-cell">
          <span v-if="hasPip(2, i - 1)" class="pip"></span>
        </div>
      </div>
      <!-- Face 3 - Top -->
      <div class="dice-face dice-face--top">
        <div v-for="i in 9" :key="i" class="pip-cell">
          <span v-if="hasPip(3, i - 1)" class="pip"></span>
        </div>
      </div>
      <!-- Face 4 - Bottom -->
      <div class="dice-face dice-face--bottom">
        <div v-for="i in 9" :key="i" class="pip-cell">
          <span v-if="hasPip(4, i - 1)" class="pip"></span>
        </div>
      </div>
      <!-- Face 5 - Left -->
      <div class="dice-face dice-face--left">
        <div v-for="i in 9" :key="i" class="pip-cell">
          <span v-if="hasPip(5, i - 1)" class="pip"></span>
        </div>
      </div>
      <!-- Face 6 - Back -->
      <div class="dice-face dice-face--back">
        <div v-for="i in 9" :key="i" class="pip-cell">
          <span v-if="hasPip(6, i - 1)" class="pip"></span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@reference "tailwindcss";

.dice-scene {
  width: 70px;
  height: 70px;
  perspective: 300px;
  margin: 0 auto;
}

.dice-cube {
  width: 100%;
  height: 100%;
  position: relative;
  transform-style: preserve-3d;
  transition: transform 0.5s cubic-bezier(0.22, 1, 0.36, 1);
  transform: rotateX(-20deg) rotateY(30deg);
}

.dice-cube.dice-tumble {
  animation: tumble 0.8s ease-in-out;
}

.dice-face {
  position: absolute;
  width: 70px;
  height: 70px;
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  grid-template-rows: repeat(3, 1fr);
  padding: 8px;
  border-radius: 8px;
  backface-visibility: hidden;

  @apply bg-white border-2 border-neutral-200 shadow-sm;
}

:root .dark .dice-face {
  @apply bg-neutral-700 border-neutral-500;
}

.pip-cell {
  display: flex;
  align-items: center;
  justify-content: center;
}

.pip {
  width: 11px;
  height: 11px;
  border-radius: 50%;
  @apply bg-neutral-800;
}

:root .dark .pip {
  @apply bg-neutral-100;
}

/* Face positions - each translated half the cube size (35px) */
.dice-face--front {
  transform: translateZ(35px);
}
.dice-face--back {
  transform: rotateY(180deg) translateZ(35px);
}
.dice-face--right {
  transform: rotateY(90deg) translateZ(35px);
}
.dice-face--left {
  transform: rotateY(-90deg) translateZ(35px);
}
.dice-face--top {
  transform: rotateX(90deg) translateZ(35px);
}
.dice-face--bottom {
  transform: rotateX(-90deg) translateZ(35px);
}

@keyframes tumble {
  0% {
    transform: rotateX(0deg) rotateY(0deg) rotateZ(0deg);
  }
  15% {
    transform: rotateX(200deg) rotateY(-120deg) rotateZ(60deg);
  }
  30% {
    transform: rotateX(400deg) rotateY(100deg) rotateZ(-40deg);
  }
  50% {
    transform: rotateX(600deg) rotateY(-200deg) rotateZ(100deg);
  }
  70% {
    transform: rotateX(520deg) rotateY(260deg) rotateZ(-30deg);
  }
  85% {
    transform: rotateX(680deg) rotateY(-300deg) rotateZ(50deg);
  }
  100% {
    transform: rotateX(720deg) rotateY(360deg) rotateZ(0deg);
  }
}
</style>
