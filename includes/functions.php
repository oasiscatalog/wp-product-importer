<?php
if (!session_id()) {
    session_start();
}

include_once(OASIS_PI_PATH . 'includes/product.php');
include_once(OASIS_PI_PATH . 'includes/category.php');

if (is_admin()) {
    include_once(OASIS_PI_PATH . 'includes/admin.php');

    $product = null;
    $import = null;
    /**
     * Инициализация плагина
     */
    function oasis_pi_import_init()
    {
        global $import, $product, $wpdb, $woocommerce;

        $wpdb->hide_errors();

        $timeout = 0;
        if (isset($_POST['timeout'])) {
            $timeout = sanitize_text_field($_POST['timeout']);
        }

        if (!ini_get('safe_mode')) {
            @set_time_limit($timeout);
        }

        @ini_set('memory_limit', '2G');

        // Prevent header sent errors for the import
        @ob_start();

        $action = (function_exists('oasis_get_action') ? oasis_get_action() : false);
        switch ($action) {

            // The opening Import screen
            default:
                $import = new stdClass;
                $import->log = '';
                $import->import_method = (isset($_POST['import_method']) ? $_POST['import_method'] : 'new');
                $import->advanced_log = (isset($_POST['advanced_log']) ? 1 : 0);

                break;

            // The options screen
            case 'upload':
                $import = new stdClass;
                $import->cancel_import = false;
                $import->advanced_log = absint(oasis_pi_get_option('advanced_log', 1));
                $import->timeout = absint(oasis_pi_get_option('timeout', 600));
                $import->upload_mb = wp_max_upload_size();
                $import->force_images = true;

                if (!empty($_POST['article'])) {
                    $json_file = oasis_pi_get_option('json_file', false);

                    $import->import_method = 'merge';

                    $urlParsed = parse_url($json_file);
                    $queryParsed = [];
                    parse_str($urlParsed['query'], $queryParsed);

                    foreach (['category', 'articles'] as $f) {
                        if (isset($queryParsed[$f])) {
                            unset($queryParsed[$f]);
                        }
                    }
                    $cleanJson = 'https://api.oasiscatalog.com/v4/products?' . http_build_query($queryParsed);

                    $rawData = oasis_request($cleanJson,
                        ['fieldset' => 'full', 'extend' => 'is_visible', 'articles' => $_POST['article']]);

                    foreach ($rawData as $row) {
                        $product = new stdClass;
                        $product->data = $row;

                        oasis_pi_create_or_update_product();
                    }
                    $import->errors = ob_get_clean();
                    $_SESSION['import_result'] = $import->log;
                    header('Location: /wp-admin/admin.php?page=oasis_pi&tab=import');
                    die();
                } else {
                    $json_file = $_POST['json_file'];

                    if (!empty($json_file)) {
                        if (substr_count($json_file, 'format=json') > 0) {
                            $items = oasis_request($json_file, ['limit' => 1]);
                            if (!$items) {
                                $import->cancel_import = true;
                                oasis_pi_admin_notice('Ссылка на выгрузку некорректная или возвращает 0 товаров. Измените ссылку и попробуйте снова',
                                    'error');
                            }
                        } else {
                            $import->cancel_import = true;
                            oasis_pi_admin_notice('Необходимо указать ссылку на выгрузку в формате JSON.', 'error');
                        }
                    } else {
                        $import->cancel_import = true;
                        oasis_pi_admin_notice('Необходимо указать ссылку на выгрузку из API.', 'error');
                    }

                    oasis_pi_update_option('json_file', $json_file);

                    if ($import->cancel_import) {
                        continue;
                    }

                    $import->json_file = $json_file;
                }
                break;

            // The AJAX import engine
            case 'save':

                global $product;

                $import = new stdClass;
                $import->cancel_import = false;
                $import->log = '';
                $import->import_method = (isset($_POST['import_method']) ? $_POST['import_method'] : 'new');
                $import->advanced_log = (isset($_POST['advanced_log']) ? 1 : 0);

                $import->json_file = $_POST['json_file'];

                oasis_pi_update_option('import_method', $import->import_method);
                oasis_pi_update_option('advanced_log', absint($import->advanced_log));

                // Check if our import has expired
                if (!oasis_pi_get_option('json_file')) {
                    $import->cancel_import = true;
                    oasis_pi_admin_notice('Ваш ссылка на выгрузку устарела. Начните процедуру загрузки сначала.',
                        'error');
                }

                if ($import->cancel_import) {
                    return;
                }

                $step = 'prepare_data';
                $settings = $_POST;

                wp_enqueue_script('jquery');
                wp_enqueue_script('progressBar', plugins_url('/js/progress.js', OASIS_PI_FILE), array('jquery'));
                wp_enqueue_script('ajaxUpload', plugins_url('/js/ajaxupload.js', OASIS_PI_FILE), array('jquery'));
                wp_register_script('ajaxImporter', plugins_url('/js/engine.js', OASIS_PI_FILE), array('jquery'));
                wp_enqueue_script('ajaxImporter');
                wp_localize_script('ajaxImporter', 'ajaxImport', array(
                    'settings' => $settings,
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'step' => $step
                ));

                oasis_pi_update_option('json_file', $import->json_file);

                unset($step, $settings);
                break;
        }
    }

    /**
     * Ajax - импортер товара
     */
    function oasis_pi_ajax_product_importer()
    {
        if (isset($_POST['step'])) {
            global $import;

            if (!ini_get('safe_mode')) {
                @ini_set('memory_limit', '2G');
            }

            ob_start();

            switch ($_POST['step']) {
                case 'prepare_data':
                    $import = new stdClass;
                    $import->start_time = time();
                    $import->cancel_import = false;
                    $import->failed_import = '';
                    $import->log = '';
                    $import->import_method = (isset($_POST['import_method']) ? $_POST['import_method'] : 'new');

                    $import->json_file = oasis_pi_get_option('json_file', false);

                    oasis_pi_prepare_data();
                    $import->log .= '<br />' . sprintf('Загрузка товаров из API завершено. Загружено: %s товаров',
                            $import->rows);
                    $import->log .= "<br /><br />Обработка категорий...";
                    $import->loading_text = 'Обработка категорий...';
                    break;

                case 'generate_categories':
                    $import = new stdClass;
                    $import->start_time = time();
                    $import->cancel_import = false;
                    $import->failed_import = '';
                    $import->log = '';
                    $import->import_method = (isset($_POST['settings']['import_method']) ? $_POST['settings']['import_method'] : 'new');

                    $import->categoriesFile = oasis_pi_get_option('last_category_file');

                    if (file_exists($import->categoriesFile)) {
                        oasis_pi_generate_categories();
                    } else {
                        $import->log .= "<br />Обработка категорий пропущена. Не найден файл.";
                    }
                    $import->log .= "<br /><br />Обработка товаров...";
                    $import->loading_text = 'Импорт товаров...';
                    break;

                case 'prepare_product_import':
                    $i = 0;
                    $import = new stdClass;
                    $import->start_time = time();
                    $import->cancel_import = false;
                    $import->failed_import = '';
                    $import->log = '';
                    $import->import_method = (isset($_POST['settings']['import_method']) ? $_POST['settings']['import_method'] : 'new');
                    break;

                case 'save_product':
                    global $import, $product;

                    $import = new stdClass;
                    $import->start_time = time();
                    $import->cancel_import = false;
                    $import->failed_import = '';
                    $import->log = '';
                    $import->import_method = (isset($_POST['settings']['import_method']) ? $_POST['settings']['import_method'] : 'new');

                    $i = $_POST['i'];

                    $import->categoriesFile = oasis_pi_get_option('last_category_file');

                    $import->json_file = oasis_pi_get_option('json_file', false);

                    $rawData = oasis_request($import->json_file, ['fieldset' => 'full', 'offset' => $i, 'limit' => 1]);

                    if ($rawData) {
                        $stat = oasis_request(str_replace('products', 'stat', $import->json_file), []);


                        switch ($import->import_method) {
                            case 'new':
                                $import->rows = $stat['products'];
                                break;
                            case 'merge':
                                $import->rows = $stat['products'];
                                break;
                            case 'update':
                                $import->rows = oasis_pi_return_product_count();
                                break;
                        }

                        oasis_pi_generate_categories_map();

                        $product = new stdClass;
                        $product->data = reset($rawData);

                        oasis_pi_create_or_update_product();

                    } else {
                        $import->log .= "<br />Обработка продуктов пропущена. Не найден файл.";
                    }

                    break;

                case 'clean_up':
                    global $wpdb, $product, $import;

                    $import = new stdClass;
                    $import->start_time = time();
                    $import->cancel_import = false;
                    $import->failed_import = '';
                    $import->log = '';
                    $import->import_method = (isset($_POST['import_method']) ? $_POST['import_method'] : 'new');
                    $import->advanced_log = (isset($_POST['advanced_log']) ? (int)$_POST['advanced_log'] : 0);

                    @unlink(oasis_pi_get_option('last_category_file'));

                    oasis_pi_update_option('last_category_file');

                    $import->log .= "<br />Очистка временных файлов завершена.";
                    $import->end_time = time();

                    $import->log .= "<br /><br />Импорт успешно завершен!";
                    $import->loading_text = 'Процесс завершен';
                    break;
            }

            // Clear transients
            if (function_exists('wc_delete_product_transients')) {
                wc_delete_product_transients();
            }

            $import->step = $_POST['step'];
            $import->errors = ob_get_clean();

            $return = array();
            if (isset($import->log)) {
                $return['log'] = $import->log;
            }
            if (isset($import->import_method)) {
                $return['import_method'] = $import->import_method;
            }
            if (isset($import->rows)) {
                $return['rows'] = $import->rows;
            }
            if (isset($import->skip_first)) {
                $return['skip_first'] = $import->skip_first;
            }
            if (isset($import->loading_text)) {
                $return['loading_text'] = $import->loading_text;
            }
            if (isset($import->cancel_import)) {
                $return['cancel_import'] = $import->cancel_import;
            }
            if (isset($import->failed_import)) {
                $return['failed_import'] = $import->failed_import;
            }
            if (isset($i)) {
                $return['i'] = $i;
            }
            if (isset($import->next)) {
                $return['next'] = $import->next;
            }
            if (isset($import->html)) {
                $return['html'] = $import->html;
            }
            if (isset($import->step)) {
                $return['step'] = $import->step;
            }

            @array_map('utf8_encode', $return);

            header("Content-type: application/json");
            echo json_encode($return);
        }
        die();
    }

    add_action('wp_ajax_product_importer', 'oasis_pi_ajax_product_importer');

    /**
     * Ajax - окончание импорта
     */
    function oasis_pi_ajax_finish_import()
    {
        global $import;

        $return = array();
        ob_start();
        $return['next'] = 'finish-import';
        $post_type = 'product';
        $manage_products_url = add_query_arg('post_type', $post_type, 'edit.php');

        include_once(OASIS_PI_PATH . 'templates/admin/import_finish.php');

        $return['html'] = ob_get_clean();
        header("Content-type: application/json");
        echo json_encode($return);

        die();
    }

    add_action('wp_ajax_finish_import', 'oasis_pi_ajax_finish_import');

    /**
     *
     */
    function oasis_pi_finish_message()
    {
        echo '<div class="updated settings-error below-h2"><p>Импорт данных завершен!</p></div>';
    }

    /**
     * Increase memory for AJAX importer process and Product Importer screens
     */
    function oasis_pi_init_memory()
    {
        $page = $_SERVER['SCRIPT_NAME'];
        if (isset($_POST['action'])) {
            $action = $_POST['action'];
        } elseif (isset($_GET['action'])) {
            $action = $_GET['action'];
        } else {
            $action = '';
        }

        $allowed_actions = array('product_importer', 'finish_import', 'upload_image');

        if ($page == '/wp-admin/admin-ajax.php' && in_array($action, $allowed_actions)) {
            @ini_set('memory_limit', WP_MAX_MEMORY_LIMIT);
        }
    }

    add_action('plugins_loaded', 'oasis_pi_init_memory');
}

