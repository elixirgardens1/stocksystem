<template>
  <div>
    <table class="table-scroll small-first-col">
      <thead>
        <tr>
          <th
            v-for="(column, index) in tableColumns"
            :key="index"
            @click="columnFilter"
          >
            {{ column }}
          </th>
        </tr>
      </thead>
      <tbody class="body-half-screen">
        <tr v-for="(value, index) in tableData" :key="index">
          <td v-for="(column, columnIndex) in tableColumns" :key="columnIndex">
            <p v-if="column !== 'Product' && column !== 'Qty'">
              {{ value[column] }}
            </p>

            <p v-if="column === 'Qty'">{{ value[column] }} {{ value.unit }}</p>

            <router-link v-if="column === 'Product'" :to="`/${value.Key}`">
              {{ value[column] }}
            </router-link>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script>
export default {
  props: {
    tableColumns: {
      type: Array,
      required: true,
    },
    tableData: {
      type: Object,
      required: true,
    },
  },
  data() {
    return {
      selectedColumn: "",
      filterType: "",
    };
  },
  methods: {
    columnFilter: function(event) {
      // Will depend on browser
      let elementText = "";
      let browser = "";
      let classList = [];

      // Check which browser the user is on
      if (navigator.userAgent.indexOf("Chrome") !== -1) {
        elementText = event.srcElement.innerText;
        browser = "Chrome";
        classList = event.srcElement.classList;
      } else {
        elementText = event.explicitOriginalTarget.textContent;
        browser = "Mozilla";
        classList = event.explicitOriginalTarget.classList;
      }

      // If a new column has been clicked to be filtered, remove css class from other headers
      if (elementText !== this.selectedColumn) {
        document
          .querySelectorAll(".activeDesc")
          .forEach((el) => el.classList.remove("activeDesc"));

        document
          .querySelectorAll(".activeAsc")
          .forEach((el) => el.classList.remove("activeAsc"));
      }

      // If no classes have been appended, append desc arrow to header
      if (classList.length === 0) {
        if (browser === "Chrome") {
          event.srcElement.classList = "activeDesc";
        } else {
          event.explicitOriginalTarget.classList = "activeDesc";
        }

        this.filterType = "Desc";
      }

      // Else if header already has the desc class, append the asc class
      else if (classList.value === "activeDesc") {
        if (browser === "Chrome") {
          event.srcElement.classList = "activeAsc";
        } else {
          event.explicitOriginalTarget.classList = "activeAsc";
        }

        this.filterType = "Asc";
      }

      // Else remove all classes form the header, indicating there is no filtering on this table column
      else {
        if (browser === "Chrome") {
          event.srcElement.classList = [];
        } else {
          event.explicitOriginalTarget.classList = [];
        }

        this.filterType = "";
      }

      // Return the filter type and column to the parent view
      this.selectedColumn = elementText;
      this.$emit("filter-column", this.selectedColumn, this.filterType);
    },
  },
};
</script>

<style scoped>
.table-scroll {
  display: block;
  empty-cells: show;
  border-radius: 8px;

  /* Decoration */
  border: solid black;
}

.table-scroll thead {
  background-color: black;
  color: white;
  position: relative;
  height: 50px;
  display: block;
  width: 100%;
  overflow-y: scroll;
}

.table-scroll tbody {
  /* Position */
  display: block;
  position: relative;
  width: 100%;
  overflow-y: scroll;
  /* Decoration */
  border-top: 1px solid rgba(0, 0, 0, 0.2);
}

.table-scroll tr {
  width: 100%;
  display: flex;
}

.table-scroll td,
.table-scroll th {
  flex-basis: 100%;
  flex-grow: 2;
  text-align: center;
  display: block;
  width: 75px;
  padding: 0.5rem;
}

.table-scroll th {
  -moz-user-select: none;
  -webkit-user-select: none;
  -ms-user-select: none;
  user-select: none;
}

th:hover {
  background: rgba(130, 130, 170, 0.66);
  color: #5bf;
}

.activeDesc:after {
  color: #5bf;
  content: " \25bc";
  font-family: "Segoe UI Symbol";
}

.activeAsc:after {
  color: #5bf;
  content: " \25b2";
  font-family: "Segoe UI Symbol";
}

.table-scroll td {
  border-bottom: 0.5px solid grey;
}

.table-scroll tbody tr:nth-child(2n) {
  background-color: rgba(130, 130, 170, 0.1);
}

.body-half-screen {
  max-height: 65vh;
}

.small-col {
  flex-basis: 10%;
}

.table-scroll td:nth-child(20),
.table-scroll td:nth-child(16),
.table-scroll td:nth-child(4) {
  border-left: thin solid black;
}
</style>
