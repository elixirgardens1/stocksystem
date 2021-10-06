<template>
  <div>
    <h1>Platform Skus</h1>
    <input
      id="editModeBtn"
      type="button"
      class="navBtn"
      value="Insert / Edit Mode"
      @click="showEditMode = !showEditMode"
    />
  </div>

  <div v-show="!showEditMode">
    <SelectList
      id="productList"
      :list-items="skuProducts.value"
      list-type="product"
      list-key="product"
      @selectedKey="setSelectedKey"
    ></SelectList>

    <SelectList
      id="skuList"
      :list-items="selectedKey.value"
      list-type="sku"
      list-key="sku"
      @selectedKey="setSelectedSku"
      v-show="showSkuList"
    ></SelectList>

    <SelectList
      id="platIdList"
      :list-items="selectedSku.value"
      @selectedKey="openPlatformid"
      v-show="showPlatIdList"
    ></SelectList>
  </div>

  <div v-show="showEditMode">
    <div id="leftSideDiv">
      <h2>Edit Platform Sku</h2>
      <div id="skuSearchDiv">
        <input
          id="editModeSearch"
          type="text"
          placeholder="Enter Sku"
          v-model.lazy="skuSearchInput"
        />
        <input
          id="skuSearchBtn"
          type="button"
          class="navBtn"
          value="Search"
          @click="skuSearch"
        />
      </div>

      <div id="skuSearchResults">
        <h3>{{ skuSearchTitle }}</h3>
        <div id="platformUrlsDiv" v-show="showSearch">
          <label
            for="amazonSkuUrl"
            :style="[!amazonSkuUrl ? { color: 'red' } : '']"
            >Amazon Sku Url</label
          >
          <input
            id="amazonSkuUrl"
            type="text"
            class="platformUrlInputs"
            v-model="amazonSkuUrl"
          />

          <label for="ebaySkuUrl" :style="[!ebaySkuUrl ? { color: 'red' } : '']"
            >Ebay Sku Url</label
          >
          <input
            id="ebaySkuUrl"
            type="text"
            class="platformUrlInputs"
            v-model="ebaySkuUrl"
          />

          <label
            for="websiteSkuUrl"
            :style="[!websiteSkuUrl ? { color: 'red' } : '']"
            >Website Sku Url</label
          >
          <input
            id="websiteSkuUrl"
            type="text"
            class="platformUrlInputs"
            v-model="websiteSkuUrl"
          />

          <label
            for="primeSkuUrl"
            :style="[!primeSkuUrl ? { color: 'red' } : '']"
            >Prime Sku Url</label
          >
          <input
            id="primeSkuUrl"
            type="text"
            class="platformUrlInputs"
            v-model="primeSkuUrl"
          />
        </div>
        <div id="platformUrlBtns" v-show="showSearch">
          <input
            type="button"
            class="navBtn"
            value="Submit Edit"
            @click="editSkuPlatForms"
          />
          <input
            type="button"
            class="navBtn"
            value="Undo Edit"
            @click="skuSearch"
          />
        </div>
      </div>
    </div>

    <div id="rightSideDiv">
      <h2>Add New Platform Sku</h2>
      <div id="addSkuDiv">
        <label for="newPlatformSku">New Sku</label>
        <input
          id="newPlatformSku"
          type="text"
          class="platformUrlInputs"
          v-model="newPlatformSku"
          required
        />

        <label for="newAmazonSku">New Amazon Url</label>
        <input
          id="newAmazonSku"
          type="text"
          class="platformUrlInputs"
          v-model="newAmazonSku"
        />

        <label for="newEbaySku">New Ebay Url</label>
        <input
          id="newEbaySku"
          type="text"
          class="platformUrlInputs"
          v-model="newEbaySku"
        />

        <label for="newWebsiteSku">New Website Url</label>
        <input
          id="newWebsiteSku"
          type="text"
          class="platformUrlInputs"
          v-model="newWebsiteSku"
        />

        <label for="newPrimeSku">New Prime Url</label>
        <input
          id="newPrimeSku"
          type="text"
          class="platformUrlInputs"
          v-model="newPrimeSku"
        />
      </div>
      <div id="addSkuBtns">
        <input
          type="button"
          class="navBtn"
          value="Add Sku"
          @click="newSkuPlatforms"
        />
        <input type="button" class="navBtn" value="Clear Input" />
      </div>
    </div>
  </div>
</template>

<script>
import { onMounted, reactive, ref } from "vue";
import { axiosGet } from "@/composables/axiosGet.js";
import { axiosPost } from "@/composables/axiosPost.js";
import SelectList from "@/components/SelectList.vue";

