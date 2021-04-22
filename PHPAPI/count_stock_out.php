<?php
/*
http://192.168.0.24/StockControl/count_stock_out.php

Runs via Windows Scheduler: 8pm/daily

DESCRIPTION:
Counts product quantities sold that have been downloaded from FESP,
whose status equals 'MARKED' in 'barcodeDB/orders.db3'.
It uses the 'sku_atts' table to look up the individual items
that make up every SKU.

The product quantities are then deducted from the existing 'qty'
levels in the 'stock' table.

The new quantities get added to the 'stock_change' table,
along with their key value and current date. This table is used to
calculate the sales rate for any given product.
Records older than 30 days are automatically deleted.

The order ID + SKU, and date get added to the 'sku_stock' table.
This is used by the system to avoid processing orders more than once.
Records older than 30 days are automatically deleted.

Any SKUs that don't exist in the 'sku_atts' table (order SKUs that
have been downloaded by FESP, but don't exist in the stock control
database) get added to the 'missing_skus' table, along with the
current date.
These records get deleted automatically once they exist in stock_control.db3.
!!! IMPORTANT !!!
These missing SKUs need adding to the system ASAP.

NOTES:
Basically, data in the stock_control.db3 database gets updated when this script runs.

The following always happens:
	1) The orderID and SKU (separated by a space) will get appended to the 'orderID sku'
	   field in the 'sku_stock' table, along with the current date.
	   Any existing records older than 30 days will be deleted.

	2) Some 'qty' levels in the 'stock' table will fall.

	3) All the existing keys (currently 659) get appended to the 'stock_change' table,
	   along with their current qtys (values taken from the 'stock' table) and the
	   current date.
	   Any existing records in the 'stock_change' table older than 30 days will be deleted.

The following may happen:
	1) Any SKUs pulled in by FESP that don't exist in the stock control database, get added
	   to the 'missing_skus' table, along with and the current date.
	   Any SKUs previously added, that have since been added to the 'sku_atts' table, will
	   be deleted.

!!! IMPORTANT !!!
Be aware that the stock control system only deducts products in FESP that have been scanned
(barcode 'status' = 'MARKED').
Consequently, stock out will always be zero on Saturdays and Sundays
(apart from when Vova processes orders at the weekend over the Summer months).

DBs:
SANDBOX/barcodeDB/orders.db3
FESP-REFACTOR/cache.db3
StockControl/stock_control.db3
*/

// Required to run via Windows Scheduler
$workingPath = dirname(realpath(__FILE__));
chdir($workingPath);
set_include_path($workingPath);

$path = 'C:\inetpub\wwwroot\\';

$stock_control = 'C:\xampp\htdocs\stocksystem\PHPAPI\stock_control.db3';
$cache = "$path\FESP-REFACTOR\cache.db3";
$orders_db = "$path\SANDBOX\barcodeDB\orders.db3";


$db_stock_control = new PDO('sqlite:' . $stock_control);
$sql = "SELECT * FROM `sku_atts`";
$skusTbl = $db_stock_control->query($sql)->fetchAll(PDO::FETCH_ASSOC);
// echo '<pre style="background:#002; color:#fff;">'; print_r($skusTbl); echo '</pre>'; die();



/*=========================================================================
| Delete 'stock_change' & 'sku_stock' records older than 30 days
|========================================================================*/
$today_minus30 = date('Ymd', strtotime(date('Y-m-d') . ' - 30 days'));

$sql = "DELETE FROM `sku_stock` WHERE `date` < '$today_minus30'";
$db_stock_control->query($sql);

/*=========================================================================
| Delete 'missing_skus' & 'sku_atts_new' records if SKU exists in 'sku_atts'
|========================================================================*/
$sql = "DELETE FROM 'missing_skus' WHERE `sku` IN (SELECT `sku` FROM `sku_atts`)";
$db_stock_control->query($sql);

$sql = "DELETE FROM 'sku_atts_new' WHERE `sku` IN (SELECT `sku` FROM `sku_atts`)";
$db_stock_control->query($sql);

$date_minus_5days = date('Y-m-d', strtotime(date('Y-m-d') . ' - 5 days'));
//@@@ DEBUG
// $date_minus_5days = '2019-11-01';
$timestamp_from = strtotime($date_minus_5days . ' 00:00');
// define('ROOT', $_SERVER['DOCUMENT_ROOT']);

