import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";
import ConnectionStatus from "@/components/ConnectionStatus.vue";
import type { ConnectionStatus as Status } from "@/composables/useMercure";

describe("ConnectionStatus", () => {
  it("renders nothing for idle status", () => {
    const wrapper = mount(ConnectionStatus, { props: { status: "idle" as Status } });
    expect(wrapper.text()).toBe("");
  });

  it("shows 'Connected' with green dot", () => {
    const wrapper = mount(ConnectionStatus, { props: { status: "connected" as Status } });
    expect(wrapper.text()).toContain("Connected");
    const dot = wrapper.find("span.bg-green-500");
    expect(dot.exists()).toBe(true);
  });

  it("shows 'Connecting…' with amber dot", () => {
    const wrapper = mount(ConnectionStatus, { props: { status: "connecting" as Status } });
    expect(wrapper.text()).toContain("Connecting…");
    const dot = wrapper.find("span.bg-amber-500");
    expect(dot.exists()).toBe(true);
  });

  it("shows 'Reconnecting…' with amber dot", () => {
    const wrapper = mount(ConnectionStatus, { props: { status: "reconnecting" as Status } });
    expect(wrapper.text()).toContain("Reconnecting…");
    const dot = wrapper.find("span.bg-amber-500");
    expect(dot.exists()).toBe(true);
  });

  it("shows 'Disconnected' with red dot", () => {
    const wrapper = mount(ConnectionStatus, { props: { status: "disconnected" as Status } });
    expect(wrapper.text()).toContain("Disconnected");
    const dot = wrapper.find("span.bg-red-500");
    expect(dot.exists()).toBe(true);
  });
});
