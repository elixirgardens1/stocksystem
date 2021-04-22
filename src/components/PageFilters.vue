<template>
  <div>
    <input
      id="filterProduct"
      ref="refProductFilter"
      type="text"
      placeholder="Filter By Product"
      @keyup="tableFilter('Product', 'refProductFilter')"
    />
    <select
      id="filterSupplier"
      ref="refSupplierFilter"
      @change="tableFilter('Supplier', 'refSupplierFilter')"
    >
      <option value="" disabled selected>Filter Supplier</option>
      <option value="">No Supplier Filter</option>
      <option v-for="(index, supplier) in suppliers" :key="supplier">{{
        supplier
      }}</option>
    </select>
  </div>
</template>

<script>
export default {
  name: "PageFilters",
  props: {
    suppliers: {
      type: Object,
      required: true,
    },
  },
  methods: {
    // Filter the current parent based on the currently mounted parent component, pass type of column to filter and the ref to get the input to compare against
    tableFilter(type, ref) {
      // Get the current parent component
      let elementToFilter = this.getCurrentParent();

      let input = this.$refs[ref].value.toLowerCase();
      let tr = elementToFilter.getElementsByTagName("tr");

      let td, txtValue;

      // Loop element, getting the td child of each tr where the id is product and comparing value to input
      for (let i = 0; i < tr.length; i++) {
        td = tr[i].cells[type];
        if (td) {
          txtValue = td.textContent || td.innerText;
          if (txtValue.toLowerCase().indexOf(input) > -1) {
            tr[i].style.display = "";
          } else {
            tr[i].style.display = "none";
          }
        }
      }
    },

    getCurrentParent() {
      // Get which element to filter by checking which refs are set in the $refs of the $parent element
      switch (true) {
        case this.$parent.$refs.viewProductsTbl !== undefined:
          return this.$parent.$refs.viewProductsTbl.$el;

        case this.$parent.$refs.pendingTbl !== undefined:
          return this.$parent.$refs.pendingTbl.$el;

        default:
          return false;
      }
    },
  },
};
</script>

<style scoped>
div {
  float: left;
  position: relative;
  margin-bottom: 12.5px;
}

select {
  width: 130px;
  margin-left: 5px;
}
</style>
