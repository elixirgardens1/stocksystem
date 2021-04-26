import axios from "axios";

export function axiosPost(request, config = false) {
  try {
    axios.post(
      // URL below is for live system, change url when testing
      // "http://192.168.0.24:8080/stocksystem/PHPAPI/StockPost.php",
      request,
      config === false ? null : config
    );
  } catch (e) {
    return e;
  }

  return true;
}
