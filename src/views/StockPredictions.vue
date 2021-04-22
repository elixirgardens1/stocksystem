<template>
  <div v-if="Object.keys(predictionsData.value).length !== 0">
    <div id="headerDiv">
      <h1>Stock Predictions</h1>
    </div>

    <div id="filterDiv">
      <div id="filterContainer">
        <select id="filterCategory" v-model="selectedCat">
          <option disabled selected>Select Category</option>
          <option v-for="(cat, index) in productCats" :key="index">{{
            cat
          }}</option>
        </select>
      </div>
    </div>

    <div id="predictionsFilterDiv">
      <div>
        <input
          id="keyInput"
          type="text"
          placeholder="Filter By Key"
          v-model="filterKeyInput"
        />

        <input
          type="text"
          placeholder="Filter By Product"
          v-model="filterProductInput"
        />
      </div>
    </div>

    <div id="predictionsDiv">
      <h1 v-if="selectedCat === 'Select Category'">
        Select Product Category
      </h1>
      <PredictionsTable
        v-else
        id="predictionTable"
        :tableColumns="predictionsColumns"
        :tableData="filterCat"
        @filter-column="setFilter"
      ></PredictionsTable>
    </div>
  </div>

  <div id="loadingDiv" v-if="Object.keys(predictionsData.value).length === 0">
    <h1>Loading...</h1>
  </div>
</template>

<script>
import PredictionsTable from "@/components/PredictionsTable.vue";
import { computed, onMounted, reactive, ref } from "@vue/runtime-core";
import { axiosGet } from "@/composables/axiosGet.js";

export default {
  name: "StockPredictions",
  components: {
    PredictionsTable,
  },
  setup() {
    const predictionsColumns = [
      "Key",
      "Product",
      "Qty",
      "Jan",
      "Feb",
      "Mar",
      "Apr",
      "May",
      "Jun",
      "Jul",
      "Aug",
      "Sep",
      "Oct",
      "Nov",
      "Dec",
      "Q1",
      "Q2",
      "Q3",
      "Q4",
      "Year Total",
    ];
    const predictionsData = reactive({ value: {} });
    const productCats = ref([]);
    const selectedCat = ref("Select Category");
    const filterColumn = ref("");
    const filterType = ref("");
    const filterKeyInput = ref("");
    const filterProductInput = ref("");

    const filterCat = computed(() => {
      if (!selectedCat.value || selectedCat.value == "Select Category") {
        return {};
      }

      let filtered = Object.values(
        predictionsData.value[selectedCat.value]
      ).filter((row) => {
        if (
          row.Product.toLowerCase().indexOf(
            filterProductInput.value.toLowerCase()
          ) == -1
        ) {
          return false;
        }

        if (
          row.Key.toLowerCase().indexOf(filterKeyInput.value.toLowerCase()) ==
          -1
        ) {
          return false;
        }

        return row;
      });

      if (filterColumn.value && filterType.value) {
        if (filterType.value === "Desc") {
          return filtered.sort(
            (a, b) => a[filterColumn.value] - b[filterColumn.value]
          );
        } else {
          return filtered.sort(
            (a, b) => b[filterColumn.value] - a[filterColumn.value]
          );
        }
      }

      return filtered;
    });

    const setFilter = (column, type) => {
      filterColumn.value = column;
      filterType.value = type;
    };

    onMounted(() => {
      axiosGet("stockPredictions").then((response) => {
        predictionsData.value = response.spProducts;
        productCats.value = response.productCats;
      });
    });

    return {
      predictionsColumns,
      predictionsData,
      productCats,
      filterKeyInput,
      filterProductInput,
      filterColumn,
      filterType,
      setFilter,
      selectedCat,
      filterCat,
    };
  },
};
</script>

<style scoped>
#headerDiv {
  position: absolute;
  background: rgba(240, 240, 240, 0.95);
  border-bottom: thin solid black;
  height: 10%;
  width: 99%;
  top: 2.5%;
}

#headerDiv h1 {
  left: 0.5%;
}

#filterDiv {
  position: absolute;
  height: 5%;
  top: 14%;
  width: 99%;
}

#filterContainer {
  position: relative;
  width: 7.5%;
  display: flex;
  margin: 0 auto;
}

#predictionsFilterDiv {
  position: absolute;
  top: 18%;
  height: 5%;
  width: 20%;
}

#predictionsDiv {
  position: absolute;
  top: 25%;
  width: 99%;
  height: 500px;
}

#predictionsDiv h1 {
  position: absolute;
  left: 41%;
}

#loadingDiv {
  position: absolute;
  left: 45%;
}

#filterCategory {
  width: 100%;
}

#keyInput {
  margin-right: 5px;
}
</style>
