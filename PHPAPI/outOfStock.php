<?php
/*
 * File: outOfStock.php
 * @Author: Ryan Denby
 * Date: 29/04/2021
*/

/*
 * Update the current stock qty for products by getting orders from the cache database from the
 * past 5 days that have the status MARKED in the barcode database. These orders will have a list
 * of skus which we will use to get the keys that build, multiply the order qty by the key qty
 * that is required for that sku which will be minused form current stock qty for that key.
 * Update the stock qtys for all keys and update the stock change using this new value, record
 * any missing skus in the missing_skus table and record the processed orders in the sku_stock
 * table.

 * After the first part of the script has updated the stock qty values, the script will calculate
 * how many days each key has until it will be out of stock, this is based on the total sales over
 * the past month used to find the average daily sales and dividing the current stock qty by this
 * daily rate. Any keys found to have less than 3 days till they are out of stock need to be out
 * of stocked on the platforms, all skus this affects will be collected and requests will be fired
 * to each of the platforms to out of stock this product.

 * Lastly the script will check for any products that are currently out of stock on the platforms,
 * that have greater than 3 days till out of stock value, these can be restocked on the platforms,
 * again collect a list of affected skus and fire requests to the platforms to restock the skus.
*/

// Required classes
// require_once 'MWSRequest.php';

// Set connection to dbs
$db = new PDO('sqlite:stock_control.db3');
$barcodeDb = new PDO('sqlite:orders.db3');
$cacheDb = new PDO('sqlite:cache.db3');

// Timestamp starting at 00:00 5 days ago
$dateMinus5Days = strtotime(date("Y-m-d", strtotime('- 5 days')));
$dateMinus30Days = strtotime(date("Ymd"), strtotime('- 30 days'));

// Delete records from sku_stock older than 30 days
$sql = "DELETE FROM sku_stock WHERE date < $dateMinus30Days";
$db->query($sql);

// Delete skus from missing_skus if they exist in sku_atts
$sql = "DELETE FROM missing_skus WHERE sku IN (SELECT sku FROM sku_atts)";
$db->query($sql);

// Delete from sku_atts_new if in sku_atts
$sql = "DELETE FROM sku_atts_new WHERE sku IN (SELECT sku FROM sku_atts)";
$db->query($sql);

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
$sql = "SELECT 'orderId sku', date FROM sku_stock";
$trackedOrderSkus = $db->query($sql);
$trackedOrderSkus = $trackedOrderSkus->fetchAll(PDO::FETCH_KEY_PAIR);

