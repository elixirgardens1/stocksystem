<template>
  <table class="tblStyle">
    <thead>
      <tr>
        <th v-for="(column, index) in columns" :key="index">{{ column }}</th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="(value, index) in dataArr" :key="index">
        <td
          :id="value.Key + column"
          v-for="(column, columnIndex) in columns"
          :key="columnIndex"
          :class="getClass(column, value[column], value.Key)"
        >
          {{ value[column] }}
        </td>
      </tr>
    </tbody>
  </table>
</template>

<script>
module.exports = {
  props: {
    columns: {
      type: Object,
      required: true
    },
    dataArr: {
      type: Object,
      required: true
    },
    tblStyle: {
      type: String,
      required: false
    }
  },
  methods: {
    getClass(column, value) {
      if (column === "Status") {
        switch (value) {
          case "Pending":
            return (this.class = "blue");

          case "Cancelled":
            return (this.class = "red");

          case "Complete":
            return (this.class = "green");
        }
      }
    }
  }
};
</script>

<style>
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
  border: thin solid grey;
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
</style>
