import axios from "axios";

export function axiosPost(request) {
  try {
    axios.post(
      // "http://localhost/projects/stocksystem/PHPAPI/StockPost.php"
      // "http://localhost/Ryan/Projects/stocksystem/PHPAPI/StockPost.php",
      // "http://localhost/projects/stocksystem/PHPAPI/StockPost.php",
      "http://192.168.0.24:8080/stocksystem/PHPAPI/StockPost.php",
      request
    );
  } catch (e) {
    return e;
  }

  return true;
}
