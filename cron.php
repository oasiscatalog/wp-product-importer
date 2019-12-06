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

    $rawData = oasis_request($json_file, ['fieldset' => 'full', 'extend' => 'is_visible']);

    $import = new stdClass;
    $import->start_time = time();
    $import->cancel_import = false;
    $import->failed_import = '';
    $import->log = '';
    $import->import_method = (isset($_POST['settings']['import_method']) ? $_POST['settings']['import_method'] : 'new');
    $import->categoriesFile = oasis_pi_get_option('last_category_file');
    $import->json_file = $json_file;

    oasis_pi_generate_categories();

    oasis_pi_generate_categories_map();

    if ($rawData) {
        foreach ($rawData as $row) {
            $product = new stdClass;
            $product->data = $row;

            oasis_pi_create_or_update_product();
            if ($product->data['is_deleted'] == false && $product->data['is_visible'] == true) {
                echo '[' . date('c') . '] Обновлен товар с артикулом #' . $row['article'] . PHP_EOL;
            } else {
                echo '[' . date('c') . '] Удален товар с артикулом #' . $row['article'] . PHP_EOL;
            }
        }
    }

} catch (Exception $e) {
    throw new Exception($e->getMessage());
}

echo '[' . date('c') . '] Обновление успешно завершено' . PHP_EOL;