/**
 * Подготовка данных. Загрузка данных
 *
 */
function oasis_pi_prepare_data()
{
    global $import;

    parse_str(substr($import->json_file, strpos($import->json_file, '?') + 1), $queryData);

    $rawDataCategories = oasis_request('https://api.oasiscatalog.com/v4/categories?format=json&key=' . $queryData['key']);

    if ($rawDataCategories) {
        $categoriesFile = tempnam(sys_get_temp_dir(), 'categories_');
        file_put_contents($categoriesFile, json_encode($rawDataCategories));
        oasis_pi_update_option('last_category_file', $categoriesFile);
    }

    unset($rawDataCategories);
    $rawData = oasis_request(str_replace('products', 'stat', $import->json_file), []);
    if ($rawData) {
        $import->rows = $rawData['products'];
        unset($rawData);
    } else {
        $import->cancel_import = true;
        $import->failed_import = 'Не получилось загрузить данные из: ' . $import->json_file . '.';
        oasis_pi_error_log($import->failed_import);
    }
}

/**
 * Пути для загрузки файлов
 */
function oasis_pi_upload_directories()
{
    global $import;

    $upload_dir = wp_upload_dir();
    $import->uploads_path = sprintf('%s/', $upload_dir['path']);
    $import->uploads_basedir = sprintf('%s/', $upload_dir['basedir']);
    $import->uploads_subdir = $upload_dir['subdir'];
    $import->uploads_url = sprintf('%s/', $upload_dir['baseurl']);
    $import->date_directory = (get_option('uploads_use_yearmonth_folders', 0) ? date('Y/m/',
        strtotime(current_time('mysql'))) : false);
}

