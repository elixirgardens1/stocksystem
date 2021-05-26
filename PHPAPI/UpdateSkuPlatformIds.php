<?php
ini_set('memory_limit', '1024M');
/**
 * @author: Ryan Denby
 * @date: 24/05/2021
 * @link: 192.168.0.24:8080/stocksystem/dist/PHPAPI/UpdateSkuPlatformIds.php
 **/

// Define class dependencies
$amPath = '/mnt/deepthought/FESP-REFACTOR/FespMVC/NEW_API_SYSTEM/amazon_mws/MWSRequest.php';
$ebPath = '/mnt/deepthought/FESP-REFACTOR/FespMVC/Controller/EbayRequest.php';

require_once "$amPath";
require_once "$ebPath";

// Typical namespacing shit, means you have to put use when using require_once for any class that uses namespaces
use FespMVC\Controller\EbayRequest;

$MWSR = new MWSRequest(6, 1, 60);
$ER = new EbayRequest();

//DEBUG 
echo '<pre style="background:#002; color:#fff;">';
print_r($ER);
echo '</pre>';
die();

// Keep this for week or so, just in case data gets manged
copy('C:\xampp\htdocs\stocksystem\PHPAPI\stock_control.db3', 'C:\xampp\htdocs\stocksystem\PHPAPI\copyOfDb\stock_control.db3');

// Define database connections
$scPath  = 'C:\xampp\htdocs\stocksystem\PHPAPI\stock_control.db3';
$db = new PDO('sqlite:' . $scPath);

// Define arrays
$changedPlatformIds = [];
$newPlatformIds = [];

// Get list of all the links we currently have stored in the system
$sql = "SELECT * FROM sku_am_eb";
$platIds = $db->query($sql);
$platIds = $platIds->fetchAll(PDO::FETCH_ASSOC);

// Format so [sku] => [platids] format
$tmp = [];
foreach ($platIds as $index => $sku) {
    $tmp[$sku['sku']] = $sku;
}
$platIds = $tmp;

// PLATFORM 1
// First handle Amazons platfrom links as the only method of getting the information is by requesting
// a csv via the api, which can take a varying amount of time for them to produce, therefore if Amazon
// has not returned the data within 10 minutes the script will cancel and run again the next day.

// Get the asins from amazon we will use to build urls
$parameters = [
    'ReportType' => '_GET_FLAT_FILE_OPEN_LISTINGS_DATA_',
];

$requestAm = $MWSR->request('RequestReport', $parameters, 'Reports');
$requestAm = json_decode(json_encode(new SimpleXMLElement($requestAm)), true);

// Need this to request the report back from amazon
$requestReportId = $requestAm['RequestReportResult']['ReportRequestInfo']['ReportRequestId'];

// Give few mins for them to produce the report, before requesting its status
sleep(120);

// Function to fire status check for the report we requested above
function checkReportStatus($reportId, $MWSR)
{
    $parameters = [
        'ReportRequestIdList.Id.1' => $reportId,
        'ReportTypeList.Type.1' => '_GET_FLAT_FILE_OPEN_LISTINGS_DATA_',
        'ReportProcessingStatusList.Status.1' => '_DONE_',
    ];

    $requestAm = $MWSR->request('GetReportRequestList', $parameters, 'Reports');
    $requestAm = json_decode(json_encode(new SimpleXMLElement($requestAm)), true);

    // If report is not ready return false
    if ($requestAm['GetReportRequestListResult']['ReportRequestInfo']['ReportProcessingStatus'] != '_DONE_') {
        return false;
    }

    // Else the report is ready and return it
    return $requestAm['GetReportRequestListResult']['ReportRequestInfo']['GeneratedReportId'];
}

// Use the function we defined above to fire requests to Amazon to check the status of the report
// if after 5 attempts (10 mins) does not return done status, exit script and run next day
for ($i = 0; $i < 5; $i++) {
    $statusResponse = checkReportStatus($requestReportId, $MWSR);

    // Report ready set the id need to get the data
    if ($statusResponse !== false) {
        $generatedReportId = $statusResponse;
        break;
    }

    sleep(120);
}

