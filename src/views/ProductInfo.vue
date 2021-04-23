<template>
  <div id="productInfoDiv" class="about">
    <h1>Product Info</h1>
    <h2>
      Key: {{ prodKey }} <span style="color: red;">|</span> Product:
      {{ productName }}
      <span style="color: red;">|</span> Supplier:
      {{ productSupplier }}
      <span style="color: red;">|</span> Qty: {{ productQty }}
    </h2>
  </div>

  <div id="pageOptionsDiv">
    <input
      id="homeViewBtn"
      type="button"
      class="navBtn"
      value="Show Main View"
      @click="showSearchResults = false"
    />
    <input
      id="searchBtn"
      type="button"
      class="navBtn"
      value="Search Prediction Period"
      @click="
        showModalSearch = true;
        showSearchResults = false;
      "
    />

    <input
      id="viewResults"
      type="button"
      class="navBtn"
      value="View Search Results"
      @click="showSearchResults = true"
    />

    <div id="viewLinksDiv">
      <input
        type="button"
        class="navBtn"
        value="Order History"
        @click="setView('orderHistory')"
      />
      <input
        type="button"
        class="navBtn"
        value="Stock History"
        @click="setView('stockHistory')"
      />
    </div>
  </div>

  <div id="leftContent" v-show="showSearchResults === false">
    <div
      class="modalBg"
      id="modalSearchPredicitonBg"
      v-show="showModalSearch === true"
    >
      <div class="modalBox" id="modalSearchPredictionBox">
        <h3>Enter Search Period</h3>

        <div id="modalSearchInput">
          <label for="modalStartDate">Start Period </label>
          <input ref="startDate" id="modalStartDate" type="date" />

          <label for="modalEndDate">End Period </label>
          <input ref="endDate" id="modalEndDate" type="date" />
        </div>

        <div id="modalSearchPredictionBtns">
          <input
            class="navBtn"
            type="button"
            value="Search"
            @click="getSearchPeriod"
          />
          <input
            class="navBtn"
            type="button"
            value="Cancel"
            @click="showModalSearch = false"
          />
        </div>
      </div>
    </div>

    <div id="weekSalesDiv">
      <h3 id="weekTitle">
        Last Week Sales <span style="color:red;">|</span>Total:
        {{ totalSalesPastWeek }}
      </h3>

      <apexchart
        width="900"
        height="275"
        type="line"
        :options="weekChartOptions"
        :series="weekChartSeries"
      ></apexchart>
    </div>

    <div id="yearSalesDiv">
      <h3>
        Year Sales Predictions <span style="color: red;">|</span>Total:
        {{ totalSalesYearPredictions.value.total }}
      </h3>
      <h4>
        Quarter One: {{ totalSalesYearPredictions.value.quarter1 }}
        <span style="color: red;">|</span> Quarter Two:
        {{ totalSalesYearPredictions.value.quarter2 }}
        <span style="color: red;">|</span> Quarter Three:
        {{ totalSalesYearPredictions.value.quarter3 }}
        <span style="color: red;">|</span>Quarter Four:
        {{ totalSalesYearPredictions.value.quarter4 }}
      </h4>

      <apexchart
        width="900"
        height="275"
        type="line"
        :options="yearChartOptions"
        :series="yearChartSeries"
      ></apexchart>
    </div>

    <h3
      style="position: absolute; left: 70%;"
      v-show="viewType === 'orderHistory'"
    >
      Orders Containing Product
    </h3>
    <div id="productOrdersDiv" v-show="viewType === 'orderHistory'">
      <DynamicTable
        id="productOrdersTbl"
        :columns="productOrderColumns"
        :data-arr="productOrders.value"
      ></DynamicTable>
    </div>

    <h3
      style="position: absolute; left: 72.5%;"
      v-show="viewType === 'stockHistory'"
    >
      Stock History
    </h3>
    <div id="stockHistoryDiv" v-show="viewType === 'stockHistory'">
      <DynamicTable
        id="stockHistoryTbl"
        :columns="productHistoryColumns"
        :data-arr="productHistory.value"
      ></DynamicTable>
    </div>
  </div>

  <div id="searchResultsDiv" v-show="showSearchResults === true">
    <div
      id="noResultsDiv"
      v-if="Object.keys(predictionSearches.results).length === 0"
    >
      <h2>No Search Results, Search To See Query Results Here</h2>
    </div>
    <div
      id="searchGraphDiv"
      v-for="(period, index) in predictionSearches.results"
      :key="index"
    >
      <div>
        <h3>
          {{ index }} <span style="color: red;">|</span> Total Sales:
          {{ period.totalSales }}
        </h3>
        <apexchart
          width="800"
          type="line"
          :options="period.chartOptions"
          :series="period.series"
        ></apexchart>
      </div>
    </div>
  </div>
