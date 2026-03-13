<script setup lang="ts">
import type { Color } from "@/types/Colors";
import type { PlayerColor } from "@/types/Game";
import PlayingField from "./PlayingField.vue";

interface Props {
  color: Color;
  rotationClass?: string;
  positions?: string[];
  pieces?: (PlayerColor | null)[];
}

withDefaults(defineProps<Props>(), {
  rotationClass: "",
  positions: () => ["", "", "", ""],
  pieces: () => [null, null, null, null],
});

const emit = defineEmits<{
  fieldClick: [position: string];
}>();
</script>

<template>
  <div class="grid h-fit">
    <div class="flex flex-col gap-4">
      <PlayingField
        :fieldColor="$props.color"
        :position="positions[0]"
        :piece="pieces[0]"
        @field-click="emit('fieldClick', $event)"
      />
      <PlayingField
        :fieldColor="$props.color"
        :position="positions[1]"
        :piece="pieces[1]"
        @field-click="emit('fieldClick', $event)"
      />
    </div>
    <div :class="['flex h-full justify-center items-center mx-auto font-bold text-2xl', rotationClass]">B</div>
    <div class="flex flex-col gap-4">
      <PlayingField
        :fieldColor="$props.color"
        :position="positions[2]"
        :piece="pieces[2]"
        @field-click="emit('fieldClick', $event)"
      />
      <PlayingField
        :fieldColor="$props.color"
        :position="positions[3]"
        :piece="pieces[3]"
        @field-click="emit('fieldClick', $event)"
      />
    </div>
  </div>
</template>

<style scoped>
.grid {
  grid-template-columns: 1fr 30px 1fr;
}
</style>
