
<?php
// Define database
$db = new PDO('sqlite:stock_control.db3');
$matrixDB = new PDO('sqlite:C:\inetpub\wwwroot\FESP-REFACTOR\FespMVC\Modules\Transparanecy\matrixCodes.db3');
// $matrixDB = new PDO('sqlite:matrixCodes.db3');

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: X-Requested-With,Origin,Content-Type,Cookie,Accept');


if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('HTTP/1.1 204 No Content');
    die;
}

$requestBody = file_get_contents('php://input');
$requestBody = json_decode($requestBody, true);
if ($requestBody === null) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode([
        'errorMessage' => 'Please provide valid JSON',
    ]);
}

// ----------------------------------------------------------------------------------------------------------------

$roomLookup = [
    'Middle' => 'm',
    'Salt' => 's',
    'Fert' => 'f',
    'Poison' => 'p',
    'Bamboo' => 'b',
    'Gallup' => 'g',
    'Cutting' => 'c',
];

// -----------------------------------------------------------------------------------------------------------------------------------------------------------

$tableLookup = [
    'products' => [
        'Product' => 'product',
        'Supplier' => 'primary_supplier',
        'product_cost' => 'product_cost',
        'yellowThreshold' => 'yellowThreshold',
        'redThreshold' => 'redThreshold',
    ],
    'product_rooms' => [
        'Room' => 'room',
        'Locations' => 'shelf_location',
    ],
    'ordered_stock' => [
        'Qty' => 'qty',
        'Order Number' => 'ord_num',
        'Delivery Date' => 'exp_del_date',
        'Supplier' => 'supplier',
        'Status' => 'status',
        'Placed Date' => 'datetime',
        'Item Cost' => 'item_cost',
    ],
];

// -----------------------------------------------------------------------------------------------------------------------------------------------------------

/**
 * Edit Product information stored in the products table and product_rooms
 */
if (isset($requestBody['editProduct'])) {
    $editData = $requestBody['editProduct'];

    // Build update statement for products table
    $responseProduct = buildQuery($tableLookup["products"], "UPDATE", "products", "WHERE key = ?");

    // Check product exists in product_rooms
    $sql = "SELECT * FROM product_rooms WHERE key = '{$editData['Key']}'";
    $response = $db->query($sql);
    $response = $response->fetchAll(PDO::FETCH_ASSOC);

    // If exists build update record statement, else insert new record statement
    if (count($response) > 0) {
        $responseRooms = buildQuery($tableLookup["product_rooms"], "UPDATE", "product_rooms", "WHERE key = ?");
    } else {
        $responseRooms = buildQuery($tableLookup["product_rooms"], "INSERT", "product_rooms", "");
    }

    // Prepare updates for both tables
    $stmtProduct = $db->prepare($responseProduct['sql']);
    $stmtRooms = $db->prepare($responseRooms['sql']);
    $db->beginTransaction();

    // Execute updates for product using values from modal
    $stmtProduct->execute([$editData['Product'], $editData['Supplier'], $editData['product_cost'], $editData['yellowThreshold'], $editData['redThreshold'], $editData['Key']]);

    // If the product exists in product_rooms, update it, else insert new record for this product
    if (count($response) > 0) {
        $stmtRooms->execute([$roomLookup[$editData['Room']], json_encode($editData['Locations']), $editData['Key']]);
    } else {
        $stmtRooms->execute([$editData['Key'], $roomLookup[$editData['Room']], json_encode($editData['Locations'])]);
    }

    $db->commit();
}

// -----------------------------------------------------------------------------------------------------------------------------------------------------------

/**
 * Add products / orders to pending orders
 */
