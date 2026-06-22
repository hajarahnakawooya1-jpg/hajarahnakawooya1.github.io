<?php
session_start();
include("connect.php");

if(isset($_GET['food_id'])) {
    $food_id = intval($_GET['food_id']);

    $stmt = mysqli_prepare($conn, "SELECT * FROM menu WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $food_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $item   = mysqli_fetch_assoc($result);

    if($item) {
        if(!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

        if(isset($_SESSION['cart'][$food_id])) {
            $_SESSION['cart'][$food_id]['qty']++;
        } else {
            $_SESSION['cart'][$food_id] = [
                'id'    => $item['id'],
                'name'  => $item['Name'],
                'price' => $item['price'],
                'image' => $item['image'],
                'qty'   => 1
            ];
        }

        // Count total items
        $total = 0;
        foreach($_SESSION['cart'] as $c) $total += $c['qty'];

        echo json_encode(['success' => true, 'count' => $total, 'name' => $item['Name']]);
    } else {
        echo json_encode(['success' => false]);
    }
}
?>