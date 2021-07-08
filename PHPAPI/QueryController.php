<?php
ini_set('memory_limit', '1024M');

/*
 * file: QueryController.php
 * located: http://deepthought:8080/stocksystem/QueryController.php
 * database: stock_control.db3
 * @author: Ryan Denby
 */

require_once 'C:/inetpub/wwwroot/database_paths.php';

// Define database
$db = new PDO('sqlite:' . $stock_control_db_path);
$matrixDb = new PDO('sqlite:' . $matrixCodes_db_path);
$dbPB = new PDO('sqlite:' . $pbi_db_path);

header('access-control-allow-origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: X-Requested-With,Origin,Content-Type,Cookie,Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('HTTP/1.1 204 No Content');
    die;
}

// $_GET['skuStats?key'] = 'acc';

// -----------------------------------------------------------------------------------------------------------------------------------------------------------

// Define units lookup
$unitLookup = [
    'q' => '',
    'l' => '(M)',
    'w' => '(KG)',
    'v' => '(Ltr)',
];

// Define room lookup
$roomLookup = [
    'm' => 'Middle',
    's' => 'Salt',
    'f' => 'Fert',
    'p' => 'Poison',
    'b' => 'Bamboo',
    'g' => 'Gallup',
    'c' => 'Cutting',
];

// -----------------------------------------------------------------------------------------------------------------------------------------------------------

/**
 * Get all products from the products table
 */
if (isset($_GET['viewProducts'])) {
    //Join products table to product_rooms and stock, via product key
    $sql = "SELECT products.*,
            stock.qty, stock.pkg_qty, stock.pkg_multiples, stock.days_amb, stock.days_red,
            product_rooms.room,product_rooms.shelf_location,
            product_cost_edits.changeDate, product_cost_edits.previousCost
            FROM products
            LEFT JOIN stock ON (products.key = stock.key)
            LEFT JOIN product_rooms ON (products.key = product_rooms.key)
            LEFT JOIN product_cost_edits ON (products.key = product_cost_edits.key)";
    $productsArr = $db->query($sql);
    $productsArr = $productsArr->fetchAll(PDO::FETCH_ASSOC);

    $tmp = [];
    foreach ($productsArr as $index => $product) {
        $tmp[$product['key']] = $product;
    }
    $productsArr = $tmp;

    $timeStampMinusMonth = date('Ymd', strtotime('1 month ago'));

    // Get weekly sales for each product / the daily rate they sell
    $sql = "SELECT key, qty, date FROM stock_change WHERE date >= $timeStampMinusMonth ORDER BY date DESC";
    $stockChange = $db->query($sql);
    $stockChange = $stockChange->fetchAll(PDO::FETCH_ASSOC);

    // Get list of keys already in pending orders, used to color code the keys that are currently on order blue
    $sql = "SELECT key FROM ordered_stock WHERE status = 'Pending'";
    $pendingKeys = $db->query($sql);
    $pendingKeys = array_flip($pendingKeys->fetchAll(PDO::FETCH_COLUMN));

    $tmp = [];
    foreach ($stockChange as $index => $rec) {
        $tmp[$rec['key']][$rec['date']] = $rec['qty'];
    }
    $stockChange = $tmp;

    $tmp = [];
    $pastWeekSales = [];
    foreach ($stockChange as $key => $month) {
        $monthlyTotal = 0;
        foreach ($month as $day => $qty) {
            $lastDay = array_keys($month);
            $lastDay = end($lastDay);

            if ($day != $lastDay) {
                $previousDayQty = next($month);
                $tmp[$key][$day] = $previousDayQty - $qty;
                $monthlyTotal += $previousDayQty - $qty;
            }
        }

        $daysToOOS = "NO SALES";
        if ($monthlyTotal) {
            $daysToOOS = number_format((float)$productsArr[$key]['qty'] / (float)($monthlyTotal / count($tmp[$key])), 2, '.', '');
            if ($daysToOOS <  0 || $daysToOOS == 0.00) {
                $daysToOOS = 0;
            }
        }

        if (isset($tmp[$key]) && is_array($tmp[$key])) {
            $pastWeekSales[$key] = array_slice($tmp[$key], 0, 7, true);
            $weekSalesString = "";
            $weekTotalSales = 0;
            foreach (array_reverse($pastWeekSales[$key], true) as $date => $sales) {
                $weekTotalSales += $sales;
                $day = date('D', strtotime($date));
                $weekSalesString .= "$day: $sales ";
            }
            $weekSalesString .= "Total: $weekTotalSales";
            $pastWeekSales[$key] = $weekSalesString;
        }

        $tmp[$key] = $daysToOOS;
        unset($previousQty);
    }
    $stockChange = $tmp;

    // Sort array by days to oos value
    asort($stockChange, SORT_NATURAL);

    // Build main products array
    $tmp = [];
    foreach ($productsArr as $key => $product) {
        // Decode from json string, if no values are set, set empty array
        $shelf_locations = json_decode($product['shelf_location']);
        if ($shelf_locations == null || count($shelf_locations) == 0) {
            $shelf_locations = '';
        }

        $room = "NO ROOM";
        if (isset($roomLookup[$product['room']])) {
            $room = $roomLookup[$product['room']];
        }

        $yellowThreshold = 28;
        if ($product['yellowThreshold']) {
            $yellowThreshold = (int)$product['yellowThreshold'];
        }

        $redThreshold = 14;
        if ($product['redThreshold']) {
            $redThreshold = (int)$product['redThreshold'];
        }

        $previosCostDate = $product['changeDate'] ? date('d-m-y', $product['changeDate']) : 'N/A';

        $tmp[$key] = [
            'Cat' => $product['cat'],
            'Key' => $product['key'],
            'Unit' => $unitLookup[$product['unit']],
            'Product' => $product['product'],
            'product_cost' => $product['product_cost'],
            'Supplier' => $product['primary_supplier'],
            'secondary_supplier' => $product['secondary_supplier'],
            'consumable' => $product['consumable'],
            'to_be_hidden' => $product['to_be_hidden'],
            'Room' => $room,
            'Locations' => $shelf_locations,
            'pkg_qty' => $product['pkg_qty'],
            'pkg_multiples' => $product['pkg_multiples'],
            'Qty' => $product['qty'] . ' ' . $unitLookup[$product['unit']],
            'days_amb' => $product['days_amb'],
            'days_red' => $product['days_red'],
            'outOfStock' => $product['outOfStock'],
            'yellowThreshold' => $yellowThreshold,
            'redThreshold' => $redThreshold,
            'previousCost' => $product['previousCost'],
            'previousCostDate' => $previosCostDate,
        ];
    }
    $productsArr = $tmp;

    $tmp = [];
    foreach ($stockChange as $key => $value) {
        $tmp[$key]['Days To OOS'] = $value;
        $tmp[$key] += $productsArr[$key];
    }

    foreach ($productsArr as $key => $product) {
        if (!isset($tmp[$key])) {
            $tmp[$key]['Days To OOS'] = 'NO SALES';
            $tmp[$key] += $product;
        };
    }
    $productsArr = $tmp;

    // Build array of suppliers for drop down filter
    $productSuppliers = [];
    foreach ($productsArr as $index => $product) {
        $productSuppliers[$product['Supplier']] = $index;
    }
    unset($productSuppliers['']);
    ksort($productSuppliers);

    // Array of pending order numbers
    $sql = "SELECT ord_num FROM ordered_stock WHERE status = 'Pending' ORDER BY ord_num DESC";
    $existingOrderNumbers = $db->query($sql);
    // Only one result, which is the highest value in the column + 1 for the next order number
    $existingOrderNumbers = array_flip($existingOrderNumbers->fetchAll(PDO::FETCH_COLUMN));
    // Next order number to be used
    $sql = "SELECT MAX(ord_num) as maxOrdNum FROM ordered_stock ORDER BY ord_num DESC";
    $nextOrdNum = $db->query($sql);
    $nextOrdNum = (int)$nextOrdNum->fetchAll(PDO::FETCH_COLUMN)[0] + 1;

    // List of shelfs and each product that shelf contains
    $sql = "SELECT key, shelf_location FROM product_rooms WHERE shelf_location IS NOT NULL";
    $allProdShelfs = $db->query($sql);
    $allProdShelfs = $allProdShelfs->fetchAll(PDO::FETCH_ASSOC);

    $allShelfs = [];
    foreach ($allProdShelfs as $index => $prod) {
        if ($prod['shelf_location'] != [] && $prod['shelf_location'] != "") {
            $shelfs = (array)json_decode($prod['shelf_location']);
            foreach ($shelfs as $shelf) {
                $allShelfs[$shelf][] = $prod['key'];
            }
        }
    }

    $response = [
        'products' => $productsArr,
        'suppliers' => $productSuppliers,
        'rooms' => $roomLookup,
        'nextOrdNumber' => $nextOrdNum,
        'pendingKeys' => $pendingKeys,
        'existingOrderNumbers' => $existingOrderNumbers,
        'allShelfs' => $allShelfs,
        'pastWeekSales' => $pastWeekSales,
    ];

    echo json_encode($response, JSON_PRETTY_PRINT);
}