$db = new PDO('sqlite:' . $orders_db);
$sql = "SELECT `orderID` FROM `orders` WHERE `status` = 'MARKED' AND `statusTime` >= $timestamp_from";
$barcodeDB = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$in = [];
foreach ($barcodeDB as $vals) {
	$in[] = $vals['orderID'];
}
$in_str = implode("','", $in);

$db = new PDO('sqlite:' . $cache);

//@@@ DEBUG
$x_items = [
	'coirfert_5g_10' => 1,
	'coir_fertiliser_5g_10' => 1,
	'coir_fertiliser_5g_5' => 1,
	'coirfert_30g_5' => 1,
	'coir_fertiliser_30g_10' => 1,
	'coir_fertiliser_30g_5' => 1,
	'coirfert_30g_15' => 1,
	'coirfert_5g_5' => 1,
	'2X1KGBS_TUB' => 1,
	'2X1KGBS_BAG' => 1,
	'2-X-1-CITRIC-ACID_TUB' => 1,
	'EPSOM_SALT_ORGANIC_10KG_2' => 1,
	'0001#2 x 1kg (Tub)' => 1,
	'0001#2 x 1kg (Polythene Bag)' => 1,
	'EPSOM_PH_2-X-5KG_BAG' => 1,
	'elixirsalt_5kg_6' => 1,
	'elixirsalt_5kg_2' => 1,
	'elixirsalt_5kg_3' => 1,
	'elixirsalt_5kg_4' => 1,
	'2X5KG_PH_EPSOM_BAG' => 1,
	'elixirsalt_5kg_5' => 1,
	'PRIME_2x5KG_PH_EPSOM_BAG' => 1,
	'0008#2 x 1kg (Polythene Bag)' => 1,
	'0015#2 x 1kg (Polythene Bag)' => 1,
	'2x1Kg_Dead-Sea_Coarse_Tub' => 1,
	'2x1Kg_Dead-Sea_Coarse_Bag' => 1,
	'DSBS_1KG_x04' => 1,
	'DSBS_1KG_x10' => 1,
	'DSBS_1KG_x06' => 1,
	'DSBS_500G_x02' => 1,
	'DSBS_1KG_x02' => 1,
	'2X1KGDS_BAG' => 1,
	'2X1KGDS_TUB' => 1,
	'2x500g_fine_bag' => 1,
	'2x1Kg_Dead-Sea_Fine_Bag' => 1,
];

$sql = "SELECT * FROM `orders` WHERE `orderID` IN('$in_str') AND `dateRetrieved` >= $timestamp_from";
$resultsFESP = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);