if (isset($requestBody['submitOrder'])) {
    $stockOrder = $requestBody['submitOrder'];

    $formTotal = 0;
    //Format data
    foreach ($stockOrder as $index => $product) {
        // This infromation will be the same for all products so just asign it here
        $orderNumber = $product['Order Number'];
        $orderSupplier = $product['Supplier'];
        $orderDatePlaced = $product['Placed Date'];

        $stockOrder[$index]['Status'] = 'Pending';
        $stockOrder[$index]['Delivery Date'] = date("Ymd", strtotime($product['Delivery Date']));
        $stockOrder[$index]['Placed Date'] = date("Ymd", strtotime($product['Placed Date']));
        // Calcualte qty for each product using Qty * Multiple
        $stockOrder[$index]['Qty'] = ($product['Qty'] * $product['Multiple']);
        $formTotal += $product['Item Cost'];
        unset($stockOrder[$index]['Multiple']);
    }

    // Get list of existing orders from the product_orders_prices table in the stock_control.db3 database to check if order already exists
    // used when adding items to existing orders
    $sql = "SELECT ord_value FROM product_orders_prices WHERE ord_num = $orderNumber";
    $existingOrders = $db->query($sql);
    $existingOrders = $existingOrders->fetchAll(PDO::FETCH_COLUMN);

    // Build query for each table, also build the formatted array for inserting into the ordered_stock table
    $orderedStockResponse = buildQuery($tableLookup['ordered_stock'], "INSERT", 'ordered_stock', "", $stockOrder);
    $stmtOrderedStock = $db->prepare($orderedStockResponse['sql']);
    //Prepare query for prod_order_prices
    $stmtProductOrdersPrices = $db->prepare("INSERT INTO product_orders_prices (ord_num, supplier, date_placed, ord_value) VALUES (?,?,?,?)");

    // If a record is found then an order already exists, assume user is adding products to an existing order and we should increase the value of the order
    if (count($existingOrders) > 0) {
        // Only retrieved the current value of order held at postion [0] as ord_num is unique only possible to get one result from query
        $formTotal += $existingOrders[0];
        // Set query to update
        $stmtProductOrdersPrices = $db->prepare("UPDATE product_orders_prices SET ord_value = ? WHERE ord_num = ?");
    }

    $db->beginTransaction();
    foreach ($orderedStockResponse['data'] as $key => $product) {
        $stmtOrderedStock->execute($product);
    }

    // If count greater than 0, perform the execution for the update, else insert the new order information
    if (count($existingOrders) > 0) {
        $stmtProductOrdersPrices->execute([$formTotal, $orderNumber]);
    } else {
        $stmtProductOrdersPrices->execute([$orderNumber, $orderSupplier, date("Ymd", strtotime($orderDatePlaced)), $formTotal]);
    }
    $db->commit();
}

// -----------------------------------------------------------------------------------------------------------------------------------------------------------

/**
 * Update the product infromation stored in the ordered_stock table, update the total value of the order number based on the change of the products cost
 */
if (isset($requestBody['editPendingProduct'])) {
    $pendingEdit = $requestBody['editPendingProduct'];
    $newShelf = json_encode($pendingEdit['newShelf']);

    if ($newShelf == '[]') {
        $newShelf = null;
    }

    // Difference in price between the previous price and the new price
    $costDiff = $pendingEdit['costDiff'];
    unset($pendingEdit['costDiff']);

    // Get current order value for the order the product belongs to
    $sql = "SELECT ord_value FROM product_orders_prices WHERE ord_num = {$pendingEdit['Order Number']}";
    $currentOrderValue = $db->query($sql);
    $currentOrderValue = $currentOrderValue->fetchAll(PDO::FETCH_COLUMN)[0];

    // New order price, if the costDiff is negative the price of the item has increased in cost, so add this value to the current order value, else minus
    $newOrderValue = 0;
    if ($costDiff < 0) {
        $newOrderValue = $currentOrderValue + abs($costDiff);
    } else {
        $newOrderValue = $currentOrderValue - abs($costDiff);
    }

    // Update the product in the ordered_stock table and update the total cost of the order in the product_orders_prices
    $stmtEditPending = $db->prepare("UPDATE ordered_stock SET qty = ?, supplier = ?, exp_del_date = ?, datetime = ?, item_cost = ?, newShelf = ? WHERE ord_num = ? AND key = ? AND status = ?");
    $stmtProductOrdersPrices = $db->prepare("UPDATE product_orders_prices SET ord_value = ? WHERE ord_num = ?");
    $db->beginTransaction();
    $stmtEditPending->execute([$pendingEdit['Qty'], $pendingEdit['Supplier'], date("Ymd", strtotime($pendingEdit['Delivery Date'])), date("Ymd", strtotime($pendingEdit['Placed Date'])), $pendingEdit['Item Cost'], $newShelf, $pendingEdit['Order Number'], $pendingEdit['Key'], 'Pending']);
    $stmtProductOrdersPrices->execute([$newOrderValue, $pendingEdit['Order Number']]);
    $db->commit();
}

