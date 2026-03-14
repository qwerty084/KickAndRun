import { describe, it, expect, vi, beforeEach } from "vitest";
import { setActivePinia, createPinia } from "pinia";

describe("useAuthStore", () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    setActivePinia(createPinia());
    // Clear localStorage
    localStorage.removeItem("kickandrun_token");
  });

  it("register stores token and user on success", async () => {
    const mockUser = { id: "u1", username: "alice" };
    vi.stubGlobal(
      "fetch",
      vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve({ user: mockUser, token: "jwt-token-123" }),
      }),
    );

    const { useAuthStore } = await import("@/stores/auth");
    const store = useAuthStore();

    const success = await store.register("alice", "secret123");

    expect(success).toBe(true);
    expect(store.user).toEqual(mockUser);
    expect(store.isAuthenticated).toBe(true);
    expect(localStorage.getItem("kickandrun_token")).toBe("jwt-token-123");
  });

  it("register sets error on conflict", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn().mockResolvedValue({
        ok: false,
        status: 409,
        json: () => Promise.resolve({ error: "Username is already taken." }),
      }),
    );

    const { useAuthStore } = await import("@/stores/auth");
    const store = useAuthStore();

    const success = await store.register("alice", "secret123");

    expect(success).toBe(false);
    expect(store.error).toBe("Username is already taken.");
    expect(store.isAuthenticated).toBe(false);
  });

  it("login stores token and user on success", async () => {
    const mockUser = { id: "u1", username: "bob" };
    vi.stubGlobal(
      "fetch",
      vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve({ user: mockUser, token: "jwt-login-token" }),
      }),
    );

    const { useAuthStore } = await import("@/stores/auth");
    const store = useAuthStore();

    const success = await store.login("bob", "password");

    expect(success).toBe(true);
    expect(store.user).toEqual(mockUser);
    expect(localStorage.getItem("kickandrun_token")).toBe("jwt-login-token");
  });

  it("login sets error on invalid credentials", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn().mockResolvedValue({
        ok: false,
        status: 401,
        json: () => Promise.resolve({ error: "Invalid credentials." }),
      }),
    );

    const { useAuthStore } = await import("@/stores/auth");
    const store = useAuthStore();

    const success = await store.login("bob", "wrong");

    expect(success).toBe(false);
    expect(store.error).toBe("Invalid credentials.");
  });

  it("logout clears user and token", async () => {
    localStorage.setItem("kickandrun_token", "some-token");

    const { useAuthStore } = await import("@/stores/auth");
    const store = useAuthStore();
    store.user = { id: "u1", username: "alice" };

    store.logout();

    expect(store.user).toBeNull();
    expect(store.isAuthenticated).toBe(false);
    expect(localStorage.getItem("kickandrun_token")).toBeNull();
  });

  it("loadUser restores user from valid token", async () => {
    localStorage.setItem("kickandrun_token", "valid-token");

    const mockUser = { id: "u1", username: "alice" };
    vi.stubGlobal(
      "fetch",
      vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve(mockUser),
      }),
    );

    const { useAuthStore } = await import("@/stores/auth");
    const store = useAuthStore();

    await store.loadUser();

    expect(store.user).toEqual(mockUser);
    expect(store.isAuthenticated).toBe(true);
  });

  it("loadUser clears token when expired/invalid", async () => {
    localStorage.setItem("kickandrun_token", "expired-token");

    vi.stubGlobal(
      "fetch",
      vi.fn().mockResolvedValue({ ok: false, status: 401 }),
    );

    const { useAuthStore } = await import("@/stores/auth");
    const store = useAuthStore();

    await store.loadUser();

    expect(store.user).toBeNull();
    expect(localStorage.getItem("kickandrun_token")).toBeNull();
  });

  it("loadUser does nothing without token", async () => {
    const fetchSpy = vi.fn();
    vi.stubGlobal("fetch", fetchSpy);

    const { useAuthStore } = await import("@/stores/auth");
    const store = useAuthStore();

    await store.loadUser();

    expect(fetchSpy).not.toHaveBeenCalled();
    expect(store.user).toBeNull();
  });
});
