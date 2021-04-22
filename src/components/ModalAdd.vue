<template>
  <div id="modalBg" class="modalBg">
    <div id="modalAdd" class="modalBox">
      <form
        id="modalForm"
        ref="addForm"
        method="post"
        @submit.prevent="modalSubmit()"
      >
        <h1>Order Details</h1>
        <input
          id="modalProduct"
          type="text"
          :value="product['Product']"
          readonly
        />

        <label id="supplierLabel" for="modalSupplier">Supplier</label>
        <select
          id="modalSupplier"
          ref="formSupplier"
          :value="product['Supplier']"
        >
          <option v-for="(index, supplier) in suppliers" :key="index">{{
            supplier
          }}</option>
        </select>

        <label id="ordNumLabel" for="modalOrdNum">Order Number</label>
        <input
          id="modalOrdNum"
          ref="formOrderNumber"
          type="text"
          :value="product['ord_num'] || product['Order Number']"
          readonly
        />

        <div id="modalQtyDiv">
          <input
            v-if="!product['Status']"
            id="modalQty"
            type="numeric"
            :value="product['pkg_qty'] || product['Qty']"
            ref="ref_qty"
            required
          />
          <input
            v-else-if="product['Status']"
            id="modalQty"
            type="numeric"
            :value="product['Qty']"
            ref="ref_qty"
            required
          />
          <span
            v-show="!product['Status']"
            style="position: absolute; left: 85%; width: 50%;"
          >
            {{ product["Unit"] }} X Qty
          </span>
          <span
            v-show="product['Status']"
            style="position: absolute; left:85%; width: 50%;"
          >
            X Qty
          </span>
        </div>

        <select
          id="modalShelf"
          ref="refShelf"
          v-model="shelfSelected"
          v-show="product['Status']"
        >
          <option selected disabled>Shelf</option>
          <option v-for="(shelf, index) in newShelfArr" :key="index">{{
            shelf
          }}</option>
        </select>

        <div id="shelfActions" v-show="product['Status']">
          <input
            id="shelfText"
            type="text"
            placeholder="Add/Edit Shelf"
            v-if="visible"
            v-model="userShelfInput"
          />
          <input
            id="shelfAdd"
            type="button"
            value="Add"
            @click="shelfAdd()"
            v-if="visible === false"
          />
          <input
            id="shelfEdit"
            type="button"
            value="Edit"
            @click="shelfEdit()"
            v-if="visible === false"
          />
          <input
            id="shelfDelete"
            type="button"
            value="Delete"
            @click="shelfDelete()"
            v-if="visible === false"
          />
          <input
            id="submitAction"
            type="button"
            value="Submit"
            @click="shelfSubmit()"
            v-if="visible"
          />
          <input
            id="shelfCancel"
            type="button"
            value="Cancel"
            @click="shelfCancel()"
            v-if="visible"
          />
        </div>

        <input
          v-show="!product['Status']"
          id="modalMultiples"
          type="numeric"
          :value="product['pkg_multiples'] || product['Multiple']"
          ref="ref_multi"
          required
        />

        <div id="modalDeliveryDiv">
          <input id="modalDelivery" type="date" ref="deliveryDate" required />
          Delivery Date
        </div>

        <div id="modalPlacedDiv">
          <input id="modalPlaced" type="date" ref="placedDate" required /> Date
          Placed
        </div>

        <div id="modalButtons">
          <input id="modalSubmit" class="navBtn" type="submit" value="Submit" />
          <input
            id="modalCancel"
            type="button"
            class="navBtn"
            value="Cancel"
            @click="
              $emit('modal-cancel');
              newShelfArr = product['newShelf'];
            "
          />
          <input
            id="modalSplit"
            class="navBtn"
            type="button"
            value="Split"
            v-show="product['Status']"
            @click="$emit('split-product-order')"
          />
        </div>
      </form>
    </div>
  </div>
</template>

