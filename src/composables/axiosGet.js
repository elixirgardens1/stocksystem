import axios from "axios";

export function axiosGet(type) {
  const promise = axios.get(
    // `http://localhost/projects/stocksystem/PHPAPI/QueryController.php?${type}`
    // `http://localhost/Ryan/Projects/stocksystem/PHPAPI/QueryController.php?${type}`
    `http://localhost/Ryan/Projects/stocksystem/PHPAPI/QueryController.php?${type}`
    // `http://192.168.0.24:8080/stocksystem/PHPAPI/QueryController.php?${type}`
  );

  const dataPromise = promise.then((response) => response.data);

  return dataPromise;
}
