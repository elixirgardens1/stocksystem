<template>
  <div id="nav">
    <header>
      <router-link to="/" tag="button">
        <input type="button" class="navBtn" value="View Stock" />
      </router-link>

      <router-link to="/pending" tag="button">
        <input type="button" class="navBtn" value="Pending Orders" />
      </router-link>

      <router-link to="/update">
        <input type="button" class="navBtn" value="Update Stock" />
      </router-link>

      <router-link to="/import" tag="button">
        <input type="button" class="navBtn" value="Import Skus" />
      </router-link>

      <router-link to="/platforms" tag="button">
        <input type="button" class="navBtn" value="Platform Skus" />
      </router-link>

      <router-link to="/history" tag="button">
        <input type="button" class="navBtn" value="Stock Order History" />
      </router-link>

      <router-link to="/predictions" tag="button">
        <input type="button" class="navBtn" value="Stock Predictions" />
      </router-link>

      <router-link to="/transparency" tag="button">
        <input type="button" class="navBtn" value="Transparency" />
      </router-link>

      <router-link to="/admin" tag="button">
        <input type="button" class="navBtn" value="Stock Admin" />
      </router-link>
    </header>
    <footer>
      <div style="float: right; margin-right: 50px;">
        Total Stock Value: £{{ totalStockValue }} | Total Products:
        {{ totalProducts }} | Total SKUS: {{ totalSkus }}
      </div>
    </footer>
  </div>
  <router-view />
</template>

<script>
import { axiosGet } from "@/composables/axiosGet.js";
import { onMounted, ref } from "vue";

export default {
  setup() {
    const totalStockValue = ref(0);
    const totalProducts = ref(0);
    const totalSkus = ref(0);

    onMounted(() => {
      axiosGet("footerData").then((response) => {
        totalStockValue.value = response.totalStockValue;
        totalProducts.value = response.totalProducts;
        totalSkus.value = response.totalSkus;
      });
    });

    return {
      totalStockValue,
      totalProducts,
      totalSkus,
    };
  },
};
</script>

<style scoped>
input {
  float: left;
}

#app {
  font-family: Avenir, Helvetica, Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  text-align: center;
  color: #2c3e50;
}

#nav {
  padding: 30px;
}

#nav a {
  font-weight: bold;
  color: #2c3e50;
}

#nav a.router-link-exact-active {
  color: #42b983;
}
</style>
