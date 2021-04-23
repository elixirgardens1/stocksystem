import axios from "axios";

export function axiosGet(type) {
  const promise = axios.get(
    `http://localhost/Ryan/Projects/stocksystem/PHPAPI/QueryController.php?${type}`
  );

  const dataPromise = promise.then((response) => response.data);

  return dataPromise;
}
