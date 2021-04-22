<?php

$response = file_get_contents('https://elixirgardensupplies.co.uk/get_links.php?id=gArdEns_123');
$response = json_decode($response, true);

$tmp = [];
foreach($response as $index => $sku) {
    $link = str_replace('https://elixirgardensupplies.co.uk/product/', "", $sku['permalink']);
    $link = substr($link, 0, strlen($link) -1);

    //DEBUG
    echo '<pre style="background:#002; color:#fff;">';
    print_r($link);
    echo '</pre>';
    die();

    $tmp[$sku['sku']] = $link;
}

//DEBUG
echo '<pre style="background:#002; color:#fff;">';
print_r($response);
echo '</pre>';
die();