<script>
export default {
  name: "ModalAdd",
  props: {
    product: {
      type: Object,
      required: true,
    },
    suppliers: {
      type: Object,
      required: true,
    },
  },
  emits: ["modal-add-submit", "modal-cancel", "split-product-order"],
  data() {
    return {
      postArr: {},
      visible: false,
      shelfSelected: "Shelf",
      userShelfInput: "",
      newShelfArr: [],
    };
  },
  methods: {
    modalSubmit() {
      // Assign required fields to postArr to be returned to parent
      this.postArr["Key"] = this.product["Key"];
      this.postArr["Product"] = this.product["Product"];
      this.postArr["Supplier"] = this.$refs.formSupplier.value;
      this.postArr["Order Number"] = this.$refs.formOrderNumber.value;
      this.postArr["Qty"] = this.$refs.ref_qty.value;
      this.postArr["Multiple"] = this.$refs.ref_multi.value;
      this.postArr["Delivery Date"] = this.$refs.deliveryDate.value;
      this.postArr["Placed Date"] = this.$refs.placedDate.value;
      this.postArr["newShelf"] = this.newShelfArr;

      // Return array to parent
      this.$emit("modal-add-submit", this.postArr);
    },
    shelfAdd() {
      this.visible = true;
      this.currentAction = "add";
    },
    shelfEdit() {
      this.visible = true;
      this.currentAction = "edit";
    },
    shelfDelete() {
      let selected = this.shelfSelected;

      // Check valid selection, if so delete the position from the array
      if (selected !== null) {
        if (confirm("Are You Sure ?")) {
          let index = this.newShelfArr.indexOf(selected);
          this.newShelfArr.splice(index, 1);
          this.visible = false;
        }
      } else {
        alert("Not a valid shelf");
      }
    },
    shelfSubmit() {
      // If add action is submitted
      if (this.currentAction === "add") {
        let shelfInput = this.userShelfInput;

        // Check not equal to exceptions
        if (!["Floor", "Pick", "Bulk"].includes(shelfInput)) {
          if (!this.shelfTest(shelfInput)) return false;
        }

        // Append to array, might have to define array if it was previously unset
        if (this.newShelfArr && this.newShelfArr.length > 0) {
          this.newShelfArr.push(shelfInput);
          this.userShelfInput = "";
        } else {
          this.newShelfArr = [];
          this.newShelfArr.push(shelfInput);
          this.userShelfInput = "";
        }
        this.visible = false;
      }
      // If Edit action is submitted
      else if (this.currentAction === "edit") {
        let selected = this.shelfSelected;

        // Check valid option is selected, if so get index of this value in array and update it
        if (selected && selected !== "Shelf") {
          if (!this.shelfTest(this.userShelfInput)) return false;
          let index = this.newShelfArr.indexOf(selected);
          this.newShelfArr[index] = this.userShelfInput;
          // Set use input back to blank
          this.userShelfInput = "";
          this.visible = false;
        } else {
          alert("Not a valid shelf");
        }
      }
    },
    shelfCancel() {
      this.visible = false;
    },
    shelfTest(testInput) {
      let regEx = /^\(?([A-Z]{1})\)?[-]?([0-9]{1,2})[-]?([0-9]{1,2})$/;
      if (!regEx.test(testInput)) {
        return alert(
          "Incorrect format for shelf location, Format like the example below." +
            "A-1-2 or B-22-11"
        );
      }
      return true;
    },
  },
  mounted() {
    // Set default date of the placed date as today
    var date = new Date(),
      month = "" + (date.getMonth() + 1),
      day = "" + date.getDate(),
      year = date.getFullYear();

    if (month.length < 2) month = "0" + month;

    if (day.length < 2) day = "0" + day;
    date = [year, month, day].join("-");

    this.$refs.placedDate.value = date;
  },
  watch: {
    product: {
      handler() {
        if (this.product.setDeliveryDate !== undefined) {
          this.$refs.deliveryDate.value = this.product.setDeliveryDate;
        }

        if (this.product.setPlacedDate !== undefined) {
          this.$refs.placedDate.value = this.product.setPlacedDate;
        }

        // Append currently saved shelf postions to the newShelf array
        if (this.product["newShelf"]) {
          this.newShelfArr = JSON.parse(
            JSON.stringify(this.product["newShelf"])
          );
        }
        // Append selected value as shelf when new product is opened in modal
        this.shelfSelected = "Shelf";
      },
      deep: true,
    },
  },
};
</script>

<style scoped>
h1 {
  position: absolute;
  text-align: left;
  top: 0;
}

#modalProduct {
  position: absolute;
  left: 2.5%;
  width: 66%;
  height: 5%;
  top: 15%;
}

#modalSupplier {
  position: absolute;
  left: 2.5%;
  width: 20%;
  top: 25%;
}

#modalOrdNum {
  position: absolute;
  left: 2.5%;
  width: 20%;
  top: 35%;
}

#modalQtyDiv {
  position: absolute;
  left: 2.5%;
  width: 25%;
  top: 45%;
}

#modalQty {
  position: absolute;
  left: 0;
  width: 80%;
  top: 45%;
}

#newShelf {
  width: 50%;
  margin-right: 5px;
}

#modalShelfDiv {
  position: absolute;
  width: 40%;
  left: 2.5%;
  top: 55%;
}

#modalMultiples {
  position: absolute;
  width: 20%;
  top: 45%;
  left: 37.5%;
}

#modalDeliveryDiv {
  position: absolute;
  top: 65%;
  left: 2.5%;
}

#modalDelivery {
  margin-right: 10px;
}

#modalPlacedDiv {
  position: absolute;
  top: 75%;
  left: 2.5%;
}

#modalPlaced {
  margin-right: 10px;
}

#ordNumLabel {
  position: absolute;
  left: 25%;
  top: 35%;
}

#modalSplit {
  position: absolute;
  left: 33%;
  top: 100%;
}

#modalButtons {
  position: absolute;
  display: flex;
  left: 50%;
  top: 85%;
  transform: translate(-50%, -50%);
  margin: 0 auto;
}
</style>
