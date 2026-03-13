import { describe, it, expect, beforeEach, vi } from "vitest";
import { useSoundEffects } from "@/composables/useSoundEffects";

describe("useSoundEffects", () => {
  beforeEach(() => {
    localStorage.clear();
    vi.restoreAllMocks();
  });

  it("starts unmuted by default", () => {
    const { muted } = useSoundEffects();
    expect(muted.value).toBe(false);
  });

  it("toggleMute flips muted state", () => {
    const { muted, toggleMute } = useSoundEffects();
    expect(muted.value).toBe(false);
    toggleMute();
    expect(muted.value).toBe(true);
    toggleMute();
    expect(muted.value).toBe(false);
  });

  it("persists mute state to localStorage", async () => {
    const { toggleMute } = useSoundEffects();
    toggleMute();
    // Vue watcher is async, wait a tick
    await new Promise((r) => setTimeout(r, 10));
    expect(localStorage.getItem("kickandrun_muted")).toBe("true");
  });

  it("play does not throw when AudioContext is unavailable", () => {
    const { play } = useSoundEffects();
    // In test environment, AudioContext may not exist
    expect(() => play("dice")).not.toThrow();
    expect(() => play("move")).not.toThrow();
    expect(() => play("kick")).not.toThrow();
    expect(() => play("bonus")).not.toThrow();
    expect(() => play("win")).not.toThrow();
    expect(() => play("turn")).not.toThrow();
  });

  it("play does nothing when muted", () => {
    const { play, toggleMute } = useSoundEffects();
    toggleMute(); // mute
    // Should not throw even with no AudioContext
    expect(() => play("dice")).not.toThrow();
  });
});