// -----------------------------------------------------------------------------------------------------------------------------------------------------------

/**
 * Get pending product orders from the ordered_stock table
 */
if (isset($_GET['pendingOrders'])) {
    // Query database for the required data for the pending orders page
    $sql = "SELECT ordered_stock.*,
            products.product,products.unit,products.product_cost,
            stock.pkg_qty,stock.pkg_multiples
            FROM ordered_stock
            LEFT JOIN products ON (ordered_stock.key = products.key)
            LEFT JOIN stock ON (ordered_stock.key = stock.key)
            WHERE status = 'Pending'
            OR status = 'Remainder'
            ORDER BY ord_num DESC";
    $pendingStock = $db->query($sql);
    $pendingStock = $pendingStock->fetchAll(PDO::FETCH_ASSOC);

    $tmp = [];
    foreach ($pendingStock as $index => $product) {
        $tmp[] = [
            'Key' => $product['key'],
            'Product' => $product['product'],
            'Qty' => $product['qty'],
            'Order Number' => $product['ord_num'],
            'Supplier' => $product['supplier'],
            'Status' => $product['status'],
            'Delivery Date' => date('Y-m-d', strtotime($product['exp_del_date'])),
            'Placed Date' => date('Y-m-d', strtotime($product['datetime'])),
            'Item Cost' => $product['item_cost'],
            'unit' => $product['unit'],
            'product_cost' => $product['product_cost'],
            'pkg_qty' => $product['pkg_qty'],
            'pkg_multiples' => $product['pkg_multiples'],
            'newShelf' => json_decode($product['newShelf'], true),
        ];
    }
    $pendingStock = $tmp;

    // Array of all suppliers belonging to the products in ordered_stock table and array of order numbers in the ordered_stock table
    $pendingSuppliers = [];
    foreach ($pendingStock as $index => $product) {
        $pendingSuppliers[$product['Supplier']] = $index;
        $pendingOrderNumbers[$product['Order Number']] = $index;
    }
    ksort($pendingSuppliers);

    // Build response
    $response = [
        'pendingProducts' => $pendingStock,
        'pendingSuppliers' => $pendingSuppliers,
        'pendingOrderNumbers' => $pendingOrderNumbers,
    ];

    echo json_encode($response, JSON_PRETTY_PRINT);
}

// -----------------------------------------------------------------------------------------------------------------------------------------------------------

/**
 * Data to display in the footer of every page
 */
if (isset($_GET['footerData'])) {
    $sql = "SELECT products.key, products.product_cost, stock.qty
                        FROM products
                        LEFT JOIN stock on (products.key = stock.key)";

    $totalStockValue = $db->query($sql);
    $totalStockValue = $totalStockValue->fetchAll(PDO::FETCH_ASSOC);

    // Loop through each product that has a qty greater than 0, multiply this by the product_cost and add to the total for all products
    $total = 0;
    foreach ($totalStockValue as $index => $product) {
        if (is_numeric($product['product_cost']) && $product['qty'] > 0) {
            $total += ($product['qty'] * $product['product_cost']);
        }
    }
    $totalStockValue = number_format($total, 2, '.', ',');

    // Count the number of products, this will include hidden products
    $sql = "SELECT COUNT(key) FROM products";
    $totalProducts = $db->query($sql);
    $totalProducts = $totalProducts->fetchAll(PDO::FETCH_COLUMN)[0];

    // Count the number of skus in the system
    $sql = "SELECT COUNT(sku) FROM sku_atts";
    $totalSkus = $db->query($sql);
    $totalSkus = $totalSkus->fetchAll(PDO::FETCH_COLUMN)[0];

    $response = [
        'totalStockValue' => $totalStockValue,
        'totalProducts' => number_format($totalProducts, 0, '.', ','),
        'totalSkus' => number_format($totalSkus, 0, '.', ','),
    ];

    echo json_encode($response, JSON_PRETTY_PRINT);
}

// -----------------------------------------------------------------------------------------------------------------------------------------------------------

/**
 * Return list of keys to products and an array containg the history of updates for each key
 */
if (isset($_GET["updateProducts"])) {
    // All non hidden products joined on records of updates to stock stored in the updated_stock table
    $sql = "SELECT products.key, products.product, products.unit,
            updated_stock.qty as insertedQty, updated_stock.deliveryID, updated_stock.datetime,
            stock.qty, stock.pkg_qty, stock.pkg_multiples
            FROM products
            LEFT JOIN updated_stock ON (products.key = updated_stock.key)
            LEFT JOIN stock ON (products.key = stock.key)
            WHERE products.to_be_hidden IS null
            ORDER BY updated_stock.datetime DESC";
    $updateProducts = $db->query($sql);
    $updateProducts = $updateProducts->fetchAll(PDO::FETCH_ASSOC);

    // Filter array to contain the current stock qty of that item, plus the pkg qty and multiples
    $keyProducts = [];
    foreach ($updateProducts as $key => $product) {
        $keyProducts[$product['key']] = [
            'key' => $product['key'],
            'product' => $product['product'],
            'unit' => $unitLookup[$product['unit']],
            'qty' => $product['qty'],
            'pkg_qty' => $product['pkg_qty'],
            'pkg_multiple' => $product['pkg_multiples'],
        ];
    }

    $tmp = [];
    foreach ($updateProducts as $index => $record) {
        if ($record['insertedQty']) {
            $tmp[$record['key']][] = [
                'key' => $record['key'],
                'Product' => $record['product'],
                'Qty' => $record['insertedQty'],
                'DeliveryID' => $record['deliveryID'],
                'Date' => date('Y-m-d', strtotime($record['datetime'])),
            ];
        }
    }
    asort($tmp);
    $updateProducts = $tmp;

    $response = [
        'updateHistory' => $updateProducts,
        'keyProducts' => $keyProducts,
        'rooms' => $roomLookup,
    ];

    echo json_encode($response, JSON_PRETTY_PRINT);
}

// -----------------------------------------------------------------------------------------------------------------------------------------------------------

