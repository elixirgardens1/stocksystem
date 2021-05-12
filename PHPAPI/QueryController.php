<?php
ini_set('memory_limit', '1024M');

/*
 * file: QueryController.php
 * located: http://deepthought:8080/stocksystem/QueryController.php
 * database: stock_control.db3
 * @author: Ryan Denby
 */

header('access-control-allow-origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: X-Requested-With,Origin,Content-Type,Cookie,Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('HTTP/1.1 204 No Content');
    die;
}

// $_GET['outOfStockDeliveries'] = 'acc';

// -----------------------------------------------------------------------------------------------------------------------------------------------------------

// Define connection to stock control database
$db = new PDO('sqlite:stock_control.db3');

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
            product_rooms.room,product_rooms.shelf_location
            FROM products
            LEFT JOIN stock ON (products.key = stock.key)
            LEFT JOIN product_rooms ON (products.key = product_rooms.key)";
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

    // Get recent inserts into the stock system for this product
    $sql = "SELECT * FROM updated_stock WHERE key = '$key' ORDER BY datetime DESC";
    $productHistory = $db->query($sql);
    $productHistory = $productHistory->fetchAll(PDO::FETCH_ASSOC);

    $tmp = [];
    foreach ($productHistory as $index => $product) {
        $placedDate = 'No Set';
        if (isset($product['datetime'])) {
            $placedDate = date("Y-m-d", strtotime($product['datetime']));
        }
        $tmp[] = [
            'Key' => $product['key'],
            'Qty' => $product['qty'],
            'Delivery Id' => $product['deliveryID'],
            'Placed date' => $placedDate,
        ];
    }
    $productHistory = $tmp;

    $tmp = [];
    foreach ($keyStockChange as $date => $qty) {
        $lastDay = array_keys($keyStockChange);
        $lastDay = end($lastDay);

        if ($date != $lastDay) {
            $nextDate = DateTime::createFromFormat('Ymd', $date + 1);
            $nextDate = $nextDate->format('Ymd');
            $tmp[$date] = $qty - $keyStockChange[$nextDate];
        }
    }
    $keyStockChange = $tmp;

    $startWeek = date("Ymd", strtotime('- 8 days'));
    $endWeek = date("Ymd", strtotime('now'));

    //Get the change in stock for the product for past week
    $sql = "SELECT date, qty FROM stock_change WHERE key = '$key' AND date >= $startWeek AND date <= $endWeek ORDER BY date";
    $salesPastWeek = $db->query($sql);
    $salesPastWeek = $salesPastWeek->fetchAll(PDO::FETCH_KEY_PAIR);

    // Get sales by day
    $tmp = [];
    foreach ($salesPastWeek as $date => $qty) {
        $lastDay = array_keys($salesPastWeek);
        $lastDay = end($lastDay);

        if ($date != $lastDay) {
            $nextDate = DateTime::createFromFormat('Ymd', $date + 1);
            $nextDate = $nextDate->format('Ymd');
            $tmp[$date +  1] = $qty - $salesPastWeek[$nextDate];
        }
    }
    $salesPastWeek = $tmp;

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

    $yearPredictions = [];
    $j = 0;
    for ($i = 1; $i < 13; $i++) {
        $startMonth = DateTime::createFromFormat('!m', $i);
        $endMonth = DateTime::createFromFormat("!m", $i + 1);
        $startMonth = $startMonth->format("F");
        $endMonth = $endMonth->format("F");

        $startDate = date("Ymd", strtotime('first day of ' . $startMonth . ' last year'));
        $endDate = date("Ymd", strtotime('first day of ' . $endMonth . ' last year'));
        if ($startMonth == 'December') {
            $endDate = date("Ymd", strtotime('first day of ' . $endMonth));
        }

        $arr = getPeriod($startDate, $endDate, $keyStockChange);

        // Increase by 10% margin to account for company growth
        $yearPredictions[$j] = ceil(periodTotalSales($arr) * 1.1);
        $j++;
    }
    $totalSalesYearPrediction = periodTotalSales($yearPredictions);

    $yearQuarters = [];
    for ($i = 0; $i < 12; $i += 3) {
        $yearQuarters[] = periodTotalSales(array_slice($yearPredictions, $i, 3));
    }

    $response = [
        'productInfo' => $productInfo,
        'productOrders' => $productOrders,
        'productHistory' => $productHistory,
        'salesWeekColumns' => array_keys($salesPastWeek),
        'salesPastWeek' => array_values($salesPastWeek),
        'totalSalesPastWeek' => number_format($totalSalesPastWeek, 2),
        'yearPredictions' => $yearPredictions,
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

if (isset($_GET['getSearchPeriod?start'])) {
    $startDate = $_GET['getSearchPeriod?start'];
    $endDate = $_GET['end'];
    $key = $_GET['key'];

    $numericStart = strtok($startDate, '-');
    $numericStart = (int)strtok('-');
    $numericEnd = strtok($endDate, '-');
    $numericEnd = (int)strtok('-');

    $startDate = date("Ymd", strtotime($startDate . '-1 year'));
    $endDate = date("Ymd", strtotime($endDate . '-1 year +1 day'));

    $sql = "SELECT date, qty FROM stock_change WHERE key = '$key' AND date >= $startDate AND date <= $endDate ORDER BY date DESC";
    $predictionPeriod = $db->query($sql);
    $predictionPeriod = $predictionPeriod->fetchAll(PDO::FETCH_KEY_PAIR);

    $periodSales = getSalesForPeriod($predictionPeriod);

    if (periodTotalSales($periodSales) == 0) {
        echo json_encode("Prediction Of 0 Sales !, Try A Different Time Period", JSON_PRETTY_PRINT);
        die();
    }

    $tmp = [];
    $j = 0;
    for ($i = $numericStart; $i <= $numericEnd; $i++) {
        $startMonth = DateTime::createFromFormat('!m', $i);
        $endMonth  = DateTime::createFromFormat('!m', $i + 1);
        $startMonth = $startMonth->format("M");
        $endMonth = $endMonth->format("M");

        $startDate  = date("Ymd", strtotime('first day of ' . $startMonth . ' last year'));
        $endDate = date("Ymd", strtotime('first day of ' . $endMonth . ' last year'));
        $arr = getPeriod($startDate, $endDate, $periodSales);

        // Increase by 10% margin to account for company growth
        $tmp[$j] = ceil(periodTotalSales($arr) * 1.1);
        $j++;
    }
    // $periodSales = array_reverse($tmp);
    $periodSales = $tmp;

    $start = (new DateTime($startDate))->modify('first day of this month');
    $end = (new DateTime($endDate))->modify('first day of this month');
    $interval = DateInterval::createFromDateString('1 month');
    $period = new DatePeriod($start, $interval, $end);

    $monthArray = [];
    foreach ($period as $dt) {
        $monthArray[] = $dt->format("M");
    }

    $response = [
        'periodSales' => $periodSales,
        'totalPeriodSales' => periodTotalSales($periodSales),
        'monthArray' => $monthArray,
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
    $matrixDb = new PDO('sqlite:matrixCodes.db3');

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
    $sql = "SELECT
            products.cat as Cat, products.key as Key, products.product as Product, unit,
            stock.qty as Qty
            FROM products
            LEFT JOIN stock ON (products.key = stock.key)";
    $spProducts = $db->query($sql);
    $spProducts = $spProducts->fetchAll(PDO::FETCH_ASSOC);

    $tmp = [];
    foreach ($spProducts as $index => $product) {
        $tmp[$index] = $product;
        $tmp[$index]['unit'] = $unitLookup[$product['unit']];
    }
    $spProducts = $tmp;

    $tmp = [];
    foreach ($spProducts as $index => $product) {
        $tmp[$product['Key']] = $product;
    }
    $spProducts = $tmp;

    $startOfLastYear = date("Ymd", strtotime('first day of january last year'));
    $endOfLastYear = date("Ymd", strtotime('first day of january this year'));

    $sql = "SELECT * FROM stock_change WHERE date >= $startOfLastYear AND date <= $endOfLastYear ORDER BY date ASC";
    $spChange = $db->query($sql);
    $spChange = $spChange->fetchAll(PDO::FETCH_ASSOC);

    $tmp = [];
    foreach ($spChange as $index => $rec) {
        $tmp[$rec['key']][$rec['date']] = $rec['qty'];
    }
    $spChange = $tmp;

    $tmp = [];
    foreach ($spChange as $key => $sales) {
        foreach ($sales as $date => $qty) {
            if (end($sales) != $qty) {
                $nextDate = DateTime::createFromFormat('Ymd', $date + 1);
                $nextDate = $nextDate->format("Ymd");
                $tmp[$key][$date] = $qty - $sales[$nextDate];
            }
        }
    }
    $spChange = $tmp;

    $yearPredictions = [];
    $yearQuarters = [];
    $yearTotals = [];
    foreach ($spChange as $key => $sales) {
        for ($i = 1; $i < 13; $i++) {
            $startMonth = DateTime::createFromFormat('!m', $i);
            $endMonth = DateTime::createFromFormat('!m', $i + 1);
            $startMonth = $startMonth->format("M");
            $endMonth = $endMonth->format("M");

            $startDate = date("Ymd", strtotime('first day of' . $startMonth . ' last year'));
            $endDate  = date("Ymd", strtotime('first day of ' . $endMonth . ' last year'));
            if ($startMonth == 'Dec') {
                $endDate = date("Ymd", strtotime('first day of ' . $endMonth));
            }

            $arr = getPeriod($startDate, $endDate, $sales);

            $yearPredictions[$key][$startMonth] = ceil(periodTotalSales($arr) * 1.1);
        }

        $j = 1;
        for ($i = 0; $i < 12; $i += 3) {
            $yearQuarters[$key]['Q' . $j] = periodTotalSales(array_slice($yearPredictions[$key], $i, 3));
            $j++;
        }
        $yearTotals[$key] = periodTotalSales($yearPredictions[$key]);
    }

    $tmp = [];
    foreach ($spProducts as $key => $product) {
        if (array_key_exists($key, $yearPredictions)) {
            $tmp[$product['Cat']][$key] = $product;
            $tmp[$product['Cat']][$key] += $yearPredictions[$key];
            $tmp[$product['Cat']][$key] += $yearQuarters[$key];
            $tmp[$product['Cat']][$key]['Year Total'] = $yearTotals[$key];
        }
    }
    $spProducts = $tmp;

    $productCats = array_keys($spProducts);

    $response = [
        'spProducts' => $spProducts,
        'productCats' => $productCats,
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