export default {
  name: "PlatformSkus",
  components: {
    SelectList,
  },
  setup() {
    /**
     * Define properties and functionality of view
     */
    const skuProducts = reactive({ value: [] });
    const keySkuPlatIds = reactive({ value: [] });
    const selectedKey = reactive({ value: [] });
    const selectedSku = reactive({ value: [] });
    const showSkuList = ref(false);
    const showPlatIdList = ref(false);
    const editMode = ref("");
    const showEditMode = ref(false);

    let platformUrls = {
      a: "https://www.amazon.co.uk/dp/",
      e: "https://www.ebay.co.uk/itm/",
      w: "https://elixirgardensupplies.co.uk/product/",
      p: "https://www.ebay.co.uk/itm/",
    };

    /**
     * Set the selected key, using the key selected by the user from the product drop down
     */
    const setSelectedKey = (key) => {
      if (!key) return false;

      selectedKey.value = keySkuPlatIds.value[key];
      showSkuList.value = true;
    };

    /**
     * Set using the selected sku get a list of platform ids to be displayed in the platIdList select list
     */
    const setSelectedSku = (sku) => {
      if (!sku) return false;

      let platIds = {};
      for (const value of Object.entries(selectedKey.value[sku])) {
        let platFormLookUp = {
          a: "Amazon:",
          e: "Ebay:",
          w: "Website:",
          p: "Prosalt:",
        };
        if (value[0] !== "sku" && value[1])
          platIds[value[0]] = platFormLookUp[value[0]] + value[1];
      }

      selectedSku.value = platIds;
      showPlatIdList.value = true;
    };

    /**
     * Given an input platform id, find which platform it belongs to and dirrect the user to the platform.
     * This is triggered on click of an option on the platIdList select.
     */
    const openPlatformid = (platid) => {
      let id = platid.split(":")[1];

      const platform = Object.keys(selectedSku.value).find(
        (key) => selectedSku.value[key] === platid
      );

      window.open(`${platformUrls[platform]}${id}`);
    };
    // -----------------------------------------------------------------------------------------------------------------------------------------------------------
    /**
     * Insert / edit mode properties / functionality
     */
    const skuPlatforms = reactive({ value: {} });
    const skuSearchInput = ref("");
    const skuSearchTitle = ref("");
    const showSearch = ref(false);

    const amazonSkuUrl = ref("");
    const ebaySkuUrl = ref("");
    const websiteSkuUrl = ref("");
    const primeSkuUrl = ref("");

    const newPlatformSku = ref("");
    const newAmazonSku = ref("");
    const newEbaySku = ref("");
    const newWebsiteSku = ref("");
    const newPrimeSku = ref("");

    // Get the platform links for the searched sku, append them to the input fields for user to edit
    const skuSearch = () => {
      axiosGet(`skuPlatLinks?sku=${skuSearchInput.value}`).then((response) => {
        if (response.length === 0) {
          skuPlatforms.value = {};
          skuSearchTitle.value = "No Sku Matching You Search On Record !";
          showSearch.value = false;
        } else {
          skuPlatforms.value = response;
          skuSearchTitle.value = `Platform Urls For ${skuSearchInput.value}`;

          amazonSkuUrl.value = skuPlatforms.value[0].a
            ? `${platformUrls["a"]}${skuPlatforms.value[0].a}`
            : "";

          ebaySkuUrl.value = skuPlatforms.value[0].e
            ? `${platformUrls["e"]}${skuPlatforms.value[0].e}`
            : "";

          websiteSkuUrl.value = skuPlatforms.value[0].w
            ? `${platformUrls["w"]}${skuPlatforms.value[0].w}`
            : "";

          primeSkuUrl.value = skuPlatforms.value[0].p
            ? `${platformUrls["p"]}${skuPlatforms.value[0].p}`
            : "";
          showSearch.value = true;
        }
      });
    };

    /**
     * Pass Edited Urls to backend and update the database
     */
    const editSkuPlatForms = () => {
      if (!confirm("Are You Sure ?")) return false;
      let request = {};
      request["editSkuPlatforms"] = {
        sku: skuSearchInput.value,
        amazonSkuUrl: amazonSkuUrl.value.replace(platformUrls["a"], ""),
        ebaySkuUrl: ebaySkuUrl.value.replace(platformUrls["e"], ""),
        websiteSkuUrl: websiteSkuUrl.value.replace(platformUrls["w"], ""),
        primeSkuUrl: primeSkuUrl.value.replace(platformUrls["p"]),
      };

      axiosPost(request);
      skuSearchInput.value = "";
      skuSearchTitle.value = "";
      showSearch.value = false;
      amazonSkuUrl.value = "";
      ebaySkuUrl.value = "";
      websiteSkuUrl.value = "";
      primeSkuUrl.value = "";
    };

    /**
     * Insert new sku and the urls for the platforms entered by the user
     */
    const newSkuPlatforms = () => {
      if (!confirm("Are You Sure ?")) return false;

      let request = {};
      request["addNewPlatformSku"] = {
        sku: newPlatformSku.value,
        newAmazonSku: newAmazonSku.value.replace(platformUrls["a"], ""),
        newEbaySku: newEbaySku.value.replace(platformUrls["e"], ""),
        newWebsiteSku: newWebsiteSku.value.replace(platformUrls["w"], ""),
        newPrimeSku: newPrimeSku.value.replace(platformUrls["p"]),
      };

      axiosPost(request);
      newPlatformSku.value = "";
      newAmazonSku.value = "";
      newEbaySku.value = "";
      newWebsiteSku.value = "";
      newPrimeSku.value = "";
    };

    // -----------------------------------------------------------------------------------------------------------------------------------------------------------
    /**
     * On view mount
     */
    onMounted(() => {
      axiosGet("stockSkuData").then((response) => {
        console.log(response);
        skuProducts.value = response.skuProducts;
        keySkuPlatIds.value = response.keySkuPlatIds;
      });
    });

    return {
      skuProducts,
      keySkuPlatIds,
      selectedKey,
      selectedSku,
      showSkuList,
      showPlatIdList,
      setSelectedKey,
      setSelectedSku,
      openPlatformid,
      editMode,
      showEditMode,
      skuSearchInput,
      skuSearch,
      skuSearchTitle,
      skuPlatforms,
      amazonSkuUrl,
      ebaySkuUrl,
      websiteSkuUrl,
      primeSkuUrl,
      showSearch,
      editSkuPlatForms,
      newSkuPlatforms,
      newPlatformSku,
      newAmazonSku,
      newEbaySku,
      newWebsiteSku,
      newPrimeSku,
    };
  },
};
</script>

