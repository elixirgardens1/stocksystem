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

  <div id="leftContent">
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

    <h3 style="position: absolute; left: 72.5%; top: 65%;">
      Rolling 30 Day Sales
    </h3>
    <div id="rolling30Div">
      <apexchart
        width="900"
        height="290"
        type="line"
        :options="rolling30Options"
        :series="rolling30Series"
      ></apexchart>
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
            name: "Last Year Sales",
            data: response.yearPredictions,
          },
          {
            name: "This Year Sales",
            data: response.thisYearSales,
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
      weekChartOptions,
      weekChartSeries,
      yearChartOptions,
      yearChartSeries,
      rolling30Options,
      rolling30Series,
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
  width: 45%;
  height: 40%;
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
  width: 45%;
  height: 40%;
  right: 0.5%;
  overflow-y: auto;
}

#stockHistoryTbl {
  top: 0;
  border: thin solid grey;
}

#rolling30Div {
  position: absolute;
  top: 67%;
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
