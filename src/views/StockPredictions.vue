<template>
  <div v-if="Object.keys(predictionsData.value).length !== 0">
    <div id="headerDiv">
      <h1>Stock Predictions</h1>
      <input
        id="predictionsBtn"
        type="button"
        class="navBtn"
        value="Predictions"
        @click="viewType = 'viewPredictions'"
      />
      <input
        id="productsBtn"
        type="button"
        class="navBtn"
        value="Under Performing Products"
        @click="viewType = 'viewProducts'"
      />
    </div>

    <div v-show="viewType === 'viewPredictions'">
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
  </div>

  <div id="uppDiv" v-show="viewType === 'viewProducts'">
    <h3>Under Performing Products</h3>
    <h4 style="text-align: center;">Product Count: {{ upProductCount }}</h4>
    <h5 style="text-align: center;">
      Products Ordered By Percentage Decrease From The Predicted Sales Based On
      Previous Year
    </h5>
    <PredictionsTable
      :tableColumns="upProductsColumns"
      :tableData="upProducts"
      @filter-column="filterUpp"
      @export-sku-stats="skusStats"
    ></PredictionsTable>
  </div>

  <div id="loadingDiv" v-if="Object.keys(predictionsData.value).length === 0">
    <h1>Loading...</h1>
  </div>
</template>

<script>
import PredictionsTable from "@/components/PredictionsTable.vue";
import { computed, onMounted, reactive, ref } from "@vue/runtime-core";
import { axiosGet } from "@/composables/axiosGet.js";
import { exportCsv } from "@/composables/exportCsv.js";

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
    const viewType = ref("viewPredictions");
    const upProducts = ref([]);
    const upProductsColumns = ref([]);
    const upProductCount = ref(0);

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

    // Set filters for predictions table, which will trigger computed properties
    const setFilter = (column, type) => {
      filterColumn.value = column;
      filterType.value = type;
    };

    // Filter under performing products table
    const filterUpp = (column, type) => {
      if (type === "Desc") {
        return upProducts.value.sort((a, b) => a[column] - b[column]);
      } else if (type === "Asc") {
        return upProducts.value.sort((a, b) => b[column] - a[column]);
      } else {
        return upProducts.value.sort((a, b) => a["Qty"] - b["Qty"]);
      }
    };

    const skusStats = (key) => {
        axiosGet(`skuStats?key=${key}`).then((response) => {
            downloadCsv(response, `${key}SkuStats`)
        });
    };

    const downloadCsv = (exportData, name) => {
      if (!Object.keys(exportData).length) {
        return alert("No Results Found, Can't Export Csv");
      }

      const csv = exportCsv(exportData);
      if (csv === "Not Valid Format") return alert(csv);

      let link = document.createElement("a");
      link.id = "download-csv";
      link.setAttribute(
        "href",
        "data:text/plain;charset=utf-8," + encodeURIComponent(csv)
      );
      link.setAttribute("download", `${name}.csv`);
      document.body.appendChild(link);
      document.querySelector("#download-csv").click();
      document.body.removeChild(link);
    };

    onMounted(() => {
      axiosGet("stockPredictions").then((response) => {
        predictionsData.value = response.spProducts;
        productCats.value = response.productCats;
        upProducts.value = response.trendingBelow;

        let firstProductKey = Object.keys(upProducts.value)[0];
        upProductsColumns.value = Object.keys(
          upProducts.value[firstProductKey]
        );

        upProductCount.value = Object.keys(upProducts.value).length;
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
      viewType,
      upProducts,
      upProductsColumns,
      upProductCount,
      filterUpp,
      skusStats,
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

#productsBtn {
  position: relative;
  top: 65%;
}

#predictionsBtn {
  position: relative;
  top: 65%;
}

#keyInput {
  margin-right: 5px;
}

#uppDiv {
  position: absolute;
  width: 99%;
  height: 80%;
  top: 11%;
}
</style>