if (isset($_GET['stockSkuData'])) {
    $sql = "SELECT key, product FROM products";
    $skuProducts = $db->query($sql);
    $skuProducts = $skuProducts->fetchAll(PDO::FETCH_ASSOC);

    $sql = "SELECT sku_atts.sku, sku_atts.atts,
            sku_am_eb.am_id, sku_am_eb.eb_id, sku_am_eb.we_id, sku_am_eb.pr_id
            FROM sku_atts
            LEFT JOIN sku_am_eb ON (sku_atts.sku = sku_am_eb.sku)";
    $keySkuPlatids = $db->query($sql);
    $keySkuPlatids = $keySkuPlatids->fetchAll(PDO::FETCH_ASSOC);

    $tmp = [];
    foreach ($keySkuPlatids as $index => $values) {
        if (isset($values['atts'])) {
            $formattedAtts = explode(',', $values['atts']);
            foreach ($formattedAtts as $index => $key) {
                $key = strtok($key, '|');
                $tmp[$key][$values['sku']] = [
                    'sku' => $values['sku'],
                    'am_id' => $values['am_id'],
                    'eb_id' => $values['eb_id'],
                    'we_id' => $values['we_id'],
                    'pr_id' => $values['pr_id'],
                ];
            }
        }
    }
    $keySkuPlatids = $tmp;

    $response = [
        'keySkuPlatIds' => $keySkuPlatids,
        'skuProducts' => $skuProducts,
    ];

    echo json_encode($response, JSON_PRETTY_PRINT);
}

// -----------------------------------------------------------------------------------------------------------------------------------------------------------

if (isset($_GET['orderHistory'])) {
    // Inlcude last 3 months
    $timeStampMinus3Months = strtotime('-3 months');

    $sql = "SELECT * FROM product_orders_prices WHERE date_placed >= $timeStampMinus3Months";
    $stockOrders = $db->query($sql);
    $stockOrders = $stockOrders->fetchAll(PDO::FETCH_ASSOC);

    $sql = "SELECT ordered_stock.*,
            products.product
            FROM ordered_stock
            LEFT JOIN products ON (ordered_stock.key = products.key)
            WHERE datetime >= $timeStampMinus3Months";
    $stockHistory = $db->query($sql);
    $stockHistory = $stockHistory->fetchAll(PDO::FETCH_ASSOC);

    $tmp = [];
    foreach ($stockOrders as $index => $values) {
        foreach ($stockHistory as $i => $value) {
            if ($value['ord_num'] === $values['ord_num']) {
                $date = "";
                if (isset($value['exp_del_date'])) {
                    $delDate = date('Y-m-d', strtotime($value['exp_del_date']));
                }
                $tmp[$values['ord_num']]['items'][] = [
                    'Key' => $value['key'],
                    'Product' => $value['product'],
                    'Qty' => $value['qty'],
                    'Order Number' => $value['ord_num'],
                    'Delivery Number' => $value['del_num'],
                    'Supplier' => $value['supplier'],
                    'Status' => $value['status'],
                    'Signed' => $value['signed'],
                    'Date Delivered' => $delDate,
                    'Date Placed' => date('Y-m-d', strtotime($value['datetime'])),
                    'Item Cost' => $value['item_cost'],
                ];
            }
        }
        $date = "";
        if (isset($values['date_delivered'])) {
            $date = date('Y-m-d', strtotime($values['date_delivered']));
        }

        $tmp[$values['ord_num']]['Order Number'] = $values['ord_num'];
        $tmp[$values['ord_num']]['Supplier'] = $values['supplier'];
        $tmp[$values['ord_num']]['Date Placed'] = date('Y-m-d', strtotime($values['date_placed']));
        $tmp[$values['ord_num']]['Date Delivered'] = $date;
        $tmp[$values['ord_num']]['Order Value'] = $values['ord_value'];
        $tmp[$values['ord_num']]['Delivery Number'] = $values['delivery_number'];
        $tmp[$values['ord_num']]['Status'] = $values['status'];
    }
    $stockHistory = $tmp;

    /**
     * Build lookup array of order numbers with the value of the status of the order, order is only marked as complete
     * if all the items belonging that order number are complete or cancelled, no pending items, orders can be complete with
     * individual items cancelled. Order numbers will be marked as cancelled if all items belonging to that order number
     * have the status cancelled, else assume order number is still pending
     */
    foreach ($stockHistory as $index => $order) {
        if (isset($order['items'])) {
            $countItems = count($order['items']);
            $j = 0; // Count of items that have status complete for current order number
            $i = 0; // Count of item that have status cancelled for current order number
            foreach ($order['items'] as $itemIndx => $item) {
                // Count cancelled items as order numbers can still be completed with cancelled items
                if ($item['Status'] === 'Complete' || $item['Status'] === 'Cancelled') {
                    $j++;
                }
                if ($item['Status'] === 'Cancelled') {
                    $i++;
                }
            }
            if (isset($j)) {
                // count of complete items j, if equals count of items for that order, then order number is complete.
                // if count of cancelled items i not equal to j then order was completed with some cancelled items, but still complete
                if (isset($countItems) && $countItems == $j && $j != $i) {
                    $stockHistory[$index]['Status'] = 'Complete';
                }
                // if count of cancelled items i is equal to total items in order number, then order is cancelled
                elseif (isset($countItems) && $countItems == $i) {
                    $stockHistory[$index]['Status'] = 'Cancelled';
                } else {
                    $stockHistory[$index]['Status'] = 'Pending';
                }
            }
        }
    }

    // Update the status of orders in the product_orders_prices, not ideal to do this everytime the order history tab is opened but rare that anything changes
    // and isnt demanding
    $stmtProductOrderPrices = $db->prepare("UPDATE product_orders_prices SET status = ? WHERE ord_num = ?");
    $db->beginTransaction();
    foreach ($stockHistory as $index => $order) {
        if (isset($order['Status'])) {
            $stmtProductOrderPrices->execute([$order['Status'], $order['Order Number']]);
        }
    }
    $db->commit();

    $stockHistory = array_reverse($stockHistory);

    $sql = "SELECT supplier FROM ordered_stock";
    $historySuppliers = $db->query($sql);
    $historySuppliers = array_flip($historySuppliers->fetchAll(PDO::FETCH_COLUMN));

    $response = [
        'stockHistory' => $stockHistory,
        'historySuppliers' => $historySuppliers,
    ];

    echo json_encode($response, JSON_PRETTY_PRINT);
}

// -----------------------------------------------------------------------------------------------------------------------------------------------------------