// -----------------------------------------------------------------------------------------------------------------------------------------------------------

/**
 * Cancel a product from an order and update the cost of the order in the ordered_stock_prices table
 */
if (isset($requestBody['deletePendingProduct'])) {
    $deletedProduct = $requestBody['deletePendingProduct'];

    // Get count of items belonging to the order number of the cancelled item
    $sql = "SELECT key, item_cost FROM ordered_stock WHERE ord_num = {$deletedProduct['Order Number']}";
    $orderNumberItems = $db->query($sql);
    $orderNumberItems = $orderNumberItems->fetchAll(PDO::FETCH_KEY_PAIR);

    // If the order has more than one item, add up the remaining items values and insert the new total value of the order
    $newOrderValue = 0;
    if (count($orderNumberItems) > 1) {
        foreach ($orderNumberItems as $key => $itemCost) {
            if ($key !== $deletedProduct['Key']) {
                $newOrderValue += $itemCost;
            }
        }
    }
    // Update the value of the order in the ordered_stock_prices table
    $stmt = $db->prepare("UPDATE product_orders_prices SET ord_value = ? WHERE ord_num = ?");
    $db->beginTransaction();
    $stmt->execute([$newOrderValue, $deletedProduct['Order Number']]);
    $db->commit();

    // Cancel product on ordered_stock table
    $stmt = $db->prepare("UPDATE ordered_stock SET status = 'Cancelled' WHERE key = ? AND ord_num = ?");
    $db->beginTransaction();
    $stmt->execute([$deletedProduct['Key'], $deletedProduct['Order Number']]);
    $db->commit();
}

// -----------------------------------------------------------------------------------------------------------------------------------------------------------

/**
 * Process a singular product into the stock_control database, updates the status of the product in ordered stock to Complete and inputs the delivery number
 * associated with the order, ideally delivery numbers would be the same for each product belonging to an order number
 *
 * Updates current qty of the product in the stock table
 *
 * Updates the stock_change, this will update 30 records in the table matching the key of the processed product, each record will be increased by the ordered qty
 *
 * Insert record into updated_stock table, so record of this amount going into the stock will be visible by users entering manual stock updates
 */
