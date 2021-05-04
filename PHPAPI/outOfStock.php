<?php

// Set connection to dbs
$db = new PDO('sqlite:stock_control.db3');
// $apiDb = new PDO('sqlite:api_orders.db3');
$barcodeDb = new PDO('sqlite:orders.db3');
$cacheDb = new PDO('sqlite:cache.db3');


$sql = "SELECT * FROM orders LIMIT 1";
$test = $barcodeDb->query($sql);
$test = $test->fetchAll(PDO::FETCH_ASSOC);

// Timestamp starting at 00:00 5 days ago
$dateMinus5Days = strtotime(date("Y-m-d", strtotime('- 5 days')));

// Get orders from the barcode database which have the status marked in the past 5 days
$sql = "SELECT orderID FROM orders WHERE status = 'MARKED' AND statusTime >= $dateMinus5Days";
$marked5DaysOrders = $barcodeDb->query($sql);
$marked5DaysOrders = array_flip($marked5DaysOrders->fetchAll(PDO::FETCH_COLUMN));

// Get orders from cache from past 5 days
$sql = "SELECT * FROM orders WHERE dateRetrieved >= $dateMinus5Days";
$orders5Days = $cacheDb->query($sql);
$orders5Days = $orders5Days->fetchAll(PDO::FETCH_ASSOC);

// Format array and remove any orders that are not in the marked in past 5 days array
$tmp = [];
foreach ($orders5Days as $index => $order) {
    if (isset($marked5DaysOrders[$order['orderID']])) {
        $tmp[$order['orderID']] = json_decode($order['content'], true);
    }
}
$orders5Days = $tmp;

// Get all skus and their key attributes
$sql = "SELECT sku, atts FROM sku_atts";
$skuKeyAtts = $db->query($sql);
$skuKeyAtts  = $skuKeyAtts->fetchAll(PDO::FETCH_KEY_PAIR);

$tmp = [];
foreach($skuKeyAtts as $sku => $atts) {
    $attsArr = explode(',', $atts);

    foreach($attsArr as $index => $att) {
        $key = strtok($att, '|');
        $qty = strtok('|');

        $tmp[$sku][] = [
            'key' => $key,
            'value' => $qty,
        ];
    }
}
$skuKeyAtts = $tmp;

// Get current stock level for each key
$sql = "SELECT key, qty FROM stock";
$keyStock = $db->query($sql);
$keyStock = $keyStock->fetchAll(PDO::FETCH_KEY_PAIR);

// Get list of orderid / skus that have already been processed into the system
$sql = "SELECT * FROM sku_stock";
$trackedOrderSkus = $db->query($sql);
$trackedOrderSkus = $trackedOrderSkus->fetchAll(PDO::FETCH_KEY_PAIR);

$orderStk = [];
$skusNotInStk = [];
foreach($orders5Days as $orderId => $order) {
    foreach($order['items'] as $item) {
        if (!isset($trackedOrderSkus[$orderId . $item['SKU']])) {
            if (isset($skuKeyAtts[$item['SKU']])) {
                // OrderID sku combinations that are not in the stock records
                $orderStk[] = [
                    'orderID' => $orderId,
                    'sku' => $item['SKU'],
                    'qty' => $item['quantity'],
                    'atts' => $skuKeyAtts[$item['SKU']],
                ];
            } elseif ($item['SKU']) {
                // Skus not in stock control
                $skusNotInStk[$item['SKU']] = $item['SKU'];
            }
        }
    }
}

// Get the qtys sold for reach of the keys
$keyQtySold = [];

// Update the stock table wit the new current stock level
$stmt = $db->prepare("UPDATE stock SET qty = ? WHERE key = ?");
$db->beginTransaction();

foreach($orderStk as $value) {
    foreach ($value['atts'] as $att) {
        // If key qty value is qty x multiple
        if (stripos($att['value'], 'x') !== false) {
            // Explode the qty and multiplier and multiply them to get the actualy qty value
            $att['value'] = explode('x', $att['value']);
            $att['value'] = $att['value'][0] * $att['value'][1];
        }

        if (isset($keyStock[$att['key']])) {
            // Multiply the order qty for this sku by the key att qty, decrease the keyStock by this qty
            $qty = $att['value'] * $value['qty'];
            $keyStock[$att['key']] = $keyStock[$att['key']] - $qty;

            // If not already set, set values for totalAmount sold and how many orders
            if (!isset($keyQtySold[$att['key']])) {
                $keyQtySold[$att['key']]['totalAmount'] = 0;
                $keyQtySold[$att['key']]['totalOrders'] = 0;
            }

            // Increment by qty of key sold, and increment by 1 amount of orders
            $keyQtySold[$att['key']]['totalAmount'] = $keyQtySold[$att['key']]['totalAmount'] + $qty;
            $keyQtySold[$att['key']]['totalOrders']++;

            $stmt->execute([$keyStock[$att['key']], $att['key']]);
        }
    }
}
// SUBMIT VALUES TO DATABASE, UNCOMMENT
// $db->commit();
//