<style scoped>
h1 {
  top: 2.5%;
  left: 0.5%;
}

#productList {
  position: absolute;
  height: 90%;
  top: 10%;
}

#skuList {
  position: absolute;
  height: 75%;
  top: 10.5%;
  left: 27.5%;
}

#platIdList {
  position: absolute;
  height: 75%;
  top: 10.5%;
  left: 55%;
}

#editModeBtn {
  position: absolute;
  left: 12.5%;
  top: 5.5%;
}

#skuSearchDiv {
  position: relative;
  top: 5%;
  width: 50%;
  margin: 0 31%;
}

#leftSideDiv {
  position: absolute;
  border-right: thin solid black;
  border-top: thin solid black;
  top: 10%;
  width: 50%;
  height: 85%;
}

#leftSideDiv h2 {
  position: relative;
  left: 32%;
}

#rightSideDiv {
  position: absolute;
  right: 0.5%;
  border-top: thin solid black;
  top: 10%;
  width: 50%;
  height: 85%;
}

#rightSideDiv h2 {
  position: relative;
  left: 42.5%;
}

#skuSearchResults {
  position: relative;
  top: 12.5%;
  height: 50%;
  margin: 0 auto;
  width: 75%;
}

#skuSearchResults h3 {
  position: relative;
  right: 10%;
}

#platformUrlsDiv {
  position: relative;
  top: 15%;
  height: 90%;
  display: grid;
  grid-template-columns: max-content max-content;
  grid-gap: 5px;
}

.platformUrlInputs {
  display: grid;
  height: 15px;
  width: 300px;
}

#platformUrlsDiv label {
  text-align: left;
}
#platformUrlsDiv label:after {
  content: ":";
}

#addSkuDiv {
  position: relative;
  top: 5%;
  height: 90%;
  display: grid;
  justify-content: center;
  grid-template-columns: max-content max-content;
  grid-gap: 5px;
}

#addSkuDiv label {
  text-align: left;
}
#addSkuDiv label:after {
  content: ":";
}

#addSkuBtns {
  position: relative;
  display: flex;
  margin-left: 47.5%;
  bottom: 5%;
}

#platformUrlBtns {
  position: relative;
  top: 5%;
  width: 75%;
  text-align: center;
}

#platformUrlBtns button {
  display: inline-block;
}

#skuSearchBtn {
  margin-left: 5px;
  height: 25px;
}
</style>
