<?php

// Set connection to dbs
$db = new PDO ('sqlite:stock_control.db3');
$apiOrders = new PDO ('sqlite:api_orders.db3');
// $cacheDb = new PDO ('sqlite:cache.db3');


// Get orders from api_orders.db3 which will be used to minus the qtys from the current products qtys
$sql = "SELECT * FROM orders LIMIT 1";
$test = $cacheDb->query($sql);
$test = $test->fetchAll(PDO::FETCH_ASSOC);



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
echo '<pre style="background: black;  color: white;">'; print_r(count($keySkus)); echo '</pre>'; die();

//  the products that need out of stocking, pass the array to the controller for the platforms