// Get all out of stock products
$sql = "SELECT key FROM products WHERE outOfStock = 1";
$outOfStockProducts = $db->query($sql);
$outOfStockProducts = array_flip($outOfStockProducts->fetchAll(PDO::FETCH_COLUMN));

// Prepare insert into stock_change
$stmt = $db->prepare("INSERT INTO stock_change VALUES (?,?,?,?)");
$date = date("Ymd");
$db->beginTransaction();
foreach($keyStock as $product) {
    $setOos = null;

    if (isset($outOfStockProducts[$product['key']])) {
        $setOos = 1;
    }

    $stmt->execute([$product['key'], $product['qty'], $date, $setOos]);
}
// UNCOMMENT
// $db->commit();



/// DEBUG
echo '<pre style="background: black;  color: white;">'; print_r($keyQtySold); echo '</pre>'; die();


// Get all the skus currently out of stocked on the platforms from the
$sql = "SELECT key,qty FROM stock";
$keyQtys = $db->query($sql);
$keyQtys = $keyQtys->fetchAll(PDO::FETCH_KEY_PAIR);

// Calculate the days to out of stock value for each of the keys in keyQtys
$timeStampMinusMonth = date("Ymd", strtotime("1 month ago"));

$sql = "SELECT key, qty, date FROM stock_change WHERE date >= $timeStampMinusMonth ORDER BY date DESC";
$keyStockChange = $db->query($sql);
$keyStockChange = $keyStockChange->fetchAll(PDO::FETCH_ASSOC);

// Format stock change data in format key => date => qty
$tmp = [];
foreach ($keyStockChange as $index => $rec) {
    $tmp[$rec['key']][$rec['date']] = $rec['qty'];
}
$keyStockChange = $tmp;

// Total the monthly sales for each key, find the average sales rate, divide the current qty by average to get the days to out of stock
$tmp = [];
foreach ($keyStockChange as $key => $month) {
    $monthlyTotal = 0;
    foreach ($month as $day => $qty) {
        $lastDay = array_keys($month);
        $lastDay = end($lastDay);

        if ($day != $lastDay) {
            $previousDayQty = next($month);
            $monthlyTotal += $previousDayQty - $qty;
        }
    }

    // Divide current qty of key by average daily sales to get days to out of stock
    if ($monthlyTotal) {
        $daysToOOS = number_format((float) $keyQtys[$key] / (float)($monthlyTotal / 30), 2, '.', '');

        if ($daysToOOS < 0 || $daysToOOS == 0.00) {
            $daysToOOS = 0;
        }

        // If products daysToOOS less than 3 day threshold
        if ($daysToOOS < 3) {
            $tmp[$key] = $daysToOOS;
        }
    }
}
$keyStockChange = $tmp;

// Get products already marked as being out of stock on platforms so not to send requests to out of stock again
// check that products that are marked as out of stock on our system are actually out of stock on the platforms.
$sql = "SELECT key FROM products WHERE outOfStock = 1";
$outOfStockProducts = $db->query($sql);
$outOfStockProducts = array_flip($outOfStockProducts->fetchAll(PDO::FETCH_COLUMN));

// Check each of these products is actually out of stock on the platforms
// get list of skus that should be out of stock due to these products being out of stock
$sql = "SELECT sku, atts FROM sku_atts";
$keySkus = $db->query($sql);
$keySkus = $keySkus->fetchAll(PDO::FETCH_ASSOC);

$tmp = [];
foreach ($keySkus as $index => $sku) {
    if (isset($sku['atts'])) {
        $atts = explode(',', $sku['atts']);

        foreach ($atts as $i => $key) {
            $key = strtok($key, '|');

            // Only if the product key is out of stock
            if (isset($outOfStockProducts[$key])) {
                $tmp[$sku['sku']] = $i;
            }
        }
    }
}
$keySkus = $tmp;

/// DEBUG
echo '<pre style="background: black;  color: white;">';
print_r(count($keySkus));
echo '</pre>';
die();

//  the products that need out of stocking, pass the array to the controller for the platforms
