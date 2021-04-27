<template>
  <div class="about">
    <h1>Pending Orders</h1>
    <h3 v-show="orderNumberFilterSelected">
      Order Value £{{ orderNumberValue }}
    </h3>
  </div>

  <div class="modalBg" v-show="showEditDate">
    <div id="editDateDiv" class="modalBox">
      <h4>Edit Delivery Date For All Products Of This Order</h4>
      <input id="editOrderDelivery" type="date" v-model="dateEdit" />
      <div id="editDateBtns">
        <input
          type="button"
          class="navBtn"
          value="Submit"
          @click="submitOrderDateEdit(orderNumberFilterSelected)"
        />
        <input
          type="button"
          class="navBtn"
          value="Cancel"
          @click="showEditDate = false"
        />
      </div>
    </div>
  </div>

  <ModalAdd
    v-show="showPendingEdit"
    :product="modalProduct.value"
    :suppliers="pendingSuppliers.value"
    @modal-add-submit="modalSubmit"
    @modal-cancel="showPendingEdit = false"
    @split-product-order="splitProduct"
  ></ModalAdd>

  <div id="filterDiv">
    <input
      id="productFilter"
      v-model="productFilter"
      placeholder="Filter By Product"
      :style="[productFilter ? 'background: yellow' : '']"
    />

    <select
      id="supplierFilter"
      v-model="supplierFilterSelected"
      :style="[supplierFilterSelected ? 'background: yellow' : '']"
    >
      <option value="" disabled selected>Filter By Supplier</option>
      <option value="">No Filter</option>
      <option
        v-for="(index, supplier) in pendingSuppliers.value"
        :key="index"
        >{{ supplier }}</option
      >
    </select>

    <div id="orderNumberFilterDiv">
      <select
        id="orderNumberFilter"
        v-model="orderNumberFilterSelected"
        :style="[orderNumberFilterSelected ? 'background: yellow' : '']"
      >
        <option value="" disabled selected>Filter Order Number</option>
        <option value="">No Order Number Filter</option>
        <option
          v-for="(index, orderNumber) in pendingOrderNumbers.value"
          :key="index"
          :value="orderNumber"
          >{{ orderNumber }}</option
        >
      </select>

      Delivery Date:
      <input
        id="delDateFilter"
        type="date"
        v-model="delDateInput"
        :style="[delDateInput ? 'background: yellow' : '']"
      />
      <input
        type="button"
        class="navBtn"
        value="Clear"
        @click="delDateInput = ''"
      />
    </div>

    <div id="orderActionsDiv" v-show="orderNumberFilterSelected">
      <input
        type="button"
        class="navBtn"
        value="Process Order"
        @click="processOrder(orderNumberFilterSelected)"
      />
      <input
        type="button"
        class="navBtn"
        value="Edit Order"
        @click="showEditDate = true"
      />
      <input
        type="button"
        class="navBtn"
        value="Cancel Order"
        @click="cancelOrder(orderNumberFilterSelected)"
      />
    </div>
  </div>

  <TableComponent
    id="pendingTbl"
    ref="pendingTbl"
    :columns="pendingColumns"
    :data-arr="fitlerTable"
    actions="pendingOrders"
    @process-product="processPendingProduct"
    @edit-product="editPendingProduct"
    @delete-product="deletePendingProduct"
  ></TableComponent>
</template>

<script>
import { axiosGet } from "@/composables/axiosGet.js";
import { axiosPost } from "@/composables/axiosPost.js";
import { computed, onMounted, reactive, ref } from "vue";
import TableComponent from "@/components/ActionTable.vue";
import ModalAdd from "@/components/ModalAdd.vue";

