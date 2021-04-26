import axios from "axios";

export function axiosPost(request, config = false) {
  try {
    axios.post(
      // URL below is for live system, change url when testing
      "http://localhost/Ryan/Projects/stocksystem/PHPAPI/StockPost.php",
      request,
      config === false ? null : config
    );
  } catch (e) {
    return e;
  }

  return true;
}