if (isset($_GET['productInfo?key'])) {
    $key = $_GET['productInfo?key'];

    // Get the products information
    $sql = "SELECT products.*,
            stock.qty
            FROM products
            LEFT JOIN stock ON (products.key = stock.key)
            WHERE products.key = '$key'";
    $productInfo = $db->query($sql);
    $productInfo = $productInfo->fetchAll(PDO::FETCH_ASSOC);

    // Convert into actual unit of the product
    $productInfo[0]['unit'] = $unitLookup[$productInfo[0]['unit']];

    $greaterThan6Month = strtotime('-6 months');

    // Get the orders that the key belongs to, greater than 6 months ago
    $sql = "SELECT * FROM ordered_stock WHERE key = '$key' AND datetime > $greaterThan6Month ORDER BY ord_num DESC";
    $productOrders = $db->query($sql);
    $productOrders = $productOrders->fetchAll(PDO::FETCH_ASSOC);

    $tmp = [];
    foreach ($productOrders as $index => $order) {
        $tmp[] = [
            'Key' => $order['key'],
            'Qty' => $order['qty'],
            'Orde Number' => $order['ord_num'],
            'Delivery Number' => $order['del_num'],
            'Supplier' => $order['supplier'],
            'Status' => $order['status'],
            'Signed' => $order['signed'],
            'Delivery Date' => date("Y-m-d", strtotime($order['exp_del_date'])),
            'Placed Date' => date("Y-m-d", strtotime($order['datetime'])),
            'Item Cost' => $order['item_cost'],
        ];
    }
    $productOrders = $tmp;

    $startOfLastYear = date("Ymd", strtotime('first day of january last year'));
    $endOfLastYear = date("Ymd", strtotime('first day of january this year'));

    // Get the change in stock for the product for each day
    $sql = "SELECT date, qty FROM stock_change WHERE key = '$key' AND date >= $startOfLastYear AND date <= $endOfLastYear ORDER BY date";
    $keyStockChange = $db->query($sql);
    $keyStockChange = $keyStockChange->fetchAll(PDO::FETCH_KEY_PAIR);

    // Get most recent date from stock_change table
    $startOfThisYear = date("Ymd", strtotime('first day of january this year'));
    $endOfMostRecentMonth = date("Ymd", strtotime('- 1 day'));

    // Get the key stock change for this year
    $sql = "SELECT date, qty FROM stock_change WHERE key = '$key' AND date >= $startOfThisYear AND date <= $endOfMostRecentMonth ORDER BY date";
    $thisYearStockChange = $db->query($sql);
    $thisYearStockChange = $thisYearStockChange->fetchAll(PDO::FETCH_KEY_PAIR);

    //Get rolling 30 days sales stats
    $start30Days = date("Ymd", strtotime('- 31 days'));
    $end30Days = date("Ymd", strtotime('- 1 days'));

    $sql = "SELECT date, qty FROM stock_change WHERE key = '$key' AND date >= $start30Days AND date <= $end30Days ORDER BY date";
    $rolling30Days = $db->query($sql);
    $rolling30Days = $rolling30Days->fetchAll(PDO::FETCH_KEY_PAIR);

    // Get recent inserts into the stock system for this product
    $sql = "SELECT * FROM updated_stock WHERE key = '$key' ORDER BY datetime DESC";
    $productHistory = $db->query($sql);
    $productHistory = $productHistory->fetchAll(PDO::FETCH_ASSOC);

    $tmp = [];
    foreach ($productHistory as $index => $product) {
        $placedDate = 'No Set';
        if (isset($product['datetime'])) {
            $deliveryDate = date("Y-m-d", strtotime($product['datetime']));
        }
        $tmp[] = [
            'Key' => $product['key'],
            'Qty' => $product['qty'],
            'Delivery Id' => $product['deliveryID'],
            'Delivery Date' => $deliveryDate,
        ];
    }
    $productHistory = $tmp;

    // Format into sales for each day for last year
    $tmp = [];
    foreach ($keyStockChange as $date => $qty) {
        $lastDay = array_keys($keyStockChange);
        $lastDay = end($lastDay);

        if ($date != $lastDay) {
            $nextDate = DateTime::createFromFormat('Ymd', $date + 1);
            $nextDate = $nextDate->format('Ymd');
            $tmp[$date] = round($qty - $keyStockChange[$nextDate]);
        }
    }
    $keyStockChange = $tmp;

    // Format into sales for each day of this year
    $tmp = [];
    foreach ($thisYearStockChange as $date => $qty) {
        $lastDay = array_keys($thisYearStockChange);
        $lastDay = end($lastDay);

        if ($date != $lastDay) {
            $nextDate = DateTime::createFromFormat('Ymd', $date + 1);
            $nextDate = $nextDate->format('Ymd');
            $tmp[$date] = round($qty - $thisYearStockChange[$nextDate]);
        }
    }
    $thisYearStockChange = $tmp;

    // Format into sales for each day for rolling 30
    $tmp = [];
    foreach ($rolling30Days as $date => $qty) {
        $lastDay = array_keys($rolling30Days);
        $lastDay = end($lastDay);

        if ($date != $lastDay) {
            $nextDate = DateTime::createFromFormat('Ymd', $date + 1);
            $nextDate = $nextDate->format('Ymd');

            $date = DateTime::createFromFormat('Ymd', $date - 1);
            $date = $date->format('M-d');
            $tmp[$date] = round($qty - $rolling30Days[$nextDate]);
        }
    }
    $rolling30Days = $tmp;

    $startWeek = date("Ymd", strtotime('- 8 days'));
    $endWeek = date("Ymd", strtotime('now'));

    //Get the change in stock for the product for past week
    $sql = "SELECT date, qty FROM stock_change WHERE key = '$key' AND date >= $startWeek AND date <= $endWeek ORDER BY date";
    $salesPastWeek = $db->query($sql);
    $salesPastWeek = $salesPastWeek->fetchAll(PDO::FETCH_KEY_PAIR);

    // Get sales by day for past week
    $tmp = [];
    foreach ($salesPastWeek as $date => $qty) {
        $lastDay = array_keys($salesPastWeek);
        $lastDay = end($lastDay);

        if ($date != $lastDay) {
            $nextDate = DateTime::createFromFormat('Ymd', $date + 1);
            $nextDate = $nextDate->format('Ymd');
            $tmp[$date +  1] = round($qty - $salesPastWeek[$nextDate]);
        }
    }
    $salesPastWeek = $tmp;

    // Build array to de displayed in the apex charts, using the names of the days from the previous week
    $totalSalesPastWeek = 0;
    $tmp = [];
    foreach ($salesPastWeek as $index => $dayQty) {
        $day = DateTime::createFromFormat('Ymd', $index);
        $day = $day->format('D');

        $dayQty = str_replace(",", "", $dayQty);
        $tmp[$day] = round($dayQty);
        $totalSalesPastWeek += round($dayQty);
    }
    $salesPastWeek = $tmp;

    // Build array of stats for previous year predictions
    $yearPredictions = [];
    $thisYearPredicitons = [];
    $lastYearColumns = [];
    $j = 0;
    for ($i = 1; $i < 13; $i++) {
        $startMonth = DateTime::createFromFormat('!m', $i);
        $endMonth = DateTime::createFromFormat("!m", $i + 1);
        $startMonth = $startMonth->format("F");
        $endMonth = $endMonth->format("F");

        $startDateLast = date("Ymd", strtotime('first day of ' . $startMonth . ' last year'));
        $endDateLast = date("Ymd", strtotime('first day of ' . $endMonth . ' last year'));

        $startDateThis = date("Ymd", strtotime('first day of ' . $startMonth));
        $endDateThis = date("Ymd", strtotime('first day of ' . $endMonth));

        if ($startMonth == 'December') {
            $endDateLast = date("Ymd", strtotime('first day of ' . $endMonth));
            $endDateThis = date("Ymd", strtotime('first day of ' . $endMonth));
        }

        $lastYearPeriod = getPeriod($startDateLast, $endDateLast, $keyStockChange);
        $thisYearPeriod = getPeriod($startDateThis, $endDateThis, $thisYearStockChange);

        // Increase by 10% margin to account for company growth from previous year
        $yearPredictions[$j] = ceil(periodTotalSales($lastYearPeriod) * 1.1);
        $thisYearPeriodTotal = ceil(periodTotalSales($thisYearPeriod));

        // Ignore months with no sales for this year
        $thisYearPredicitons[$j] = $thisYearPeriodTotal == 0 ? null : $thisYearPeriodTotal;
        $j++;
    }
    $totalSalesYearPrediction = periodTotalSales($yearPredictions);

    // Roughly seperate sales into weeks for previous years sales
    $tmp = [];
    $j = 1;
    $weekSales = 0;
    $dayCount = 0;
    foreach ($keyStockChange as $date => $qty) {
        $weekSales += $qty;
        $dayCount++;

        if ($dayCount == 7) {
            $endDateRange = date('M-d', strtotime($date));
            $startDateRange = date('M-d', strtotime($date . '- 6 days'));
            $tmp[$startDateRange . '-' . $endDateRange] = ceil($weekSales * 1.1);
            $dayCount = 0;
            $weekSales = 0;
            $j++;
        }
    }
    $keyStockChange = $tmp;

    // Roughly seperate sales into weeks for this years sales
    $tmp = [];
    $j = 1;
    $weekSales = 0;
    $dayCount = 0;
    foreach ($thisYearStockChange as $date => $qty) {
        $weekSales += $qty;
        $dayCount++;

        if ($dayCount == 7) {
            $tmp[$j] = round($weekSales);
            $dayCount = 0;
            $weekSales = 0;
            $j++;
        }
    }
    $thisYearStockChange = $tmp;

    $yearQuarters = [];
    for ($i = 0; $i < 12; $i += 3) {
        $yearQuarters[] = periodTotalSales(array_slice($yearPredictions, $i, 3));
    }

    $response = [
        'productInfo' => $productInfo,
        'productOrders' => $productOrders,
        'productHistory' => $productHistory,
        'rolling30Total' => periodTotalSales($rolling30Days),
        'rolling30Columns' => array_keys($rolling30Days),
        'salesWeekColumns' => array_keys($salesPastWeek),
        'salesPastWeek' => array_values($salesPastWeek),
        'totalSalesPastWeek' => number_format($totalSalesPastWeek, 2),
        'yearPredictions' => $yearPredictions,
        'keyStockChange' => $keyStockChange,
        'thisYearSales' => $thisYearStockChange,
        'rolling30DaySales' => $rolling30Days,
        'totalSalesYearPrediction' => [
            'total' => number_format($totalSalesYearPrediction, 2),
            'quarter1' => $yearQuarters[0],
            'quarter2' => $yearQuarters[1],
            'quarter3' => $yearQuarters[2],
            'quarter4' => $yearQuarters[3],
        ],
    ];

    echo json_encode($response, JSON_PRETTY_PRINT);
}