export default {
  name: "PendingOrders",
  components: {
    TableComponent,
    ModalAdd,
  },
  setup() {
    const pendingColumns = [
      "Key",
      "Product",
      "Qty",
      "Order Number",
      "Supplier",
      "Status",
      "Delivery Date",
      "Placed Date",
      "Item Cost",
      "Actions",
    ];
    const pendingData = reactive({ value: {} });
    const pendingSuppliers = reactive({ value: {} });
    const pendingOrderNumbers = reactive({ value: {} });
    const orderNumberFilterSelected = ref("");
    const supplierFilterSelected = ref("");
    const productFilter = ref("");
    const delDateInput = ref("");

    const fitlerTable = computed(() => {
      if (
        !orderNumberFilterSelected.value &&
        !supplierFilterSelected.value &&
        !productFilter.value &&
        !delDateInput.value
      ) {
        return pendingData.value;
      }

      return pendingData.value.filter((row) => {
        if (
          orderNumberFilterSelected.value &&
          row["Order Number"] !== orderNumberFilterSelected.value
        ) {
          return false;
        }

        if (
          supplierFilterSelected.value &&
          row["Supplier"] !== supplierFilterSelected.value
        ) {
          return false;
        }

        if (
          productFilter.value &&
          row["Product"]
            .toLowerCase()
            .indexOf(productFilter.value.toLowerCase()) == -1
        ) {
          return false;
        }

        if (delDateInput.value && row["Delivery Date"] !== delDateInput.value) {
          return false;
        }

        return row;
      });
    });

    const filteredOrderNumbers = computed(() => {
      if (!orderNumberFilterSelected.value) return pendingData.value;

      let orderNumberProducts = {};
      for (const value of Object.entries(pendingData.value)) {
        if (value[1]["Order Number"] === orderNumberFilterSelected.value) {
          orderNumberProducts[value[0]] = value[1];
        }
      }
      return orderNumberProducts;
    });

    const orderNumberValue = computed(() => {
      if (!orderNumberFilterSelected.value) return "";

      let orderValue = 0;
      for (const value of Object.entries(filteredOrderNumbers.value)) {
        orderValue += +value[1]["Item Cost"];
      }
      return orderValue.toFixed(2);
    });

    // -----------------------------------------------------------------------------------------------------------------------------------------------------------
    /**
     * Product modal properties and functionality
     */
    const modalProduct = reactive({ value: {} });
    const showPendingEdit = ref(false);

    const processPendingProduct = (key, orderNumber) => {
      let deliveryNumber = prompt("Please Enter Delivery Number");
      let signedBy = prompt("Please Enter Name To Sign For Order");

      if (!deliveryNumber || !signedBy)
        return alert("Try Again, Please Enter Information");

      let product = findProduct(key, orderNumber);

      const request = {};
      request["processPendingProduct"] = {
        "Delivery Number": deliveryNumber,
        signedBy,
        ...product,
      };

      axiosPost(request);
      pendingData.value.splice(product.Index, 1);
    };

    const editPendingProduct = (key, orderNumber) => {
      pendingData.value.find((row) => {
        if (row.Key === key && row["Order Number"] === orderNumber) {
          modalProduct.value = row;
          modalProduct.value.setDeliveryDate = row["Delivery Date"];
          modalProduct.value.setPlacedDate = row["Placed Date"];
        }
      });

      showPendingEdit.value = true;
    };

    const deletePendingProduct = (key, orderNumber) => {
      if (!confirm("Are You Sure ?")) return false;

      let product = findProduct(key, orderNumber);
      let request = {};
      request["deletePendingProduct"] = product;

      axiosPost(request);
      pendingData.value.splice(product.Index, 1);
    };

    const modalSubmit = (product) => {
      let request = {};
      let productIndex = 0;

      request["editPendingProduct"] = product;
      pendingData.value.find((row, indx) => {
        if (
          row.Key === product.Key &&
          row["Order Number"] === product["Order Number"]
        ) {
          productIndex = indx;
          product["Item Cost"] = productItemCost(product.Qty, row.product_cost);
          request["editPendingProduct"]["costDiff"] =
            row["Item Cost"] - request["editPendingProduct"]["Item Cost"];
        }
      });

      axiosPost(request);

      for (let key in product) {
        if (product[key]) {
          pendingData.value[productIndex][key] = product[key];
        }
      }
      showPendingEdit.value = false;
    };

    const splitProduct = () => {
      const splitOrderNumber = (
        +modalProduct.value["Order Number"] + +0.1
      ).toFixed(1);
      const splitQty = prompt(
        `Please Enter Qty To Split From This Product Order, Split Will Be Added To Order Number: ${splitOrderNumber}`
      );

      let checkDuplicateSplit = false;
      Object.entries(pendingData.value).find((row) => {
        if (row[1]["Order Number"] === splitOrderNumber && row[1]["Key"]) {
          checkDuplicateSplit = true;
          return alert(
            `Product Already Exists In The Order ! Edit The Qty Value In ${splitOrderNumber} Or Split The Product Order Again From ${splitOrderNumber} Into ${(
              +splitOrderNumber + +0.1
            ).toFixed(2)}`
          );
        }
      });

      if (checkDuplicateSplit === true) {
        return false;
      }

      if (+splitQty > +modalProduct.value.Qty) {
        return alert("Please Enter Value Less Than The Amount On Order !");
      }

      if (!splitQty || isNaN(splitQty))
        return alert("Please Enter Valid Data !");

      let request = {};
      request["splitOrder"] = {
        ...modalProduct.value,
        splitQty,
        splitOrderNumber,
      };
      axiosPost(request);

      Object.entries(pendingData.value).forEach((row) => {
        if (
          row[1]["Order Number"] === modalProduct.value["Order Number"] &&
          row[1].Key === modalProduct.value.Key
        ) {
          row[1].Qty = +row[1].Qty - +splitQty;
          row[1]["Item Cost"] = row[1].Qty * row[1]["product_cost"];
        }
      });

      let copyOriginal = JSON.parse(JSON.stringify(modalProduct.value));
      copyOriginal["Order Number"] = splitOrderNumber;
      copyOriginal["Qty"] = splitQty;
      copyOriginal["Item Cost"] = splitQty * modalProduct.value["product_cost"];

      pendingData.value.push({ ...copyOriginal });

      showPendingEdit.value = false;
    };

    // -----------------------------------------------------------------------------------------------------------------------------------------------------------
    /**
     * Order number actions properties and functionality
     */
    const dateEdit = ref("");
    const showEditDate = ref(false);

    const processOrder = (orderNumber) => {
      let deliveryNumber = prompt("Please Enter Delivery Number");
      let signedBy = prompt("Please Enter Name To Sign For Order");

      if (!deliveryNumber || !signedBy)
        return alert("Try Again, Please Enter Information");

      let request = {};
      request["processOrder"] = {
        orderNumber,
        orderDeliveryDate: new Date(),
        orderValue: orderNumberValue.value,
        deliveryNumber,
        signedBy,
        products: filteredOrderNumbers.value,
      };

      axiosPost(request);
      Object.keys(filteredOrderNumbers).forEach((key) =>
        pendingData.value.splice(key, 1)
      );
      delete pendingOrderNumbers.value[orderNumberFilterSelected.value];
      orderNumberFilterSelected.value = "";
    };

    const submitOrderDateEdit = (orderNumber) => {
      if (!dateEdit.value) return alert("Please Enter Date Before Submitting");

      let request = {};
      request["editOrderDate"] = {
        orderNumber,
        newDate: dateEdit.value,
      };

      axiosPost(request);
      for (const value of Object.entries(filteredOrderNumbers.value)) {
        value[1]["Delivery Date"] = dateEdit.value;
      }
      showEditDate.value = false;
    };

    const cancelOrder = (orderNumber) => {
      if (!confirm("Are You Sure You Want To Cancel The Order ?")) return false;

      let request = {};
      request["cancelOrder"] = {
        orderNumber,
      };

      axiosPost(request);
      Object.keys(filteredOrderNumbers).forEach((key) =>
        pendingData.value.splice(key, 1)
      );
      delete pendingOrderNumbers.value[orderNumberFilterSelected.value];
      orderNumberFilterSelected.value = "";
    };

    // -----------------------------------------------------------------------------------------------------------------------------------------------------------
    /**
     * Misc
     */
    function productItemCost(itemQty, productCost) {
      return (itemQty * productCost).toFixed(2);
    }

    function findProduct(key, orderNumber) {
      let product = pendingData.value.find((row, index) => {
        if (row.Key == key && row["Order Number"] == orderNumber) {
          row["Index"] = index;
          return row;
        }
      });
      return product;
    }

    // -----------------------------------------------------------------------------------------------------------------------------------------------------------
    /**
     * On view mount
     */
    onMounted(() => {
      axiosGet("pendingOrders").then((response) => {
        pendingData.value = response.pendingProducts;
        pendingSuppliers.value = response.pendingSuppliers;
        pendingOrderNumbers.value = response.pendingOrderNumbers;
      });
    });

    return {
      pendingColumns,
      pendingData,
      pendingSuppliers,
      pendingOrderNumbers,
      fitlerTable,
      orderNumberFilterSelected,
      supplierFilterSelected,
      productFilter,
      delDateInput,
      orderNumberValue,
      modalProduct,
      showPendingEdit,
      processPendingProduct,
      editPendingProduct,
      splitProduct,
      deletePendingProduct,
      modalSubmit,
      showEditDate,
      processOrder,
      submitOrderDateEdit,
      cancelOrder,
      dateEdit,
    };
  },
};
</script>

<style scoped>
h1 {
  top: 2.5%;
  left: 0.5%;
}

h3 {
  position: absolute;
  top: 4%;
  left: 15.5%;
}

h4 {
  text-align: center;
}

#editDateDiv {
  height: 15%;
}

#editOrderDelivery {
  position: absolute;
  right: 40%;
  left: 40%;
}

#editDateBtns {
  position: relative;
  display: flex;
  margin-left: 37.5%;
  top: 30%;
}

#pendingFilters {
  position: relative;

  margin-top: 2%;
}

#orderNumberFilter {
  width: 150px;
}

#filterDiv {
  float: left;
  display: flex;
  top: 7.5%;
  flex-direction: row;
  height: 22px;
  padding: 20px;
  width: 80%;
}

#filterDiv input,
#filterDiv select {
  max-width: 140px;
  margin-right: 7.5px;
}
</style>
