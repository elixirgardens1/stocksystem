<template>
  <div>
    <h1>Update Stock</h1>
    <h2 v-show="selectedKey">Current Product Stock: {{ selectedKeyQty }}</h2>
  </div>

  <div id="setModeCheck">
    Set Stock Mode
    <input type="checkbox" v-model="setCheck" />
  </div>
  <SelectList
    id="productListDiv"
    :list-items="updateProducts.value"
    list-type="product"
    list-key="product"
    @selectedKey="setSelectedKey"
  ></SelectList>

  <div id="updateForm" v-show="selectedKey !== '' && !setCheck">
    <form @submit.prevent="submitStockUpdate()">
      <input id="pkgQty" type="number" v-model="keyPkgQty" />
      <span id="unitSpan"> {{ keyUnit }} X Qty</span>
      <input id="pkgMultiple" type="number" v-model="keyPkgMult" />
      <input
        id="minusCheck"
        type="checkbox"
        v-model="minusChecked"
        @change="addChecked = ''"
      />
      <label id="labelMinusCheck" for="minusCheck">Subtract</label>
      <input
        id="addCheck"
        type="checkbox"
        v-model="addChecked"
        @change="minusChecked = false"
      />
      <label id="labelAddCheck" for="addcheck">Add</label>
      <input id="deliveryNumber" type="text" v-model="delNumber" />
      <label id="labelDeliveryNumber" for="deliveryNumber"
        >Delivery Number</label
      >
      <input id="submitUpdate" class="navBtn" type="submit" value="Submit" />
    </form>
  </div>

  <div id="setQtyDiv" v-show="selectedKey && setCheck">
    Qty
    <input id="setStockQty" type="numeric" ref="setStockQtyInput" />
    <input
      id="submitSetStock"
      class="navBtn"
      type="button"
      value="Submit Set Stock"
      @click="setStock"
    />
  </div>

  <div id="productHistoryDiv" v-show="selectedKey">
    <DynamicTable
      id="historyTbl"
      :columns="productColumns"
      :data-arr="filterProductHistory"
    ></DynamicTable>
  </div>
</template>

<script>
import { computed, onMounted, reactive, ref, watch } from "vue";
import { axiosGet } from "@/composables/axiosGet.js";
import { axiosPost } from "@/composables/axiosPost.js";
import SelectList from "@/components/SelectList.vue";
import DynamicTable from "@/components/DynamicTable.vue";