</template>

<script>
import { onMounted, reactive, ref } from "@vue/runtime-core";
import { axiosGet } from "@/composables/axiosGet.js";
import DynamicTable from "@/components/DynamicTable.vue";
import VueApexCharts from "vue3-apexcharts";

export default {
  name: "ProductInfo",
  components: {
    DynamicTable,
    apexchart: VueApexCharts,
  },
  props: {
    productKey: {
      type: String,
      required: true,
    },
  },
  setup(props) {
    /**
     * Define view properties
     */
    const product = reactive({ value: {} });
    const productOrders = reactive({ value: {} });
    const totalSalesYearPredictions = reactive({ value: {} });
    const totalSalesPastWeek = ref(0);
    const productHistory = reactive({ value: {} });
    const viewType = ref("orderHistory");

    const prodKey = ref("");
    const productName = ref("");
    const productSupplier = ref("");
    const productQty = ref(0);

    const productColumns = ref([]);
    const productOrderColumns = ref([]);
    const productHistoryColumns = ref([]);

    const startDate = ref("");
    const endDate = ref("");
    const showModalSearch = ref(false);

    const showSearchResults = ref(false);
    const predictionSearches = reactive({ results: {} });

    const setView = (type) => {
      viewType.value = type;
    };

    /**
     * Get the user input from the search modal and send a request to get sales predictions for the user entered period.
     * Adds the return results to the predictionSearches array, which will render a new component that will be displayed in the view search results div
     */
    const getSearchPeriod = () => {
      if (!startDate.value.value || !endDate.value.value)
        return alert("Please Enter Date Periods Before Searching !");

      if (startDate.value.value > endDate.value.value)
        return alert("Start Date Can Not Be A Later Date Than The End Date !");

      axiosGet(
        `getSearchPeriod?start=${startDate.value.value}&end=${endDate.value.value}&key=${props.productKey}`
      ).then((response) => {
        const id = `${startDate.value.value} - ${endDate.value.value}`;
        const monthArray = response.monthArray;

        if (response.periodSales === undefined) return alert(response);

        predictionSearches.results[id] = {
          totalSales: response.totalPeriodSales,
          chartOptions: {
            chart: {
              id,
            },
            xaxis: {
              categories: monthArray,
            },
          },
          series: [
            {
              name: "Sales",
              data: response.periodSales,
            },
          ],
        };
      });
      showModalSearch.value = false;
    };

    // -----------------------------------------------------------------------------------------------------------------------------------------------------------
    /**
     * On view mount
     */
    let weekChartOptions = ref({});
    let weekChartSeries = ref([]);

    let yearChartOptions = ref({});
    let yearChartSeries = ref([]);

    onMounted(() => {
      axiosGet(`productInfo?key=${props.productKey}`).then((response) => {
        product.value = response.productInfo;
        productOrders.value = response.productOrders;
        productHistory.value = response.productHistory;
        totalSalesPastWeek.value = response.totalSalesPastWeek;
        totalSalesYearPredictions.value = response.totalSalesYearPrediction;

        if (productColumns.value !== undefined) {
          productColumns.value = Object.keys(product.value[0]);
          prodKey.value = product.value[0].key;
          productName.value = product.value[0].product;
          productSupplier.value = product.value[0].primary_supplier;
          productQty.value = product.value[0].qty;
        }

        if (Object.keys(productHistory.value).length > 0) {
          productHistoryColumns.value = Object.keys(productHistory.value[0]);
        }

        if (productOrders.value[0] !== undefined) {
          productOrderColumns.value = Object.keys(productOrders.value[0]);
        }

        if (productOrders.value === undefined) {
          productOrders.value = {};
        }

        weekChartOptions.value = {
          chart: {
            id: "Week Predictions Chart",
          },
          xaxis: {
            categories: response.salesWeekColumns,
          },
        };

        weekChartSeries.value = [
          {
            name: "Sales",
            data: response.salesPastWeek,
          },
        ];

        yearChartOptions.value = {
          chart: {
            id: "Year Predictions Chart",
          },
          xaxis: {
            categories: [
              "January",
              "February",
              "March",
              "April",
              "May",
              "June",
              "July",
              "August",
              "September",
              "October",
              "November",
              "December",
            ],
          },
        };

        yearChartSeries.value = [
          {
            name: "Sales",
            data: response.yearPredictions,
          },
        ];
      });
    });

    return {
      product,
      productColumns,
      productHistory,
      productHistoryColumns,
      totalSalesPastWeek,
      totalSalesYearPredictions,
      productOrders,
      productOrderColumns,
      prodKey,
      productName,
      productSupplier,
      productQty,
      viewType,
      setView,
      showModalSearch,
      getSearchPeriod,
      startDate,
      endDate,
      showSearchResults,
      predictionSearches,
      weekChartOptions,
      weekChartSeries,
      yearChartOptions,
      yearChartSeries,
    };
  },
};
</script>

