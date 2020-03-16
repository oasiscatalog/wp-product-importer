<?php
set_time_limit(0);
ini_set('memory_limit', '2G');

/** Set up WordPress environment */
require_once(__DIR__ . '/../../../wp-load.php');

define('OASIS_PI_FILE', __FILE__);
define('OASIS_PI_DIRNAME', basename(dirname(__FILE__)));
define('OASIS_PI_RELPATH', basename(dirname(__FILE__)) . '/' . basename(__FILE__));
define('OASIS_PI_PATH', plugin_dir_path(__FILE__));
define('OASIS_PI_PREFIX', 'oasis_pi');
define('OASIS_PI_PLUGINPATH', WP_PLUGIN_URL . '/' . basename(dirname(__FILE__)));
define('OASIS_PI_VERSION', '1.0');

echo '[' . date('c') . '] Начало обновления товаров' . PHP_EOL;

include_once(OASIS_PI_PATH . 'common/common.php');
include_once(OASIS_PI_PATH . 'includes/functions.php');

try {
    $json_file = oasis_pi_get_option('json_file', false);

    $rawData = [];

    global $wpdb;
    $productSku = [];
    $results = $wpdb->get_results("SELECT meta_value FROM {$wpdb->prefix}postmeta WHERE meta_key = '_sku'", ARRAY_A);
    foreach ($results as $row) {
        $productSku[] = $row['meta_value'];
    }

    $urlParsed = parse_url($json_file);
    $queryParsed = [];
    parse_str($urlParsed['query'], $queryParsed);

    foreach (['category', 'articles'] as $f) {
        if (isset($queryParsed[$f])) {
            unset($queryParsed[$f]);
        }
    }
    $cleanJson = 'https://api.oasiscatalog.com/v4/products?' . http_build_query($queryParsed);

    if ($productSku) {
        foreach (array_chunk($productSku, 100) as $k => $chunk) {
            $rawData = array_merge($rawData, oasis_request($cleanJson,
                ['fieldset' => 'full', 'extend' => 'is_visible', 'articles' => implode(",", $chunk)]));
            echo '[' . date('c') . '] Загружена часть каталога из oasiscatalog.com (часть №' . $k . ')' . PHP_EOL;
            break;
        }
    }

//    $rawData = array_merge($rawData, oasis_request($json_file, ['fieldset' => 'full', 'extend' => 'is_visible']));

    echo '[' . date('c') . '] Загружен JSON каталог из oasiscatalog.com (часть №' . $k . ')' . PHP_EOL;

    $import = new stdClass;
    $import->start_time = time();
    $import->cancel_import = false;
    $import->failed_import = '';
    $import->log = '';
    $import->import_method = 'merge';
    $import->categoriesFile = oasis_pi_get_option('last_category_file');
    $import->json_file = $json_file;
    $import->force_images = false;

    oasis_pi_generate_categories();

    oasis_pi_generate_categories_map();

    if ($rawData) {
        foreach ($rawData as $row) {
            $product = new stdClass;
            $product->data = $row;

            oasis_pi_create_or_update_product();
            echo '[' . date('c') . '] ' . str_replace('<br />>>>>>> ', '', $import->log) . PHP_EOL;
            $import->log = '';
        }
    }

} catch (Exception $e) {
    throw new Exception($e->getMessage());
}

echo '[' . date('c') . '] Обновление успешно завершено' . PHP_EOL;