export const exportCsv = (exportData) => {
  if (!Object.keys(exportData).length) return "Not Valid Format";

  // Get headers from first postion in the array
  const firstKey = Object.keys(exportData)[0];
  const headers = Object.keys(exportData[firstKey]).join(",");

  // If Locations exists, implode locations into string seperated by |
  if (headers.includes("Locations")) {
    Object.values(exportData).forEach((item) => {
      item["Locations"] = Object.values(item["Locations"]).join("|");
    });
  }

  const csv = [
    headers,
    ...Object.values(exportData).map((item) => Object.values(item).join(",")),
  ].reduce((string, item) => {
    string += item + "\n";
    return string;
  }, "");

  return csv;
};