if (isset($requestBody['processPendingProduct'])) {
    $processProduct = $requestBody['processPendingProduct'];

    $productNewShelfs = (array)$processProduct['newShelf'];
    // If not an array force empty array to keep current shelfs
    if (!is_array($productNewShelfs)) {
        $productNewShelfs = [];
    }

    // Only update shelfs if new shelfs have been entered
    if (count($productNewShelfs) > 0) {
        // Get the current shelf_locations for the product
        $sql = "SELECT shelf_location FROM product_rooms WHERE key = '{$processProduct['Key']}'";
        $currentShelfs = $db->query($sql);
        $currentShelfs = json_decode($currentShelfs->fetchAll(PDO::FETCH_COLUMN)[0], true);

        if (!$currentShelfs) {
            $currentShelfs = [];
        }

        // Remove duplicate shelfs
        $shelfsToAdd = array_diff($productNewShelfs, $currentShelfs);
        foreach ($shelfsToAdd as $shelf) {
            $currentShelfs[] = $shelf;
        }
        $currentShelfs = json_encode($currentShelfs);

        $stmtProductRooms = $db->prepare("UPDATE product_rooms SET shelf_location = ? WHERE key = ?");
        $db->beginTransaction();
        $stmtProductRooms->execute([$currentShelfs, $processProduct['Key']]);
        $db->commit();
    }

    // Update the product to have status Complete in the ordered_stock table
    $stmtOrderedStock = $db->prepare("UPDATE ordered_stock SET status = ?, del_num = ?, signed = ? WHERE ord_num = ? AND key = ?");
    // Update the values in stock table
    $stmtStock = $db->prepare("UPDATE stock SET qty = qty + ? WHERE key = ?");
    // Update stock_change table
    $stmtStockChange = $db->prepare("UPDATE stock_change SET qty = (qty + ?) + qty + (qty * -1) WHERE key = ?");
    // Insert into updated_stock table
    $stmtUpdatedStock = $db->prepare("INSERT INTO updated_stock VALUES (?,?,?,?)");

    // Execute the variables needed to update the tables
    $db->beginTransaction();
    $stmtOrderedStock->execute(['Complete', $processProduct['Delivery Number'], $processProduct['signedBy'], $processProduct['Order Number'], $processProduct['Key']]);
    $stmtStock->execute([$processProduct['Qty'], $processProduct['Key']]);
    $stmtStockChange->execute([$processProduct['Qty'], $processProduct['Key']]);
    $stmtUpdatedStock->execute([$processProduct['Key'], $processProduct['Qty'], $processProduct['Delivery Number'], date('YmdHi')]);
    $db->commit();
}

// -----------------------------------------------------------------------------------------------------------------------------------------------------------

/**
 * Given an array of products belonging to an order number, insert the information into the stock control
 */
if (isset($requestBody['processOrder'])) {
    $orderProducts = (array)$requestBody['processOrder'];

    // Prepare statements required to update the database tables
    $stmtStock = $db->prepare("UPDATE stock SET qty = qty + ? WHERE key = ?");
    $stmtStockChange = $db->prepare("UPDATE stock_change SET qty = (qty + ?) + qty + (qty * -1) WHERE key = ?");
    $stmtOrderedStock = $db->prepare("UPDATE ordered_stock SET status = ?, del_num = ?, signed = ? WHERE ord_num = ? AND key = ?");
    $stmtProductOrdersPrices = $db->prepare("UPDATE product_orders_prices SET delivery_number = ?, date_delivered = ?, ord_value = ?, status = ? WHERE ord_num = ?");
    $stmtUpdatedStock = $db->prepare("INSERT INTO updated_stock VALUES(?,?,?,?)");
    $db->beginTransaction();

    // Loop through each product and insert the values into the required tables
    foreach ($orderProducts['products'] as $key => $product) {

        $productNewShelfs = (array)$product['newShelf'];
        // If not an array force empty array to keep current shelfs
        if (!is_array($productNewShelfs)) {
            $productNewShelfs = [];
        }

        // Only update shelfs if new shelfs have been entered
        if (count($productNewShelfs) > 0) {
            // Get the current shelf_locations for the product
            $sql = "SELECT shelf_location FROM product_rooms WHERE key = '{$product['Key']}'";
            $currentShelfs = $db->query($sql);
            $currentShelfs = json_decode($currentShelfs->fetchAll(PDO::FETCH_COLUMN)[0], true);

            if (!$currentShelfs) {
                $currentShelfs = [];
            }

            // Remove duplicate shelfs
            $shelfsToAdd = array_diff($productNewShelfs, $currentShelfs);
            foreach ($shelfsToAdd as $shelf) {
                $currentShelfs[] = $shelf;
            }
            $currentShelfs = json_encode($currentShelfs);

            $stmtProductRooms = $db->prepare("UPDATE product_rooms SET shelf_location = ? WHERE key = ?");
            $stmtProductRooms->execute([$currentShelfs, $product['Key']]);
        }

        $stmtStock->execute([$product['Qty'], $product['Key']]);
        $stmtStockChange->execute([$product['Qty'], $product['Key']]);
        $stmtOrderedStock->execute(['Complete', $orderProducts['deliveryNumber'], $orderProducts['signedBy'], $orderProducts['orderNumber'], $product['Key']]);
        $stmtProductOrdersPrices->execute([$orderProducts['deliveryNumber'], date("Ymd", strtotime($orderProducts['orderDeliveryDate'])), $orderProducts['orderValue'], 'Complete', $orderProducts['orderNumber']]);
        $stmtUpdatedStock->execute([$product['Key'], $product['Qty'], $orderProducts['deliveryNumber'], date('YmdHi')]);
    }
    $db->commit();
}