/*
	[content] => {"orderID":"026-0021182-8378728","source":"amazon","channel":"elixir","total":"17.67","date":"2019-12-18T04:15:45.351Z","buyer":"Gregory Ogden","phone":"07851 377109","email":"6bmcx2l1t7cwrwb@marketplace.amazon.co.uk","service":"SFP","shipping":{"name":"Gregory Ogden","address1":"25 Dyers Mews","address2":"Neath Hill","city":"MILTON KEYNES","county":"Bucks","countryCode":"GB","postCode":"MK14 6ER"},"items":{"B072KHGT3M-0":{"itemID":"B072KHGT3M","SKU":"PRIME_rodex_100g_1","quantity":3,"name":"Elixir Gardens  Rat Poison Strongest Available Online - 100g Sachets Rodex Rodent Control PRIME","variations":{"PackageQuantity":"1"},"price":"17.67","shipping":null,"url":"http:\/\/www.amazon.co.uk\/dp\/B072KHGT3M"}},"message":"","status":"","barcodeGenerated":"","barcode":"","courier":"SFP","parcelCount":1,"weight":0.3,"length":0}

	[content] => {"orderID":"781889","source":"ebay","channel":"elixir","total":"6.99","date":"2019-12-17T12:11:20.000Z","buyer":"ice-black-fire5","phone":"07523386705","email":"ice-black-fire@hotmail.co.uk","service":"standard","shipping":{"name":"kerri audoire","address1":"29 Colesborne Close","address2":"","city":"Worcester","county":"Worcestershire","countryCode":"GB","postCode":"WR4 9XF"},"items":{"401994901965-0":{"itemID":"401994901965","SKU":"AUCTION_DM9","quantity":"1","name":"End of Line Clearance Stock Wipe your Paws Door Mat Natural Coir","variations":null,"price":"3.99","shipping":"3","url":"http:\/\/www.ebay.co.uk\/itm\/401994901965"}},"message":"","status":"MARKED","barcodeGenerated":1576659831,"barcode":"40786699","courier":"N\/A","parcelCount":1,"weight":0,"length":0}

	[content] => {"orderID":58015,"source":"website","channel":"elixir","total":"284.89","date":"2019-12-17T10:14:49Z","buyer":"Clark","phone":"07910870035","email":"","service":"standard","shipping":{"name":"Robert Clark (Scentz Wholesale Ltd)","company":"Scentz Wholesale Ltd","address1":"107 High Street","address2":"Eston","city":"Middlesbrough","county":"","countryCode":"GB","postCode":"TS6 9JD"},"items":{"24081-0":{"itemID":24081,"SKU":"sodium_soda_25kg_bag","quantity":5,"name":"SODIUM BICARBONATE of Soda 25KG BAG 100% BP Food Grade |Bath, Baking","price":20.99,"url":"https:\/\/elixirgardensupplies.co.uk\/sodium-bicarbonate-of-soda-25kg-bag-100-bp-food-grade-bath-baking\/","shipping":0},"2131-1":{"itemID":2131,"SKU":"00316","quantity":3,"name":"25Kg CITRIC ACID FOR BATH BOMBS, DE SCALING ETC FOOD GRADE","price":44.99,"url":"https:\/\/elixirgardensupplies.co.uk\/25kg-citric-acid-for-bath-bombs-de-scaling-etc-food-grade\/","shipping":0},"29202-2":{"itemID":29202,"SKU":"EPSOM_SALT_SINGLE_25KG","quantity":3,"name":"EPSOM BATH SALTS 20KG Pharmaceutical Grade Magnesium Sulphate Replaces 25kg Bag","price":14.99,"url":"https:\/\/elixirgardensupplies.co.uk\/elixir-salts-pharmaceutical-epsom-salt-25kg-bag\/","shipping":0}},"message":"quarter pallet ","status":"MARKED","barcodeGenerated":1576580524,"barcode":"40786182","courier":"W48","parcelCount":10,"weight":260,"length":1.31}

	[content] => {"orderID":58015,"items":{"24081-0":{"itemID":24081,"SKU":"sodium_soda_25kg_bag","quantity":5},"2131-1":{"itemID":2131,"SKU":"00316","quantity":3},"29202-2":{"itemID":29202,"SKU":"EPSOM_SALT_SINGLE_25KG","quantity":3}}}
*/

$test_order = [
	[
		'orderID' => '60238',
		'content' => '{"orderID":"60238","items":{"0000-0":{"itemID":0000,"SKU":"gallup_2ltr","quantity":3},"0000-1":{"itemID":0000,"SKU":"full-tray_24_010","quantity":1}}}',
		// 3 x gallup_2ltr
		// 10 x full size tray stock
		// 10 x 24 cell pack
	],

	[
		'orderID' => 'T123450',
		'content' => '{"orderID":"T123450","items":{"0000-0":{"itemID":0000,"SKU":"COMP_LAWN_10KG","quantity":3}}}',
		// 30 of complete lawn
	],

	[
		'orderID' => 'T123451',
		'content' => '{"orderID":"T123451","items":{"0000-0":{"itemID":0000,"SKU":"dec_stones_blue_slate_20kg_02","quantity":2},"0000-1":{"itemID":0000,"SKU":"dec_stones_pea_gravel_20kg_10","quantity":1},"0000-2":{"itemID":0000,"SKU":"capillarymatting_080_10","quantity":1}}}',
		// 80 blue slate & 200 pea gravel & 10 capillary 80cm
	],

	[
		'orderID' => 'T123452',
		'content' => '{"orderID":"T123452","items":{"0000-0":{"itemID":0000,"SKU":"blk-net_06_010","quantity":1},"0000-1":{"itemID":0000,"SKU":"blk-net_06_003","quantity":3},"0000-2":{"itemID":0000,"SKU":"liquid_growmore_5litre","quantity":1},"0000-3":{"itemID":0000,"SKU":"EPSOM_SALT_OR_TUB_3","quantity":1}}}',
		// 10 black netting 6m & 9 black netting 6m & 5 liquid growmore & 3 epsom organic
	],
];
// $resultsFESP = $test_order;

