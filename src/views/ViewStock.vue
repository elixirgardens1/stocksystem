<template>
  <div class="home">
    <ModalBox
      v-show="showModal"
      :product="modalProduct.value"
      :suppliers="productSuppliers.value"
      :rooms="productRooms.value"
      @modal-submit="modalSubmit"
      @modal-cancel="modalCancel"
      @modal-hide="modalHide"
    ></ModalBox>

    <ModalAdd
      v-show="showAddModal"
      :product="modalAddProduct.value"
      :suppliers="productSuppliers.value"
      @modal-add-submit="modalAddSubmit"
      @modal-cancel="showAddModal = false"
    ></ModalAdd>

    <div class="modalBg" v-show="showExport">
      <div id="modalExport" class="modalBox">
        <div id="exportOption">
          <h4 id="exportTitle">Actions</h4>
          Type:
          <select v-model="exportOptionValue">
            <option value="oosProducts">Out Of Stock Products</option>
            <option value="cisProducts">Products Coming Back Into Stock</option>
            <option value="ddProducts">Products Due Delivery Today</option>
            <option value="removeShelf">Remove Shelf From All Products</option>
            <option value="noShelfProducts">Product Orders No Shelfs</option>
          </select>
        </div>
        <div id="modalExportBtns">
          <input
            type="button"
            class="navBtn"
            value="Submit"
            @click="productsExport"
          />
          <input
            type="button"
            class="navBtn"
            value="Cancel"
            @click="showExport = false"
          />
        </div>
      </div>
    </div>

    <div class="modalBg" v-if="showFormModal">
      <div id="modalFormBox" class="modalBox">
        <ModalAdd
          id="modalFormTbl"
          v-show="showAddModal"
          :product="modalAddProduct.value"
          :suppliers="productSuppliers.value"
          @modal-add-submit="modalAddSubmit"
          @modal-cancel="showAddModal = false"
        ></ModalAdd>
        <h3>Order Total £ {{ formTotal }}</h3>
        <TableComponent
          id="modalForm"
          v-if="showFormModal"
          :columns="orderFormColumns"
          :data-arr="formOrderProducts.value"
          actions="formProducts"
          @edit-product="modalFormEdit"
          @delete-product="modalFormDelete"
        ></TableComponent>
        <div id="modalFormButtons">
          <input
            id="modalFormSubmit"
            class="navBtn"
            type="submit"
            value="Submit"
            @click="submitOrderForm"
          />
          <input
            id="modalFormCancel"
            class="navBtn"
            type="button"
            value="Close"
            @click="showFormModal = false"
          />
        </div>
      </div>
    </div>

    <span id="activeProductsSpan"
      >Total Products In Category #{{ countProducts }}</span
    >
    <span id="activeOrderNumber"
      >Active Order Number: {{ currentOrderNumber }}</span
    >

    <div id="tableFilters">
      <input
        id="productFilter"
        type="text"
        placeholder="Filter By Product"
        v-model="filterProduct"
      />

      <input
        id="keyFilter"
        type="text"
        placeholder="Filter By Key"
        v-model="filterKey"
      />

      <input
        id="shelfFilter"
        type="text"
        placeholder="Filter By Shelf"
        v-model="filterShelf"
      />

      <select id="supplierFilter" v-model="filterSupplier">
        <option disabled selected>Filter Supplier</option>
        <option value="">No Filter</option>
        <option
          v-for="(index, supplier) in productSuppliers.value"
          :key="supplier"
          >{{ supplier }}</option
        >
      </select>

      <select id="filterView" v-model="filterViewSelected">
        <option disabled selected>Filter View</option>
        <option value="">No Filter</option>
        <option value="to_be_hidden">Hidden Products</option>
        <option value="Days To OOS">No Sales</option>
        <option value="outOfStock">Out Of Stock</option>
      </select>
    </div>

    <button id="clearForm" class="navBtn" @click="clearOrderForm">
      Clear Form
    </button>
    <button
      id="productForm"
      type="button"
      class="navBtn"
      @click="showFormModal = true"
    >
      Order Form ({{ formProductCount }})
    </button>

    <div id="existingOrdersDiv">
      <input
        id="addToExisting"
        class="navBtn"
        type="button"
        value="Add To Existing Order"
        v-show="!showExisting"
        @click="showExisting = true"
      />
      <input
        id="cancelAddToExisting"
        class="navBtn"
        type="button"
        value="Cancel"
        v-show="showExisting"
        @click="
          showExisting = false;
          activeOrderNumber = 0;
        "
      />
      <select
        id="existingSelect"
        ref="refExistingSelect"
        v-show="showExisting"
        v-model="activeOrderNumber"
      >
        <option
          v-for="(index, orderNumber) in existingOrderNumbers.value"
          :key="index"
          >{{ orderNumber }}</option
        >
      </select>
    </div>
    <input
      id="exportBtn"
      type="button"
      class="navBtn"
      value="Stock Actions"
      @click="showExport = true"
    />
    <TableComponent
      id="viewProductsTbl"
      ref="viewProductsTbl"
      :columns="productColumns"
      :data-arr="filterView"
      actions="viewProducts"
      :pendingKeys="pendingKeys"
      :salesPastWeek="salesPastWeek.value"
      @edit-product="editProduct"
      @add-product="addProduct"
      @oos-product="setProductOos"
    ></TableComponent>
  </div>
