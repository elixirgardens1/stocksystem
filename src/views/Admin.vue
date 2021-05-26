<template>
  <div id="headerDiv">
    <h1>Admin</h1>
  </div>

  <div id="rightContent">
    <div id="nsProductsDiv">
      <h2>No Shelf Product Deliveries</h2>
      <input
        type="button"
        class="navBtn"
        value="Export Response Csv"
        @click="getResponseCsv"
      />

      <div id="responseDiv">
        <form @submit.prevent="submitResponse($event)">
          <input id="responseInput" type="file" name="responseCSV" />
          <input type="submit" class="navBtn" value="Submit Response" />
        </form>
      </div>
    </div>
  </div>

  <div id="adminMainDiv">
    <h2>Stock Admin Alerts</h2>
    <div>
      <ul>
        <li v-for="(message, index) in adminMessages.value" :key="index">
          Error Type: {{ message.errorType }}
          <span style="color:red;">|</span> Message: {{ message.description }}
          <span style="color:red;">|</span> Alert: {{ message.alert }}
          <span style="color:red;">|</span> Time: {{ message.date }}
        </li>
      </ul>
    </div>
  </div>

  <div id="csvExportDiv">
    <h2>Export CSV</h2>
    <div id="csvExportInputs">
      <select id="selectExportDiv" v-model="exportType">
        <option value="">
          Select Type
        </option>
        <option>
          Merged Asins
        </option>
      </select>

      <input type="button" class="navBtn" value="Export" @click="adminExport" />
    </div>
  </div>
</template>

<script>
import { axiosGet } from "@/composables/axiosGet.js";
import { axiosPost } from "@/composables/axiosPost.js";
import { exportCsv } from "@/composables/exportCsv.js";
import { onMounted, reactive, ref } from "@vue/runtime-core";

export default {
  name: "Admin",
  components: {},
  setup() {
    const adminMessages = reactive({ value: {} });
    const exportType = ref("");

    const getResponseCsv = () => {
      axiosGet("noShelfCsv").then((response) => {
        const csv = exportCsv(response);
        if (csv === "Not Valid Format") return alert(csv);

        downloadCsv(csv, "missingShelfsForm");
      });
    };

    const adminExport = () => {
      if (!exportType.value) return alert("Select Valid Type !");

      axiosGet("mergedAsins").then((response) => {
        const csv = exportCsv(response);

        if (csv === "Not Valid Format") return alert(csv);

        downloadCsv(csv, "mergedAsins");
      });
    };

    const downloadCsv = (csv, name) => {
      let link = document.createElement("a");
      link.id = "download-csv";
      link.setAttribute(
        "href",
        "data:text/plain;charset=utf-8," + encodeURIComponent(csv)
      );
      link.setAttribute("download", `${name}.csv`);
      document.body.appendChild(link);
      document.querySelector("#download-csv").click();
      document.body.removeChild(link);
    };

    const submitResponse = (event) => {
      let responseFile = event.target[0].files[0];

      let formData = new FormData();
      formData.append("responseCSV", responseFile);

      axiosPost(formData, {
        headers: { "Content-Type": "multipart/form-data" },
      });
    };

    onMounted(() => {
      axiosGet("stockAdmin").then((response) => {
        adminMessages.value = response;
      });
    });

    return {
      adminMessages,
      getResponseCsv,
      downloadCsv,
      adminExport,
      submitResponse,
      exportType,
    };
  },
};
</script>

<style scoped>
#headerDiv {
  position: absolute;
  background: rgba(240, 240, 240, 0.95);
  border-bottom: thin solid black;
  height: 7.5%;
  width: 99%;
  top: 2.5%;
}

#rightContent {
  position: absolute;
  top: 12.5%;
  width: 45%;
  height: 80%;
}

#nsProductsDiv {
  width: 40%;
  height: 25%;
  border: thin solid grey;
  text-align: center;
}

#responseDiv {
  position: relative;
  top: 25%;
}

#responseInput {
  width: 50%;
}

#adminMainDiv {
  position: absolute;
  top: 10%;
  left: 25%;
  width: 55%;
  height: 75%;
}

#adminMainDiv h2 {
  text-align: center;
}

#csvExportDiv {
  position: absolute;
  top: 10%;
  width: 22%;
  right: 0.5%;
}

#csvExportInputs {
  position: relative;
  display: flex;
  justify-content: center;
}

#selectExportDiv {
  margin-right: 10px;
}

#csvExportDiv h2 {
  text-align: center;
}
</style>