// -----------------------------------------------------------------------------------------------------------------------------------------------------------
/**
 * Get Products that were put back into stock, by checking for products that are marked as outOfStock null in products and had the value
 * of outOfStock 1 in stock_change the day before
 */
if (isset($_GET['cisProducts'])) {
    $yesterDay = date("Ymd", strtotime('-1 day'));
    $sql = "SELECT stock_change.key,
            products.key
            FROM stock_change
            LEFT JOIN products ON (stock_change.key = products.key)
            WHERE stock_change.date = $yesterDay AND stock_change.outOfStock = 1 AND products.outOfStock IS NULL";
    $cisKeys = $db->query($sql);
    $cisKeys = array_flip($cisKeys->fetchAll(PDO::FETCH_COLUMN));

    echo json_encode($cisKeys, JSON_PRETTY_PRINT);
}

// -----------------------------------------------------------------------------------------------------------------------------------------------------------
/**
 * Get Products/Product Orders due for delivery today, will be passed back and the presented to the user in csv format
 */
if (isset($_GET['ddProducts'])) {
    $dateToday = time();
    $dateToday = date('Ymd', $dateToday);

    // $sql = "SELECT * FROM ordered_stock WHERE status = 'Pending' AND exp_del_date = '$dateToday'";
    $sql = "SELECT ordered_stock.*,
            products.product
            FROM ordered_stock
            LEFT JOIN products ON (ordered_stock.key = products.key)
            WHERE status = 'Pending' AND exp_del_date = $dateToday
            ORDER BY ord_num";
    $ddProducts = $db->query($sql);
    $ddProducts = $ddProducts->fetchAll(PDO::FETCH_ASSOC);

    $tmp = [];
    foreach ($ddProducts as $index => $product) {
        $product['exp_del_date'] = date("Y-m-d", strtotime($product['exp_del_date']));
        $product['datetime'] = date("Y-m-d", strtotime($product['datetime']));
        $tmp[] = [
            'Key' => $product['key'],
            'Product' => $product['product'],
            'Qty' => $product['qty'],
            'Order Number' => $product['ord_num'],
            'Delivery Date' => $product['exp_del_date'],
            'Placed Date' => $product['datetime'],
            'Supplier' => $product['supplier'],
        ];
    }
    $ddProducts = $tmp;

    echo json_encode($ddProducts, JSON_PRETTY_PRINT);
}

// -----------------------------------------------------------------------------------------------------------------------------------------------------------

if (isset($_GET['outOfStockDeliveries'])) {
    $sql = "SELECT ordered_stock.key  as Key, products.product as Product, ordered_stock.qty as Qty, ordered_stock.ord_num as OrderNumber, ordered_stock.supplier as Supplier, 
            ordered_stock.datetime as PlacedDate, ordered_stock.exp_del_date as DeliveryDate
            FROM ordered_stock
            JOIN products ON (ordered_stock.key = products.key)
            WHERE products.outOfStock = 1 AND ordered_stock.status = 'Pending'";
    $oosProductDeliveries = $db->query($sql);
    $oosProductDeliveries = $oosProductDeliveries->fetchAll(PDO::FETCH_ASSOC);

    $ordersArr = [];
    $longWait = [];
    foreach ($oosProductDeliveries as $index => $product) {
        $monthsAgo3 = strtotime('- 3 months');
        $placedDate = strtotime($product['PlacedDate']);

        if ($placedDate >= $monthsAgo3) {
            $ordersArr[] = $product;
        }
    }

    // Seperate orders made within the last 3 months from orders older than 3 months
    $ordersArr[] = array_combine(array_keys($ordersArr[0]), array_fill(0, count(array_keys($ordersArr[0])), null));
    $ordersArr[] = array_combine(array_keys($ordersArr[0]), array_fill(0, count(array_keys($ordersArr[0])), 'LONG WAIT ORDERS'));
    $ordersArr[] = array_combine(array_keys($ordersArr[0]), array_fill(0, count(array_keys($ordersArr[0])), null));

    foreach ($oosProductDeliveries as $index => $product) {
        $placedDate = strtotime($product['PlacedDate']);

        if ($placedDate <= $monthsAgo3) {
            $ordersArr[] = $product;
        }
    }

    echo json_encode($ordersArr, JSON_PRETTY_PRINT);
}

// -----------------------------------------------------------------------------------------------------------------------------------------------------------

if (isset($_GET['protectedAsins'])) {
    $sql = "SELECT protected_asins.asin, protected_asins.active,
             count(amazon_qrcodes.asin) as asinCount
             FROM protected_asins
             LEFT JOIN amazon_qrcodes ON (protected_asins.asin = amazon_qrcodes.asin)
             GROUP BY protected_asins.asin
             ORDER BY asinCount";
    $protectedAsins = $matrixDb->query($sql);
    $protectedAsins = $protectedAsins->fetchAll(PDO::FETCH_ASSOC);

    $sql = "SELECT asin, count(asin) as asinSold FROM qrcode_records GROUP BY asin";
    $asinsSold = $matrixDb->query($sql);
    $asinsSold = $asinsSold->fetchAll(PDO::FETCH_KEY_PAIR);

    $tmp = [];
    foreach ($protectedAsins as $asin) {
        $status = $asin['active'] == 1 ? 'Active' : 'Disabled';
        $asinSold = isset($asinsSold[$asin['asin']]) ? $asinsSold[$asin['asin']] : 0;

        $tmp[$asin['asin']] = [
            'Asin' => $asin['asin'],
            'Count' => $asin['asinCount'],
            'Sold' => $asinSold,
            'Status' => $status,
        ];
    }
    $protectedAsins = $tmp;

    $response = [
        'AsinData' => $protectedAsins,
    ];

    echo json_encode($response, JSON_PRETTY_PRINT);
}

// -----------------------------------------------------------------------------------------------------------------------------------------------------------

