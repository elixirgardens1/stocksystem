import { createRouter, createWebHistory } from "vue-router";
import ViewStock from "@/views/ViewStock.vue";

const routes = [
  {
    path: "/",
    name: "ViewStock",
    component: ViewStock,
  },
  {
    path: "/pending",
    name: "PendingOrders",
    component: () => import("@/views/PendingOrders.vue"),
  },
  {
    path: "/update",
    name: "UpdateStock",
    component: () => import("@/views/UpdateStock.vue"),
  },
  {
    path: "/import",
    name: "ImportSkus",
    component: () => import("@/views/ImportSkus.vue"),
  },
  {
    path: "/platforms",
    name: "PlatformSkus",
    component: () => import("@/views/PlatformSkus.vue"),
  },
  {
    path: "/history",
    name: "StockOrderHistory",
    component: () => import("@/views/StockOrderHistory.vue"),
  },
  {
    path: "/predictions",
    name: "StockPredictions",
    component: () => import("@/views/StockPredictions.vue"),
  },
  {
    path: "/:productKey",
    name: "ProductInfo",
    props: true,
    component: () => import("@/views/ProductInfo.vue"),
  },
  {
    path: "/transparency",
    name: "Transparency",
    component: () => import("@/views/AsinTransparency.vue"),
  },
  /**
   * Catch all routes that are not set in the router and return the user to view stock
   */
  {
    path: "/:pathMatch(.*)",
    name: "404",
    component: ViewStock,
  },
];

const router = createRouter({
  history: createWebHistory(process.env.BASE_URL),
  routes,
});
router.beforeEach((to, from, next) => {
  next();
});
export default router;
