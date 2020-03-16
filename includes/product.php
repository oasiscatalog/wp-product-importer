<?php
/**
 * Количество товаров
 *
 * @return int
 */
function oasis_pi_return_product_count()
{
    $post_type = 'product';
    $count = 0;
    if ($statuses = wp_count_posts($post_type)) {
        foreach ($statuses as $key => $status) {
            if (!in_array($key, ['auto-draft'])) {
                $count = $count + $status;
            }
        }
    }
    return $count;
}

/**
 * Создание товара
 *
 */
function oasis_pi_create_or_update_product()
{
    global $wpdb, $product, $import, $user_ID;

    $post_type = 'product';

    $meta_key = '_sku';
    $args = [
        'post_type'   => $post_type,
        'meta_key'    => $meta_key,
        'meta_value'  => $product->data['article'],
        'numberposts' => 1,
        'post_status' => 'any',
        'fields'      => 'ids',
    ];
    $products = new WP_Query($args);
    if (!empty($products->found_posts)) {
        $product->exists = $products->posts[0];
    } else {
        $product->exists = false;
    }

    $post_data = [
        'post_author'    => $user_ID,
        'post_date'      => current_time('mysql'),
        'post_date_gmt'  => current_time('mysql', 1),
        'post_title'     => (!empty($product->data['full_name']) ? $product->data['full_name'] : ''),
        'post_status'    => 'publish',
        'comment_status' => 'closed',
        'ping_status'    => 'closed',
        'post_type'      => $post_type,
        'post_content'   => (!is_null($product->data['description']) ? $product->data['description'] : ''),
        'post_excerpt'   => '',
        'tax_input'      => [
            'product_type' => 'simple',
        ],
    ];

    if ($product->data['is_deleted'] == false && $product->data['is_visible'] == true) {
        if (empty($product->exists) && in_array($import->import_method, ['new', 'merge'])) {
            $product->ID = wp_insert_post($post_data, true);

            if (is_wp_error($product->ID) !== true) {
                $wpdb->update($wpdb->posts, [
                    'guid' => sprintf('%s/?post_type=%s&p=%d', get_bloginfo('url'), $post_type, $product->ID),
                ], ['ID' => $product->ID]);

                oasis_pi_create_product_defaults();
                oasis_pi_create_or_update_product_details();

                if (function_exists('wc_delete_product_transients')) {
                    wc_delete_product_transients($product->ID);
                }
                $import->products_added++;
                $product->imported = true;

                $import->log .= "<br />>>>>>> " . sprintf('Добавлен товар: %s',
                        $post_data['post_title'] . ' (Арт.' . $product->data['article'] . ')');
            } else {
                $import->products_failed++;
            }
        } else {
            if (!empty($product->exists) && in_array($import->import_method, ['update', 'merge'])) {
                $product->ID = $product->exists;
                $post_data['ID'] = $product->ID;

                $existPost = get_post($product->exists);

                if (strtotime($product->data['updated_at']) > strtotime($existPost->post_modified)) {
                    if ($post_data['post_title'] != $existPost->post_title || $post_data['post_content'] != $existPost->post_content || $product->data['updated_at'] != $existPost->post_modified) {
                        wp_update_post($post_data);
                    }

                    oasis_pi_create_or_update_product_details();
                    $import->log .= "<br />>>>>>> " . sprintf('Товар обновлен: %s',
                            $post_data['post_title'] . ' (Арт.' . $product->data['article'] . ')');
                } else {
                    $import->log .= "<br />>>>>>> " . sprintf('Нет необходимости обновлять товар: %s',
                            $post_data['post_title'] . ' (Арт.' . $product->data['article'] . ')');
                }

                $import->products_added++;
                $product->imported = true;
            }
        }
    } elseif (!empty($product->exists)) {
        wp_delete_post($product->exists);
        $import->log .= "<br />>>>>>> " . sprintf('Товар удален: %s',
                $post_data['post_title'] . ' (Арт.' . $product->data['article'] . ')');
    }
}

/**
 * Подстановка параметров товара по умолчанию
 */
function oasis_pi_create_product_defaults()
{
    global $product;

    $defaults = [
        '_regular_price'         => 0,
        '_price'                 => '',
        '_sale_price'            => '',
        '_sale_price_dates_from' => '',
        '_sale_price_dates_to'   => '',
        '_sku'                   => '',
        '_weight'                => 0,
        '_length'                => 0,
        '_width'                 => 0,
        '_height'                => 0,
        '_tax_status'            => 'taxable',
        '_tax_class'             => '',
        '_stock_status'          => 'instock',
        '_visibility'            => 'visible',
        '_featured'              => 'no',
        '_downloadable'          => 'no',
        '_virtual'               => 'no',
        '_sold_individually'     => '',
        '_product_attributes'    => [],
        '_manage_stock'          => 'yes',
        '_backorders'            => 'no',
        '_stock'                 => '',
        '_purchase_note'         => '',
        'total_sales'            => 0,
    ];
    if ($defaults = apply_filters('oasis_pi_create_product_defaults', $defaults, $product->ID)) {
        if (OASIS_PI_DEBUG !== true) {
            foreach ($defaults as $key => $default) {
                update_post_meta($product->ID, $key, $default);
            }
        }
    }
}

/**
 * Создание детализации товара
 */
