<script setup lang="ts">
import { computed } from "vue";
import BaseElement from "./BaseElement.vue";
import PlayingField from "./PlayingField.vue";
import type { GameState, PlayerColor, ValidMove } from "@/types/Game";
import { buildPieceMap, moveTargetToFieldId } from "@/composables/boardLayout";

interface Props {
  gameState?: GameState | null;
  validMoves?: ValidMove[];
  selectedPieceIndex?: number | null;
  myColor?: PlayerColor | null;
}

const props = withDefaults(defineProps<Props>(), {
  gameState: null,
  validMoves: () => [],
  selectedPieceIndex: null,
  myColor: null,
});

const emit = defineEmits<{
  fieldClick: [position: string];
}>();

// Build piece placement map from game state
const pieceMap = computed(() => {
  if (!props.gameState) return new Map();
  return buildPieceMap(props.gameState);
});

// Build set of highlighted field IDs (valid move targets for selected piece)
const highlightedFields = computed(() => {
  const set = new Set<string>();
  if (props.selectedPieceIndex === null || !props.myColor) return set;

  for (const move of props.validMoves) {
    if (move.pieceIndex === props.selectedPieceIndex) {
      set.add(moveTargetToFieldId(move.to, props.myColor));
    }
  }
  return set;
});

function getPiece(fieldId: string): PlayerColor | null {
  return pieceMap.value.get(fieldId)?.color ?? null;
}

function isHighlighted(fieldId: string): boolean {
  return highlightedFields.value.has(fieldId);
}

function isSelected(fieldId: string): boolean {
  const piece = pieceMap.value.get(fieldId);
  if (!piece) return false;
  return piece.pieceIndex === props.selectedPieceIndex && piece.color === props.myColor;
}

function handleClick(position: string) {
  emit("fieldClick", position);
}

// Base pieces for each color
function getBasePieces(color: PlayerColor): (PlayerColor | null)[] {
  if (!props.gameState) return [null, null, null, null];
  const pieces = props.gameState.pieces[color];
  if (!pieces) return [null, null, null, null];

  const result: (PlayerColor | null)[] = [null, null, null, null];
  let slot = 0;
  for (const p of pieces) {
    if (p.position === "base" && slot < 4) {
      result[slot] = color;
      slot++;
    }
  }
  return result;
}
</script>

