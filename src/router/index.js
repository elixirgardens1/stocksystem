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
  {
    path: "/admin",
    name: "Admin",
    component: () => import("@/views/Admin.vue"),
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

let scrollPosition = {};

const router = createRouter({
  history: createWebHistory(process.env.BASE_URL),
  routes,
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) {
      scrollPosition = savedPosition;
    } else {
      scrollPosition = { x: 0, y: 0 };
    }
  },
});

router.beforeEach((to, from, next) => {
  next();
});

// Wait and reload scroll position on previous page
router.afterEach(() => {
  setTimeout(() => {
    window.scrollTo(scrollPosition);
  }, 133);
});
export default router;
