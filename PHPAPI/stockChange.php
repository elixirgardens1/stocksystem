<?php

ini_set('memory_limit', '1024M');

$db = new PDO('sqlite:stock_control.db3');

$files = glob('salesReports/*.csv');

$headers = ['key', 'date', 'qty'];

$sql = "SELECT key, qty FROM stock";
$currentKeyQty = $db->query($sql);
$currentKeyQty = $currentKeyQty->fetchAll(PDO::FETCH_KEY_PAIR);

$stockChange = [];
foreach ($files as $file) {
    $salesArr = file_get_contents($file);
    $salesArr = array_map("str_getcsv", explode("\n", $salesArr));

    foreach ($salesArr as $index => $rec) {
        if ($index === 0) continue;

        if (isset($rec[0]) && isset($rec[1])) {
            $reformat = str_replace("/", "-", $rec[1]);
            $rec[1] = date("Ymd", strtotime($reformat));
            $stockChange[] = array_combine($headers, $rec);
        }
    }
}

$start = new DateTime('01-10-2019');
$end = new DateTime('23-04-2021');
$interval = new DateInterval('P1D');
$period = new DatePeriod($start, $interval, $end);

// Array of all the dates between the 01-10-2019 and current date 23-04-2021, in the format Ymd
$allDates = [];
foreach ($period as $date) {
    $allDates[] = $date->format("Ymd");
}
$allDates = array_flip(array_reverse($allDates));

// Total the sales for each day for each key
$tmp = [];
$dateTotalSales = 0;
foreach ($stockChange as $index => $value) {
    if (isset($tmp[$value['key']][$value['date']])) {
        $tmp[$value['key']][$value['date']] = $tmp[$value['key']][$value['date']] + $value['qty'];
    }
    if (!isset($tmp[$value['key']][$value['date']])) {
        $tmp[$value['key']][$value['date']] = $value['qty'];
    }
}
$stockChange = $tmp;

// Format so its shows a change in stock for everyday over the time period
$tmp = [];
foreach ($stockChange as $key => $dates) {
    $i = 0;
    foreach ($allDates as $day => $index) {
        if ($key && $key != 'sto111' && $key != 'pot245') {

            if ($i == 0) {
                $stockQty = $currentKeyQty[$key];
            }
            $i++;

            if (!isset($stockChange[$key][$day])) {
                $tmp[$key][$day] = $stockQty;
            }

            if (isset($stockChange[$key][$day])) {
                $stockQty += $stockChange[$key][$day];
                $tmp[$key][$day] = $stockQty;
            }
        }
    }
}
$stockChange = $tmp;

// Insert records into the stock_change table in the stock_control.db3
// $stmt = $db->prepare("INSERT INTO stock_change VALUES (?,?,?,?)");
// $db->beginTransaction();
// foreach ($stockChange as $key => $dates) {
//     foreach ($dates as $date => $qty) {
//         $stmt->execute([$key, $qty, $date, null]);
//     }
// }
// $db->commit();

//DEBUG 
echo '<pre style="background:#002; color:#fff;">';
print_r($stockChange);
echo '</pre>';
die();