<template>
  <div class="grid grid-cols-3 grid-rows-3 justify-items-center h-full">
    <!-- Yellow Base (top-left) -->
    <BaseElement
      color="yellow"
      class="mr-auto"
      rotation-class="rotate-90"
      :positions="['base:yellow:0', 'base:yellow:1', 'base:yellow:2', 'base:yellow:3']"
      :pieces="getBasePieces('yellow')"
      @field-click="handleClick"
    />

    <!-- Top arm (Green arm) -->
    <div class="flex justify-between w-[85%] relative">
      <!-- Left column: departure (path:2-5) -->
      <div class="row-path">
        <PlayingField field-color="white" position="path:2" :piece="getPiece('path:2')" :highlighted="isHighlighted('path:2')" :selected="isSelected('path:2')" @field-click="handleClick" />
        <PlayingField field-color="white" position="path:3" :piece="getPiece('path:3')" :highlighted="isHighlighted('path:3')" :selected="isSelected('path:3')" @field-click="handleClick" />
        <PlayingField field-color="white" position="path:4" :piece="getPiece('path:4')" :highlighted="isHighlighted('path:4')" :selected="isSelected('path:4')" @field-click="handleClick" />
        <PlayingField field-color="white" position="path:5" :piece="getPiece('path:5')" :highlighted="isHighlighted('path:5')" :selected="isSelected('path:5')" @field-click="handleClick" />
      </div>
      <!-- Middle column: path:1 + green goals -->
      <div class="row-path">
        <PlayingField field-color="white" position="path:1" :piece="getPiece('path:1')" :highlighted="isHighlighted('path:1')" :selected="isSelected('path:1')" @field-click="handleClick" />
        <PlayingField field-color="green" position="goal:green:0" :piece="getPiece('goal:green:0')" :highlighted="isHighlighted('goal:green:0')" @field-click="handleClick" />
        <PlayingField field-color="green" position="goal:green:1" :piece="getPiece('goal:green:1')" :highlighted="isHighlighted('goal:green:1')" @field-click="handleClick" />
        <PlayingField field-color="green" position="goal:green:2" :piece="getPiece('goal:green:2')" :highlighted="isHighlighted('goal:green:2')" @field-click="handleClick" />
      </div>
      <!-- Right column: approach + entry (path:37-39, path:0) -->
      <div class="row-path">
        <PlayingField field-color="green" position="path:0" :piece="getPiece('path:0')" :highlighted="isHighlighted('path:0')" :selected="isSelected('path:0')" @field-click="handleClick" />
        <PlayingField field-color="white" position="path:39" :piece="getPiece('path:39')" :highlighted="isHighlighted('path:39')" :selected="isSelected('path:39')" @field-click="handleClick" />
        <PlayingField field-color="white" position="path:38" :piece="getPiece('path:38')" :highlighted="isHighlighted('path:38')" :selected="isSelected('path:38')" @field-click="handleClick" />
        <PlayingField field-color="white" position="path:37" :piece="getPiece('path:37')" :highlighted="isHighlighted('path:37')" :selected="isSelected('path:37')" @field-click="handleClick" />
      </div>
      <div class="absolute -right-[5.5rem] text-7xl rotate-90 top-3 opacity-0 pointer-events-none" aria-hidden="true">&xrarr;</div>
    </div>

    <!-- Green Base (top-right) -->
    <BaseElement
      color="green"
      class="ml-auto"
      rotation-class="rotate-180"
      :positions="['base:green:0', 'base:green:1', 'base:green:2', 'base:green:3']"
      :pieces="getBasePieces('green')"
      @field-click="handleClick"
    />

    <!-- Left arm (Yellow arm, rotated -90°) -->
    <div class="flex justify-between w-[85%] -rotate-90 relative">
      <div class="row-path">
        <PlayingField field-color="white" position="path:12" :piece="getPiece('path:12')" :highlighted="isHighlighted('path:12')" :selected="isSelected('path:12')" @field-click="handleClick" />
        <PlayingField field-color="white" position="path:13" :piece="getPiece('path:13')" :highlighted="isHighlighted('path:13')" :selected="isSelected('path:13')" @field-click="handleClick" />
        <PlayingField field-color="white" position="path:14" :piece="getPiece('path:14')" :highlighted="isHighlighted('path:14')" :selected="isSelected('path:14')" @field-click="handleClick" />
        <PlayingField field-color="white" position="path:15" :piece="getPiece('path:15')" :highlighted="isHighlighted('path:15')" :selected="isSelected('path:15')" @field-click="handleClick" />
      </div>
      <div class="row-path">
        <PlayingField field-color="white" position="path:11" :piece="getPiece('path:11')" :highlighted="isHighlighted('path:11')" :selected="isSelected('path:11')" @field-click="handleClick" />
        <PlayingField field-color="yellow" position="goal:yellow:0" :piece="getPiece('goal:yellow:0')" :highlighted="isHighlighted('goal:yellow:0')" @field-click="handleClick" />
        <PlayingField field-color="yellow" position="goal:yellow:1" :piece="getPiece('goal:yellow:1')" :highlighted="isHighlighted('goal:yellow:1')" @field-click="handleClick" />
        <PlayingField field-color="yellow" position="goal:yellow:2" :piece="getPiece('goal:yellow:2')" :highlighted="isHighlighted('goal:yellow:2')" @field-click="handleClick" />
      </div>
      <div class="row-path">
        <PlayingField field-color="yellow" position="path:10" :piece="getPiece('path:10')" :highlighted="isHighlighted('path:10')" :selected="isSelected('path:10')" @field-click="handleClick" />
        <PlayingField field-color="white" position="path:9" :piece="getPiece('path:9')" :highlighted="isHighlighted('path:9')" :selected="isSelected('path:9')" @field-click="handleClick" />
        <PlayingField field-color="white" position="path:8" :piece="getPiece('path:8')" :highlighted="isHighlighted('path:8')" :selected="isSelected('path:8')" @field-click="handleClick" />
        <PlayingField field-color="white" position="path:7" :piece="getPiece('path:7')" :highlighted="isHighlighted('path:7')" :selected="isSelected('path:7')" @field-click="handleClick" />
      </div>
      <div class="absolute -right-[5.5rem] text-7xl rotate-90 top-3 opacity-0 pointer-events-none" aria-hidden="true">&xrarr;</div>
    </div>

    <!-- Center cross -->
    <div class="flex flex-col w-[85%] justify-around">
      <div class="flex justify-between">
        <div><PlayingField field-color="white" position="path:6" :piece="getPiece('path:6')" :highlighted="isHighlighted('path:6')" :selected="isSelected('path:6')" @field-click="handleClick" /></div>
        <div><PlayingField field-color="green" position="goal:green:3" :piece="getPiece('goal:green:3')" :highlighted="isHighlighted('goal:green:3')" @field-click="handleClick" /></div>
        <div><PlayingField field-color="white" position="path:36" :piece="getPiece('path:36')" :highlighted="isHighlighted('path:36')" :selected="isSelected('path:36')" @field-click="handleClick" /></div>
      </div>
      <div class="flex justify-between">
        <div><PlayingField field-color="yellow" position="goal:yellow:3" :piece="getPiece('goal:yellow:3')" :highlighted="isHighlighted('goal:yellow:3')" @field-click="handleClick" /></div>
        <div><PlayingField field-color="red" position="goal:red:3" :piece="getPiece('goal:red:3')" :highlighted="isHighlighted('goal:red:3')" @field-click="handleClick" /></div>
      </div>
      <div class="flex justify-between">
        <div><PlayingField field-color="white" position="path:16" :piece="getPiece('path:16')" :highlighted="isHighlighted('path:16')" :selected="isSelected('path:16')" @field-click="handleClick" /></div>
        <div><PlayingField field-color="black" position="goal:black:3" :piece="getPiece('goal:black:3')" :highlighted="isHighlighted('goal:black:3')" @field-click="handleClick" /></div>
        <div><PlayingField field-color="white" position="path:26" :piece="getPiece('path:26')" :highlighted="isHighlighted('path:26')" :selected="isSelected('path:26')" @field-click="handleClick" /></div>
      </div>
    </div>

    <!-- Right arm (Red arm, rotated 90°) -->
    <div class="flex justify-between w-[85%] rotate-90">
      <div class="row-path">
        <PlayingField field-color="white" position="path:22" :piece="getPiece('path:22')" :highlighted="isHighlighted('path:22')" :selected="isSelected('path:22')" @field-click="handleClick" />
        <PlayingField field-color="white" position="path:23" :piece="getPiece('path:23')" :highlighted="isHighlighted('path:23')" :selected="isSelected('path:23')" @field-click="handleClick" />
        <PlayingField field-color="white" position="path:24" :piece="getPiece('path:24')" :highlighted="isHighlighted('path:24')" :selected="isSelected('path:24')" @field-click="handleClick" />
        <PlayingField field-color="white" position="path:25" :piece="getPiece('path:25')" :highlighted="isHighlighted('path:25')" :selected="isSelected('path:25')" @field-click="handleClick" />
      </div>
      <div class="row-path">
        <PlayingField field-color="white" position="path:21" :piece="getPiece('path:21')" :highlighted="isHighlighted('path:21')" :selected="isSelected('path:21')" @field-click="handleClick" />
        <PlayingField field-color="red" position="goal:red:0" :piece="getPiece('goal:red:0')" :highlighted="isHighlighted('goal:red:0')" @field-click="handleClick" />
        <PlayingField field-color="red" position="goal:red:1" :piece="getPiece('goal:red:1')" :highlighted="isHighlighted('goal:red:1')" @field-click="handleClick" />
        <PlayingField field-color="red" position="goal:red:2" :piece="getPiece('goal:red:2')" :highlighted="isHighlighted('goal:red:2')" @field-click="handleClick" />
      </div>
      <div class="row-path">
        <PlayingField field-color="red" position="path:20" :piece="getPiece('path:20')" :highlighted="isHighlighted('path:20')" :selected="isSelected('path:20')" @field-click="handleClick" />
        <PlayingField field-color="white" position="path:19" :piece="getPiece('path:19')" :highlighted="isHighlighted('path:19')" :selected="isSelected('path:19')" @field-click="handleClick" />
        <PlayingField field-color="white" position="path:18" :piece="getPiece('path:18')" :highlighted="isHighlighted('path:18')" :selected="isSelected('path:18')" @field-click="handleClick" />
        <PlayingField field-color="white" position="path:17" :piece="getPiece('path:17')" :highlighted="isHighlighted('path:17')" :selected="isSelected('path:17')" @field-click="handleClick" />
      </div>
      <div class="absolute -right-[5.5rem] text-7xl rotate-90 top-3 opacity-0 pointer-events-none" aria-hidden="true">&xrarr;</div>
    </div>

    <!-- Black Base (bottom-left) -->
    <BaseElement
      color="black"
      class="mr-auto mt-auto"
      :positions="['base:black:0', 'base:black:1', 'base:black:2', 'base:black:3']"
      :pieces="getBasePieces('black')"
      @field-click="handleClick"
    />

    <!-- Bottom arm (Black arm, rotated 180°) -->
    <div class="flex justify-between w-[85%] rotate-180">
      <div class="row-path">
        <PlayingField field-color="white" position="path:32" :piece="getPiece('path:32')" :highlighted="isHighlighted('path:32')" :selected="isSelected('path:32')" @field-click="handleClick" />
        <PlayingField field-color="white" position="path:33" :piece="getPiece('path:33')" :highlighted="isHighlighted('path:33')" :selected="isSelected('path:33')" @field-click="handleClick" />
        <PlayingField field-color="white" position="path:34" :piece="getPiece('path:34')" :highlighted="isHighlighted('path:34')" :selected="isSelected('path:34')" @field-click="handleClick" />
        <PlayingField field-color="white" position="path:35" :piece="getPiece('path:35')" :highlighted="isHighlighted('path:35')" :selected="isSelected('path:35')" @field-click="handleClick" />
      </div>
      <div class="row-path">
        <PlayingField field-color="white" position="path:31" :piece="getPiece('path:31')" :highlighted="isHighlighted('path:31')" :selected="isSelected('path:31')" @field-click="handleClick" />
        <PlayingField field-color="black" position="goal:black:0" :piece="getPiece('goal:black:0')" :highlighted="isHighlighted('goal:black:0')" @field-click="handleClick" />
        <PlayingField field-color="black" position="goal:black:1" :piece="getPiece('goal:black:1')" :highlighted="isHighlighted('goal:black:1')" @field-click="handleClick" />
        <PlayingField field-color="black" position="goal:black:2" :piece="getPiece('goal:black:2')" :highlighted="isHighlighted('goal:black:2')" @field-click="handleClick" />
      </div>
      <div class="row-path">
        <PlayingField field-color="black" position="path:30" :piece="getPiece('path:30')" :highlighted="isHighlighted('path:30')" :selected="isSelected('path:30')" @field-click="handleClick" />
        <PlayingField field-color="white" position="path:29" :piece="getPiece('path:29')" :highlighted="isHighlighted('path:29')" :selected="isSelected('path:29')" @field-click="handleClick" />
        <PlayingField field-color="white" position="path:28" :piece="getPiece('path:28')" :highlighted="isHighlighted('path:28')" :selected="isSelected('path:28')" @field-click="handleClick" />
        <PlayingField field-color="white" position="path:27" :piece="getPiece('path:27')" :highlighted="isHighlighted('path:27')" :selected="isSelected('path:27')" @field-click="handleClick" />
      </div>
      <div class="absolute -right-[5.5rem] text-7xl rotate-90 top-3 opacity-0 pointer-events-none" aria-hidden="true">&xrarr;</div>
    </div>

    <!-- Red Base (bottom-right) -->
    <BaseElement
      color="red"
      class="ml-auto mt-auto"
      rotation-class="-rotate-90"
      :positions="['base:red:0', 'base:red:1', 'base:red:2', 'base:red:3']"
      :pieces="getBasePieces('red')"
      @field-click="handleClick"
    />
  </div>
</template>

<style>
@reference "tailwindcss";

.row-path {
  @apply flex flex-col gap-4;
}
</style>