// -----------------------------------------------------------------------------------------------------------------------------------------------------------

/**
 * Edit the date for all items belonging to an order number, which have the status pending, assume items already complete were delivered early and are not to be changed
 */
if (isset($requestBody["editOrderDate"])) {
    $dateEdit = $requestBody["editOrderDate"];

    $stmtOrderedStock = $db->prepare("UPDATE ordered_stock SET exp_del_date = ? WHERE ord_num = ? AND status = ?");
    $db->beginTransaction();
    $stmtOrderedStock->execute([date("Ymd", strtotime($dateEdit['newDate'])), $dateEdit['orderNumber'], 'Pending']);
    $db->commit();
}

// -----------------------------------------------------------------------------------------------------------------------------------------------------------

/**
 * Set status of remaining items belong to an order number as cancelled in ordered_stock, set status in product_orders_prices as cancelled
 */
if (isset($requestBody["cancelOrder"])) {
    $cancelData = $requestBody["cancelOrder"];

    // Only set status cancelled for items of this order number that are still pending, assume items that had status complete were delivered
    $stmtOrderedStock = $db->prepare("UPDATE ordered_stock SET status = ? WHERE ord_num = ? AND status = ?");
    $stmtProductOrdersPrices = $db->prepare("UPDATE product_orders_prices SET status = ? WHERE ord_num = ?");
    $db->beginTransaction();
    $stmtOrderedStock->execute(['Cancelled', $cancelData['orderNumber'], 'Pending']);
    $stmtProductOrdersPrices->execute(['Cancelled', $cancelData['orderNumber']]);
    $db->commit();
}

// -----------------------------------------------------------------------------------------------------------------------------------------------------------

/**
 * Passes a key and a value for the to_be_hidden field, used to hide and show products
 */
if (isset($requestBody["hideProduct"])) {
    $productData = $requestBody["hideProduct"];
    $key = $productData['productKey'];
    $setToBeHidden = $productData['toBeHidden'];

    $stmtProduct = $db->prepare("UPDATE products SET to_be_hidden = ? WHERE key = ?");
    $db->beginTransaction();
    $stmtProduct->execute([$setToBeHidden, $key]);
    $db->commit();
}

// -----------------------------------------------------------------------------------------------------------------------------------------------------------

/**
 * Update stock control with values passed from the update stock page
 */
if (isset($requestBody["updateStock"])) {
    $stockData = $requestBody["updateStock"];

    $stmtStock = $db->prepare("UPDATE stock SET qty = qty + ? WHERE key = ?");
    $stmtUpdatedStock = $db->prepare("INSERT INTO updated_stock VALUES(?,?,?,?)");
    $stmtStockChange = $db->prepare("UPDATE stock_change SET qty = (qty + ?) + qty + (qty * -1) WHERE key = ?");
    $db->beginTransaction();
    $stmtStock->execute([$stockData['qty'], $stockData['key']]);
    $stmtUpdatedStock->execute([$stockData['key'], $stockData['qty'], $stockData['delNumber'], date('YmdHi', time())]);
    $stmtStockChange->execute([$stockData['qty'], $stockData['key']]);
    $db->commit();
}

// -----------------------------------------------------------------------------------------------------------------------------------------------------------

/**
 * Insert new skus into stock control created by the user on the import skus page
 */