// // If the generated report id variable is not set then the Amazon has not retunred the done status
//  for the report, report that the script timed out on stock_admin, script will be run next day
if (!isset($generatedReportId)) {
    $stmt = $db->prepare("INSERT INTO stock_admin VALUES (?,?,?,?)");
    $db->beginTransaction();
    $stmt->execute(['PlatformID-SCRIPT', 'Script timed out while wating for amazon response, will run next hour', 'Low', time()]);
    $db->commit();
    die();
}

// Else if the variable is set, then the report is ready to be requested
$parameters = [
    'ReportId' => $generatedReportId,
];

// Amazon return tab delimited response
$requestAm = $MWSR->request('GetReport', $parameters, 'Reports');

// Format that amazon response into array format
$amazonIds = explode("\n", $requestAm);
$headers = explode("\t", $amazonIds[0]);
unset($amazonIds[0]);

// Reformat the response into an [sku] => [asin]
$tmp = [];
foreach ($amazonIds as $index => $row) {
    $row = explode("\t", $row);

    if ($row[0]) {
        $row = array_combine($headers, $row);
        $tmp[$row['sku']] = $row['asin'];
    }
}
$amazonIds = $tmp;

// Build array to compare against the values we currently have in the stock system
$currentAmIds = array_column($platIds, 'am_id', 'sku');

// If not in the system, or if is in the system and the asin related to it has changed
foreach ($amazonIds as $sku => $asin) {

    // Add platform to changed skus array
    if (isset($currentAmIds[$sku]) && $currentAmIds[$sku] != $asin) {
        $changedPlatformIds[$sku]['am_id'] = $asin;
    }

    // Add platform id to new sku array
    if (!isset($currentAmIds[$sku])) {
        $newPlatformIds[$sku]['am_id'] = $asin;
    }
}


// PLATFORM 2
// Get the inventory list from Ebay via the api and check the platform ids against what we have
// stored in the system. NOTE: Ebay platform ids rarely changed in comparission to Amazon, so we do
// not need to store information about which ones get updated. (merged_asins)

// Get ebay active inventory list
$ebayIds = $ER->request(
    'GetMyeBaySelling',
    [
        'ActiveList' => [
            'Include' => true,
            'Pagination' => [
                'EntriesPerPage' => 200,
                'PageNumber' => 1,
            ],
        ],
        'DetailLevel' => 'ReturnSummary',
    ]
);

// Get elixir response, decode into assoc array from xml object
$ebayIds = simplexml_load_string($ebayIds['elixir'], 'SimpleXMLElement');
$ebayIds = json_decode(json_encode((array)$ebayIds), true);

// Get pagination page count
$tmp = [];
$paginationPages = $ebayIds['ActiveList']['PaginationResult']['TotalNumberOfPages'] + 1;
$tmp += $ebayIds['ActiveList']['ItemArray']['Item'];

// Build array of items from each of page
for ($i = 2; $i < $paginationPages; $i++) {
    $ebayIds = $ER->request(
        'GetMyeBaySelling',
        [
            'ActiveList' => [
                'Include' => true,
                'Pagination' => [
                    'EntriesPerPage' => 200,
                    'PageNumber' => $i,
                ],
            ],
            'DetailLevel' => 'ReturnSummary',
        ]
    );
    $ebayIds = simplexml_load_string($ebayIds['elixir'], 'SimpleXMLElement');
    $ebayIds = json_decode(json_encode((array)$ebayIds), true);
    foreach ($ebayIds['ActiveList']['ItemArray']['Item'] as $index => $item) {
        $tmp[] = $item;
    }
}
$ebayIds = $tmp;

// Get the [sku] => [itemID] values to be saved into the sku_am_eb table
// Ebay response isnt as clean as the amazon one and includes parent skus, which we dont need to store
// If the variation is set in the item array then we can assume that the sku in the $item is the parent
// and the skus in the $item['Variations'] array are the skus we need to record the itemID for.
$tmp = [];
$noSkuItemIds = [];
foreach ($ebayIds as $index => $item) {
    $itemID = $item['ItemID'];

    // If no the $item has variations ignore the base level sku, as this is a parent, only add the variations
    if (!isset($item['Variations'])) {
        // If No SKU associated with the itemid, save this to be reported back via stock control admin page
        if (!isset($item['SKU'])) {
            $noSkuItemIds[$itemID] = $index;
        }

        // Elese the SKU is set and append this itemid
        if (isset($item['SKU'])) {
            $tmp[$item['SKU']] = $itemID;
        }
    }

    // Foreach of the variation skus append this itemid
    if (isset($item['Variations'])) {
        foreach ($item['Variations']['Variation'] as $i => $var) {

            // Singular variation
            if ($i === 'SKU') {
                $tmp[$var] = $itemID;
            }

            // Multiple variations, some have missing sku feild, so cant be used
            if (is_numeric($i) && isset($var['SKU'])) {
                $tmp[$var['SKU']] = $itemID;
            }
        }
    }
}
$ebayIds = $tmp;

