<?php

/**
 * Создание категорий
 */
function oasis_pi_generate_categories()
{
    global $import;

    @ini_set('memory_limit', '2G');

    $availableRoots = array(
        2891 => 'Продукция',
        1906 => 'VIP',
    );

    $data = json_decode(file_get_contents($import->categoriesFile), true);

    $json_file = oasis_pi_get_option('json_file', false);
    $json_file_params = parse_url($json_file, PHP_URL_QUERY);

    $json_query = [];
    parse_str($json_file_params, $json_query);

    $allowedCategory = [];
    if (isset($json_query['category'])) {
        $allowedCategory = explode(",", $json_query['category']);
    }


    if ($data) {
        $categoriesTree = array();
        foreach ($data as $row) {
            if (isset($availableRoots[$row['root']]) && in_array((int)$row['id'], $allowedCategory)) {
                $categoriesTree[(int)$row['id']] = $row;
            }
        }

        if ($categoriesTree) {
            oasis_pi_recursive_upsert_categories($categoriesTree);
        } else {
            $import->log .= "<br />Нет доступных категорий.";
            return;
        }
    } else {
        $import->log .= "<br />Файл категорий пустой.";
        return;
    }
}

/**
 *
 */
function oasis_pi_generate_categories_map()
{
    global $import;
    @ini_set('memory_limit', '2G');

    $availableRoots = array(
        2891 => 'Продукция',
        1906 => 'VIP',
    );

    $data = json_decode(file_get_contents($import->categoriesFile), true);
    if ($data) {
        $categories = array();
        foreach ($data as $row) {
            if (isset($availableRoots[$row['root']])) {
                $categories[(int)$row['id']] = $row['name'];
            }
        }
        $import->categories = $categories;
    } else {
        $import->log .= "<br />Файл категорий пустой.";
        return;
    }
}

/**
 * Рекурсивное создание дерева категорий
 *
 * @param $tree
 * @param $parent_id
 * @param int $parent_term_id
 */
function oasis_pi_recursive_upsert_categories($tree)
{
    global $wpdb, $import;

    $term_taxonomy = 'product_cat';

    foreach ($tree as $catId => $catData) {
        $category = htmlspecialchars(trim($catData['name']));
        $termExists = term_exists($category, $term_taxonomy, 0);

        if (empty($termExists)) {
            $term = wp_insert_term(
                $category,
                $term_taxonomy,
                array(
                    'parent' => 0,
                )
            );
            if (!is_wp_error($term)) {
                $next_parent_id = $term['term_id'];
                $import->log .= "<br />>>>>>> " . sprintf('Добавлена категория: %s', $category);
            }
        } else {
            $next_parent_id = !empty($termExists['term_id']) ? $termExists['term_id'] : $termExists;
        }
    }
}