/**
 * Build two arrays, first will contain a list of orders that need to be processed into the stock
 * system and will be used to decrease the current qty of stock in the stock table.
 *
 * The second array will contain skus that are not currently in the system and will be inserted
 * into the missing_skus table.
*/
$orderStk = [];
$skusNotInStk = [];
foreach($orders5Days as $orderId => $order) {
    foreach($order['items'] as $item) {
        if (!isset($trackedOrderSkus[$orderId . $item['SKU']])) {
            if (isset($skuKeyAtts[$item['SKU']])) {
                // OrderID sku combinations that are not in the stock records
                $orderStk[] = [
                    'orderID' => $orderId,
                    'source' => $order['source'],
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

// Get the qtys sold for each of the keys and update the stock qtys for each product
$keyQtySold = [];
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
$db->commit();

// Get all out of stock products
$sql = "SELECT key FROM products WHERE outOfStock = 1";
$outOfStockProducts = $db->query($sql);
$outOfStockProducts = array_flip($outOfStockProducts->fetchAll(PDO::FETCH_COLUMN));

// Prepare insert into stock_change
$stmt = $db->prepare("INSERT INTO stock_change VALUES (?,?,?,?)");
$date = date("Ymd");
$db->beginTransaction();
foreach($keyStock as $key => $qty) {
    $setOos = null;

    if (isset($outOfStockProducts[$key])) {
        $setOos = 1;
    }

    $stmt->execute([$key, $qty, $date, $setOos]);
}

$db->commit();

// Insert processed orderids into sku_stock, in format {orderid}{sku} as unique identifier for orderid
$stmt = $db->prepare("INSERT INTO sku_stock VALUES (?,?,?,?)");
$db->beginTransaction();
foreach ($orderStk as $order) {
    $stmt->execute([$order['orderID'] . $order['sku'], $order['source'],  $order['sku'],date("Ymd")]);
}
$db->commit();

//Insert missing skus
ksort($skusNotInStk, SORT_NATURAL);
$stmt = $db->prepare("INSERT OR REPLACE INTO missing_skus VALUES (?,?)");
$db->beginTransaction();
foreach ($skusNotInStk as $sku) {
    $stmt->execute([$sku, date("Ymd")]);
}
$db->commit();

die();


/*
 * Now calculate the days to out of stock for keys and out of stock the affected skus on the platforms
*/


// Get all the skus currently out of stocked on the platforms from the
$sql = "SELECT key,qty FROM stock";
$keyQtys = $db->query($sql);
$keyQtys = $keyQtys->fetchAll(PDO::FETCH_KEY_PAIR);

$timeStampMinusMonth = date("Ymd", strtotime("1 month ago"));

// Calculate the days to out of stock value for each of the keys in keyQtys
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
$parameters = [
    'ReportType' => '_GET_MERCHANT_LISTINGS_INACTIVE_DATA_',
];

$requestOos = $MWSR->request('RequestReport', $parameters, 'Reports');
$requestOos  = json_decode(json_encode(new SimpleXMLElement($requestOos)), true);

// This will be used to request the status of the report on amazons side, when the status of the report
// is set to _DONE_ they will return a reportId to get the report data.
$reportRequestId = $requestOos['RequestReportResult']['ReportRequestInfo']['ReportRequestId'];

// Give them few mins to sort it out
sleep(180);

function checkReportStatus () {
    $parameters = [
        'ReportRequestIdList.Id.1' => $reportRequestId,
        'ReportTypeList.Type.1' => '_GET_MERCHANT_LISTINGS_INACTIVE_DATA_',
        'ReportProcessingStatusList.Status.1' => '_DONE_',
    ];

    $requestOos = $MWSR->request('GetReportRequestList', $parameters, 'Reports');
    $requestOos = json_decode(json_encode(new SimpleXMLElement($requestOos)), true);

    // If report is not ready to be requested return false
    if ($requestOos['GetReportRequestListResult']['ReportRequestInfo']['ReportProcessingStatus'] != '_DONE_') {
        return false;
    }

    // Get generated report id, this is required to get the report
    return $requestOos['GetReportRequestListResult']['ReportRequestInfo']['GeneratedReportId'];
}

// Attempt to check the status of the report, do this a max of 5 times, if still pending cancel script
for ($i = 0; $i < 5; $i++) {

    $statusResponse = checkReportStatus();

    if ($statusResponse !== false) {
        $generatedReportId = $statusResponse;
        break;
    }

    // If the report check returns false, wait 2 mins and check again
    sleep(120);
}

if (!isset($generatedReportId)) {
    // PUT AN ERROR MESSAGE INTO STOCK CONTROL ERROR TABLE
}

// With the generatedReportId we can request the report data
$parameters = [
    'ReportId' => $generatedReportId,
];

// This will be a raw tab delimited string, this will be quite messy and will contain lots of borken
// strings due to the commas and special characters in the listing descriptions etc
$requestOos = $MWSR->request('GetReport', $parameters, 'Reports');

// Get headers from the file
$headers = explode("\n", $requestOos)[0];
$headers = array_flip(explode("\t", $headers));

$headersCount = count($headers) - 1;

// Explode the tab delimited string into array format
$arr = explode("\t", $requestOos);

// i for each listing index, j for the proerties for each listing index
$tmp = [];
$i = 0;
$j = 0;
foreach ($arr as $index => $value) {

    // If not a header add to array
    if (!isset($headers[$value])) {
        $tmp[$i][$j] = $value;

        // Increment property count for current listing index
        $j++;
    }

    // At 30 properties for listing index, reset i and j to build the next listing
    if ($j == $headersCount) {
        $i++;
        $j = 0;
    }
}
$arr = $tmp;

// Build array of sku -> qty, which will be used to check that the skus we have currently out of stock
// on the system are actually out of stock on the platforms
$tmp = [];
foreach ($arr as $index => $value) {
    if (isset($value[3]) && isset($value[5])) {
        $sku = $value[3];
        $qty = $value[5];

        $tmp[$sku] = $qty;
    }
}
$arr = $tmp;

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
print_r($keySkus);
echo '</pre>';
die();

//  the products that need out of stocking, pass the array to the controller for the platforms