// Get the current skus => itemid for ebay stored in our system
$currentEbIds = array_column($platIds, 'eb_id', 'sku');

// If not in the sytem, or if it in is the system but the itemId hass changed
foreach ($ebayIds as $sku => $itemId) {

    // Same as Amazon, add skus that have changed for this platform
    if (isset($currentEbIds[$sku]) && $currentEbIds[$sku] != $itemId && $itemId) {
        $changedPlatformIds[$sku]['eb_id'] = $itemId;
    }

    // Same as Amazon, add new skus for this platform
    if (!isset($currentEbIds[$sku])) {
        $newPlatformIds[$sku]['eb_id'] = $itemId;
    }
}


// PROCESS THE PLATFORM IDS INTO THE STOCK CONTROL
//
// Check for any skus not in the sku_atts table
$sql = "SELECT sku FROM sku_atts";
$skuAtts = $db->query($sql);
$skuAtts = $skuAtts->fetchAll(PDO::FETCH_COLUMN);

// Check missing from changed skus, should always be none
$missingSkuAtts = array_diff(array_keys($changedPlatformIds), $skuAtts);

// Check missing from new sku platform links
$missingNewSkuAtts = array_diff(array_keys($newPlatformIds), $skuAtts);

// Add missing skus into one array
$missingSkuAtts = $missingSkuAtts + $missingNewSkuAtts;

// Update existing skus with the updated platformlinks
$stmt = $db->prepare("UPDATE sku_am_eb SET am_id = ? , eb_id = ? WHERE sku = ?");
$stmtAm = $db->prepare("INSERT OR REPLACE INTO merged_asins VALUES (?,?,?,?)");
$db->beginTransaction();
foreach ($changedPlatformIds as $sku => $platforms) {
    // Should always have a value, so if a the position is set updat the database
    $amId = isset($platforms['am_id']) ? $platforms['am_id'] : $platIds[$sku]['am_id'];
    $ebId = isset($platforms['eb_id']) ? $platforms['eb_id'] : $platIds[$sku]['eb_id'];

    // Update the table
    $stmt->execute([$amId, $ebId, $sku]);

    // Insert into merged if the amazon id is changed for this sku
    if (isset($platforms['am_id'])) {
        $previousAsin = isset($platIds[$sku]['am_id']) ? $platIds[$sku]['am_id'] : null;

        $stmtAm->execute([$sku, $previousAsin, $platforms['am_id'], time()]);
    }
}
$db->commit();

// Insert new skus into sku_am_eb
$stmt = $db->prepare("INSERT INTO sku_am_eb VALUES (?,?,?,?,?)");
$db->beginTransaction();
foreach ($newPlatformIds as $sku => $platforms) {
    $amId = isset($platforms['am_id']) ? $platforms['am_id'] : null;
    $ebId = isset($platforms['eb_id']) ? $platforms['eb_id'] : null;

    // Insert values if they are set
    $stmt->execute([$sku, $amId, $ebId, null, null]);
}
$db->commit();

// If Skus Are Missing From Skus Atts,  Insert the missing sku atts and make an error message in stock_admin
if (count($missingSkuAtts) > 0) {
    $stmt = $db->prepare("INSERT INTO sku_atts_new VALUES (?,?,?,?)");
    $stmtAdmin = $db->prepare("INSERT INTO stock_admin VALUES (?,?,?,?)");
    $db->beginTransaction();

    // Put alert into stock_admin to tell users they need to sort missing sku_atts out
    $stmtAdmin->execute(['PlatformID-SCRIPT', 'Skus missing from sku_atts, added to the stock_atts_new', 'MEDIUM', time()]);

    foreach ($missingSkuAtts as $index => $sku) {
        // Insert each of the missing skus into the sku_atts_new table
        $stmt->execute([$sku, null, null, time()]);
    }

    $db->commit();
}