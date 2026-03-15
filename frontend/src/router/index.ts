import { createRouter, createWebHistory } from "vue-router";
import HomePage from "@/views/HomePage.vue";

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: "/",
      name: "home",
      component: HomePage,
      meta: { title: "Mensch ärgere dich nicht" },
    },
    {
      path: "/lobby/:id",
      name: "lobby",
      component: () => import("@/views/LobbyRoom.vue"),
      meta: { title: "Lobby – Mensch ärgere dich nicht" },
    },
    {
      path: "/game/:id",
      name: "game",
      component: () => import("@/views/GamePage.vue"),
      meta: { title: "Game – Mensch ärgere dich nicht" },
    },
  ],
});

router.afterEach((to) => {
  const title = (to.meta.title as string | undefined) ?? "Mensch ärgere dich nicht";
  document.title = title;
});

export default router;
