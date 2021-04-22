<?php
ini_set('memory_limit', '1024M');

$db = new PDO('sqlite:stock_control.db3');

$files = glob('sales_csvs/*.csv');

$headers = ['Key', 'Date', 'Qty'];

// or each product key using the current stock qty, add sale qty for each to show the change in stock day to day
$sql = "SELECT key, qty FROM stock";
$currentKeyQty = $db->query($sql);
$currentKeyQty = $currentKeyQty->fetchAll(PDO::FETCH_KEY_PAIR);

// Build array of all sales for all the csvs in the folder
$stockChange = [];
foreach ($files as $file) {
    if ($file == 'sales_csvs/sale report pastyear.csv') {
        $csv = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($csv as $index => $rec) {
            if ($index == 0) continue;
            list($key, $qty, $date) = explode("\t", $rec);

            if (isset($key)) {
                $stockChange[] = [
                    'Key' => $key,
                    'Qty' => $qty,
                    'Date' => $date
                ];
            }
        }
    } else {
        $csv = file_get_contents($file);
        $arr = array_map("str_getcsv", explode("\n", $csv));
        unset($arr[0]);

        foreach ($arr as $index => $record) {
            if ($record[0]) {
                $record[1] = substr($record[1], 0, strlen($record[1]) - 5);
                $dateStr = strtok($record[1], '/');
                $dateStr2 = strtok('/');
                $dateStr3 = strtok('/');
                $record[1] = $dateStr3 . $dateStr2 . $dateStr;
                $record[1] = str_replace(' ', '', $record[1]);
                $stockChange[] = array_combine($headers, $record);
            }
        }
    }
}

// Build array of all days that we need to record the qty of the key for
$start = new DateTime('01-10-2019');
$end = new DateTime('19-04-2021');
$interval = new DateInterval('P1D');
$period = new DatePeriod($start, $interval, $end);

$allDays = [];
foreach ($period as $dt) {
    $allDays[] = $dt->format("Ymd");
}

$allDays = array_flip(array_reverse($allDays));

// Format stock change to be in format key => dates => qty
$tmp = [];
$dateTotalSales = 0;
foreach ($stockChange as $index => $value) {
    if (isset($tmp[$value['Key']][$value['Date']])) {
        $tmp[$value['Key']][$value['Date']] = $tmp[$value['Key']][$value['Date']] + $value['Qty'];
    } else {
        $tmp[$value['Key']][$value['Date']] = $value['Qty'];
    }
}
$stockChange = $tmp;

// Build array using current stock and increment the stock by the number of sales to represent a change in stock day to day over the period defined in allDays
$tmp = [];
foreach ($stockChange as $key => $dates) {
    $i = 0;
    foreach ($allDays as $day => $index) {
        if ($key != 'sto111' && $key != 'pot245') {
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

// $stmt = $db->prepare("INSERT INTO stock_change VALUES (?,?,?,?)");
// $db->beginTransaction();

// foreach ($stockChange as $key => $dates) {
//     foreach ($dates as $date => $qty) {
//         $stmt->execute([$key, $qty, $date]);
//     }
// }
// $db->commit();

//DEBUG 
echo '<pre style="background:#002; color:#fff;">';
print_r($stockChange);
echo '</pre>';
die();
