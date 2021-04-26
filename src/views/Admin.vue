<template>
  <div id="headerDiv">
    <h1>Admin</h1>
  </div>

  <div id="rightContent">
    <div id="nsProductsDiv">
      <h2>No Shelf Product Deliveries</h2>
      <input
        type="button"
        class="navBtn"
        value="Export Response Csv"
        @click="getResponseCsv"
      />
      <input type="button" class="navBtn" value="Submit Response" />
    </div>
  </div>
</template>

<script>
import { axiosGet } from "@/composables/axiosGet.js";

export default {
  name: "Admin",
  components: {},
  setup() {
    const getResponseCsv = () => {
      axiosGet("noShelfCsv").then((response) => {
        console.log(response);
      });
    };

    return {
      getResponseCsv,
    };
  },
};
</script>

<style scoped>
#headerDiv {
  position: absolute;
  background: rgba(240, 240, 240, 0.95);
  border-bottom: thin solid black;
  height: 7.5%;
  width: 99%;
  top: 2.5%;
}

#rightContent {
  position: absolute;
  top: 12.5%;
  width: 45%;
  height: 80%;
}

#nsProductsDiv {
  width: 37.5%;
  height: 15%;
  border: thin solid grey;
  text-align: center;
}
</style>
