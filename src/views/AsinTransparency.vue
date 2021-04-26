<template>
  <div>
    <h1>Asin Transparency</h1>
  </div>

  <h2 id="activeTitle">Active Asins</h2>
  <SelectList
    id="activeAsinsList"
    :list-items="ActiveAsins()"
    list-type="Asin"
    list-key="Asin"
    @selectedKey="SetAsinState"
  ></SelectList>

  <h2 id="disabledTitle">Disabled Asins</h2>
  <SelectList
    id="disabledAsinsList"
    :list-items="DisabledAsins()"
    list-type="Asin"
    list-key="Asin"
    @selectedKey="SetAsinState"
  ></SelectList>

  <h3 id="csvUploadTitle">Upload Asin Csv</h3>
  <div id="uploadCsvDiv">
    <form @submit.prevent="submitCsvUpload($event)">
      <input id="csvUploadFile" type="file" />
      <input
        id="csvUploadText"
        placeholder="Enter Asin To Upload"
        v-model="uploadAsinText"
      />
      <select id="csvUploadType" v-model="uploadAsinType">
        <option disabled selected>Type</option>
        <option value="insertCodes">Insert Codes</option>
        <option value="InsertAndGenerate10Images"
          >Insert and Generate 10 Images</option
        >
      </select>
      <input
        id="submitFileUpload"
        class="navBtn"
        type="submit"
        value="Submit"
      />
    </form>
  </div>

  <input
    id="filterAsinInput"
    type="text"
    placeholder="Filter By Asin"
    v-model="asinFilter"
  />
  <div id="asinDataTblDiv">
    <DynamicTable
      id="asinDataTbl"
      :columns="AsinColumns"
      :dataArr="filterAsins"
    ></DynamicTable>
  </div>
</template>

<script>
import { computed, onMounted, reactive, ref } from "vue";
import { axiosGet } from "@/composables/axiosGet.js";
import { axiosPost } from "@/composables/axiosPost.js";
import SelectList from "@/components/SelectList.vue";
import DynamicTable from "@/components/DynamicTable.vue";

export default {
  components: {
    SelectList,
    DynamicTable,
  },
  setup() {
    const AsinColumns = ref([]);
    const AsinData = reactive({ value: [] });
    const asinFilter = ref("");
    const uploadAsinText = ref("");
    const uploadAsinType = ref("");

    /**
     * Change active state for asin clicked in either disabled or active list
     */
    const SetAsinState = (asin) => {
      // inverse the current active value of the asin
      let asinState =
        AsinData.value[asin].Status == "Disabled" ? "Active" : "Disabled";
      if (!confirm(`Set ${asin} Status To ${asinState} ?`)) return false;

      let request = {};
      request["setAsinState"] = {
        asin,
        asinState,
      };

      axiosPost(request);

      AsinData.value[asin].Status = asinState;
    };

    const ActiveAsins = () => {
      let filtered = {};
      Object.entries(AsinData.value).forEach((row) => {
        if (row[1].Status == "Active") filtered[row[0]] = row[1];
      });

      return filtered;
    };

    const DisabledAsins = () => {
      let filtered = {};
      Object.entries(AsinData.value).forEach((row) => {
        if (row[1].Status == "Disabled") filtered[row[0]] = row[1];
      });

      return filtered;
    };

    const filterAsins = computed(() => {
      if (!asinFilter.value) return AsinData.value;

      let filtered = {};
      Object.entries(AsinData.value).forEach((row) => {
        if (
          row[1].Asin.toLowerCase().indexOf(asinFilter.value.toLowerCase()) !=
          -1
        ) {
          filtered[row[0]] = row[1];
        }
      });
      return filtered;
    });

    const submitCsvUpload = (event) => {
      let uploadAsin = uploadAsinText.value;
      let uploadType = uploadAsinType.value;
      let uploadCsv = event.target[0].files[0];

      let formData = new FormData();

      formData.append("file", uploadCsv);
      formData.append("asinInput", uploadAsin);
      formData.append("type", uploadType);

      axiosPost(formData, {
        headers: { "Content-Type": "multipart/form-data" },
      });

      uploadAsinText.value = "";
      uploadAsinType.value = "Type";
      document.getElementById("csvUploadFile").value = "";
      return alert("Successful");
    };

    onMounted(() => {
      axiosGet("protectedAsins").then((response) => {
        AsinData.value = response.AsinData;

        let firstKey = Object.keys(AsinData.value)[0];
        if (firstKey !== undefined) {
          AsinColumns.value = Object.keys(AsinData.value[firstKey]);
        }
      });
    });

    return {
      AsinColumns,
      AsinData,
      ActiveAsins,
      DisabledAsins,
      SetAsinState,
      asinFilter,
      filterAsins,
      uploadAsinText,
      uploadAsinType,
      submitCsvUpload,
    };
  },
};
</script>

<style scoped>
h1 {
  top: 2.5%;
  left: 0.5%;
}

#disabledAsinsList {
  position: absolute;
  top: 10%;
  left: 27.5%;
  height: 90%;
}

#activeAsinsList {
  position: absolute;
  top: 10%;
  height: 90%;
}

#asinDataTbl {
  position: absolute;
  height: 200px;
}

#asinDataTblDiv {
  position: absolute;
  top: 22.5%;
  left: 55%;
  width: 45%;
  overflow-y: auto;
  height: 70%;
}

#activeTitle {
  position: absolute;
  left: 10%;
}

#disabledTitle {
  position: absolute;
  left: 37.5%;
}

#filterAsinInput {
  position: absolute;
  top: 20%;
  left: 55%;
}

#uploadCsvDiv {
  position: relative;
  left: 55%;
}

#csvUploadType {
  width: 200px;
}

#csvUploadText {
  margin-right: 5px;
}

#csvUploadType {
  margin-right: 5px;
}

#csvUploadTitle {
  position: relative;
  left: 25%;
  top: 5%;
}
</style>
