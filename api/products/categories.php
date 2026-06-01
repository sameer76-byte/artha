<?php
// ============================================================
//  api/products/categories.php
//  GET /api/products/categories
//  Returns nested category tree with product counts
// ============================================================

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/response.php';

allow_methods(['GET']);

// Fetch all active categories with product count
$stmt = $pdo->query("
    SELECT
        c.category_id,
        c.parent_id,
        c.name,
        c.slug,
        c.description,
        c.image_url,
        c.sort_order,
        COUNT(p.product_id) AS product_count
    FROM categories c
    LEFT JOIN products p
           ON p.category_id = c.category_id AND p.is_active = 1
    WHERE c.is_active = 1
    GROUP BY c.category_id
    ORDER BY c.sort_order ASC, c.name ASC
");
$flat = $stmt->fetchAll();

// Build nested tree
function build_tree(array $flat, ?int $parent_id = null): array
{
    $tree = [];
    foreach ($flat as $node) {
        if ((int)($node['parent_id'] ?? 0) === (int)$parent_id) {
            $node['product_count'] = (int)$node['product_count'];
            $node['children']      = build_tree($flat, (int)$node['category_id']);
            $tree[]                = $node;
        }
    }
    return $tree;
}

send_success(build_tree($flat));