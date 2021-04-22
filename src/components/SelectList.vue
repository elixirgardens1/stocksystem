<template>
  <div>
    <input
      type="text"
      :placeholder="filterMsg"
      v-model="productFilter"
      v-show="listKey"
    />
    <select
      id="selectList"
      size="39"
      v-model="selectedKey"
      @change="$emit('selectedKey', emitSelected())"
    >
      <option
        v-for="(item, index) in filterList"
        :key="index.Key ? index.Key : index[listKey]"
        :value="item"
      >
        {{ item[listType] ? item[listType] : item }}
      </option>
    </select>
  </div>
</template>

<script>
export default {
  props: {
    listItems: {
      type: Object,
      required: true
    },
    listKey: {
      type: String,
      required: false
    },
    listType: {
      type: String,
      required: false
    }
  },
  emits: ["selectedKey"],
  data() {
    return {
      productFilter: "",
      selectedKey: "",
      filterMsg: `Filter By ${this.listType}`,
    };
  },
  computed: {
    filterList() {
      if (!this.productFilter) return this.listItems;

      let filtered = {};
      for (const value of Object.entries(this.listItems)) {
        if (
          value[1][this.listKey]
            .toLowerCase()
            .indexOf(this.productFilter.toLowerCase()) != -1
        ) {
          filtered[value[0]] = value[1];
        }
      }
      return filtered;
    }
  },
  methods: {
    emitSelected() {
      if (this.selectedKey.key) return this.selectedKey.key;

      if (this.listKey) {
        return this.selectedKey[this.listKey];
      } else return this.selectedKey;
    }
  }
};
</script>

<style scoped>
div {
  width: 66%;
}
#selectList {
  position: absolute;
  top: 4%;
  left: 0;
  width: 40%;
  height: 90%;
}

option {
  display: list-item;
}
</style>
