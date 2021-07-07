<template>
  <div id="modalBg" class="modalBg">
    <div id="modalBox" class="modalBox">
      <form
        id="modalForm"
        ref="postForm"
        method="post"
        v-if="(postArr.Key = product['Key'])"
        @submit.prevent="modalSubmit()"
      >
        <h1>Product Data</h1>
        <input
          id="modalProduct"
          ref="refProduct"
          type="text"
          v-model.lazy="product['Product']"
        />

        <label id="supplierLabel" for="modalSupplier">Supplier</label>
        <div id="modalSupplierDiv" v-show="showNewSupplier === false">
          <select
            id="modalSupplier"
            ref="refSupplier"
            v-model="product['Supplier']"
          >
            <option v-for="(index, supplier) in suppliers" :key="index">{{
              supplier
            }}</option>
          </select>
        </div>
        <div id="newSupplierDiv">
          <input
            id="newSupplierText"
            style="margin-right: 5px;"
            type="text"
            v-model="supplierText"
            v-show="showNewSupplier"
          />
          <input
            id="newSupplierBtn"
            type="button"
            value="New Supplier"
            v-show="!showNewSupplier"
            @click="showNewSupplier = true"
          />
          <input
            id="newSupplierCancel"
            type="button"
            value="Cancel"
            v-show="showNewSupplier"
            @click="showNewSupplier = false"
          />
        </div>

        <label id="costLabel" for="modalCost">Cost [ Last Edit: {{ this.product.previousCostDate }} | Previous Cost: {{ this.product.previousCost }} ]</label>
        <div id="modalCostDiv">
          <input
            id="modalCost"
            ref="refCost"
            type="numeric"
            v-model="product['product_cost']"
          />
        </div>

        <div id="modalRoomDiv">
          <label id="roomLabel">Room</label>
          <select
            id="modalRoom"
            v-model="product['Room']"
            ref="refRoom"
            required
          >
            <option
              v-for="(room, index) in rooms"
              :key="index"
              :selected="room === product['Room']"
              >{{ room }}</option
            >
          </select>
        </div>

        <select id="modalShelf" ref="refShelf" v-model="shelfSelected">
          <option selected disabled>Shelf</option>
          <option
            v-for="(shelf, index) in this.postArr['Locations']"
            :key="index"
            >{{ shelf }}</option
          >
        </select>

        <div id="shelfActions">
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

        <div id="thresholdDiv">
          Warning
          <input
            ref="refYellowThresh"
            id="yellowThreshold"
            type="number"
            v-model.lazy="product['yellowThreshold']"
          />
          Danger
          <input
            ref="refRedThresh"
            id="redThreshold"
            type="number"
            v-model.lazy="product['redThreshold']"
          />
        </div>

        <div id="modalButtons">
          <input id="modalSubmit" class="navBtn" type="submit" value="Submit" />
          <input
            id="modalCancel"
            type="button"
            class="navBtn"
            value="Cancel"
            @click="$emit('modal-cancel', this.product.Key)"
          />
          <input
            id="modalHideProd"
            type="button"
            class="navBtn"
            :value="!this.product.to_be_hidden ? 'Hide' : 'Show'"
            @click="
              $emit(
                'modal-hide',
                this.product.Key,
                !this.product.to_be_hidden ? 'Hide' : 'Show'
              )
            "
          />
        </div>
      </form>
    </div>
  </div>
</template>

<script>
module.exports = {
  props: {
    product: {
      type: Object,
      required: true,
    },
    suppliers: {
      type: Object,
      required: true,
    },
    rooms: {
      type: Object,
      required: true,
    },
  },
  emits: ["modal-submit", "modal-cancel", "modal-hide"],
  data() {
    return {
      postArr: {
        Key: "",
        Product: "",
        Supplier: "",
        product_cost: "",
        Room: "",
        Locations: [],
        yellowThreshold: "",
        redThreshold: "",
      },
      visible: false,
      submit: false,
      showNewSupplier: false,
      supplierText: "",
      userShelfInput: "",
      shelfSelected: "Shelf",
      currentAction: "",
    };
  },
  watch: {
    product: {
      handler() {
        this.postArr.Locations = JSON.parse(
          JSON.stringify(this.product["Locations"])
        );
      },
    },
  },  
  methods: {
    modalSubmit() {
      // Using refs to the elements get the values and assign them to the array that will be passed back to parent
      this.postArr.Product = this.$refs.refProduct.value;
      this.postArr.Supplier = this.supplierText
        ? this.supplierText
        : this.$refs.refSupplier.value;
      this.postArr.product_cost = this.$refs.refCost.value;
      this.postArr.Room = this.$refs.refRoom.value;

      this.postArr.yellowThreshold = this.$refs.refYellowThresh.value;
      this.postArr.redThreshold = this.$refs.refRedThresh.value;

      this.showNewSupplier = false;

      this.$emit("modal-submit", JSON.parse(JSON.stringify(this.postArr)));
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
          let index = this.postArr.Locations.indexOf(selected);
          this.postArr.Locations.splice(index, 1);
          this.visible = false;
        }
      } else {
        alert("Not a valid shelf");
      }
    },
    shelfSubmit() {
      // If add action is submitted
      if (this.currentAction === "add") {
        let newShelf = this.userShelfInput;

        // Check user input meets required format
        if (!["Floor", "Pick", "Bulk"].includes(newShelf)) {
          if (!this.shelfTest(newShelf)) return false;
        }

        // Append to array, might have to define array if it was previously unset
        if (this.postArr.Locations.length > 0) {
          this.postArr.Locations.push(newShelf);
          this.userShelfInput = "";
        } else {
          this.postArr.Locations = [];
          this.postArr.Locations.push(newShelf);
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
          let index = this.postArr.Locations.indexOf(selected);
          this.postArr.Locations[index] = this.userShelfInput;
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
};
</script>

<style>
h1 {
  position: absolute;
  top: 0;
  left: 2.5%;
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

#modalCost {
  width: 100%;
}

#modalCostDiv {
  position: absolute;
  left: 2.5%;
  width: 17%;
  top: 35%;
}

#modalRoom {
  position: absolute;
  width: 20%;
  left: 2.5%;
  top: 45%;
}

#modalShelf {
  position: absolute;
  display: flex;
  left: 2.5%;
  width: 20%;
  top: 55%;
}

#shelfActions {
  position: absolute;
  display: inline;
  top: 55%;
  left: 25%;
}

#supplierLabel {
  position: absolute;
  left: 25%;
  top: 25%;
}

#costLabel {
  position: absolute;
  left: 25%;
  top: 35%;
}

#roomLabel {
  position: absolute;
  left: 25%;
  top: 45%;
}

#newSupplierDiv {
  position: absolute;
  left: 37.5%;
  top: 25%;
}

#modalButtons {
  position: absolute;
  display: flex;
  left: 50%;
  top: 85%;
  transform: translate(-50%, -50%);
  margin: 0 auto;
}

#thresholdDiv {
  position: absolute;
  display: flex;
  left: 2.5%;
  top: 65%;
}

#yellowThreshold {
  width: 15%;
  margin-right: 5px;
}

#redThreshold {
  width: 15%;
}
</style>
