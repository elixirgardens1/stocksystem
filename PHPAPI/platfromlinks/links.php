<?php

$db = new PDO('sqlite:stock_control.db3');

$file = file_get_contents('FileExchange_Response_96632225.csv');
$arr = array_map("str_getcsv", explode("\n", $file));

$headers = $arr[0];
unset($arr[0]);

$tmp = [];
$missingFields = [];
foreach ($arr as $index => $record) {
    if (!isset($record[10])) {
        $record[10] = null;
    }

    if (count($record) == 11) {
        $tmp[] = array_combine($headers, $record);
    }
}
$arr = $tmp;

$ebaySkuLinks = [];
$groupItemId = 0;
foreach ($arr as $index => $sku) {
    if (!isset($ebaySkuLinks[$sku['CustomLabel']])) {
        $nextSku = next($arr);
        if ($sku['Relationship'] != 'Variation' && $nextSku['Relationship'] == 'Variation') {
            $groupItemId = $sku['ItemID'];
            continue;
        }

        if ($sku['Relationship'] == 'Variation' && isset($groupItemId)) {
            $ebaySkuLinks[$sku['CustomLabel']] = $groupItemId;
        } else {
            unset($groupItemId);
            $ebaySkuLinks[$sku['CustomLabel']] = $sku['ItemID'];
        }
    }
}

// $arrSkus = array_column($arr, 'CustomLabel');
// $arrSkus = array_flip($arrSkus);
// $ebaySkus = array_keys($ebaySkuLinks);
// $missingSkus = array_diff($ebaySkus, $arrSkus);

// all listings report 
$file = 'All+Listings+Report+04-20-2021.txt';
$csv = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

$headers = ["sku", "asin", "itemName", "price", "qty", "merchant"];
$listingReport = [];
foreach ($csv as $index  => $rec) {
    if ($index == 0) continue;

    $rec = explode("\t", $rec);
    $listingReport[$rec[0]] = $rec[1];
}

$sql = "SELECT * FROM sku_am_eb";
$existingSkus = $db->query($sql);
$existingSkus = $existingSkus->fetchAll(PDO::FETCH_ASSOC);

// Find asins that have been merged by amazon and store them in merged_asins
$skuAsins = [];
foreach ($existingSkus as $sku) {
    if ($sku['am_id']) {
        $skuAsins[$sku['sku']] = $sku['am_id'];
    }
}

$tmp = [];
foreach ($existingSkus as $sku) {
    $tmp[$sku['sku']] = $sku;
}
$existingSkus = $tmp;

// Insert/update the ebay links for the skus
$stmt = $db->prepare("UPDATE sku_am_eb SET eb_id = ? WHERE sku = ?");
$stmt2 = $db->prepare("INSERT INTO sku_am_eb VALUES (?,?,?,?,?)");
$db->beginTransaction();
foreach ($ebaySkuLinks as $sku => $itemID) {
    if (isset($existingSkus[$sku])) {

        $stmt->execute([$itemID, $sku]);
    } elseif (!isset($existingSkus[$sku])) {
        //DEBUG 
        echo '<pre style="background:#002; color:#fff;">';
        print_r("INSERTED" . $sku);
        echo '</pre>';
        $stmt2->execute([$sku, null, $itemID, null, null]);
    }
}
$db->commit();


// -----------------------------------------------------------------------------------------------------------------------------------------------------------

// Get list skus after instering/updating ebay links
$sql = "SELECT sku FROM sku_am_eb";
$existingSkus = $db->query($sql);
$existingSkus = array_flip($existingSkus->fetchAll(PDO::FETCH_COLUMN));

// Insert/update amazon links
$stmt = $db->prepare("UPDATE sku_am_eb SET am_id = ? WHERE sku = ?");
$stmt2 = $db->prepare("INSERT INTO sku_am_eb VALUES (?,?,?,?,?)");
$stmt3 = $db->prepare("INSERT INTO merged_asins VALUES (?,?)");
$db->beginTransaction();
foreach ($listingReport as $sku => $asin) {
    if (isset($existingSkus[$sku])) {
        // If exists and has changed, then record this value in merged_asins
        if (isset($skuAsins[$sku]) && $skuAsins[$sku] != $asin) {
            //DEBUG 
            echo '<pre style="background:#002; color:#fff;">';
            print_r("MERGED: " . $skuAsins[$sku] . " TO: " . $asin);
            echo '</pre>';
            $stmt3->execute([$sku, $skuAsins[$sku]]);
        }
        //DEBUG 
        echo '<pre style="background:#002; color:#fff;">';
        print_r("UPDATED: " . $sku);
        echo '</pre>';
        $stmt->execute([$asin, $sku]);
    } elseif (!isset($existingSkus[$sku])) {
        //DEBUG 
        echo '<pre style="background:#002; color:#fff;">';
        print_r("INSERTED: " . $sku);
        echo '</pre>';
        $stmt2->execute([$sku, $asin, null, null, null]);
    }
}
$db->commit();

// // xml 
// $file = 'wc-product-export-21-4-2021-1618996778362.csv';
// $csv = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
// $tmp = [];
// $headers = explode(",", $csv[0]);


// foreach ($csv as $index => $rec) {
//     if ($index == 0) continue;

//     $rec = explode(",", $rec);

//     if (!isset($rec[3])) {
//         $rec[3] = null;
//     }

//     $tmp[] = array_combine($headers, $rec);
// }
// $csvProducts = $tmp;

// $file = 'elixirgardensupplies.WordPress.2021-04-20.xml';
// $xml = simplexml_load_file($file);
// $post_idArr = json_encode($xml->xpath('//wp:post_id'));
// $post_idArr = json_decode($post_idArr, true);
// $tmp = [];
// foreach ($post_idArr as $id) {
//     $tmp[] = $id[0];
// }
// $post_idArr = $tmp;

// $xml = json_decode(json_encode($xml), true);
// $xml = $xml['channel']['item'];


// // //DEBUG 
// echo '<pre style="background:#002; color:#fff;">';
// print_r($csvProducts);
// echo '</pre>';
// die();
