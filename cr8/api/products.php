<?php
require_once 'config.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$conn = getDbConnection();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list':
    case 'get':
        // Match the original get_products.php logic with variants
        $sql = "
            SELECT 
                p.id, p.product_name, p.base_variant_name, p.description as product_description, p.image, p.price, p.quantity,
                p.artist_id,
                a.artist_name, 
                c.category_name,
                COALESCE(AVG(r.rating), 0) AS average_rating,
                COUNT(DISTINCT r.id) AS review_count,
                (
                    SELECT CONCAT('[', GROUP_CONCAT(JSON_OBJECT('id', v.id, 'name', v.variant_name, 'image', v.image, 'price', v.price, 'quantity', v.quantity)), ']')
                    FROM variants v
                    WHERE v.product_id = p.id
                ) AS variants_json,
                (
                    SELECT CONCAT('[', GROUP_CONCAT(JSON_OBJECT('id', r_inner.id, 'rating', r_inner.rating, 'comments', r_inner.comments, 'created_at', DATE_FORMAT(r_inner.created_at, '%M %d, %Y'), 'user_name', COALESCE(u.username, 'Anonymous'))), ']')
                    FROM reviews r_inner
                    LEFT JOIN users u ON r_inner.user_id = u.id
                    WHERE r_inner.product_id = p.id
                    ORDER BY r_inner.created_at DESC
                ) AS reviews_json
            FROM 
                products p
            LEFT JOIN 
                artists a ON p.artist_id = a.id AND a.is_archived = 0
            LEFT JOIN 
                categories c ON p.category_id = c.id
            LEFT JOIN 
                reviews r ON p.id = r.product_id
            WHERE p.is_active = 1
            GROUP BY p.id
            ORDER BY p.id DESC
        ";
        
        $result = $conn->query($sql);
        $products = [];
        
        while ($row = $result->fetch_assoc()) {
            $row['price'] = (float)$row['price'];
            $row['quantity'] = (int)$row['quantity'];
            
            // Parse variants and add base variant
            $other_variants = [];
            if (!empty($row['variants_json'])) {
                $decoded = json_decode($row['variants_json'], true);
                if ($decoded !== null) {
                    $other_variants = $decoded;
                }
            }
            
            $base_variant = [
                'id' => 'base',
                'name' => isset($row['base_variant_name']) && $row['base_variant_name'] ? $row['base_variant_name'] : 'Default',
                'image' => $row['image'],
                'price' => $row['price'],
                'quantity' => $row['quantity']
            ];
            $row['variants'] = array_merge([$base_variant], $other_variants);
            unset($row['variants_json']);
            unset($row['base_variant_name']);
            
            // Parse reviews
            $reviews = [];
            if (!empty($row['reviews_json'])) {
                $decoded_reviews = json_decode($row['reviews_json'], true);
                if ($decoded_reviews !== null) {
                    $reviews = $decoded_reviews;
                }
            }
            $row['reviews'] = $reviews;
            unset($row['reviews_json']);
            
            $products[] = $row;
        }
        
        echo json_encode(['success' => true, 'products' => $products]);
        break;

    case 'get-by-id':
        $id = $_GET['id'] ?? 0;
        $stmt = $conn->prepare("
            SELECT p.*, a.artist_name 
            FROM products p 
            JOIN artists a ON p.artist_id = a.id 
            WHERE p.id = ?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($product = $result->fetch_assoc()) {
            echo json_encode(['success' => true, 'product' => $product]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Product not found']);
        }
        $stmt->close();
        break;

    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

$conn->close();