function oasis_pi_create_or_update_product_details()
{
    global $wpdb, $product, $import, $user_ID;

    oasis_pi_upload_directories();
    $allMetas = get_post_meta($product->ID);

    // Insert SKU
    if (!empty($product->data['article'])) {
        if ((!isset($allMetas['_sku']) && !isset($allMetas['_sku'][0])) || $allMetas['_sku'][0] != $product->data['article']) {
            update_post_meta($product->ID, '_sku', $product->data['article']);
        }
    }

    // Insert Price
    if (!empty($product->data['price'])) {
        if ((!isset($allMetas['_price']) && !isset($allMetas['_price'][0])) || $allMetas['_price'][0] != $product->data['price']) {
            update_post_meta($product->ID, '_regular_price', $product->data['price']);
            update_post_meta($product->ID, '_price', $product->data['price']);
        }
    }

    // Insert Sale Price
    if (!empty($product->data['discount_price'])) {
        if ((!isset($allMetas['_sale_price']) && !isset($allMetas['_sale_price'][0])) || $allMetas['_sale_price'][0] != $product->data['discount_price']) {
            update_post_meta($product->ID, '_sale_price', $product->data['discount_price']);
            update_post_meta($product->ID, '_price', $product->data['discount_price']);
        }
    }


    // Insert Category
    $term_taxonomy = 'product_cat';
    if (!empty($import->categories) && !empty($product->data['categories'])) {
        $linkedTerms = [];
        foreach ($product->data['categories'] as $category) {
            $linkedTerms[] = $import->categories[$category];
        }

        $existTaxonomy = wp_get_object_terms($product->ID, $term_taxonomy);
        $existTaxonomyNames = [];
        foreach ($existTaxonomy as $taxItem) {
            $existTaxonomyNames[] = $taxItem->name;
        }

        if (!empty($linkedTerms) && array_diff($existTaxonomyNames, $linkedTerms)) {
            wp_set_object_terms(
                $product->ID,
                $linkedTerms,
                $term_taxonomy
            );
        }
    }

    // Insert Quantity
    if (!empty($product->data['total_stock'])) {
        if ((!isset($allMetas['_stock']) && !isset($allMetas['_stock'][0])) || $allMetas['_stock'][0] != $product->data['total_stock']) {
            update_post_meta($product->ID, '_stock', $product->data['total_stock']);
        }
    }


    // Insert attributes
    if (!empty($product->data['attributes'])) {
        if (OASIS_PI_DEBUG !== true) {
            $productAttributes = [];
            foreach ($product->data['attributes'] as $key => $attribute) {
                $attr = wc_sanitize_taxonomy_name(stripslashes($attribute["name"]));

                $productAttributes[$attr] = [
                    'name'         => $attribute["name"],
                    'value'        => $attribute["value"] . (!empty($attribute['dim']) ? ' ' . $attribute['dim'] : ''),
                    'position'     => ($key + 1),
                    'is_visible'   => 1,
                    'is_variation' => 0,
                    'is_taxonomy'  => 0,
                ];
            }
            if ((!isset($allMetas['_product_attributes']) && !isset($allMetas['_product_attributes'][0])) || $allMetas['_product_attributes'][0] != serialize($productAttributes)) {
                update_post_meta($product->ID, '_product_attributes', $productAttributes);
            }
        }
    }

    if (!empty($product->data['images'])) {
        $upload_dir = wp_upload_dir();
        $productObj = new WC_product($product->ID);
        $attachment_ids = $productObj->get_gallery_image_ids();
        $updatePhoto = false;

        foreach ($attachment_ids as $attachment_id) {
            $original = wp_get_attachment_metadata($attachment_id);
            $file_path = $upload_dir['basedir'] . '/' . $original['file'];
            if (empty($file_path)) {
                $updatePhoto = true;
                unset($fileData);
                break;
            }
            unset($fileData);
        }

        if (empty($attachment_ids) || $updatePhoto || count($attachment_ids) != count($product->data['images'])) {
            foreach ($attachment_ids as $attachment_id) {
                wp_delete_attachment($attachment_id, true);
            }

            $attaches = [];
            foreach ($product->data['images'] as $image) {
                if (!isset($image['big'])) {
                    continue;
                }

                $filename = $upload_dir['path'] . basename($image['big']);

                if (!file_exists($filename) || (file_exists($filename) && filesize($filename) < 2000)) {
                    copy($image['big'], $filename);
                }
                $attachment = [
                    'guid'           => $upload_dir['url'] . '/' . basename($filename),
                    'post_mime_type' => 'image/jpeg',
                    'post_title'     => preg_replace('/\.[^.]+$/', '', basename($filename)),
                    'post_content'   => '',
                    'post_status'    => 'inherit',
                ];
                $attach_id = wp_insert_attachment($attachment, $filename, $product->ID);
                $attaches[] = $attach_id;
                require_once ABSPATH . 'wp-admin/includes/image.php';

                $attach_data = wp_generate_attachment_metadata($attach_id, $filename);
                wp_update_attachment_metadata($attach_id, $attach_data);
            }

            if ($attaches) {
                set_post_thumbnail($product->ID, reset($attaches));
                update_post_meta($product->ID, '_product_image_gallery', implode(',', $attaches));
            }
        }
    }

    // Allow Plugin/Theme authors to add support for additional Product details
    $product = apply_filters('oasis_pi_create_product_addons', $product, $import);
    $import = apply_filters('oasis_pi_create_product_log_addons', $import, $product);
}