if (isset($_GET['stockPredictions'])) {
    $sql = "SELECT products.cat as Cat, products.key as Key, products.product as Product, products.unit,
            stock.qty as Qty
            FROM products
            LEFT JOIN stock ON (products.key = stock.key)";
    $spProducts = $db->query($sql);
    $spProducts = $spProducts->fetchAll(PDO::FETCH_ASSOC);

    $tmp = [];
    foreach ($spProducts as $product) {
        $tmp[$product['Key']] = $product;
        $tmp[$product['Key']]['unit'] = $unitLookup[$product['unit']];
    }
    $spProducts = $tmp;

    // Start and end of period we need to pull data for
    $startOfLastYear = date('Ymd', strtotime('first day of january last year'));
    $mostRecentDay = date('Ymd', strtotime('- 1 day'));

    $sql = "SELECT * FROM stock_change WHERE date BETWEEN $startOfLastYear AND $mostRecentDay ORDER BY date ASC";
    $spChange = $db->query($sql);
    $spChange = $spChange->fetchAll(PDO::FETCH_ASSOC);

    // Format stock change into key => date => qty
    $tmp = [];
    foreach ($spChange as $rec) {
        $date =
            $tmp[$rec['key']][$rec['date']] = $rec['qty'];
    }
    $spChange = $tmp;

    // Change from qty on date to sales on date
    $tmp = [];
    foreach ($spChange as $key => $sales) {
        $lastKey = key(array_slice($sales, -1, 1, true));
        foreach ($sales as $date => $qty) {
            if ($date != $lastKey) {
                $nextDate = date('Ymd', strtotime($date . ' + 1 day'));
                $tmp[$key][$date] = number_format(($qty - $sales[$nextDate]), 2, '.', '');
            }
        }
    }
    $spChange = $tmp;

    // Total up data
    $yearPredictions = [];
    $yearQuarters = [];
    $yearTotals = [];

    // Month Count
    $year1 = date('Y', strtotime($startOfLastYear));
    $year2 = date('Y', strtotime($mostRecentDay));
    $month1 = date('m', strtotime($startOfLastYear));
    $month2 = date('m', strtotime($mostRecentDay));
    $monthsCount = (($year2 - $year1) * 12) + ($month2 - $month1);

    foreach ($spChange as $key => $sales) {
        for ($i = 0; $i < $monthsCount; $i++) {
            $startDate = date('Ymd', strtotime($startOfLastYear . ' +' . $i . 'month'));
            $endDate = date('Ymd', strtotime($startOfLastYear . ' +' . ($i + 1) . 'month'));
            $startMonth = date('Y-F', strtotime($startDate . ' + 1 year'));

            // Using this period get the total sales 
            $arr = getPeriod($startDate, $endDate, $sales);
            $yearPredictions[$key][$startMonth] = ceil(periodTotalSales($arr) * 1.1);
        }

        // Get quarters sales for fixed 12 months        
        $quartersCount = floor($monthsCount / 3) * 3;
        $j = 1;
        for ($i = 0; $i < $quartersCount; $i += 3) {
            $yearQuarters[$key]['Q' . $j] = periodTotalSales(array_slice($yearPredictions[$key], $i, 3));
            $j++;
        }

        // Get year total sales predictions for the fixed 12 months
        $yearTotals[$key] = periodTotalSales(array_slice($yearPredictions[$key], 0, 12));
    }

    $spProductsPrevious = [];
    $spProductsCurrent = [];
    $spProductsColumns = [];
    $shortHandMonths = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    $shortQuarters = ['Q1', 'Q2', 'Q3', 'Q4'];
    foreach ($spProducts as $key => $product) {
        if (isset($yearPredictions[$key]) && !isset($spProductsPrevious[$product['Cat']][$key])) {
            $spProductsPrevious[$product['Cat']][$key] = $product;
            $spProductsPrevious[$product['Cat']][$key] += array_combine($shortHandMonths, array_splice($yearPredictions[$key], 0, 12));
            $spProductsPrevious[$product['Cat']][$key] += array_combine($shortQuarters, array_splice($yearQuarters[$key], 0, 4));
            $spProductsPrevious[$product['Cat']][$key]['Year Total'] = $yearTotals[$key];

            if (!count($spProductsColumns)) {
                unset($spProductsPrevious[$product['Cat']][$key]['Cat']);
                unset($spProductsPrevious[$product['Cat']][$key]['unit']);
                $spProductsColumns = array_keys($spProductsPrevious[$product['Cat']][$key]);
            }

            // Add current year
            $spProductsCurrent[$product['Cat']][$key] = array_combine(
                array_slice($shortHandMonths, 0, count($yearPredictions[$key])),
                $yearPredictions[$key]
            );
            $spProductsCurrent[$product['Cat']][$key] += array_combine(
                array_slice($shortQuarters, 0, count($yearQuarters[$key])),
                $yearQuarters[$key]
            );
        }
    }

    $productCats = array_keys($spProductsPrevious);

    // Get sales by week for previous year
    $spChangePrevious = [];
    $j = 1;
    $weekSales = 0;
    $dayCount = 0;
    foreach ($spChange as $key => $sales) {
        // For previous year
        foreach (array_slice($sales, 0, 366) as $date => $sale) {
            $weekSales += $sale;
            $dayCount++;

            if ($dayCount == 7) {
                $spChangePrevious[$key][$j] = ceil($weekSales * 1.1);
                $dayCount = 0;
                $weekSales = 0;
                $j++;
            }
        }
        $j = 1;
        $dayCount = 0;
        $weekSales = 0;
    }

    // Get sales by week for current year
    $spChangeCurrent = [];
    foreach ($spChange as $key => $sales) {
        // Leap years will be out by a day but not a big deal
        foreach (array_slice($sales, 366, (count($sales)  - 366)) as $date => $sale) {
            $weekSales += $sale;
            $dayCount++;

            if ($dayCount == 7) {
                $spChangeCurrent[$key][$j] = ceil(($weekSales * 1.1));
                $dayCount = 0;
                $weekSales = 0;
                $j++;
            }
        }
        $j = 1;
        $dayCount = 0;
        $weekSales = 0;
    }

    // Get product info for product trending below
    $sql = "SELECT products.key as Key, products.product as Product, stock.qty as Qty, products.primary_supplier as Supplier, products.outOfStock as 'Out Of Stock' 
            FROM products
            LEFT JOIN stock ON (products.key = stock.key)";
    $productInfo = $db->query($sql);
    $productInfo = $productInfo->fetchAll(PDO::FETCH_ASSOC);

    $tmp = [];
    foreach ($productInfo as $index => $product) {
        $tmp[$product['Key']] = $product;
    }
    $productInfo = $tmp;

    // Get products that are trending below the predicited sales
    $trendingBelow = [];
    foreach ($spChangeCurrent as $key => $sales) {
        $currentWeek1 = array_slice($sales, -1, 1, true);
        $currentWeek2 = array_slice($sales, -2, 1, true);

        // If the data exists to compare the current and previous years, and the key has been trending below for 2 weeks add to trendingBelow array
        if (isset($spChangePrevious[$key][key($currentWeek1)]) && isset($spChangePrevious[$key][key($currentWeek2)])) {
            // Get by week number (key($currentWeek)) is the current week number
            $predictedSalesWeek1 = $spChangePrevious[$key][key($currentWeek1)];
            $currentSalesWeek1 = $sales[key($currentWeek1)];

            $predictedSalesWeek2 = $spChangePrevious[$key][key($currentWeek2)];
            $currentSalesWeek2 = $sales[key($currentWeek2)];

            if (($currentSalesWeek1 < $predictedSalesWeek1) && ($currentSalesWeek2 < $predictedSalesWeek2)) {
                // Calculate percentage decrease between the predicted sales and the current sales
                $percentageDecreaseWeek1 = 0;
                $percentageDecreaseWeek2 = 0;

                if ($currentSalesWeek1 != 0) {
                    $percentageDecreaseWeek1 = number_format((($currentSalesWeek1 - $predictedSalesWeek1) / $predictedSalesWeek1) * 100, 2);
                }

                if ($currentSalesWeek2 != 0) {
                    $percentageDecreaseWeek2 = number_format((($currentSalesWeek2 - $predictedSalesWeek2) / $predictedSalesWeek2) * 100, 2);
                }

                $trendingBelow[$key] = $productInfo[$key];

                // Change this to be more user friendly value
                if ($trendingBelow[$key]['Out Of Stock'] == 1) {
                    $trendingBelow[$key]['Out Of Stock'] = true;
                }

                $trendingBelow[$key]['Week2 Change (%)'] = isset($percentageDecreaseWeek2) ? $percentageDecreaseWeek2 : 0;
                $trendingBelow[$key]['Week1 Change (%)'] = isset($percentageDecreaseWeek1) ? $percentageDecreaseWeek1 : 0;
                $trendingBelow[$key]['Actions'] = null;
            }
        }
    }

    usort($trendingBelow, function ($a, $b) {
        return $a['Qty'] - $b['Qty'];
    });

    $response = [
        'spProducts' => $spProductsPrevious,
        'spProductsCurrent' => $spProductsCurrent,
        'spProductsColumns' => $spProductsColumns,
        'productCats' => $productCats,
        'trendingBelow' => $trendingBelow,
    ];

    echo json_encode($response, JSON_PRETTY_PRINT);
}

