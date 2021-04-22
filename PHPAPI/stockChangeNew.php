<?php

ini_set('memory_limit', '1024M');

$db = new PDO('sqlite:stock_control.db3');

$files = glob('sales_csvs/newCSVS');

$headers = ['key', 'date', 'qty'];

$sql = "SELECT key, qty FROM stock";
$currentKeyQty = $db->query($sql);
$currentKeyQty = $currentKeyQty->fetchAll(PDO::FETCH_KEY_PAIR);

//DEBUG 
echo '<pre style="background:#002; color:#fff;">';
print_r($currentKeyQty);
echo '</pre>';
die();
