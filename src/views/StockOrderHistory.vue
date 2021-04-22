<template>
  <div id="itemsTableDiv">
    <DynamicTable
      id="itemsTable"
      :columns="itemColumns"
      :data-arr="itemHistory.value"
      v-show="showItemTbl"
    ></DynamicTable>
  </div>

  <div id="orderTableFilters">
    <input
      id="orderNumberFilter"
      type="text"
      placeholder="Filter Order Number"
      v-model="orderNumberFilter"
    />
    <select id="filterSupplier" v-model="supplierFilter">
      <option selected disabled>Filter Supplier</option>
      <option value="">No Filter</option>
      <option
        v-for="(index, supplier) in historySuppliers.value"
        :key="supplier"
        >{{ supplier }}</option
      ></select
    >

    Delivery Date:
    <input id="filterDate" type="date" v-model="dateFilter" />
  </div>

  <div id="orderTableDiv">
    <ActionTable
      id="orderTable"
      :columns="orderColumns"
      :data-arr="filterOrdersHistory"
      @order-number-items="setOrderItems"
    ></ActionTable>
  </div>
</template>

<script>
import { computed, onMounted, reactive, ref } from "vue";
import { axiosGet } from "@/composables/axiosGet.js";
import ActionTable from "@/components/ActionTable.vue";
import DynamicTable from "@/components/DynamicTable.vue";

export default {
  name: "StockOrderHistory",
  components: {
    DynamicTable,
    ActionTable,
  },
  setup() {
    /**
     * Define properties and functionality of view
     */
    const itemColumns = [
      "Order Number",
      "Supplier",
      "Delivery Number",
      "Date Placed",
      "Date Delivered",
      "Status",
      "Product",
      "Qty",
      "Item Cost",
      "Key",
      "Signed",
    ];
    const orderColumns = [
      "Order Number",
      "Supplier",
      "Delivery Number",
      "Date Placed",
      "Date Delivered",
      "Status",
      "Order Value",
      "Show Items",
    ];
    const orderHistory = reactive({ value: [] });
    const orderItems = reactive({ value: {} });
    const itemHistory = reactive({ value: {} });
    const showItemTbl = ref(false);
    const historySuppliers = reactive({ value: [] });

    const orderNumberFilter = ref("");
    const supplierFilter = ref("");
    const dateFilter = ref("");

    const filterOrdersHistory = computed(() => {
      if (
        !orderNumberFilter.value &&
        !supplierFilter.value &&
        !dateFilter.value
      ) {
        return orderHistory.value;
      }

      return orderHistory.value.filter((row) => {
        if (
          orderNumberFilter.value &&
          row["Order Number"] !== orderNumberFilter.value
        ) {
          return false;
        }

        if (supplierFilter.value && row["Supplier"] !== supplierFilter.value) {
          return false;
        }

        if (dateFilter.value && row["Date Delivered"] !== dateFilter.value) {
          return false;
        }

        return row;
      });
    });

    /**
     * When show items is clicked for an order nuber in the order table, display the items belonging to that order number in the items table above it
     */
    const setOrderItems = (orderNumber) => {
      for (const value of Object.entries(orderHistory.value)) {
        if (value[1]["Order Number"] === orderNumber)
          itemHistory.value = value[1].items;
      }

      showItemTbl.value = true;
    };
    // -----------------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * On view mount
     */
    onMounted(() => {
      axiosGet("orderHistory").then((response) => {
        orderHistory.value = response.stockHistory;
        historySuppliers.value = response.historySuppliers;
      });
    });

    return {
      itemColumns,
      orderColumns,
      orderHistory,
      orderItems,
      itemHistory,
      showItemTbl,
      setOrderItems,
      orderNumberFilter,
      supplierFilter,
      dateFilter,
      filterOrdersHistory,
      historySuppliers,
    };
  },
};
</script>

<style scoped>
#orderTableDiv {
  position: absolute;
  top: 36%;
  width: 99%;
  overflow-y: auto;
  height: 66%;
}

#filterSupplier {
  width: 200px;
}

#orderTableFilters {
  position: relative;
  margin: 13% auto;
  width: 33%;
  display: flex;
  justify-content: space-between;
}

#itemsTableDiv {
  position: absolute;
  top: 2%;
  width: 99%;
  height: 30%;
  overflow-y: auto;
}
</style>