/*
    (60238 | gallup_2ltr) UPDATE `stock` SET `qty` = 2877 WHERE `key` = wee_2g 			[ 2880 -> 2877 ]
    (60238 | full-tray_24_010) UPDATE `stock` SET `qty` = 220 WHERE `key` = see_fst 	[ 230 -> 220 ]
    (60238 | full-tray_24_010) UPDATE `stock` SET `qty` = 890 WHERE `key` = see_24cp 	[ 900 -> 890 ]
*/



$tmp = [];
foreach ($skusTbl as $vals) {
	$tmp[$vals['sku']] = $vals['atts'];
}
$skusTbl = $tmp;

$sql = "SELECT * FROM `stock`";
$stockTbl = $db_stock_control->query($sql)->fetchAll(PDO::FETCH_ASSOC);
$tmp = [];
foreach ($stockTbl as $vals) {
	$tmp[$vals['key']] = $vals['qty'];
}
$stockTbl = $tmp;

$sql = "SELECT * FROM `sku_stock`";
$track_order_stockTbl = $db_stock_control->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$tmp = [];
foreach ($track_order_stockTbl as $vals) {
	$tmp[$vals['orderID sku']] = $vals['date'];
}
$track_order_stockTbl = $tmp;

$order_stk = [];
$skus_not_in_stk = [];
foreach ($resultsFESP as $order) {
	$php_order = json_decode($order['content']);

	foreach ($php_order->items as $items) {
		if (!isset($track_order_stockTbl[$php_order->orderID . $items->SKU])) {
			if (isset($skusTbl[$items->SKU])) {
				$order_stk[] = [
					'orderID' => $php_order->orderID,
					'sku' => $items->SKU,
					'qty' => $items->quantity,
					'atts' => $skusTbl[$items->SKU],
				];
			}
			// Create list of SKUs in FESP that don't exist in our system
			else {
				$skus_not_in_stk[$items->SKU] = $items->SKU;
			}
		}
	}
}

/*
// Debug
$order_stk = [
	[
		'orderID' => '026-4800517-0758747',
		'sku' => 'groundcoverextra_p_2x25_25pegs',
		'qty' => '2',
		'atts' => '[gro_w2x25|l:50,fix_spp|q:25]',
	],[
		'orderID' => '205-8724353-6159524',
		'sku' => 'PRIME_TH02_ANALOGUE_GUARD',
		'qty' => '1',
		'atts' => '[ele_th90|q:1,ele_an|q:1,ele_tg2|q:1]',
	],
];
*/

// UPDATE `stock` SET `qty` = 10000


/*
CREATE TABLE IF NOT EXISTS `sku_stock` (
	`orderID sku` TEXT type UNIQUE,
	`date` TEXT
);
*/


$get_product_qtys_sold = [];
$stmt = $db_stock_control->prepare("UPDATE `stock` SET `qty` = ? WHERE `key` = ?");
$db_stock_control->beginTransaction();
$sql = [];
foreach ($order_stk as $vals) {
	$cats_arr = $vals['atts'];
	$cats_arr = explode(',', $cats_arr);

	$bag_tub = isset($atts_arr[1]) ? substr($atts_arr[1], 2) : '';

	foreach ($cats_arr as $cats) {
		list($key, $atts) = explode('|', $cats);

		$qtr_str = $atts;

		if (false !== stripos($qtr_str, 'x')) {
			list($q1, $q2) = explode('x', $qtr_str);
			$qtr_str = $q1 * $q2;
		}

		$qty = $qtr_str * $vals['qty'];
		$stockTbl[$key] = $stockTbl[$key] - $qty;

		if (!isset($get_product_qtys_sold[$key])) {
			$get_product_qtys_sold[$key]['total_amount'] = 0;
			$get_product_qtys_sold[$key]['total_orders'] = 0;
		}
		$get_product_qtys_sold[$key]['total_amount'] = $get_product_qtys_sold[$key]['total_amount'] + $qty;
		$get_product_qtys_sold[$key]['total_orders']++;

		//@@@ DEBUG
		$display_sku = '';
		$display_sku = '(' . $vals['orderID'] . ' | ' . $vals['sku'] . ') ';

		$sql[] = "{$display_sku}UPDATE `stock` SET `qty` = {$stockTbl[$key]} WHERE `key` = $key";


		$stmt->execute([$stockTbl[$key], $key]);
	}
}
$db_stock_control->commit();

