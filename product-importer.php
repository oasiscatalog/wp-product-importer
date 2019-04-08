<?php
/*
Plugin Name: Oasiscatalog - Product Importer
Plugin URI: https://forum.oasiscatalog.com
Description: Импорт товаров из каталога oasiscatalog.com
Version: 1.0.1
Author: Oasiscatalog Team (Krasilnikov Andrey)
Author URI: https://forum.oasiscatalog.com
License: GPL2

WC requires at least: 2.3
WC tested up to: 3.1
*/

if (!defined('ABSPATH')) {
    exit;
}

define('OASIS_PI_FILE', __FILE__);
define('OASIS_PI_DIRNAME', basename(dirname(__FILE__)));
define('OASIS_PI_RELPATH', basename(dirname(__FILE__)) . '/' . basename(__FILE__));
define('OASIS_PI_PATH', plugin_dir_path(__FILE__));
define('OASIS_PI_PREFIX', 'oasis_pi');
define('OASIS_PI_PLUGINPATH', WP_PLUGIN_URL . '/' . basename(dirname(__FILE__)));
define('OASIS_PI_VERSION', '1.0');

if (!defined('OASIS_PI_DEBUG')) {
    define('OASIS_PI_DEBUG', false);
}

include_once(OASIS_PI_PATH . 'common/common.php');
include_once(OASIS_PI_PATH . 'includes/functions.php');

if (is_admin()) {

    /**
     * Register Product Importer in the list of available WordPress importers
     */
    function oasis_pi_register_importer()
    {
        register_importer(
            'oasi_pi',
            'Товары',
            '<strong>Oasis - импорт товаров</strong> - Импорт товаров в WooCommerce из API oasiscatalog.com.',
            'oasis_pi_html_page'
        );
    }

    add_action('admin_init', 'oasis_pi_register_importer');

    /**
     * Initial scripts and import process
     */
    function oasis_pi_admin_init()
    {
        if (current_user_can('manage_woocommerce') == false) {
            return;
        }

        $product_importer = false;
        if (isset($_GET['import']) || isset($_GET['page'])) {
            if (isset($_GET['import'])) {
                if ($_GET['import'] == OASIS_PI_PREFIX) {
                    $product_importer = true;
                }
            }
            if (isset($_GET['page'])) {
                if ($_GET['page'] == OASIS_PI_PREFIX) {
                    $product_importer = true;
                }
            }
        }
        if ($product_importer !== true) {
            return;
        }

        @ini_set('memory_limit', WP_MAX_MEMORY_LIMIT);
        oasis_pi_import_init();
    }

    add_action('admin_init', 'oasis_pi_admin_init');

    /**
     * HTML templates and form processor for Product Importer screen
     */
    function oasis_pi_html_page()
    {
        global $import;

        // Check the User has the manage_woocommerce capability
        if (current_user_can('manage_woocommerce') == false) {
            return;
        }

        $action = (function_exists('oasis_get_action') ? oasis_get_action() : false);
        $title = 'Oasis - импорт товаров';
        if (in_array($action, array('upload', 'save')) && !$import->cancel_import) {
            if ($file = oasis_pi_get_option('csv')) {
                $title .= ': <em>' . basename($file) . '</em>';
            }
        }

        oasis_pi_template_header($title);
        oasis_pi_upload_directories();
        switch ($action) {
            case 'upload':

                // Display the opening Import tab if the import fails
                if ($import->cancel_import) {
                    oasis_pi_manage_form();
                    return;
                }

                if (!empty($import->json_file)) {
                    $products = oasis_pi_return_product_count();

                    if (!$products) {
                        $import->import_method = 'new';
                    }

                    $template = 'import_upload.php';
                    if (file_exists(OASIS_PI_PATH . 'templates/admin/' . $template)) {
                        include_once(OASIS_PI_PATH . 'templates/admin/' . $template);
                    } else {
                        $message = sprintf('We couldn\'t load the import template file <code>%s</code> within <code>%s</code>, this file should be present.',
                            $template, OASIS_PI_PATH . 'templates/admin/...');
                        oasis_pi_admin_notice_html($message, 'error');
                    }
                }
                break;

            case 'save':
                // Display the opening Import tab if the import fails
                if ($import->cancel_import == false) {
                    include_once(OASIS_PI_PATH . 'templates/admin/import_save.php');
                } else {
                    oasis_pi_manage_form();
                    return;
                }
                break;

            default:
                oasis_pi_manage_form();
                break;

        }
        oasis_pi_template_footer();
    }

    /**
     * HTML template for Import screen
     */
    function oasis_pi_manage_form()
    {
        $tab = false;
        if (isset($_GET['tab'])) {
            $tab = sanitize_text_field($_GET['tab']);
        } else {
            if (oasis_pi_get_option('skip_overview', false)) {
                // If Skip Overview is set then jump to Export screen
                $tab = 'import';
            }
        }
        $url = add_query_arg('page', 'oasis_pi');

        include_once(OASIS_PI_PATH . 'templates/admin/tabs.php');
    }
}
