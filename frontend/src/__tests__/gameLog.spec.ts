import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";
import GameLog from "@/components/GameLog.vue";
import type { GameEvent } from "@/stores/game";

function makeEvent(overrides: Partial<GameEvent> = {}): GameEvent {
  return {
    type: "dice_rolled",
    playerColor: "green",
    playerName: "Alice",
    isBot: false,
    diceRoll: 4,
    timestamp: Date.now(),
    ...overrides,
  };
}

describe("GameLog", () => {
  it("shows empty state when no events", () => {
    const wrapper = mount(GameLog, { props: { events: [] } });
    expect(wrapper.text()).toContain("No actions yet");
  });

  it("renders dice_rolled event", () => {
    const events = [makeEvent({ playerName: "Alice", diceRoll: 6 })];
    const wrapper = mount(GameLog, { props: { events } });
    expect(wrapper.text()).toContain("Alice rolled a 6");
  });

  it("renders piece_moved event", () => {
    const events = [
      makeEvent({
        type: "piece_moved",
        playerName: "Bob",
        moved: { pieceIndex: 0, from: "base", to: "path:10" },
      }),
    ];
    const wrapper = mount(GameLog, { props: { events } });
    expect(wrapper.text()).toContain("Bob moved piece");
  });

  it("shows kick indicator", () => {
    const events = [
      makeEvent({
        type: "piece_moved",
        playerName: "Alice",
        kicked: true,
      }),
    ];
    const wrapper = mount(GameLog, { props: { events } });
    expect(wrapper.text()).toContain("kicked");
    expect(wrapper.text()).toContain("💥");
  });

  it("shows extra turn indicator", () => {
    const events = [
      makeEvent({
        type: "piece_moved",
        playerName: "Alice",
        extraTurn: true,
      }),
    ];
    const wrapper = mount(GameLog, { props: { events } });
    expect(wrapper.text()).toContain("extra turn");
  });

  it("shows winner", () => {
    const events = [
      makeEvent({
        type: "piece_moved",
        playerName: "Alice",
        winner: "green",
      }),
    ];
    const wrapper = mount(GameLog, { props: { events } });
    expect(wrapper.text()).toContain("🏆");
    expect(wrapper.text()).toContain("wins");
  });

  it("shows bot indicator for bot events", () => {
    const events = [
      makeEvent({
        playerName: "Bot 1",
        isBot: true,
        diceRoll: 3,
      }),
    ];
    const wrapper = mount(GameLog, { props: { events } });
    expect(wrapper.text()).toContain("🤖");
    expect(wrapper.text()).toContain("Bot 1");
  });

  it("shows bot thinking indicator", () => {
    const wrapper = mount(GameLog, { props: { events: [], botThinking: true } });
    expect(wrapper.text()).toContain("Bot is thinking");
  });

  it("does not show bot thinking when false", () => {
    const wrapper = mount(GameLog, { props: { events: [], botThinking: false } });
    expect(wrapper.text()).not.toContain("Bot is thinking");
  });

  it("renders multiple events in order", () => {
    const events = [
      makeEvent({ playerName: "Alice", diceRoll: 6 }),
      makeEvent({ type: "piece_moved", playerName: "Alice", moved: { pieceIndex: 0, from: "base", to: "path:0" } }),
      makeEvent({ playerName: "Bob", playerColor: "yellow", diceRoll: 3 }),
    ];
    const wrapper = mount(GameLog, { props: { events } });
    const text = wrapper.text();
    expect(text.indexOf("Alice rolled a 6")).toBeLessThan(text.indexOf("Alice moved piece"));
    expect(text.indexOf("Alice moved piece")).toBeLessThan(text.indexOf("Bob rolled a 3"));
  });

  it("renders events with distinct styling per player color", () => {
    const events = [
      makeEvent({ playerColor: "green", playerName: "Alice", diceRoll: 3 }),
      makeEvent({ playerColor: "yellow", playerName: "Bob", diceRoll: 5 }),
    ];
    const wrapper = mount(GameLog, { props: { events } });
    const text = wrapper.text();
    expect(text).toContain("Alice rolled a 3");
    expect(text).toContain("Bob rolled a 5");
  });

  it("renders bot events with bot prefix in text", () => {
    const events = [makeEvent({ isBot: true, playerName: "Bot 1" })];
    const wrapper = mount(GameLog, { props: { events } });
    const text = wrapper.text();
    expect(text).toContain("🤖");
    expect(text).toContain("Bot 1 rolled a 4");
  });
});
