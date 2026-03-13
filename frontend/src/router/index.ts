import { createRouter, createWebHistory } from "vue-router";
import HomePage from "@/views/HomePage.vue";

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: "/",
      name: "home",
      component: HomePage,
    },
    {
      path: "/lobby/:id",
      name: "lobby",
      component: () => import("@/views/LobbyRoom.vue"),
    },
    {
      path: "/game/:id",
      name: "game",
      component: () => import("@/views/GamePage.vue"),
    },
  ],
});

export default router;