// -----------------------------------------------------------------------------------------------------------------------------------------------------------

// Old method for generating stock predictions
if (isset($_GET['skuPlatLinks?sku'])) {
    $sku = $_GET['skuPlatLinks?sku'];

    $sql = "SELECT * FROM sku_am_eb WHERE sku = '$sku'";
    $skuPlatforms  = $db->query($sql);
    $skuPlatforms = $skuPlatforms->fetchAll(PDO::FETCH_ASSOC);


    echo json_encode($skuPlatforms, JSON_PRETTY_PRINT);
}

// -----------------------------------------------------------------------------------------------------------------------------------------------------------

if (isset($_GET['noShelfCsv'])) {
    $sql = "SELECT ordered_stock.key, ordered_stock.qty, ordered_stock.ord_num, ordered_stock.del_num, ordered_stock.exp_del_date, ordered_stock.datetime, ordered_stock.newShelf,
            products.product
            FROM ordered_stock
            LEFT JOIN products ON (ordered_stock.key = products.key)
            WHERE ordered_stock.status = 'Complete'
            AND ordered_stock.newShelf IS NULL
            OR ordered_stock.newShelf = ''
            ORDER BY ordered_stock.ord_num";

    $noShelfPP = $db->query($sql);
    $noShelfPP = $noShelfPP->fetchAll(PDO::FETCH_ASSOC);

    $tmp = [];
    foreach ($noShelfPP as $index => $product) {
        $tmp[] = [
            'Product' => $product['product'],
            'key' => $product['key'],
            'qty' => $product['qty'],
            'order number' => $product['ord_num'],
            'delivery number' => $product['del_num'],
            'delivery date' => date("Y-m-d", strtotime($product['exp_del_date'])),
            'placed date' => date("Y-m-d", strtotime($product['datetime'])),
            'new shelfs' => null,
        ];
    }
    $noShelfPP = $tmp;

    echo json_encode($noShelfPP, JSON_PRETTY_PRINT);
}

// -----------------------------------------------------------------------------------------------------------------------------------------------------------

if (isset($_GET['skuStats?key'])) {
    $key = $_GET['skuStats?key'];

    // Get the skus we need to collect sales data for
    $sql = "SELECT sku, _rowid_ FROM sku_atts WHERE atts LIKE '%$key%'";
    $keySkus = $db->query($sql);
    $keySkus = $keySkus->fetchAll(PDO::FETCH_KEY_PAIR);

    // Get date for year ago
    $yearAgo = date('Y-m-d H:i', strtotime('first day of this month 1 year ago 00:00'));

    // Create array of dates from start of year to current date
    $monthArr = [];
    $start = new DateTime(date("Ymd", strtotime($yearAgo)));
    $end = new DateTime(date("Ymd", strtotime('now')));
    $interval = new DateInterval('P1M');
    $period = new DatePeriod($start, $interval, $end);

    // Get the start and end periods for each of the months in the year period (Rolling)
    foreach ($period  as $dt) {
        $monthArr[] = $dt->format('Y-m');
    }

    $tmp = [];
    foreach ($monthArr as $index => $month) {
        $tmp[] = [
          'start' => date('Ymd', strtotime('first day of ' . $month)),
          'end' => date('Ymd', strtotime('last day of ' . $month)),
        ];

    }
    $monthArr = $tmp;

    // Foreach of the skus, get the sales stats for the past week, this should be split into platforms
    $sql = "SELECT sku, qty, source, purchase_date FROM products WHERE purchase_date >= '$yearAgo'";
    $skuPlatformSales = $dbPB->query($sql);
    $skuPlatformSales = $skuPlatformSales->fetchAll(PDO::FETCH_ASSOC);

    $skuPlatformSales = array_filter($skuPlatformSales, function ($record) use ($keySkus) {
        return isset($keySkus[$record['sku']]);
    });

    // Total sales for each month for each of the platforms
    $tmp = [];
    $k = 0;
    foreach ($skuPlatformSales as $index => $rec) {
        if (isset($keySkus[$rec['sku']])) {
            $yearMonth = date('Ym', strtotime($rec['purchase_date']));
            // Uppercase the platform name, for the csv later
            $platform = ucfirst($rec['source']);

            // If isnt set that set it, else increment the value
            if (!isset($tmp[$rec['sku']][$platform][$yearMonth])) {
                $tmp[$rec['sku']][$platform][$yearMonth] = $rec['qty'];
            } else {
                $tmp[$rec['sku']][$platform][$yearMonth] += $rec['qty'];
            }
        }
    }
    $skuPlatformSales = $tmp;

    // Calculate the decrease / increase in sales over the rolling year
    foreach ($skuPlatformSales as $sku => $platforms) {
        // Total the sales across the platforms for each of the months
        foreach ($monthArr as $index => $period) {
            $month = date('Ym', strtotime($period['start']));

            $skuPlatformSales[$sku][$month] = array_sum(array_column($platforms, $month));
        }

        // Total the sales for all the months across the platforms and totals for each of the platfroms for the year
        $skuPlatformSales[$sku]['Year Total'] = array_sum($skuPlatformSales[$sku]);
        $skuPlatformSales[$sku]['Amazon Total'] = isset($skuPlatformSales[$sku]['Amazon']) ? array_sum($skuPlatformSales[$sku]['Amazon']) : null;
        $skuPlatformSales[$sku]['Ebay Total'] = isset($skuPlatformSales[$sku]['Ebay']) ? array_sum($skuPlatformSales[$sku]['Ebay']) : null;
        $skuPlatformSales[$sku]['Website Total'] = isset($skuPlatformSales[$sku]['Website']) ? array_sum($skuPlatformSales[$sku]['Website']) : null;
        $skuPlatformSales[$sku]['Onbuy Total'] = isset($skuPlatformSales[$sku]['Onbuy']) ? array_sum($skuPlatformSales[$sku]['Onbuy']) : null;

        // Percentage change between the months
        foreach ($monthArr as $index => $period) {
            $month = date('Ym', strtotime($period['start']));
            // This is the most current month we have data for
            $lastMonthPeriod = date('Ym', strtotime(end($monthArr)['start']));

            // If not the end of the year period
            if ($month !== $lastMonthPeriod) {
                $nextMonth = date('Ym', strtotime($period['start'] . ' + 1 month'));

                // (Next - Current) / Current * 100 to get the percentage change between those two months
                if (
                    isset($skuPlatformSales[$sku][$month]) &&
                    $skuPlatformSales[$sku][$month] !== 0 &&
                    isset($skuPlatformSales[$sku][$nextMonth]) &&
                    $skuPlatformSales[$sku][$nextMonth] !== 0
                ) {
                    $skuPlatformSales[$sku]['PC '. $month . ' - ' . $nextMonth] = number_format((($skuPlatformSales[$sku][$nextMonth] - $skuPlatformSales[$sku][$month]) / $skuPlatformSales[$sku][$month]) * 100, 2, '.', '');
                } else {
                    $skuPlatformSales[$sku]['PC ' . $month . ' - ' . $nextMonth] = null;
                }
            }
        }
    }

    // Get the platform id for each of the skus
    $sql = "SELECT sku, am_id, eb_id, we_id FROM sku_am_eb";
    $skuPlatformIds = $db->query($sql);
    $skuPlatformIds = $skuPlatformIds->fetchAll(PDO::FETCH_ASSOC);

    $tmp = [];
    foreach ($skuPlatformIds as $index => $sku) {
        if (isset($keySkus[$sku['sku']])) {
            $tmp[$sku['sku']] = $sku;
        }
    }
    $skuPlatformIds = $tmp;

    // Format for csv export from stock control
    $platforms = ['Amazon', 'Ebay', 'Website', 'Onbuy'];
    $tmp = [];
    foreach ($skuPlatformSales as $sku => $stats) {
        $tmp[$sku]['Sku'] = $sku;
        $tmp[$sku]['AmazonID'] = isset($skuPlatformIds[$sku]['am_id']) ? $skuPlatformIds[$sku]['am_id'] : null;
        $tmp[$sku]['EbayID'] = isset($skuPlatformIds[$sku]['eb_id']) ? $skuPlatformIds[$sku]['eb_id'] : null;
        $tmp[$sku]['WebsiteID'] = isset($skuPlatformIds[$sku]['we_id']) ? $skuPlatformIds[$sku]['we_id'] : null;

        // Append the totals for each of the platforms for each of the months
        foreach ($platforms as $index => $platform) {
            foreach ($monthArr as $index => $period) {
                $month = date('Ym', strtotime($period['start']));
                $yearMonth = date('Y-M', strtotime($period['start']));

             $tmp[$sku][$platform . ' ' . $yearMonth] = isset($stats[$platform][$month]) ? number_format($stats[$platform][$month], 2, '.', '') : null;
            }
            $tmp[$sku][$platform . ' Total'] = $stats[$platform . ' Total'];
        }

        // Append total sales for the year
        $tmp[$sku]['Year Total'] = $stats['Year Total'];

        // Append the totals for each month across the platforms
        foreach ($monthArr as $index => $period) {
            $month = date('Ym', strtotime($period['start']));
            $tmp[$sku]['Total ' . $month] = isset($stats[$month]) ? number_format($stats[$month], 2, '.', '') : null;
        }

        // Append the percentage change across the months
        foreach ($monthArr as $index => $period) {
            $month = date('Ym', strtotime($period['start']));
            // This is the most current month we have data for
            $lastMonthPeriod = date('Ym', strtotime(end($monthArr)['start']));

            // If not the end of the year period
            if ($month !== $lastMonthPeriod) {
                $nextMonth = date('Ym', strtotime($period['start'] . '+ 1 month'));
                $tmp[$sku]['PC '. $month . ' - ' . $nextMonth] = isset($stats['PC ' . $month . ' - ' . $nextMonth]) ? number_format($stats['PC ' . $month . ' - ' . $nextMonth], 2, '.', '') : null;
            }
        }
    }
    $skuPlatformSales = $tmp;

    // Get the skus that have not sold in the 4 week period
    $soldSkus = array_column($skuPlatformSales, 'Sku');
    $noSaleSkus = array_keys($keySkus);

    $noSaleSkus = array_diff($noSaleSkus, $soldSkus);

    // First key
    $firstKey = array_keys($skuPlatformSales)[0];

    // Section with the no sale skus
    $skuPlatformSales['nullIndex1'] = array_combine(array_keys($skuPlatformSales[$firstKey]), array_fill(0, count(array_keys($skuPlatformSales[$firstKey])), null));
    $skuPlatformSales['seperator'] = array_combine(array_keys($skuPlatformSales[$firstKey]), array_fill(0, count(array_keys($skuPlatformSales[$firstKey])), 'NO SALE SKUS'));
    $skuPlatformSales['nullIndex2'] = array_combine(array_keys($skuPlatformSales[$firstKey]), array_fill(0, count(array_keys($skuPlatformSales[$firstKey])), null));

    foreach ($noSaleSkus as $index => $sku) {
        $emptyRecord = array_combine(array_keys($skuPlatformSales[$firstKey]), array_fill(0, count(array_keys($skuPlatformSales[$firstKey])), null));
        $emptyRecord['Sku'] = $sku;
        $skuPlatformSales[$sku] = $emptyRecord;
    }

    echo json_encode($skuPlatformSales, JSON_PRETTY_PRINT);
}

