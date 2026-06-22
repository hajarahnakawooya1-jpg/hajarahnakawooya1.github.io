<?php
session_start();
include("connect.php");

// ── Add item to cart ──────────────────────────────────────────────
if (isset($_GET['food_id'])) {
    $food_id = intval($_GET['food_id']);

    // Fetch item from DB
    $stmt = mysqli_prepare($conn, "SELECT * FROM menu WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $food_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $item   = mysqli_fetch_assoc($result);

    if ($item) {
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        if (isset($_SESSION['cart'][$food_id])) {
            // Already in cart — increase qty
            $_SESSION['cart'][$food_id]['qty']++;
        } else {
            // New item
            $_SESSION['cart'][$food_id] = [
                'id'    => $item['id'],
                'name'  => $item['Name'],
                'price' => $item['price'],
                'image' => $item['image'],
                'qty'   => 1
            ];
        }
    }
    // Redirect back to same page (clean URL)
    header("Location: cart.php");
    exit();
}

// ── Remove item ───────────────────────────────────────────────────
if (isset($_GET['remove'])) {
    $rid = intval($_GET['remove']);
    unset($_SESSION['cart'][$rid]);
    header("Location: cart.php");
    exit();
}

// ── Update quantity ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_qty'])) {
    foreach ($_POST['qty'] as $id => $qty) {
        $id  = intval($id);
        $qty = intval($qty);
        if ($qty <= 0) {
            unset($_SESSION['cart'][$id]);
        } elseif (isset($_SESSION['cart'][$id])) {
            $_SESSION['cart'][$id]['qty'] = $qty;
        }
    }
    header("Location: cart.php");
    exit();
}

// ── Clear entire cart ─────────────────────────────────────────────
if (isset($_GET['clear'])) {
    $_SESSION['cart'] = [];
    header("Location: cart.php");
    exit();
}