if (isset($requestBody['importSku'])) {
    $skuData = $requestBody['importSku'];

    $stmtSkuAtts = $db->prepare("INSERT INTO sku_atts VALUES (?,?)");
    $stmtSkuRoomLookup = $db->prepare("INSERT INTO sku_room_lookup VALUES (?,?)");
    $db->beginTransaction();
    $stmtSkuAtts->execute([$skuData['sku'], $skuData['atts']]);
    $stmtSkuRoomLookup->execute([$skuData['sku'], $skuData['room']]);
    $db->commit();
}

if (isset($requestBody['splitOrder'])) {
    $splitData = $requestBody['splitOrder'];

    $newQty = $splitData['Qty'] - $splitData['splitQty'];

    $stmtOrderedStock = $db->prepare("UPDATE ordered_stock SET qty = ?, item_cost = ? WHERE ord_num = ? AND key = ?");
    $stmtOrderedStockIns = $db->prepare("INSERT INTO ordered_stock VALUES (?,?,?,?,?,?,?,?,?,?,?)");
    $db->beginTransaction();
    $stmtOrderedStock->execute([$newQty, $newQty * $splitData['product_cost'], $splitData['Order Number'], $splitData['Key']]);

    $stmtOrderedStockIns->execute([
        $splitData['Key'], $splitData['splitQty'], $splitData['splitOrderNumber'], null,
        $splitData['Supplier'], 'Pending', null, date("Ymd", strtotime($splitData['Delivery Date'])), date("Ymd", strtotime($splitData['Placed Date'])),
        $splitData['splitQty'] * $splitData['product_cost'], null
    ]);
    $db->commit();
}

// -----------------------------------------------------------------------------------------------------------------------------------------------------------

if (isset($requestBody['setProductOos'])) {
    $oosData = $requestBody['setProductOos'];

    $stmt = $db->prepare("UPDATE products SET outOfStock = ? WHERE key = ?");
    $db->beginTransaction();
    $stmt->execute([$oosData['setState'], $oosData['key']]);
    $db->commit();
}

// -----------------------------------------------------------------------------------------------------------------------------------------------------------
/**
 * Get the shelfs from the database that match the shelf to be removed, decode the json string and remove the shelfToRemove and insert the updated locations
 */
if (isset($requestBody['removeShelf'])) {
    $shelfToRemove = $requestBody['removeShelf']['shelfToRemove'];

    $sql = "SELECT key, shelf_location FROM product_rooms WHERE shelf_location LIKE '%$shelfToRemove%'";
    $productLocations = $db->query($sql);
    $productLocations = $productLocations->fetchAll(PDO::FETCH_KEY_PAIR);

    $stmt = $db->prepare("UPDATE product_rooms SET shelf_location = ? WHERE key = ?");
    $db->beginTransaction();

    foreach ($productLocations as $key => $locations) {
        $newLocations = json_decode($locations);
        foreach ($newLocations as $index => $shelf) {
            if ($shelf == $shelfToRemove) {
                unset($newLocations[$index]);
            }
        }
        if (!count($newLocations)) $stmt->execute([null, $key]);
        else $stmt->execute([json_encode(array_values($newLocations)), $key]);
    }
    $db->commit();
}

// -----------------------------------------------------------------------------------------------------------------------------------------------------------

/**
 * Set the active column of an asin stored in the protected_asins table in the matrixCodes.db3 database
 */
if (isset($requestBody['setAsinState'])) {
    $asinData = $requestBody['setAsinState'];

    $asinState = $asinData['asinState'] == 'Active' ? 1 : 0;

    $stmt = $matrixDB->prepare("UPDATE protected_asins SET active = ? WHERE asin = ?");
    $matrixDB->beginTransaction();
    $stmt->execute([$asinState, $asinData['asin']]);
    $matrixDB->commit();
}

// -----------------------------------------------------------------------------------------------------------------------------------------------------------

