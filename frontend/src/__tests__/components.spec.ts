import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";
import PlayingField from "@/components/PlayingField.vue";
import BaseElement from "@/components/BaseElement.vue";

describe("PlayingField", () => {
  it("renders with the correct color class", () => {
    const wrapper = mount(PlayingField, {
      props: { fieldColor: "green" },
    });
    expect(wrapper.find(".playingfield").classes()).toContain("green");
  });

  it("renders text when provided and no piece", () => {
    const wrapper = mount(PlayingField, {
      props: { fieldColor: "red", text: "A", textColor: "white" },
    });
    expect(wrapper.find("span").text()).toBe("A");
  });

  it("renders piece token instead of text when piece is set", () => {
    const wrapper = mount(PlayingField, {
      props: { fieldColor: "white", piece: "green" },
    });
    expect(wrapper.find(".piece-token").exists()).toBe(true);
    expect(wrapper.find(".piece-token").classes()).toContain("piece-green");
    expect(wrapper.find("span").exists()).toBe(false);
  });

  it("does not render piece token when piece is null", () => {
    const wrapper = mount(PlayingField, {
      props: { fieldColor: "white" },
    });
    expect(wrapper.find(".piece-token").exists()).toBe(false);
  });

  it("emits fieldClick with position when clicked", async () => {
    const wrapper = mount(PlayingField, {
      props: { fieldColor: "white", position: "path:5" },
    });
    await wrapper.find(".playingfield").trigger("click");
    expect(wrapper.emitted("fieldClick")).toEqual([["path:5"]]);
  });

  it("does not emit fieldClick when no position", async () => {
    const wrapper = mount(PlayingField, {
      props: { fieldColor: "white" },
    });
    await wrapper.find(".playingfield").trigger("click");
    expect(wrapper.emitted("fieldClick")).toBeUndefined();
  });

  it("applies highlighted class when highlighted", () => {
    const wrapper = mount(PlayingField, {
      props: { fieldColor: "white", position: "path:5", highlighted: true },
    });
    expect(wrapper.find(".playingfield").classes()).toContain("animate-pulse");
  });

  it("applies selected class when selected", () => {
    const wrapper = mount(PlayingField, {
      props: { fieldColor: "white", position: "path:5", selected: true },
    });
    expect(wrapper.find(".playingfield").classes()).toContain("ring-2");
  });

  it("applies rotation class to text", () => {
    const wrapper = mount(PlayingField, {
      props: { fieldColor: "green", text: "a", rotationClass: "rotate-180" },
    });
    expect(wrapper.find("span").classes()).toContain("rotate-180");
  });

  it("renders all four color pieces correctly", () => {
    const colors = ["green", "yellow", "red", "black"] as const;
    for (const color of colors) {
      const wrapper = mount(PlayingField, {
        props: { fieldColor: "white", piece: color },
      });
      expect(wrapper.find(`.piece-${color}`).exists()).toBe(true);
    }
  });
});

describe("BaseElement", () => {
  it("renders 4 PlayingField components", () => {
    const wrapper = mount(BaseElement, {
      props: { color: "green" },
    });
    const fields = wrapper.findAllComponents(PlayingField);
    expect(fields).toHaveLength(4);
  });

  it("passes color to all fields", () => {
    const wrapper = mount(BaseElement, {
      props: { color: "red" },
    });
    const fields = wrapper.findAllComponents(PlayingField);
    for (const field of fields) {
      expect(field.props("fieldColor")).toBe("red");
    }
  });

  it("passes positions to fields", () => {
    const positions = ["base:green:0", "base:green:1", "base:green:2", "base:green:3"];
    const wrapper = mount(BaseElement, {
      props: { color: "green", positions },
    });
    const fields = wrapper.findAllComponents(PlayingField);
    expect(fields[0].props("position")).toBe("base:green:0");
    expect(fields[1].props("position")).toBe("base:green:1");
    expect(fields[2].props("position")).toBe("base:green:2");
    expect(fields[3].props("position")).toBe("base:green:3");
  });

  it("passes pieces to fields", () => {
    const pieces = ["green", null, "green", null] as const;
    const wrapper = mount(BaseElement, {
      props: { color: "green", pieces: [...pieces] },
    });
    const fields = wrapper.findAllComponents(PlayingField);
    expect(fields[0].props("piece")).toBe("green");
    expect(fields[1].props("piece")).toBeNull();
    expect(fields[2].props("piece")).toBe("green");
    expect(fields[3].props("piece")).toBeNull();
  });

  it("forwards fieldClick events", async () => {
    const positions = ["base:green:0", "base:green:1", "base:green:2", "base:green:3"];
    const wrapper = mount(BaseElement, {
      props: { color: "green", positions },
    });
    const fields = wrapper.findAllComponents(PlayingField);
    await fields[0].vm.$emit("fieldClick", "base:green:0");
    expect(wrapper.emitted("fieldClick")).toEqual([["base:green:0"]]);
  });

  it("renders the B label", () => {
    const wrapper = mount(BaseElement, {
      props: { color: "yellow" },
    });
    expect(wrapper.text()).toContain("B");
  });

  it("applies rotation class to B label", () => {
    const wrapper = mount(BaseElement, {
      props: { color: "yellow", rotationClass: "rotate-90" },
    });
    const label = wrapper.find(".rotate-90");
    expect(label.exists()).toBe(true);
  });
});
