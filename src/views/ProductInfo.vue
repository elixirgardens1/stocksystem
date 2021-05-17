<template>
  <div id="productInfoDiv" class="about">
    <h1>Product Info</h1>
    <h2>
      Key: {{ prodKey }} <span style="color: red;">|</span> Product:
      {{ productName }}
      <span style="color: red;">|</span> Supplier:
      {{ productSupplier }}
      <span style="color: red;">|</span> Qty: {{ productQty }} {{ productUnit }}
    </h2>
  </div>

  <div id="pageOptionsDiv">
    <div id="viewLinksDiv">
      <input
        type="button"
        class="navBtn"
        value="Stock Stats"
        @click="setView('stockStats')"
      />
      <input
        type="button"
        class="navBtn"
        value="Order History"
        @click="setView('stockInfo')"
      />
    </div>
  </div>

  <div id="yearSalesDiv" v-show="viewType === 'stockStats'">
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
    <h4>
      January: {{ yearPredictions[0] }}
      <span style="color: red;">|</span> February: {{ yearPredictions[1] }}
      <span style="color: red;">|</span>
      March: {{ yearPredictions[2] }} <span style="color: red;">|</span> April:
      {{ yearPredictions[3] }} <span style="color: red;">|</span> May:
      {{ yearPredictions[4] }} <span style="color: red;">|</span> June:
      {{ yearPredictions[5] }} <span style="color: red;">|</span> July:
      {{ yearPredictions[6] }} <span style="color: red;">|</span> August:
      {{ yearPredictions[7] }} <span style="color: red;">|</span> September:
      {{ yearPredictions[8] }} <span style="color: red;">|</span> October:
      {{ yearPredictions[9] }} <span style="color: red;">|</span> November:
      {{ yearPredictions[10] }} <span style="color: red;">|</span> December:
      {{ yearPredictions[11] }}
    </h4>

    <apexchart
      width="100%"
      height="275"
      type="line"
      :options="yearChartOptions"
      :series="yearChartSeries"
    ></apexchart>
  </div>

  <div id="leftContent">
    <div id="weekSalesDiv" v-show="viewType === 'stockStats'">
      <h3 id="weekTitle">
        Last Week Sales <span style="color:red;">|</span>Total:
        {{ totalSalesPastWeek }}
      </h3>

      <apexchart
        width="100%"
        height="250"
        type="line"
        :options="weekChartOptions"
        :series="weekChartSeries"
      ></apexchart>
    </div>

    <div id="productOrdersDiv" v-show="viewType === 'stockInfo'">
      <h3>
        Orders Containing Product
      </h3>

      <ScrollTable
        :table-columns="productOrderColumns"
        :table-data="productOrders.value"
      ></ScrollTable>
    </div>

    <div id="stockHistoryDiv" v-show="viewType === 'stockInfo'">
      <h3>
        Stock History
      </h3>
      <ScrollTable
        :table-columns="productHistoryColumns"
        :table-data="productHistory.value"
      ></ScrollTable>
    </div>

    <div v-show="viewType === 'stockStats'">
      <h3 style="position: absolute; left: 72.5%; top: 59%;">
        Rolling 30 Day Sales <span style="color:red;">|</span> Total:
        {{ rolling30Total }}
      </h3>
      <div id="rolling30Div">
        <apexchart
          width="100%"
          height="290"
          type="line"
          :options="rolling30Options"
          :series="rolling30Series"
        ></apexchart>
      </div>
    </div>
  </div>
</template>

<script>
import { onMounted, reactive, ref } from "@vue/runtime-core";
import { axiosGet } from "@/composables/axiosGet.js";
import ScrollTable from "@/components/PredictionsTable.vue";
import VueApexCharts from "vue3-apexcharts";

export default {
  name: "ProductInfo",
  components: {
    ScrollTable,
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
    const viewType = ref("stockStats");

    const prodKey = ref("");
    const productName = ref("");
    const productSupplier = ref("");
    const productQty = ref(0);
    const productUnit = ref("");

    const productColumns = ref([]);
    const productOrderColumns = ref([]);
    const productHistoryColumns = ref([]);

    const yearPredictions = ref([]);

    const setView = (type) => {
      viewType.value = type;
    };

    // -----------------------------------------------------------------------------------------------------------------------------------------------------------
    /**
     * On view mount
     */
    const weekChartOptions = ref({});
    const weekChartSeries = ref([]);

    const yearChartOptions = ref({});
    const yearChartSeries = ref([]);

    const rolling30Options = ref({});
    const rolling30Series = ref([]);
    const rolling30Total = ref(0);

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
          productUnit.value = product.value[0].unit;
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

        yearPredictions.value = response.yearPredictions;

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

        rolling30Options.value = {
          chart: {
            id: "Rolling Predictions Chart",
          },
          xaxis: {
            categories: Object.keys(response.rolling30DaySales),
          },
        };

        rolling30Series.value = [
          {
            name: "Sales",
            data: Object.values(response.rolling30DaySales),
          },
        ];

        rolling30Total.value = response.rolling30Total;

        yearChartOptions.value = {
          chart: {
            id: "Year Predictions Chart",
          },
          xaxis: {
            categories: Object.keys(response.keyStockChange),
          },
        };

        yearChartSeries.value = [
          {
            name: "Last Year Sales",
            data: Object.values(response.keyStockChange),
          },
          {
            name: "This Year Sales",
            data: Object.values(response.thisYearSales),
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
      productUnit,
      viewType,
      setView,
      weekChartOptions,
      weekChartSeries,
      yearChartOptions,
      yearChartSeries,
      rolling30Options,
      rolling30Series,
      rolling30Total,
      yearPredictions,
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
  top: 60%;
  width: 45%;
  float: left;
  width: 47.5%;
}

#yearSalesDiv {
  position: absolute;
  top: 18%;
  float: left;
  width: 99%;
}

#yearSalesDiv h4 {
  text-align: center;
}

#searchBtn {
  left: 0.5%;
}

#viewLinksDiv {
  position: absolute;
  float: left;
}

#productOrdersDiv {
  position: absolute;
  top: 18%;
  width: 45%;
  right: 0.5%;
}

#stockHistoryDiv {
  position: absolute;
  top: 18%;
  width: 45%;
  left: 0.5%;
}

#rolling30Div {
  position: absolute;
  top: 65%;
  width: 45%;
  right: 0.5%;
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
