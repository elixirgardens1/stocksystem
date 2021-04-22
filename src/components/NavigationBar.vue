<template>
<header>
  <input type="button" class="navBtn" value="View Stock">
  <input type="button" class="navBtn" value="Update Stock">
  <input type="button" class="navBtn" value="Import Skus">
  <input type="button" class="navBtn" value="Order History">
  <input type="button" class="navBtn" value="Amazon/Ebay">
  <input type="button" class="navBtn" value="Stock Predictions">
</header>
</template>

<script>
export default {
  name: "NavigationBar"
}
</script>

<style scoped>
input {
  float: left;
}
</style>