export default {
  name: "UpdateStock",
  components: {
    SelectList,
    DynamicTable,
  },
  setup() {
    /**
     * Define properties and functionality for update stock page
     */
    const productColumns = ["Product", "Qty", "DeliveryID", "Date"];
    const updateProducts = reactive({ value: [] });
    const updateHistory = reactive({ value: [] });
    const selectedKey = ref("");
    const selectedKeyQty = ref(0);
    const keyUnit = ref("");
    const keyPkgQty = ref(0);
    const keyPkgMult = ref(0);
    const addChecked = ref("");
    const minusChecked = ref("");
    const setCheck = ref("");
    const delNumber = ref("");
    const setStockQtyInput = ref(0);

    const filterProductHistory = computed(() => {
      if (!selectedKey.value) return updateHistory.value;
      if (updateHistory[selectedKey.value]) return {};

      return updateHistory.value[selectedKey.value];
    });

    const setSelectedKey = (key) => {
      if (!key) return false;
      selectedKey.value = key;
    };

    watch(
      () => selectedKey.value,
      (newSelected) => {
        keyUnit.value = updateProducts.value[newSelected].unit;
        keyPkgQty.value = updateProducts.value[newSelected].pkg_qty;
        keyPkgMult.value = updateProducts.value[newSelected].pkg_multiple;
        selectedKeyQty.value = updateProducts.value[newSelected].qty;
      },
      {
        lazy: true,
      }
    );

    const submitStockUpdate = () => {
      if (!addChecked.value && !minusChecked.value) {
        return alert("Please Check An Operation Before Subitting !");
      } else if (addChecked.value && minusChecked.value) {
        return alert("Please Select One Operation Before Submitting !");
      }

      if (!keyPkgQty.value || !keyPkgMult.value)
        return alert(
          "Please Enter Values For Qty And Multiples Before Submitting !"
        );

      let qty = keyPkgQty.value * keyPkgMult.value;
      if (minusChecked.value) qty = qty * -1;

      updateProducts.value[selectedKey.value].qty =
        +selectedKeyQty.value + +qty;

      selectedKeyQty.value = updateProducts.value[selectedKey.value].qty;

      let request = {};
      request["updateStock"] = {
        key: selectedKey.value,
        qty,
        delNumber: delNumber.value,
      };

      axiosPost(request);

      let ins = {
        key: selectedKey.value,
        Product: updateProducts.value[selectedKey.value].product,
        Qty: qty,
        DeliveryID: delNumber.value,
        // eslint-disable-next-line prettier/prettier
        Date: new Date().toISOString().split("T")[0],
      };

      updateHistory.value[selectedKey.value].splice(0, 0, ins);
      delNumber.value = "";
      addChecked.value = "";
      minusChecked.value = "";
    };

    const setStock = () => {
      let qtyInput = setStockQtyInput.value.value;

      if (isNaN(qtyInput)) return alert("Please Enter Numeric Value !");

      if (
        confirm(
          `Are You Sure You Want To Set ${selectedKey.value} Stock Qty To ${qtyInput} ?`
        )
      ) {
        let request = {};
        request["setStock"] = {
          selectedKey: selectedKey.value,
          currentQty: selectedKeyQty.value,
          qtyInput,
        };

        axiosPost(request);

        setStockQtyInput.value.value = "";
        selectedKeyQty.value = qtyInput;
        updateProducts.value[selectedKey.value].qty = qtyInput;
        return alert("Success");
      }
    };

    // -----------------------------------------------------------------------------------------------------------------------------------------------------------
    /**
     * On view mount
     */
    onMounted(() => {
      axiosGet("updateProducts").then((response) => {
        updateProducts.value = response.keyProducts;
        updateHistory.value = response.updateHistory;
      });
    });

    return {
      productColumns,
      updateProducts,
      updateHistory,
      selectedKey,
      filterProductHistory,
      submitStockUpdate,
      setSelectedKey,
      selectedKeyQty,
      keyUnit,
      keyPkgQty,
      keyPkgMult,
      addChecked,
      minusChecked,
      setCheck,
      delNumber,
      setStockQtyInput,
      setStock,
    };
  },
};
</script>

<style scoped>
h1 {
  top: 2.5%;
  left: 0.5%;
}

h2 {
  position: absolute;
  top: 2.5%;
  right: 25%;
}

#filterProduct {
  position: absolute;
  left: 0.4%;
  top: 9%;
  width: 17.5%;
}

#productList {
  position: absolute;
  left: 1%;
  width: 90%;
  width: 75%;
}

#productListDiv {
  position: absolute;
  top: 10%;
  left: 1%;
  left: 0.5%;
  width: 66%;
  height: 90%;
}

#updateForm {
  position: absolute;
  top: 10%;
  left: 50%;
  height: 50%;
  width: 50%;
}

#pkgQty {
  position: absolute;
  left: 0;
  top: 10%;
}

#unitSpan {
  position: absolute;
  left: 20%;
  top: 10%;
}

#minusCheck {
  position: absolute;
  left: 0;
}

#labelMinusCheck {
  position: absolute;
  left: 2.5%;
}

#addCheck {
  position: absolute;
  left: 10%;
}

#labelAddCheck {
  position: absolute;
  left: 12.5%;
}

#deliveryNumber {
  position: absolute;
  top: 10%;
  left: 50%;
}

#labelDeliveryNumber {
  position: absolute;
  left: 66%;
  top: 10.5%;
}

#submitUpdate {
  position: absolute;
  top: 10%;
  left: 80%;
}

#productHistoryDiv {
  position: absolute;
  width: 66%;
  top: 17.5%;
  height: 80%;
  left: 33%;
  overflow: auto;
}

#pkgMultiple {
  position: absolute;
  left: 30%;
  top: 10%;
}

#setModeCheck {
  position: absolute;
  left: 20%;
  top: 8%;
}

#setQtyDiv {
  position: absolute;
  left: 59%;
  top: 12.5%;
}

#setStockQty {
  margin-right: 5px;
  height: 20px;
}
</style>