// Update 'stock_change' table from 'stock' table after current stock has been updated via the previous operation
$sql = "SELECT `key`, `qty` FROM `stock`";
$stockTbl = $db_stock_control->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$sql = "SELECT key FROM products WHERE outOfStock = 1";
$outOfStockProducts = $db_stock_control->query($sql);
$outOfStockProducts = array_flip($outOfStockProducts->fetchAll(PDO::FETCH_COLUMN));

$stmt = $db_stock_control->prepare("INSERT INTO `stock_change` (`key`,`qty`,`date`,`outOfStock`) VALUES (?,?,?,?)");
$date = date('Ymd');
$db_stock_control->beginTransaction();
foreach ($stockTbl as $vals) {
	$setOos = null;
	if (isset($outOfStockProducts[$vals['key']])) {
		$setOos = 1;
	}
	$stmt->execute([$vals['key'], $vals['qty'], $date, $setOos]);
}
$db_stock_control->commit();

$sql = "SELECT * FROM `products`";
$productsTbl = $db_stock_control->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$key_product_lookup = [];
foreach ($productsTbl as $vals) {
	$key_product_lookup[$vals['key']]['product'] = $vals['product'];
	$key_product_lookup[$vals['key']]['unit'] = $vals['unit'];
}

$sql = "SELECT * FROM `stock_qty`";
$stockQtyTbl = $db_stock_control->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$stock_qty_lookup = [];
foreach ($stockQtyTbl as $vals) {
	$stock_qty_lookup[$vals['key']] = $vals['qty'];
}


// arsort($get_product_qtys_sold);

$tmp = [];
foreach ($get_product_qtys_sold as $key => $vals) {
	$tmp[$key] = $vals;
	$tmp[$key]['key'] = $key;
	$tmp[$key]['stock_qty'] = isset($stock_qty_lookup[$key]) ? $stock_qty_lookup[$key] : '?';
	$tmp[$key]['product'] = $key_product_lookup[$key]['product'];
	$tmp[$key]['unit'] = $key_product_lookup[$key]['unit'];

	if ('?' != $tmp[$key]['stock_qty']) {
		if (0 != $tmp[$key]['stock_qty']) {
			$tmp[$key]['diff'] = $vals['total_amount'] / $tmp[$key]['stock_qty'];
		} else {
			$tmp[$key]['diff'] = '- - -';
		}
	} else {
		$tmp[$key]['diff'] = '';
	}

	// $tmp[ $key ]['diff'] = '?' != $tmp[ $key ]['stock_qty'] ? $vals['total_amount'] / $tmp[ $key ]['stock_qty'] : '';
}

$get_product_qtys_sold = $tmp;

uasort($get_product_qtys_sold, function ($b, $a) {
	// Sort by 1st field
	$return_val = strnatcmp($a['diff'], $b['diff']);
	return $return_val;
});
$get_product_qtys_sold = array_values($get_product_qtys_sold);


$csv = [];
foreach ($get_product_qtys_sold as $vals) {
	$units = '';
	if ('w' == $vals['unit']) {
		$units = 'Kilograms';
	} elseif ('v' == $vals['unit']) {
		$units = 'Litres';
	} elseif ('l' == $vals['unit']) {
		$units = 'Metres';
	}


	$csv[] = "{$vals['key']}\t{$vals['total_amount']}\t{$vals['stock_qty']}\t$units\t{$vals['product']}";
}

$csv_str = implode("\n", $csv);


// Add orderIDs to database to track which orders have been processed
$stmt = $db_stock_control->prepare("INSERT INTO `sku_stock` (`orderID sku`,`date`) VALUES (?,?)");
$db_stock_control->beginTransaction();
foreach ($order_stk as $vals) {
	$stmt->execute([$vals['orderID'] . $vals['sku'], date('Ymd')]);
}

$db_stock_control->commit();

// Add missing skus to 'missing_skus' table
ksort($skus_not_in_stk, SORT_NATURAL);
$date = date('Ymd');
$stmt = $db_stock_control->prepare("INSERT INTO `missing_skus` (`sku`,`date`) VALUES (?,?)");
$db_stock_control->beginTransaction();
foreach ($skus_not_in_stk as $sku) {
	$stmt->execute([$sku, $date]);
}
$db_stock_control->commit();
