<template>
  <table class="tblStyle">
    <thead>
      <tr>
        <th v-for="(column, index) in columns" :key="index">{{ column }}</th>
      </tr>
    </thead>
    <tbody>
      <tr
        v-for="(value, index) in dataArr"
        :key="index"
        :style="[value.outOfStock ? 'background: #FF4D4D' : '']"
      >
        <td
          :id="column"
          v-for="(column, columnIndex) in columns"
          :key="columnIndex"
          :class="
            column === 'Days To OOS'
              ? getClass(
                  column,
                  value[column],
                  value.Key,
                  value.yellowThreshold,
                  value.redThreshold
                )
              : ''
          "
        >
          <router-link v-if="column === 'Product'" :to="`/${value.Key}`">
            <p>{{ value[column] }}</p>
          </router-link>

          <p
            v-if="
              column !== 'Locations' &&
                column !== 'Product' &&
                column !== 'Days To OOS'
            "
          >
            {{ value[column] }}
          </p>

          <p
            id="dtoText"
            v-if="column === 'Days To OOS'"
            @mouseover="hovering[value.Key] = true"
            @mouseleave="hovering[value.Key] = false"
          >
            {{ hovering[value.Key] ? salesPastWeek[value.Key] : value[column] }}
          </p>

          <div v-if="column === 'Locations'">
            <select v-if="value[column].length > 0" v-model="shelfSelected">
              <option selected disabled>Shelf</option>
              <option v-for="(shelf, index) in value[column]" :key="index">
                {{ shelf }}
              </option>
            </select>
          </div>
          <input
            id="showItemsBtn"
            class="navBtn"
            type="button"
            value="Show Items"
            v-if="column === 'Show Items'"
            @click="$emit('order-number-items', value['Order Number'])"
          />
          <div id="modalActionBtns" v-if="column === 'Actions'">
            <div id="oosDiv" v-if="actions === 'viewProducts'">
              Out Of Stock
              <input
                id="oosCheckBox"
                type="checkbox"
                :checked="value.outOfStock"
                @change="$emit('oos-product', value.Key, value.outOfStock)"
              />
            </div>
            <input
              v-show="actions === 'pendingOrders'"
              @click="
                $emit('process-product', value.Key, value['Order Number'])
              "
              id="viewAction"
              type="button"
              class="actionBtn navBtn"
              value="Process"
            />
            <input
              @click="$emit('edit-product', value.Key, value['Order Number'])"
              id="editAction"
              type="button"
              class="actionBtn navBtn"
              value="Edit"
            />
            <input
              v-show="actions === 'viewProducts'"
              @click="$emit('add-product', value.Key)"
              id="addAction"
              type="button"
              class="actionBtn navBtn"
              value="Add"
            />
            <input
              v-show="actions === 'pendingOrders' || actions === 'formProducts'"
              @click="$emit('delete-product', value.Key, value['Order Number'])"
              id="deleteAction"
              type="button"
              class="actionBtn navBtn"
              value="Delete"
            />
          </div>
        </td>
      </tr>
    </tbody>
  </table>
</template>

<script>
export default {
  name: "viewStockComponent",
  props: {
    columns: {
      type: Array,
      required: true,
    },
    dataArr: {
      type: Object,
      required: true,
    },
    actions: {
      type: String,
      required: false,
    },
    pendingKeys: {
      type: Object,
      required: false,
    },
    salesPastWeek: {
      type: Object,
      required: false,
    },
  },
  data() {
    return {
      shelfSelected: "Shelf",
      originalValue: "",
      hovering: [],
    };
  },
  emits: [
    "edit-product",
    "add-product",
    "process-product",
    "delete-product",
    "order-number-items",
    "oos-product",
  ],
  methods: {
    // Set color of td of days to oos based on the value
    getClass(column, value, key, yellowThreshold, redThreshold) {
      if (column === "Days To OOS") {
        if (key in this.pendingKeys) {
          return (this.class = "blue");
        }
        switch (true) {
          case value <= redThreshold:
            return (this.class = "red");

          case value <= yellowThreshold:
            return (this.class = "yellow");

          case value >= yellowThreshold:
            return (this.class = "green");

          case value === "NO SALES":
            return (this.class = "grey");

          default:
            break;
        }
      } else if (column === "Status") {
        switch (value) {
          case "Pending":
            return (this.class = "blue");

          case "Cancelled":
            return (this.class = "red");

          case "Complete":
            return (this.class = "green");
        }
      }
    },
  },
};
</script>

<style scoped>
th {
  text-align: center;
}

td {
  text-align: center;
}

select {
  width: 75%;
}

.tblStyle {
  width: 100%;
  border-collapse: collapse;
  margin: 25px 0;
  font-size: 0.9em;
  font-family: sans-serif;
  min-width: 400px;
  box-shadow: 0 0 20px rgba(0, 0, 0, 0.15);
}

.tblStyle thead tr {
  height: 50px;
  background-color: #009879;
  color: #ffffff;
  text-align: left;
}

.tblStyle th,
.tblStyle td {
  height: 75px;
  padding: 10px 10px;
}

.tblStyle tbody tr {
  border-bottom: thin solid #dddddd;
}

.tblStyle tbody tr:nth-of-type(even) {
  background-color: #f3f3f3;
}

.tblStyle tbody tr:last-of-type {
  border-bottom: 2px solid #009879;
}

.tblStyle tbody tr.active-row {
  font-weight: bold;
  color: #009879;
}

#showItemsBtn {
  display: block;
  text-align: center;
  margin: auto;
  transform: translate(10%, -25%);
}

#oosDiv {
  margin-bottom: 5px;
}

.actionBtn {
  position: relative;
  display: flex;
  margin: 0 auto;
  width: 66%;
  margin-bottom: 5px;
  height: 100%;
  transform: translate(5%, -15%);
}

#dtoText {
  height: 90%;
  width: 100%;
}
</style>