if (isset($requestBody['setStock'])) {
    $stockData = $requestBody['setStock'];

    $qtyDiff =  $stockData['currentQty'] - $stockData['qtyInput'];

    $stmtStock = $db->prepare("UPDATE stock SET qty = ? WHERE key = ?");
    $stmtStockChange = $db->prepare("UPDATE stock_change SET qty = qty - ? WHERE key = ?");
    $db->beginTransaction();
    $stmtStock->execute([$stockData['qtyInput'], $stockData['selectedKey']]);
    $stmtStockChange->execute([$qtyDiff, $stockData['selectedKey']]);
    $db->commit();
}

// -----------------------------------------------------------------------------------------------------------------------------------------------------------

if (isset($requestBody['editSkuPlatforms'])) {
    $skuUrls = $requestBody['editSkuPlatforms'];

    $stmt = $db->prepare("UPDATE sku_am_eb SET am_id = ?, eb_id = ?, we_id = ?, pr_id =? WHERE sku = ?");
    $db->beginTransaction();
    $stmt->execute([$skuUrls['amazonSkuUrl'], $skuUrls['ebaySkuUrl'], $skuUrls['websiteSkuUrl'], $skuUrls['primeSkuUrl'], $skuUrls['sku']]);
    $db->commit();
}

// -----------------------------------------------------------------------------------------------------------------------------------------------------------

if (isset($requestBody['addNewPlatformSku'])) {
    $newSku = $requestBody['addNewPlatformSku'];

    $stmt = $db->prepare("INSERT INTO sku_am_eb VALUES (?,?,?,?,?)");
    $db->beginTransaction();
    $stmt->execute([$newSku['sku'], $newSku['newAmazonSku'], $newSku['newEbaySku'], $newSku['newWebsiteSku'], $newSku['newPrimeSku']]);
    $db->commit();
}

// -----------------------------------------------------------------------------------------------------------------------------------------------------------

/**
 * Pass the asin input and type of operation, plus the chosen csv to be uploaded, to TransparencyCodes class
 */
if (isset($_FILES['file'])) {
    // require_once 'TransparencyCodes.php';
    require_once 'C:\inetpub\wwwroot\FESP-REFACTOR\FespMVC\Modules\Transparanecy\TransparencyCodes.php';

    $ATC = new TransparencyCodes($_POST);
}

// -----------------------------------------------------------------------------------------------------------------------------------------------------------

/**
 * Reformat Array to use database required column names
 *
 * @param Array $keys
 * @param Array $data
 * @return void
 */
function buildQuery($keys, $type, $table, $conditions, $data = null)
{
    switch ($type) {
        case "UPDATE":
            $sql = "UPDATE $table SET ";
            $binds = array_flip($keys);
            $cols = implode(" = ?,", array_keys($binds));
            $sql .= trim("$cols = ? $conditions");
            break;

        case "INSERT":
            $sql = "INSERT INTO $table ";
            $binds = array_flip($keys);
            $cols = implode(", ", array_keys($binds));
            $cols = implode(", ", array_keys($binds));
            $cols_bind = (":" . implode(",:", array_keys($binds)));
            // Append key, assume insert insert
            $sql .=  trim("(key,$cols) VALUES (:key,$cols_bind) $conditions");
            break;

        default:
            break;
    }

    // If data parameter is passed to function format the array to use the required columns of the passed table
    if (isset($data)) {
        // Foreach index in the data array reformat to use the keys required for the database table
        $tmp = [];
        foreach ($data as $index => $record) {
            foreach ($record as $key => $value) {
                if (isset($keys[$key]) && $keys[$key] !== $key) {
                    $tmp[$index][$keys[$key]] = $value;
                } elseif (isset($keys[$key]) && $keys[$key] === $key) {
                    $tmp[$index][$key] = $value;
                }
                if ($type === "INSERT") {
                    $tmp[$index]['key'] = $record['Key'];
                }
            }
        }
        $data = $tmp;

        // Return sql string with the formatted data matching the keys
        return [
            'sql' => $sql,
            'data' => $data,
        ];
    }

    // Just return sql string
    return [
        'sql' => $sql,
    ];
}