</template>

<script>
// @ is an alias to /src
import ModalBox from "@/components/ModalBox.vue";
import ModalAdd from "@/components/ModalAdd.vue";
import TableComponent from "@/components/ActionTable.vue";
import { axiosGet } from "@/composables/axiosGet.js";
import { axiosPost } from "@/composables/axiosPost.js";
import { exportCsv } from "@/composables/exportCsv.js";
import { reactive, computed, onMounted, ref } from "vue";

export default {
  name: "Home",
  components: {
    ModalBox,
    ModalAdd,
    TableComponent,
  },
  setup() {
    /**
     * Product table and modal box properties and functionality
     */
    const productColumns = [
      "Cat",
      "Key",
      "Product",
      "Qty",
      "Supplier",
      "Room",
      "Locations",
      "Days To OOS",
      "Actions",
    ];
    const productData = reactive({ value: [] });
    const productSuppliers = reactive({ value: [] });
    const productRooms = reactive({ value: {} });
    const modalProduct = reactive({ value: {} });
    const allShelfs = reactive({ value: {} });
    const salesPastWeek = reactive({ value: {} });
    const showModal = ref(false);
    const exportOptionValue = ref("");
    let pendingKeys = ref([]);
    let originalProduct = {};

    const filterProduct = ref("");
    const filterKey = ref("");
    const filterShelf = ref("");
    const filterSupplier = ref("");
    const filterViewSelected = ref("");

    const modalSubmit = (editData) => {
      let request = {};
      request["editProduct"] = editData;

      productData.value[editData.Key].Locations = editData.Locations;
      productData.value[editData.Key].Supplier = editData.Supplier;
      productSuppliers.value[editData.Supplier] = editData.Supplier;

      axiosPost(request);
      showModal.value = false;
    };

    const setProductOos = (key, state) => {
      let setOos = null;
      if (state === null) {
        setOos = "1";
      }

      let request = {};
      request["setProductOos"] = {
        key,
        setState: setOos,
      };

      axiosPost(request);
      productData.value[key]["outOfStock"] = setOos;
    };

    const editProduct = (key) => {
      originalProduct = Object.assign([], productData.value[key]);
      modalProduct.value = productData.value[key];
      showModal.value = true;
    };

    const productsExport = () => {
      let exportData = [];

      switch (exportOptionValue.value) {
        case "oosProducts":
          for (const value of Object.entries(productData.value)) {
            if (value[1].outOfStock == 1) {
              exportData[value[0]] = value[1];
            }
          }
          downloadCsv(exportData, "OutOfStockProducts");
          break;

        case "cisProducts":
          axiosGet("cisProducts").then((response) => {
            Object.keys(response).forEach((key) => {
              exportData[key] = productData.value[key];
            });
            downloadCsv(exportData, "comeIntoStock");
          });
          break;

        case "ddProducts":
          axiosGet("ddProducts").then((response) => {
            downloadCsv(response, "productsDueDelivery");
          });
          break;

        case "removeShelf":
          removeShelf();
          break;

        case "noShelfProducts":
          axiosGet("noShelfCsv").then((response) => {
            downloadCsv(response, "noShelfProcessedProducts");
          });
          break;

        default:
          alert("Not Valid Option");
          break;
      }

      showExport.value = false;
    };

    const modalCancel = (key) => {
      productData.value[key] = originalProduct;
      modalProduct.value = originalProduct;
      showModal.value = false;
    };

    const modalHide = (key, type) => {
      if (!confirm("Are You Sure ?")) return false;

      let setToBeHidden = "";
      if (type === "Hide") setToBeHidden = "y";

      let request = {};
      request["hideProduct"] = {
        productKey: key,
        toBeHidden: setToBeHidden,
      };

      axiosPost(request);
      productData.value[key]["to_be_hidden"] = setToBeHidden;
      showModal.value = false;
    };

    const filterView = computed(() => {
      if (
        !filterProduct.value &&
        !filterKey.value &&
        !filterShelf.value &&
        !filterSupplier.value &&
        !filterViewSelected.value
      ) {
        return Object.values(productData.value).filter((row) => {
          return row["to_be_hidden"] !== "y";
        });
      }

      return Object.values(productData.value).filter((row) => {
        if (
          filterProduct.value &&
          row["Product"]
            .toLowerCase()
            .indexOf(filterProduct.value.toLowerCase()) == -1
        ) {
          return false;
        }

        if (
          filterKey.value &&
          row["Key"].toLowerCase().indexOf(filterKey.value.toLowerCase()) == -1
        ) {
          return false;
        }

        if (filterSupplier.value && row["Supplier"] !== filterSupplier.value) {
          return false;
        }

        if (
          filterShelf.value &&
          !row["Locations"].includes(filterShelf.value)
        ) {
          return false;
        }

        // Filter view
        let filterValue = "";
        if (
          filterViewSelected.value &&
          filterViewSelected.value === "Days To OOS"
        ) {
          filterValue = "NO SALES";
        }

        if (
          filterViewSelected.value &&
          filterViewSelected.value === "outOfStock"
        )
          filterValue = 1;

        if (
          filterViewSelected.value &&
          filterViewSelected.value === "to_be_hidden"
        ) {
          filterValue = "y";
        }

        if (
          filterViewSelected.value &&
          row[filterViewSelected.value] != filterValue
        ) {
          return false;
        }

        if (!filterViewSelected.value && row["to_be_hidden"] == "y") {
          return false;
        }

        return row;
      });
    });

    const countProducts = computed(() => {
      return Object.keys(filterView.value).length;
    });

    // --------------------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Add product modal, properties and functionality
     */
    const modalAddProduct = reactive({ value: {} });
    const nextOrderNumber = ref(0);
    const activeOrderNumber = ref(0);
    const showAddModal = ref(false);

    const currentOrderNumber = computed(() => {
      if (!activeOrderNumber.value) return nextOrderNumber.value;

      return activeOrderNumber.value;
    });

    const addProduct = (key) => {
      modalAddProduct.value = productData.value[key];
      modalAddProduct.value.ord_num = currentOrderNumber;
      showAddModal.value = true;
    };

    const modalAddSubmit = (product) => {
      product["Item Cost"] = productItemCost(
        product.Qty,
        product.Multiple,
        productData.value[product.Key].product_cost
      );

      if (Object.keys(formOrderProducts.value).length > 0) {
        let firstProductKey = Object.keys(formOrderProducts.value)[0];
        let orderSupplier = formOrderProducts.value[firstProductKey].Supplier;
        if (product.Supplier !== orderSupplier) {
          return alert(
            "Supplier for the item you are trying does not match existing order number supplier! Only add items from the same supplier to existing order numbers."
          );
        }
      }

      formOrderProducts.value[product.Key] = Object.assign({}, product);
      showAddModal.value = false;
    };

    // --------------------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Order Form properties and functionality
     */
    const orderFormColumns = [
      "Product",
      "Supplier",
      "Qty",
      "Multiple",
      "Delivery Date",
      "Placed Date",
      "Order Number",
      "Key",
      "Item Cost",
      "Actions",
    ];
    const formOrderProducts = reactive({ value: {} });
    let showFormModal = ref(false);

    const formProductCount = computed(() => {
      return Object.keys(formOrderProducts.value).length;
    });

    const formTotal = computed(() => {
      let total = 0;
      for (const value of Object.entries(formOrderProducts.value)) {
        total += +value[1]["Item Cost"];
      }
      return total;
    });

    const submitOrderForm = () => {
      if (!confirm("Are You Sure ?")) return false;

      generateSupplierEmail();

      let request = {};
      request["submitOrder"] = formOrderProducts.value;
      axiosPost(request);
      formOrderProducts.value = {};
      nextOrderNumber.value++;
      showFormModal.value = false;
    };

    const modalFormEdit = (key) => {
      modalAddProduct.value = formOrderProducts.value[key];
      modalAddProduct.value.setDeliveryDate =
        formOrderProducts.value[key]["Delivery Date"];
      modalAddProduct.value.setPlacedDate =
        formOrderProducts.value[key]["Placed Date"];

      showAddModal.value = true;
    };

    const modalFormDelete = (key) => {
      if (!confirm("Are You Sure ?")) return false;
      delete formOrderProducts.value[key];
    };

    const clearOrderForm = () => {
      if (!confirm("Are You Sure ?")) return false;

      formOrderProducts.value = {};
    };

    // --------------------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Misc properties and functionality
     */
    const existingOrderNumbers = reactive({ value: {} });
    const showExisting = ref(false);
    const showExport = ref(false);

    function productItemCost(itemQty, itemMulti, productCost) {
      return (itemQty * itemMulti * productCost).toFixed(2);
    }

    function generateSupplierEmail() {
      let key = Object.keys(formOrderProducts.value)[0];
      let fileName =
        formOrderProducts.value[key].Supplier +
        "_" +
        formOrderProducts.value[key]["Order Number"] +
        ".txt";

      let content = "";
      content +=
        "Hello " +
        formOrderProducts.value[key].Supplier +
        ", I would like to order the following items listed below.\n";
      for (let i = 0; i < Object.keys(formOrderProducts.value).length; i++) {
        let curKey = Object.keys(formOrderProducts.value)[i];
        content +=
          "\nProduct: " +
          formOrderProducts.value[curKey].Product +
          " , Quantity: " +
          formOrderProducts.value[curKey].Qty +
          " , Multiple: " +
          formOrderProducts.value[curKey].Multiple +
          "\n";
      }
      content += "\nRegards, Elixir Gardens - Kevin";

      let element = document.createElement("a");
      element.setAttribute(
        "href",
        "data:text/plain;charset=utf-8," + encodeURIComponent(content)
      );
      element.setAttribute("download", fileName);
      element.style.display = "none";

      element.click();
    }

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

    const removeShelf = () => {
      let shelfToRemove = prompt("Enter Shelf To Remove From All Products");

      let regEx = /^\(?([A-Z]{1})\)?[-]?([0-9]{1,2})[-]?([0-9]{1,2})$/;

      // Check user input meets required format
      if (!regEx.test(shelfToRemove)) {
        return alert(
          "Incorrect format for shelf location, Format like the example below." +
            "A-1-2 or B-22-11"
        );
      }

      if (!shelfToRemove) return alert("Please Enter Value");

      let countOfRemovedShelfs = 0;
      Object.entries(productData.value).forEach((row) => {
        let typeLocations = typeof row[1].Locations;
        if (typeLocations === "object") {
          row[1]["Locations"].forEach((shelf, shelfIndx) => {
            if (shelf === shelfToRemove) {
              productData.value[row[0]]["Locations"].splice(shelfIndx, 1);
              countOfRemovedShelfs++;
            }
          });
        }
      });

      if (countOfRemovedShelfs === 0)
        return alert("No Shelf Positions Removed, Shelf Is Not Set !");

      let request = {};
      request["removeShelf"] = {
        shelfToRemove,
      };

      axiosPost(request);
    };

    // --------------------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * On view mount
     */
    onMounted(() => {
      axiosGet("viewProducts").then((response) => {
        productData.value = response.products;
        productSuppliers.value = response.suppliers;
        productRooms.value = response.rooms;
        pendingKeys.value = response.pendingKeys;
        nextOrderNumber.value = response.nextOrdNumber;
        existingOrderNumbers.value = response.existingOrderNumbers;
        allShelfs.value = response.allShelfs;
        salesPastWeek.value = response.pastWeekSales;
      });
    });

    return {
      productColumns,
      productData,
      productSuppliers,
      productRooms,
      pendingKeys,
      filterView,
      filterProduct,
      filterKey,
      filterShelf,
      filterSupplier,
      filterViewSelected,
      countProducts,
      orderFormColumns,
      formOrderProducts,
      showFormModal,
      formTotal,
      formProductCount,
      clearOrderForm,
      modalAddProduct,
      modalAddSubmit,
      showAddModal,
      addProduct,
      nextOrderNumber,
      activeOrderNumber,
      currentOrderNumber,
      showModal,
      modalSubmit,
      modalCancel,
      modalHide,
      editProduct,
      modalProduct,
      submitOrderForm,
      modalFormEdit,
      modalFormDelete,
      existingOrderNumbers,
      showExisting,
      showExport,
      setProductOos,
      exportOptionValue,
      productsExport,
      salesPastWeek,
    };
  },
};
</script>