// ── Totals ────────────────────────────────────────────────────────
$cart      = $_SESSION['cart'] ?? [];
$subtotal  = 0;
$itemCount = 0;
foreach ($cart as $c) {
    $subtotal  += $c['price'] * $c['qty'];
    $itemCount += $c['qty'];
}
$deliveryFee = ($itemCount > 0) ? 5000 : 0;
$total       = $subtotal + $deliveryFee;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>My Cart – Mugiez Hotel And Apartments</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #f5f3ef; }

        /* ── NAVBAR ── */
        nav {
            background: #1a1a1a;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 40px;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        nav .logo img { height: 50px; }
        nav ul { list-style: none; display: flex; gap: 30px; }
        nav ul li a { color: white; text-decoration: none; font-size: 15px; }
        nav ul li a:hover, nav ul li a.active { color: #c8860a; }
        nav ul li a.active { border-bottom: 2px solid #c8860a; padding-bottom: 4px; }
        .cart-btn {
            border: 2px solid #c8860a;
            color: white;
            padding: 8px 20px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 15px;
            position: relative;
        }
        .cart-btn:hover { background: #c8860a; }
        .cart-badge {
            position: absolute;
            top: -8px; right: -8px;
            background: #C0392B;
            color: #fff;
            border-radius: 50%;
            width: 20px; height: 20px;
            font-size: 11px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* ── PAGE HEADER ── */
        .page-header {
            background: #1a1a1a;
            padding: 28px 40px 22px;
            text-align: center;
            border-bottom: 3px solid #C0392B;
        }
        .page-header h1 { color: #fff; font-size: 28px; font-weight: 800; }
        .page-header h1 span { color: #C0392B; }
        .page-header p { color: #aaa; font-size: 13px; margin-top: 4px; }

        /* ── BREADCRUMB ── */
        .breadcrumb {
            max-width: 1100px;
            margin: 18px auto 0;
            padding: 0 20px;
            font-size: 12px;
            color: #999;
        }
        .breadcrumb a { color: #C0392B; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }

        /* ── LAYOUT ── */
        .cart-wrapper {
            max-width: 1100px;
            margin: 20px auto 40px;
            padding: 0 20px;
            display: flex;
            gap: 28px;
            align-items: flex-start;
        }

        /* ── CART ITEMS ── */
        .cart-items-section { flex: 1; }
        .section-title {
            font-size: 15px;
            font-weight: 700;
            color: #111;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .section-title i { color: #C0392B; }

        .cart-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        }
        .cart-table thead tr {
            background: #1a1a1a;
            color: #fff;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.04em;
        }
        .cart-table thead th {
            padding: 14px 18px;
            text-align: left;
        }
        .cart-table thead th:last-child { text-align: right; }
        .cart-table tbody tr {
            border-bottom: 1px solid #f0ece4;
            transition: background 0.15s;
        }
        .cart-table tbody tr:last-child { border-bottom: none; }
        .cart-table tbody tr:hover { background: #fdf9f5; }
        .cart-table td { padding: 16px 18px; vertical-align: middle; }

        .item-cell { display: flex; align-items: center; gap: 14px; }
        .item-img {
            width: 72px; height: 72px;
            border-radius: 8px;
            object-fit: cover;
            background: #f0ece4;
            flex-shrink: 0;
        }
        .item-img-placeholder {
            width: 72px; height: 72px;
            border-radius: 8px;
            background: #f0ece4;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            flex-shrink: 0;
        }
        .item-name { font-size: 14px; font-weight: 700; color: #111; }
        .item-unit { font-size: 11px; color: #aaa; margin-top: 2px; }

        /* Qty input */
        .qty-form { display: flex; align-items: center; gap: 0; }
        .qty-btn {
            width: 30px; height: 30px;
            border: 1.5px solid #ddd;
            background: #f9f9f9;
            border-radius: 6px 0 0 6px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            color: #333;
            font-family: 'Poppins', sans-serif;
            transition: all 0.15s;
        }
        .qty-btn.plus { border-radius: 0 6px 6px 0; }
        .qty-btn:hover { background: #C0392B; color: #fff; border-color: #C0392B; }
        .qty-input {
            width: 42px; height: 30px;
            text-align: center;
            font-size: 14px;
            font-weight: 700;
            color: #111;
            border: 1.5px solid #ddd;
            border-left: none; border-right: none;
            font-family: 'Poppins', sans-serif;
            outline: none;
        }

        .line-price {
            font-size: 16px;
            font-weight: 800;
            color: #C0392B;
            text-align: right;
        }
        .unit-price-col { font-size: 14px; font-weight: 600; color: #555; }

        .remove-btn {
            background: none;
            border: 1.5px solid #eee;
            border-radius: 6px;
            color: #bbb;
            width: 32px; height: 32px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            transition: all 0.15s;
            text-decoration: none;
        }
        .remove-btn:hover { border-color: #E74C3C; color: #E74C3C; background: #fff5f5; }

        /* Action row below table */
        .cart-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 14px;
        }
        .btn-clear {
            background: transparent;
            border: 1.5px solid #ddd;
            color: #999;
            padding: 9px 18px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-clear:hover { border-color: #E74C3C; color: #E74C3C; }
        .btn-update {
            background: #1a1a1a;
            color: #fff;
            border: none;
            padding: 10px 22px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            display: flex;
            align-items: center;
            gap: 7px;
            transition: background 0.2s;
        }
        .btn-update:hover { background: #333; }

        /* Empty cart */
        .empty-cart {
            background: #fff;
            border-radius: 12px;
            padding: 70px 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        }
        .empty-cart i { font-size: 56px; color: #ddd; margin-bottom: 18px; display: block; }
        .empty-cart h3 { font-size: 20px; color: #aaa; margin-bottom: 8px; }
        .empty-cart p { font-size: 13px; color: #bbb; margin-bottom: 20px; }
        .empty-cart a {
            background: #C0392B;
            color: #fff;
            padding: 12px 30px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }

        /* ── ORDER SUMMARY ── */
        .order-summary {
            width: 320px;
            flex-shrink: 0;
            position: sticky;
            top: 90px;
        }
        .summary-card {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }
        .summary-header {
            background: #1a1a1a;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .summary-header i { color: #C0392B; }
        .summary-header h3 { color: #fff; font-size: 15px; font-weight: 700; }
        .summary-body { padding: 20px; }

        .summary-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            color: #555;
            margin-bottom: 12px;
        }
        .summary-row span:last-child { font-weight: 600; color: #111; }

        .summary-divider { border: none; border-top: 1.5px dashed #e5e5e5; margin: 14px 0; }

        .summary-total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4px;
        }
        .summary-total-label { font-size: 15px; font-weight: 700; color: #111; }
        .summary-total-price { font-size: 22px; font-weight: 800; color: #C0392B; }
        .tax-note { font-size: 10px; color: #bbb; text-align: right; margin-bottom: 20px; }

        /* Delivery toggle */
        .delivery-toggle {
            display: flex;
            background: #f5f3ef;
            border-radius: 8px;
            padding: 4px;
            margin-bottom: 20px;
        }
        .del-btn {
            flex: 1;
            padding: 8px;
            border: none;
            background: transparent;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            color: #777;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            transition: all 0.2s;
        }
        .del-btn.active { background: #C0392B; color: #fff; }

        /* Note */
        .note-label { font-size: 12px; font-weight: 600; color: #555; margin-bottom: 6px; display: block; }
        .note-area {
            width: 100%;
            border: 1.5px solid #eee;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 12px;
            font-family: 'Poppins', sans-serif;
            color: #333;
            resize: none;
            height: 72px;
            margin-bottom: 18px;
            outline: none;
            transition: border 0.2s;
        }
        .note-area:focus { border-color: #C0392B; }

        /* Buttons */
        .btn-checkout {
            width: 100%;
            background: #C0392B;
            color: #fff;
            border: none;
            padding: 14px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 10px;
            transition: background 0.2s;
            text-decoration: none;
        }
        .btn-checkout:hover { background: #A93226; }
        .btn-whatsapp {
            width: 100%;
            background: #25D366;
            color: #fff;
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 10px;
            transition: background 0.2s;
            text-decoration: none;
        }
        .btn-whatsapp:hover { background: #1ebe5c; }
        .btn-call {
            width: 100%;
            background: transparent;
            color: #555;
            border: 1.5px solid #ddd;
            padding: 11px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
            text-decoration: none;
        }
        .btn-call:hover { border-color: #C0392B; color: #C0392B; }

        .continue-link {
            display: block;
            text-align: center;
            margin-top: 16px;
            font-size: 12px;
            color: #999;
            text-decoration: none;
        }
        .continue-link:hover { color: #C0392B; }

        /* ── BOTTOM BAR ── */
        .bottom-bar {
            background: #222;
            text-align: center;
            padding: 12px;
            color: #bbb;
            font-size: 12px;
        }

        /* ── TOAST ── */
        .toast {
            position: fixed;
            bottom: 28px; right: 28px;
            background: #1a1a1a;
            color: #fff;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            border-left: 4px solid #C0392B;
            z-index: 999;
            display: none;
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav>
    <div class="logo">
        <img src="media/logo2.png" alt="Logo" width="100px" height="100px">
    </div>
    <ul>
        <li><a href="index.html">Home</a></li>
        <li><a href="menu.php">Menu</a></li>
        
    </ul>
    <a href="cart.php" class="cart-btn" style="position:relative;">
        🛒 Cart
        <?php if($itemCount > 0): ?>
            <span class="cart-badge"><?php echo $itemCount; ?></span>
        <?php endif; ?>
    </a>
</nav>

<!-- PAGE HEADER -->
<div class="page-header">
    <h1>My <span>Cart</span></h1>
    <p>Review your order before placing it</p>
</div>

<!-- BREADCRUMB -->
<div class="breadcrumb">
    <a href="index.html">Home</a> &rsaquo;
    <a href="menu.php">Menu</a> &rsaquo;
    Cart
</div>

<!-- CART LAYOUT -->
<div class="cart-wrapper">

    <!-- LEFT: Items -->
    <div class="cart-items-section">

        <div class="section-title">
            <i class="fa fa-cart-shopping"></i>
            Your Items
            <span style="color:#999; font-weight:500; font-size:13px;">
                (<?php echo $itemCount; ?> item<?php echo $itemCount !== 1 ? 's' : ''; ?>)
            </span>
        </div>

        <?php if (empty($cart)): ?>
            <!-- Empty state -->
            <div class="empty-cart">
                <i class="fa fa-cart-shopping"></i>
                <h3>Your cart is empty</h3>
                <p>You haven't added anything yet. Go pick something delicious!</p>
                <a href="menu.php">Browse Menu</a>
            </div>

        <?php else: ?>

            <form method="POST" action="cart.php">
                <input type="hidden" name="update_qty" value="1"/>

                <table class="cart-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Unit Price</th>
                            <th>Quantity</th>
                            <th>Total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($cart as $id => $item): ?>
                        <tr>
                            <!-- Item -->
                            <td>
                                <div class="item-cell">
                                    <?php if(!empty($item['image']) && file_exists("media/" . $item['image'])): ?>
                                        <img class="item-img"
                                             src="media/<?php echo htmlspecialchars($item['image']); ?>"
                                             alt="<?php echo htmlspecialchars($item['name']); ?>"/>
                                    <?php else: ?>
                                        <div class="item-img-placeholder">🍽️</div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                        <div class="item-unit">UGX <?php echo number_format($item['price']); ?> / serving</div>
                                    </div>
                                </div>
                            </td>

                            <!-- Unit price -->
                            <td class="unit-price-col">
                                UGX <?php echo number_format($item['price']); ?>
                            </td>

                            <!-- Quantity -->
                            <td>
                                <div class="qty-form">
                                    <button type="button" class="qty-btn"
                                            onclick="changeQty(<?php echo $id; ?>, -1)">&#8722;</button>
                                    <input type="number"
                                           name="qty[<?php echo $id; ?>]"
                                           id="qty-<?php echo $id; ?>"
                                           value="<?php echo $item['qty']; ?>"
                                           min="0"
                                           class="qty-input"
                                           onchange="updateLinePrice(<?php echo $id; ?>, <?php echo $item['price']; ?>)"/>
                                    <button type="button" class="qty-btn plus"
                                            onclick="changeQty(<?php echo $id; ?>, 1)">&#43;</button>
                                </div>
                            </td>

                            <!-- Line total -->
                            <td class="line-price" id="line-<?php echo $id; ?>">
                                UGX <?php echo number_format($item['price'] * $item['qty']); ?>
                            </td>

                            <!-- Remove -->
                            <td>
                                <a href="cart.php?remove=<?php echo $id; ?>"
                                   class="remove-btn"
                                   title="Remove item"
                                   onclick="return confirm('Remove this item from your cart?')">
                                    <i class="fa fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Actions row -->
                <div class="cart-actions">
                    <a href="cart.php?clear=1"
                       class="btn-clear"
                       onclick="return confirm('Clear your entire cart?')">
                        <i class="fa fa-trash"></i> Clear Cart
                    </a>
                    <button type="submit" class="btn-update">
                        <i class="fa fa-rotate"></i> Update Cart
                    </button>
                </div>

            </form>

        <?php endif; ?>
    </div>

    <!-- RIGHT: Summary -->
    <?php if (!empty($cart)): ?>
    <div class="order-summary">
        <div class="summary-card">
            <div class="summary-header">
                <i class="fa fa-receipt"></i>
                <h3>Order Summary</h3>
            </div>
            <div class="summary-body">

                <!-- Delivery toggle -->
                <div class="delivery-toggle">
                    <button class="del-btn active" id="btn-delivery" onclick="setMode('delivery')">
                        <i class="fa fa-motorcycle"></i> Delivery
                    </button>
                    <button class="del-btn" id="btn-pickup" onclick="setMode('pickup')">
                        <i class="fa fa-store"></i> Pickup
                    </button>
                </div>

                <!-- Rows -->
                <div class="summary-row">
                    <span>Subtotal (<?php echo $itemCount; ?> items)</span>
                    <span>UGX <?php echo number_format($subtotal); ?></span>
                </div>
                <div class="summary-row" id="delivery-row">
                    <span>Delivery fee</span>
                    <span id="delivery-fee-val">UGX <?php echo number_format($deliveryFee); ?></span>
                </div>

                <hr class="summary-divider"/>

                <div class="summary-total-row">
                    <span class="summary-total-label">Total</span>
                    <span class="summary-total-price" id="grand-total">
                        UGX <?php echo number_format($total); ?>
                    </span>
                </div>
                <p class="tax-note">Inclusive of all taxes</p>

                <!-- Note -->
                <label class="note-label">
                    <i class="fa fa-pen" style="color:#C0392B; margin-right:4px;"></i>
                    Special instructions
                </label>
                <textarea class="note-area" id="order-note"
                          placeholder="E.g. No onions, extra spicy, deliver to gate 2…"></textarea>

                <!-- Buttons -->
                <a href="checkout.php" class="btn-checkout">
                    <i class="fa fa-check-circle"></i> PLACE ORDER
                </a>
<a href="#" target="_blank" class="btn-whatsapp" id="whatsapp-btn">
    <i class="fab fa-whatsapp"></i> Order via WhatsApp
</a>

                <a href="tel:+0706464182" class="btn-call">
                    <i class="fa fa-phone"></i> Call to Confirm Order
                </a>

            </div>
        </div>
        <a href="menu.php" class="continue-link">
            <i class="fa fa-arrow-left"></i> Continue Shopping
        </a>
    </div>
    <?php endif; ?>

</div>

<div class="bottom-bar">Thank you for choosing Mugiez Hotel And Apartments.</div>

<!-- TOAST -->
<div class="toast" id="toast"></div>

<script>
    // ── Constants from PHP ────────────────────────────────────────
    const SUBTOTAL     = <?php echo $subtotal; ?>;
    const DELIVERY_FEE = <?php echo $deliveryFee; ?>;
    let   deliveryMode = 'delivery';

    // ── Delivery / Pickup toggle ──────────────────────────────────
    function setMode(mode) {
        deliveryMode = mode;
        document.getElementById('btn-delivery').classList.toggle('active', mode === 'delivery');
        document.getElementById('btn-pickup').classList.toggle('active',  mode === 'pickup');

        const fee   = mode === 'delivery' ? DELIVERY_FEE : 0;
        const total = SUBTOTAL + fee;

        document.getElementById('delivery-fee-val').textContent =
            fee === 0 ? 'Free' : 'UGX ' + fee.toLocaleString();
        document.getElementById('grand-total').textContent =
            'UGX ' + total.toLocaleString();
        document.getElementById('delivery-row').style.display =
            mode === 'delivery' ? 'flex' : 'none';

        buildWhatsApp();
    }

    // ── Qty buttons (client-side preview, form submits to update) ─
    function changeQty(id, delta) {
        const input = document.getElementById('qty-' + id);
        let val = parseInt(input.value) + delta;
        if (val < 0) val = 0;
        input.value = val;
        // trigger line price update
        const price = parseInt(input.dataset.price || 0);
        updateLinePrice(id, price);
    }

    function updateLinePrice(id, unitPrice) {
        const input = document.getElementById('qty-' + id);
        const qty   = parseInt(input.value) || 0;
        const el    = document.getElementById('line-' + id);
        if (el) el.textContent = 'UGX ' + (unitPrice * qty).toLocaleString();
    }

    // ── Build WhatsApp message ──────────────────────────────
function buildWhatsApp() {
    let lines = [];
    lines.push("🍽️ MUGIEZ HOTEL ORDER");
    lines.push("");

    <?php foreach($cart as $id => $item): ?>
    lines.push("• <?php echo addslashes($item['name']); ?> x" +
               document.getElementById('qty-<?php echo $id; ?>').value);
    <?php endforeach; ?>

    const fee = deliveryMode === 'delivery' ? <?php echo $deliveryFee; ?> : 0;
    const total = <?php echo $subtotal; ?> + fee;

    lines.push("");
    lines.push("Mode: " + (deliveryMode === 'delivery' ? "Delivery" : "Pickup"));
    lines.push("Subtotal: UGX <?php echo number_format($subtotal); ?>");
    if (deliveryMode === 'delivery') {
        lines.push("Delivery Fee: UGX " + fee.toLocaleString());
    }
    lines.push("Total: UGX " + total.toLocaleString());

    const note = document.getElementById('order-note').value;
    if (note.trim() !== "") {
        lines.push("");
        lines.push("Special Instructions:");
        lines.push(note);
    }

    const msg = lines.join("\n");

    // ✅ International format: 256 is Uganda's country code, drop the leading 0
    document.getElementById("whatsapp-btn").href =
        "https://wa.me/256709197935?text=" + encodeURIComponent(msg);
}
// Build link when page loads
buildWhatsApp();

// Rebuild when note changes
document.getElementById('order-note')
        .addEventListener('input', buildWhatsApp);
    // Init WhatsApp link
    buildWhatsApp();

    document.querySelectorAll('.qty-input').forEach(function(input){
    input.addEventListener('change', buildWhatsApp);
});

    // Re-build whenever note changes
    document.getElementById('order-note').addEventListener('input', buildWhatsApp);
</script>
</body>
</html>