import axios from "axios";

export function axiosGet(type) {
  // eslint-disable-next-line prettier/prettier
  const promise = axios.get(
    // URL below is for live system, change url when testing
    `http://192.168.0.24:8080/stocksystem/PHPAPI/QueryController.php?${type}`
  );

  const dataPromise = promise.then((response) => response.data);

  return dataPromise;
}