<style>
h3 {
  text-align: center;
}

#clearForm {
  position: relative;
  top: 7.5%;
  float: right;
}

#productForm {
  position: relative;
  top: 7.5%;
  float: right;
}

#addToExisting {
  position: relative;
  top: 7.5%;
  float: right;
}

#activeProductsSpan {
  position: absolute;
  left: 0.5%;
  top: 4.75%;
  height: 5%;
}

#activeOrderNumber {
  position: absolute;
  left: 45%;
  top: 7.5%;
  width: 10%;
}

#existingOrdersDiv {
  position: relative;
  width: 10%;
  float: right;
}

#existingSelect {
  width: 50%;
}

#modalFormBox {
  position: relative;
  border-collapse: collapse;
  font-size: 0.9em;
  align-items: center;
  width: 60%;
  height: 66%;
  overflow: hidden;
  overflow-y: auto;
}

#exportBtn {
  position: relative;
  float: right;
}

#modalExport {
  display: flex;
  text-align: center;
  height: 150px;
  overflow: hidden;
}

#modalExportBtns {
  position: relative;
  right: 5%;
  top: 75%;
}

#exportOption {
  position: relative;
  left: 27%;
}

#exportTitle {
  position: relative;
  left: 7.5%;
}

#modalFormButtons {
  position: relative;
  display: flex;
  left: 50%;
  bottom: 5px;
  transform: translate(-7.5%);
  margin: 0 auto;
}

#tableFilters {
  position: relative;
  width: 40%;
  display: flex;
}

#tableFilters select,
#tableFilters input {
  max-width: 100px;
  margin-right: 5px;
}

#viewProductsTbl {
  display: block;
}

#viewProductsTbl thead {
  position: relative;
  display: block;
  width: 99%;
}

#viewProductsTbl tbody {
  position: relative;
  display: block;
  width: 100%;
  overflow-y: scroll;
  height: 77vh;
}

#viewProductsTbl td,
#viewProductsTbl th {
  flex-basis: 100%;
  display: block;
  text-align: center;
  vertical-align: middle;
  height: 100px;
}

#viewProductsTbl tr {
  display: flex;
  width: 100%;
}
</style>
