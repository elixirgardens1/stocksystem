<?php

// Define database connections
$dbStock = new PDO('sqlite:stock_control.db3');
$dbCache = new PDO('sqlite:Z:\FESP-REFACTOR\cache.db3');
$dbOrders = new PDO('sqlite:Z:\SANDBOX\barcodeDB\orders.db3');

// Get sku attributes from sku_atts, contains a sku and the individual product keys that build it up
$sql = "SELECT sku, atts FROM sku_atts";
$skuTable = $dbStock->query($sql);
$skuTable = $skuTable->fetchAll(PDO::FETCH_KEY_PAIR);

// Delete sku_stock records older than 30 days
$daysAgo30 = date("Ymd", strtotime('- 30 days'));
$sql = "DELETE FROM sku_stock WHERE date < '$daysAgo30'";
$dbStock->query($sql);

// Delete missing_skus & sku_atts_new records if SKU exists in sku_atts
$sql = "DELETE FROM missing_skus WHERE sku in (SELECT sku FROM sku_atts)";
$dbStock->query($sql);

$sql = "DELETE FROM sku_atts_new WHERE sku IN (SELECT sku FROM sku_atts)";
$dbStock->query($sql);

// Get orderids from past 5 days that have status marked from barcode database
$daysAgo5 = strtotime(date("Y-m-d", strtotime('00:00 5 days ago')));
$sql = "SELECT orderID FROM orders WHERE status = 'MARKED' AND statusTime >= $daysAgo5";
$markedBarcodes = $dbOrders->query($sql);
$markedBarcodes = $markedBarcodes->fetchAll(PDO::FETCH_ASSOC);

// String of marked barcodes from the past 5 days
$barcodesArr = [];
foreach ($markedBarcodes as $vals) {
    $barcodesArr[] = $vals['orderID'];
}
$barcodeString = implode("','", $barcodesArr);

// Retrieve orders from cache database that are makred in the barcode database from the past 5 days
$sql = "SELECT * FROM orders WHERE orderID IN ('$barcodeString') AND dateRetrieved >= $daysAgo5";
$fespOrders = $dbCache->query($sql);
$fespOrders = $fespOrders->fetchAll(PDO::FETCH_ASSOC);

// Get the current stock qty level for each product in the stock table
$sql = "SELECT key, qty FROM stock";
$stockTable = $dbStock->query($sql);
$stockTable = $stockTable->fetchAll(PDO::FETCH_KEY_PAIR);

// Get orderID sku => date from sku_stock
$sql = "SELECT 'orderID sku', date FROM sku_stock";
$trackOrderStockTable = $dbStock->query($sql);
$trackOrderStockTable = $trackOrderStockTable->fetchAll(PDO::FETCH_KEY_PAIR);

$orderStk = [];
$skusNotInStk = [];
foreach ($fespOrders as $order) {
    $orderContent = json_decode($order['content'], true);
    foreach ($orderContent['items'] as $item) {
        if (!isset($trackOrderStockTable[$orderContent['orderID'] . $item['SKU']])) {
            $orderStk[] = [
                'orderID' => $orderContent['orderID'],
                'sku' => $item['SKU'],
                'qty' => $item['quantity'],
                'atts' => $skuTable[$item['SKU']],
            ];
        } else {
            $skusNotInStk[$item['SKU']] = $item['SKU'];
        }
    }
}

// Update the stock qty values in the stock control using the 


//DEBUG 
echo '<pre style="background:#002; color:#fff;">';
print_r($daysAgo5);
echo '</pre>';
die();