/**
 * @param string $message
 * @return bool|void
 */
function oasis_pi_error_log($message = '')
{
    if ($message == '') {
        return;
    }

    if (class_exists('WC_Logger')) {
        $logger = new WC_Logger();
        $logger->add(OASIS_PI_PREFIX, $message);
        return true;
    } else {
        error_log(sprintf('[Oasis-product-importer] %s', $message));
    }
}

/**
 * Получение опции по имени
 *
 * @param null $option
 * @param bool $default
 * @param bool $allow_empty
 * @return bool|mixed|string|void
 */
function oasis_pi_get_option($option = null, $default = false, $allow_empty = false)
{
    $output = '';
    if (isset($option)) {
        $separator = '_';
        $output = get_option(OASIS_PI_PREFIX . $separator . $option, $default);
        if ($allow_empty == false && $output != 0 && ($output == false || $output == '')) {
            $output = $default;
        }
    }
    return $output;
}

/**
 * Запись опции
 *
 * @param null $option
 * @param null $value
 * @return bool
 */
function oasis_pi_update_option($option = null, $value = null)
{
    $output = false;
    if (isset($option) && isset($value)) {
        $separator = '_';
        $output = update_option(OASIS_PI_PREFIX . $separator . $option, $value);
    }
    return $output;
}

/**
 * Запрос к API
 *
 * @param $url
 */
function oasis_request($url, $params = array())
{
    @ini_set('memory_limit', '2G');

    $data = false;
    try {

        $params['plugin'] = 'wordpress';
        $params['version'] = OASIS_PI_VERSION;

        if ($params) {
            $url .= '&' . http_build_query($params);
        }

        $dataRes = file_get_contents($url);
        $data = json_decode($dataRes, true);
    } catch (\Exception $e) {
        error_log(sprintf('[Oasis-product-importer] %s', $e->getMessage() . ' Line: ' . $e->getLine()));

    }
    return $data;
}
