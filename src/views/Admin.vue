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
</template>

<script>
import { axiosGet } from "@/composables/axiosGet.js";
import { axiosPost } from "@/composables/axiosPost.js";
import { exportCsv } from "@/composables/exportCsv.js";

export default {
  name: "Admin",
  components: {},
  setup() {
    const getResponseCsv = () => {
      axiosGet("noShelfCsv").then((response) => {
        const csv = exportCsv(response);
        if (csv === "Not Valid Format") return alert(csv);

        let link = document.createElement("a");
        link.id = "download-csv";
        link.setAttribute(
          "href",
          "data:text/plain;charset=utf-8," + encodeURIComponent(csv)
        );
        link.setAttribute("download", "missingShelfsForm.csv");
        document.body.appendChild(link);
        document.querySelector("#download-csv").click();
        document.body.removeChild(link);
      });
    };

    const submitResponse = (event) => {
      let responseFile = event.target[0].files[0];

      let formData = new FormData();
      formData.append("responseCSV", responseFile);

      axiosPost(formData, {
        headers: { "Content-Type": "multipart/form-data" },
      });
    };

    return {
      getResponseCsv,
      submitResponse,
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
</style>
