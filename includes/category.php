<?php

/**
 * Создание категорий
 */
function oasis_pi_generate_categories()
{
    global $import;

    @ini_set('memory_limit', WP_MAX_MEMORY_LIMIT);

    $availableRoots = array(
        2891 => 'Продукция',
        1906 => 'VIP',
    );

    $data = json_decode(file_get_contents($import->categoriesFile), true);
    if ($data) {
        $categoriesTree = array();
        foreach ($data as $row) {
            if (isset($availableRoots[$row['root']])) {
                $categoriesTree[(int)$row['parent_id']][(int)$row['id']] = $row;
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
    @ini_set('memory_limit', WP_MAX_MEMORY_LIMIT);

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
function oasis_pi_recursive_upsert_categories($tree, $parent_id = 0, $parent_term_id = 0)
{
    global $wpdb, $import;

    $term_taxonomy = 'product_cat';

    if (isset($tree[$parent_id])) {
        foreach ($tree[$parent_id] as $catId => $catData) {
            $category = htmlspecialchars(trim($catData['name']));
            $termExists = term_exists($category, $term_taxonomy, $parent_term_id);

            if (empty($termExists)) {
                $term = wp_insert_term(
                    $category,
                    $term_taxonomy,
                    array(
                        'parent' => $parent_term_id,
                    )
                );
                if (!is_wp_error($term)) {
                    $next_parent_id = $term['term_id'];
                    $import->log .= "<br />>>>>>> " . sprintf('Добавлена категория: %s', $category);
                }
            } else {
                $next_parent_id = !empty($termExists['term_id']) ? $termExists['term_id'] : $termExists;
            }
            oasis_pi_recursive_upsert_categories($tree, $catId, $next_parent_id);
        }
    }
}
