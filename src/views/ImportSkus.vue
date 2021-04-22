<template>
  <div>
    <h1>Import Skus</h1>
  </div>

  <SelectList
    id="importSelectList"
    :list-items="skuData.value"
    list-type="product"
    list-key="product"
    @selectedKey="setSelectedKey"
  ></SelectList>

  <div id="skuForm" v-show="selectedKey !== ''">
    <form @submit.prevent="submitNewSku()">
      <input id="newSkuText" type="text" v-model="newSkuText" />
      <label id="labelNewSkuText" for="newSkuText">New Sku</label>
      <select id="newSkuAttKey" @change="addSkuAtt" v-model="additionalSkuAtts">
        <option disabled selected>Product Select To Build Atts</option>
        <option
          v-for="(item, index) in skuData.value"
          :key="index.Key"
          :value="item.key"
        >
          {{ item.product }}
        </option>
      </select>
      <select id="newSkuRoomSelect" v-model="newSkuRoom">
        <option disabled selected>Room</option>
        <option v-for="(room, index) in skuRooms" :key="index" :value="index">
          {{ room }}
        </option>
      </select>
      <input id="newSkuAttText" type="text" v-model="newSkuAtts" readonly />
      <input
        id="clearNewSkuAtts"
        class="navBtn"
        type="button"
        value="Clear Attributes"
        @click="clearAtts"
      />
      <input id="submitNewSku" class="navBtn" type="submit" value="Submit" />
    </form>
  </div>
</template>

<script>
import { onMounted, reactive, ref } from "vue";
import { axiosGet } from "@/composables/axiosGet.js";
import { axiosPost } from "@/composables/axiosPost.js";
import SelectList from "@/components/SelectList.vue";

export default {
  name: "ImportSkus",
  components: {
    SelectList
  },
  setup() {
    /**
     * Define properties and functionality of view
     */
    const skuData = reactive({ value: [] });
    const selectedKey = ref("");
    const newSkuText = ref("");
    const newSkuRoom = ref("Room");
    const newSkuAtts = ref("");
    const additionalSkuAtts = ref("");
    const skuRooms = ref([]);

    /**
     * Sets the reference to the value of the product key when an option is selected in the select list, will then ask the user to input an attribute qty
     * they wish to add to the new sku and will append this to the newSkuAtts reference
     */
    const setSelectedKey = key => {
      if (!key) return false;

      newSkuAtts.value = "";
      let qty = prompt("Enter Attribute Qty For This Key");

      if (!qty) return alert("Please Enter Qty To Import New Sku");

      newSkuAtts.value += `${key}|${qty}`;
      selectedKey.value = key;
    };

    /**
     * Submit the new sku the user has built to the back end to be inserted into the sku_atts and sku_room_lookup tables
     */
    const submitNewSku = () => {
      if (!newSkuText.value || !newSkuRoom.value)
        return alert("Please Complete Form Before Submitting !");

      if (newSkuRoom.value === "Room") return alert("Enter Valid Room !");

      let request = {};
      request["importSku"] = {
        sku: newSkuText.value,
        atts: newSkuAtts.value,
        room: newSkuRoom.value
      };

      axiosPost(request);
      newSkuText.value = "";
      newSkuRoom.value = "Room";
      selectedKey.value = "";
    };

    /**
     * Add aditional product keys to the newSkuAtts reference, function asks the user to enter a qty for the key they select from the product
     * select in the form
     */
    const addSkuAtt = () => {
      if (
        !additionalSkuAtts.value ||
        additionalSkuAtts.value === "Product Select To Build Atts"
      ) {
        return alert("Select Valid Product, Not The Default Option !");
      }

      let attQty = prompt("Enter Attribute Qty For This Key");
      if (!attQty) return alert("Please Enter Qty To Add Key To Sku Attribute");

      newSkuAtts.value += `,${additionalSkuAtts.value}|${attQty}`;
      additionalSkuAtts.value = "Product Seledt To Build";
    };

    /**
     * Clears the additional attributes, will leave the original attribute that was added
     */
    const clearAtts = () => {
      if (!confirm("Clear Attributes ?")) return false;

      if (newSkuAtts.value.includes(",")) {
        return (newSkuAtts.value = newSkuAtts.value.substr(
          0,
          newSkuAtts.value.indexOf(",")
        ));
      }

      return alert(
        "You Have Added No Additional Attributes, Nothing To Clear !"
      );
    };

    // -----------------------------------------------------------------------------------------------------------------------------------------------------------
    /**
     * On view mount
     */
    onMounted(() => {
      axiosGet("updateProducts").then(response => {
        skuData.value = response.keyProducts;
        skuRooms.value = response.rooms;
      });
    });

    return {
      skuData,
      selectedKey,
      newSkuText,
      newSkuRoom,
      newSkuAtts,
      additionalSkuAtts,
      skuRooms,
      setSelectedKey,
      submitNewSku,
      addSkuAtt,
      clearAtts
    };
  }
};
</script>

<style scoped>
h1 {
  top: 2.5%;
  left: 0.5%;
}

#skuForm {
  position: absolute;
  top: 10%;
  left: 50%;
  height: 50%;
  width: 50%;
}

#labelNewSkuText {
  position: relative;
  margin-right: 5px;
  float: left;
}

#newSkuAttKey {
  position: absolute;
  left: 25%;
  width: 200px;
}

#newSkuAttText {
  position: absolute;
  width: 195px;
  left: 25%;
  top: 10%;
}

#submitNewSku {
  position: absolute;
  left: 32.5%;
  left: 32%;
  top: 20%;
}

#clearNewSkuAtts {
  position: absolute;
  top: 9.5%;
  left: 47.5%;
}

#newSkuRoomSelect {
  position: absolute;
  left: 47.5%;
  width: 100px;
}

#importSelectList {
  position: absolute;
  float: left;
  top: 10%;
  height: 90%;
}
</style>
