<script setup lang="ts">
import BaseElement from "./BaseElement.vue";
import PlayingField from "./PlayingField.vue";
</script>

<template>
  <div class="grid grid-cols-3 grid-rows-3 justify-items-center h-full">
    <BaseElement color="yellow" class="mr-auto" rotation-class="rotate-90" />
    <div class="flex justify-between w-[85%] relative">
      <div class="row-path">
        <PlayingField field-color="white" />
        <PlayingField field-color="white" />
        <PlayingField field-color="white" />
        <PlayingField field-color="white" />
      </div>
      <div class="row-path">
        <PlayingField field-color="white" />
        <PlayingField field-color="green" text="a" rotation-class="rotate-180" />
        <PlayingField field-color="green" text="b" rotation-class="rotate-180" />
        <PlayingField field-color="green" text="c" rotation-class="rotate-180" />
      </div>
      <div class="row-path">
        <PlayingField field-color="green" text="A" rotation-class="rotate-180" />
        <PlayingField field-color="white" />
        <PlayingField field-color="white" />
        <PlayingField field-color="white" />
      </div>
      <div class="absolute -right-[5.5rem] text-7xl rotate-90 top-3">&xrarr;</div>
    </div>
    <BaseElement color="green" class="ml-auto" rotation-class="rotate-180" />
    <div class="flex justify-between w-[85%] -rotate-90 relative">
      <div class="row-path">
        <PlayingField field-color="white" />
        <PlayingField field-color="white" />
        <PlayingField field-color="white" />
        <PlayingField field-color="white" />
      </div>
      <div class="row-path">
        <PlayingField field-color="white" />
        <PlayingField field-color="yellow" text="a" rotation-class="rotate-180" />
        <PlayingField field-color="yellow" text="b" rotation-class="rotate-180" />
        <PlayingField field-color="yellow" text="c" rotation-class="rotate-180" />
      </div>
      <div class="row-path">
        <PlayingField field-color="yellow" text="A" rotation-class="rotate-180" />
        <PlayingField field-color="white" />
        <PlayingField field-color="white" />
        <PlayingField field-color="white" />
      </div>
      <div class="absolute -right-[5.5rem] text-7xl rotate-90 top-3">&xrarr;</div>
    </div>
    <div class="flex flex-col w-[85%] justify-around">
      <div class="flex justify-between">
        <div><PlayingField field-color="white" /></div>
        <div><PlayingField field-color="green" text="d" rotation-class="rotate-180" /></div>
        <div><PlayingField field-color="white" /></div>
      </div>
      <div class="flex justify-between">
        <div><PlayingField field-color="yellow" text="d" rotation-class="rotate-90" /></div>
        <div><PlayingField field-color="red" text="d" rotation-class="-rotate-90" /></div>
      </div>
      <div class="flex justify-between">
        <div><PlayingField field-color="white" /></div>
        <div><PlayingField field-color="black" text="d" text-color="white" /></div>
        <div><PlayingField field-color="white" /></div>
      </div>
    </div>
    <div class="flex justify-between w-[85%] rotate-90">
      <div class="row-path">
        <PlayingField field-color="white" />
        <PlayingField field-color="white" />
        <PlayingField field-color="white" />
        <PlayingField field-color="white" />
      </div>
      <div class="row-path">
        <PlayingField field-color="white" />
        <PlayingField field-color="red" text="a" rotation-class="rotate-180" />
        <PlayingField field-color="red" text="b" rotation-class="rotate-180" />
        <PlayingField field-color="red" text="c" rotation-class="rotate-180" />
      </div>
      <div class="row-path">
        <PlayingField field-color="red" text="A" rotation-class="rotate-180" />
        <PlayingField field-color="white" />
        <PlayingField field-color="white" />
        <PlayingField field-color="white" />
      </div>
      <div class="absolute -right-[5.5rem] text-7xl rotate-90 top-3">&xrarr;</div>
    </div>
    <BaseElement color="black" class="mr-auto mt-auto" />
    <div class="flex justify-between w-[85%] rotate-180">
      <div class="row-path">
        <PlayingField field-color="white" />
        <PlayingField field-color="white" />
        <PlayingField field-color="white" />
        <PlayingField field-color="white" />
      </div>
      <div class="row-path">
        <PlayingField field-color="white" />
        <PlayingField field-color="black" text="a" text-color="white" rotation-class="rotate-180" />
        <PlayingField field-color="black" text="b" text-color="white" rotation-class="rotate-180" />
        <PlayingField field-color="black" text="c" text-color="white" rotation-class="rotate-180" />
      </div>
      <div class="row-path">
        <PlayingField field-color="black" text="A" text-color="white" rotation-class="rotate-180" />
        <PlayingField field-color="white" />
        <PlayingField field-color="white" />
        <PlayingField field-color="white" />
      </div>
      <div class="absolute -right-[5.5rem] text-7xl rotate-90 top-3">&xrarr;</div>
    </div>
    <BaseElement color="red" class="ml-auto mt-auto" rotation-class="-rotate-90" />
  </div>
</template>

<style>
.row-path {
  @apply flex flex-col gap-4;
}
</style>
