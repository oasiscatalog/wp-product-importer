<?php
/**
 *
 * Filename: common.php
 * Description: common.php загрузка основных функций
 *
 * Free
 * - oasis_get_action
 * - oasis_is_wpsc_activated
 * - oasis_is_woo_activated
 * - oasis_is_jigo_activated
 * - oasis_is_exchange_activated
 *
 */

if (!function_exists('oasis_get_action')) {
    /**
     * @todo description
     *
     * @param bool $prefer_get
     * @return bool|string
     */
    function oasis_get_action($prefer_get = false)
    {
        if (isset($_GET['action']) && $prefer_get) {
            return sanitize_text_field($_GET['action']);
        }

        if (isset($_POST['action'])) {
            return sanitize_text_field($_POST['action']);
        }

        if (isset($_GET['action'])) {
            return sanitize_text_field($_GET['action']);
        }

        return false;
    }
}

if (!function_exists('oasis_is_wpsc_activated')) {
    /**
     * * @todo description
     * @return bool
     */
    function oasis_is_wpsc_activated()
    {
        if (class_exists('WP_eCommerce') || defined('WPSC_VERSION')) {
            return true;
        }
    }
}

if (!function_exists('oasis_is_woo_activated')) {
    /**
     * * @todo description
     * @return bool
     */
    function oasis_is_woo_activated()
    {
        if (class_exists('Woocommerce')) {
            return true;
        }
    }
}

if (!function_exists('oasis_is_jigo_activated')) {
    /**
     * * @todo description
     * @return bool
     */
    function oasis_is_jigo_activated()
    {
        if (function_exists('jigoshop_init')) {
            return true;
        }
    }
}

if (!function_exists('oasis_is_exchange_activated')) {
    /**
     * * @todo description
     * @return bool
     */
    function oasis_is_exchange_activated()
    {
        if (function_exists('IT_Exchange')) {
            return true;
        }
    }
}
