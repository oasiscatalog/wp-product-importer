<?php

/**
 * Отображение сообщений при загрузке
 *
 * @param string $message
 * @param string $priority
 * @param string $screen
 */
function oasis_pi_admin_notice($message = '', $priority = 'updated', $screen = '')
{
    if ($priority == false || $priority == '') {
        $priority = 'updated';
    }
    if ($message <> '') {
        ob_start();
        oasis_pi_admin_notice_html($message, $priority, $screen);
        $output = ob_get_contents();
        ob_end_clean();

        $existing_notice = get_transient(OASIS_PI_PREFIX . '_notice');
        if ($existing_notice !== false) {
            $existing_notice = base64_decode($existing_notice);
            $output = $existing_notice . $output;
        }
        $response = set_transient(OASIS_PI_PREFIX . '_notice', base64_encode($output), MINUTE_IN_SECONDS);
        if ($response !== false) {
            add_action('admin_notices', 'oasis_pi_admin_notice_print');
        }
    }
}

/**
 * HTML-шаблон для уведомления
 *
 * @param string $message
 * @param string $priority
 * @param string $screen
 */
function oasis_pi_admin_notice_html($message = '', $priority = 'updated', $screen = '')
{
    if (!empty($screen)) {
        global $pagenow;
        if (is_array($screen)) {
            if (in_array($pagenow, $screen) == false) {
                return;
            }
        } else {
            if ($pagenow <> $screen) {
                return;
            }
        }
    }
    echo '<div id="message" class="' . $priority . '">
        <p>' . $message . '</p>
    </div>';
}

/**
 * Отображение флеш-сообщений
 */
function oasis_pi_admin_notice_print()
{
    $output = get_transient(OASIS_PI_PREFIX . '_notice');
    if ($output !== false) {
        delete_transient(OASIS_PI_PREFIX . '_notice');
        $output = base64_decode($output);
        echo $output;
    }
}

/**
 * HTML-шаблон шапки
 *
 * @param string $title
 * @param string $icon
 */
function oasis_pi_template_header($title = '', $icon = 'woocommerce')
{
    echo '<div id="oasis-pi" class="wrap">
    <div id="icon-' . $icon . '" class="icon32 icon32-woocommerce-importer"><br/></div>
    <h2>' . $title . '</h2>';
}

/**
 * HTML-шаблон подвала
 */
function oasis_pi_template_footer()
{
    echo '</div>';
}

/**
 * Добаление ссылок на поддержку в админке
 *
 * @param $links
 * @param $file
 * @return mixed
 */
function oasis_pi_add_settings_link($links, $file)
{
    $this_plugin = OASIS_PI_RELPATH;

    if ($file == $this_plugin) {
        $import_link = sprintf('<a href="%s">Импорт товаров</a>', add_query_arg('page', 'oasis_pi', 'admin.php'));
        array_unshift($links, $import_link);
    }
    return $links;
}

add_filter('plugin_action_links', 'oasis_pi_add_settings_link', 10, 2);

/**
 * Add Store Export page to WooCommerce screen IDs
 *
 * @param array $screen_ids
 * @return array
 */
function oasis_pi_wc_screen_ids($screen_ids = array())
{
    $screen_ids[] = 'woocommerce_page_oasis_pi';
    return $screen_ids;
}

add_filter('woocommerce_screen_ids', 'oasis_pi_wc_screen_ids', 10, 1);

/**
 * Добаление пункта меню в админке
 */
function oasis_pi_admin_menu()
{
    $page = add_submenu_page('woocommerce', 'Oasis - импорт товаров', 'Oasis - импорт товаров', 'manage_woocommerce',
        'oasis_pi', 'oasis_pi_html_page');
    add_action('admin_print_styles-' . $page, 'oasis_pi_enqueue_scripts');
}

add_action('admin_menu', 'oasis_pi_admin_menu', 11);

/**
 * Load CSS and jQuery scripts for Product Importer screen
 *
 * @param $hook
 */
function oasis_pi_enqueue_scripts($hook)
{
    if (class_exists('WooCommerce')) {
        global $woocommerce;
        wp_enqueue_style('woocommerce_admin_styles', $woocommerce->plugin_url() . '/assets/css/admin.css');
    }

    wp_enqueue_style('oasis_pi_styles', plugins_url('/templates/admin/import.css', OASIS_PI_RELPATH));
    wp_enqueue_style('dashicons');
    wp_enqueue_script('jquery-toggleblock', plugins_url('/js/toggleblock.js', OASIS_PI_RELPATH), array('jquery'));

    wp_enqueue_style('oasis_vm_styles',
        plugins_url('/templates/admin/woocommerce-admin_dashboard_vm-plugins.css', OASIS_PI_RELPATH));
}

/**
 * HTML active class for the currently selected tab on the Product Importer screen
 *
 * @param null $tab_name
 * @param null $tab
 */
function oasis_pi_admin_active_tab($tab_name = null, $tab = null)
{
    if (isset($_GET['tab']) && !$tab) {
        $tab = $_GET['tab'];
    } else {
        if (!isset($_GET['tab']) && oasis_pi_get_option('skip_overview', false)) {
            $tab = 'import';
        } else {
            $tab = 'overview';
        }
    }

    $output = '';
    if (isset($tab_name) && $tab_name) {
        if ($tab_name == $tab) {
            $output = ' nav-tab-active';
        }
    }
    echo $output;
}

/**
 * HTML template for each tab on the Product Importer screen
 *
 * @param string $tab
 */
function oasis_pi_tab_template($tab = '')
{
    global $import;

    if (!$tab) {
        $tab = 'overview';
    }

    switch ($tab) {
        case 'import':
            oasis_pi_upload_directories();

            $json_file = oasis_pi_get_option('json_file', false);

            if (isset($_GET['import']) && $_GET['import'] == OASIS_PI_PREFIX) {
                $url = 'import';
            }
            if (isset($_GET['page']) && $_GET['page'] == OASIS_PI_PREFIX) {
                $url = 'page';
            }
            break;
    }
    if ($tab) {
        if (file_exists(OASIS_PI_PATH . 'templates/admin/tabs-' . $tab . '.php')) {
            include_once(OASIS_PI_PATH . 'templates/admin/tabs-' . $tab . '.php');
        } else {
            $message = sprintf('We couldn\'t load the import template file <code>%s</code> within <code>%s</code>, this file should be present.',
                'tabs-' . $tab . '.php', OASIS_PI_PATH . 'templates/admin/...');
            oasis_pi_admin_notice_html($message, 'error');
        }
    }
}