<style scoped>
h1 {
  width: 99%;
  background: rgba(240, 240, 240, 0.95);
  top: 2.5%;
  left: 0.5%;
}

#productInfoDiv {
  display: flex;
  background: rgba(240, 240, 240, 0.95);
  height: 100%;
}

#weekSales {
  border: thin solid grey;
}

#yearSales {
  border: thin solid grey;
}

#weekSalesDiv {
  position: absolute;
  top: 20%;
  float: left;
  width: 47.5%;
}

#yearSalesDiv {
  position: absolute;
  top: 55%;
  float: left;
  width: 47.5%;
}

#searchBtn {
  left: 0.5%;
}

#viewLinksDiv {
  position: absolute;
  right: 0.5%;
}

#productOrdersDiv {
  position: absolute;
  top: 25%;
  width: 50%;
  height: 57.5%;
  right: 0.5%;
  overflow-y: auto;
}

#productOrdersTbl {
  top: 0;
  border: thin solid grey;
}

#stockHistoryDiv {
  position: absolute;
  top: 25%;
  width: 50%;
  height: 57.5%;
  right: 0.5%;
  overflow-y: auto;
}

#stockHistoryTbl {
  top: 0;
  border: thin solid grey;
}

#modalSearchPredictionBtns {
  position: absolute;
  display: flex;
  left: 50%;
  top: 85%;
  transform: translate(-50%, -50%);
  margin: 0 auto;
}

#modalSearchInput {
  position: absolute;
  left: 40%;
  display: grid;
  width: 20%;
  align-items: center;
}

#modalSearchPredictionBox {
  height: 25%;
}

#noResultsDiv {
  text-align: center;
}

#searchGraphDiv {
  position: relative;
  display: flex;
  margin: 0 auto;
  width: 800px;
}

#pageOptionsDiv {
  display: flex;
  align-items: center;
  border-bottom: thin solid grey;
  width: 99%;
  height: 50px;
  background: rgba(240, 240, 240, 0.95);
}
</style>
