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
            <p v-if="column !== 'Product'">{{ value[column] }}</p>

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
      // If the selected column changes remove the filter arrows from the other column
      if (event.explicitOriginalTarget.textContent !== this.selectedColumn) {
        document
          .querySelectorAll(".activeDesc")
          .forEach((el) => el.classList.remove("activeDesc"));

        document
          .querySelectorAll(".activeAsc")
          .forEach((el) => el.classList.remove("activeAsc"));
      }

      // Add class depending on the values currently in the classList, if none add desc arrow, if desc arrow add asc arrow, if asc arrow reset class
      if (event.explicitOriginalTarget.classList.length === 0) {
        event.explicitOriginalTarget.classList = "activeDesc";
        this.filterType = "Desc";
      } else if (
        event.explicitOriginalTarget.classList.value === "activeDesc"
      ) {
        event.explicitOriginalTarget.classList = "activeAsc";
        this.filterType = "Asc";
      } else {
        event.explicitOriginalTarget.classList = [];
        this.filterType = "";
      }

      this.selectedColumn = event.explicitOriginalTarget.textContent;
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
  padding: 0.75rem;
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
}

.activeAsc:after {
  color: #5bf;
  content: " \25b2";
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
</style>