// -----------------------------------------------------------------------------------------------------------------------------------------------------------

/**
 * Get information for the stock admin page
 */
if (isset($_GET['stockAdmin'])) {
    // Get the last 25 stock alerts, order them by date desc
    $sql = "SELECT * FROM stock_admin ORDER BY date DESC LIMIT 25";
    $adminMessages = $db->query($sql);
    $adminMessages = $adminMessages->fetchAll(PDO::FETCH_ASSOC);

    $tmp = [];
    foreach ($adminMessages as $index => $message) {
        $message['date'] = date('Y-m-d (H:i)', $message['date']);
        $tmp[] = $message;
    }
    $adminMessages = $tmp;

    echo json_encode($adminMessages, JSON_PRETTY_PRINT);
}

// -----------------------------------------------------------------------------------------------------------------------------------------------------------

if (isset($_GET['mergedAsins'])) {
    $sql = "SELECT * FROM merged_asins";
    $mergedAsins = $db->query($sql);
    $mergedAsins = $mergedAsins->fetchAll(PDO::FETCH_ASSOC);

    $tmp = [];
    foreach ($mergedAsins as $index => $rec) {
        $rec['date'] = date("Y-m-d", $rec['date']);

        $tmp[] = $rec;
    }
    $mergedAsins = $tmp;

    echo json_encode($mergedAsins, JSON_PRETTY_PRINT);
}

// -----------------------------------------------------------------------------------------------------------------------------------------------------------

if (isset($_GET['missingSkuAtts'])) {
    $sql = "SELECT sku FROM sku_atts_new";
    $skuAttsMissing = $db->query($sql);
    $skuAttsMissing = $skuAttsMissing->fetchAll(PDO::FETCH_COLUMN);

    $tmp = [];
    foreach ($skuAttsMissing as $index => $sku) {
        $tmp[] = [
            'sku' => $sku,
            'atts' => null,
            'room' => null,
        ];
    }
    $skuAttsMissing = $tmp;

    echo json_encode($skuAttsMissing, JSON_PRETTY_PRINT);
}

// -----------------------------------------------------------------------------------------------------------------------------------------------------------

function getSalesForPeriod($arr, $currentQty = null)
{
    // Get the sales for for each day of the period for the product
    $result = [];
    foreach ($arr as $date => $qty) {
        $lastDay = array_keys($arr);
        $lastDay = end($lastDay);

        if ($date != $lastDay) {
            $result[$date] = next($arr) - $qty;
        }

        return $result;
    }
}

// -----------------------------------------------------------------------------------------------------------------------------------------------------------

// Get sales for each day for a period of time
function getPeriod($startPeriod, $endPeriod, $arr)
{
    $result = [];

    $start = new DateTime($startPeriod);
    $end = new DateTime($endPeriod);
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end);

    foreach ($period as $dt) {
        if (isset($arr[$dt->format("Ymd")])) {
            $result[$dt->format("Ymd")] = number_format($arr[$dt->format("Ymd")], 2, '.', '');
        }
    }

    return $result;
}

// -----------------------------------------------------------------------------------------------------------------------------------------------------------

// get the total sales for a given array
function periodTotalSales($arr)
{
    $total = 0;
    foreach ($arr as $date => $qty) {
        $total += (float)$qty;
    }

    return round($total);
}
