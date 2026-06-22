<?php
session_start();
include("connect.php");

// Redirect if cart is empty
$cart = $_SESSION['cart'] ?? [];
if (empty($cart)) {
    header("Location: menu.php");
    exit();
}

// ── Totals ────────────────────────────────────────────────────────
$subtotal  = 0;
$itemCount = 0;
foreach ($cart as $c) {
    $subtotal  += $c['price'] * $c['qty'];
    $itemCount += $c['qty'];
}
$deliveryFee = 5000;

// ── Handle order submission ───────────────────────────────────────
$errors  = [];
$success = false;
$order_id = null;
$post = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['name']       ?? '');
    $phone      = trim($_POST['phone']      ?? '');
    $address    = trim($_POST['address']    ?? '');
    $notes      = trim($_POST['notes']      ?? '');
    $payment    = $_POST['payment']         ?? '';
    $order_type = $_POST['order_type']      ?? 'delivery';
    $mm_number  = trim($_POST['mm_number']  ?? '');
    $post       = $_POST;

    if (empty($name))    $errors[] = "Full name is required.";
    if (empty($phone))   $errors[] = "Phone number is required.";
    if ($order_type === 'delivery' && empty($address)) $errors[] = "Delivery address is required.";
    if (empty($payment)) $errors[] = "Please select a payment method.";
    if (($payment === 'mtn' || $payment === 'airtel') && empty($mm_number))
        $errors[] = "Mobile money number is required.";

    if (empty($errors)) {
        $fee        = ($order_type === 'delivery') ? $deliveryFee : 0;
        $total      = $subtotal + $fee;
        $items_json = json_encode(array_values($cart));

        // Convert numbers to strings to avoid bind_param type issues
        $subtotal_str = (string)$subtotal;
        $fee_str      = (string)$fee;
        $total_str    = (string)$total;

        $stmt = mysqli_prepare($conn,
            "INSERT INTO orders (customer_name, phone, address, notes, payment_method, order_type, subtotal, delivery_fee, total, items, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())"
        );

        if (!$stmt) {
            $errors[] = "Database error: " . mysqli_error($conn) . " — Make sure you have run the create_orders_table.sql in phpMyAdmin.";
        } else {
            mysqli_stmt_bind_param($stmt, "ssssssssss",
                $name, $phone, $address, $notes,
                $payment, $order_type,
                $subtotal_str, $fee_str, $total_str,
                $items_json
            );

            if (mysqli_stmt_execute($stmt)) {
                $order_id    = mysqli_insert_id($conn);
                $success     = true;
                $saved_cart  = $cart;
                $saved_total = $total;
                $saved_name  = $name;
                $_SESSION['cart'] = [];
            } else {
                $errors[] = "Failed to save order: " . mysqli_stmt_error($stmt);
            }
        }
    }
}

function val($post, $key, $default = '') {
    return htmlspecialchars($post[$key] ?? $default);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?php echo $success ? 'Order Confirmed' : 'Checkout'; ?> – Mugiez Hotel And Apartments</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #f5f3ef; color: #1a1a1a; }

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
        nav ul li a:hover { color: #c8860a; }
        .cart-btn {
            border: 2px solid #c8860a;
            color: white;
            padding: 8px 20px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 15px;
        }
        .cart-btn:hover { background: maroon; }

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

        /* ── STEPS ── */
        .steps {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 18px 40px;
            background: #fff;
            border-bottom: 1px solid #eee;
            gap: 0;
        }
        .step { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; color: #bbb; }
        .step.done   { color: #27ae60; }
        .step.active { color: #C0392B; }
        .step-num {
            width: 28px; height: 28px;
            border-radius: 50%;
            background: #eee;
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 700; flex-shrink: 0;
        }
        .step.done   .step-num { background: #27ae60; color: #fff; }
        .step.active .step-num { background: #C0392B; color: #fff; }
        .step-line { width: 60px; height: 2px; background: #eee; margin: 0 4px; }
        .step-line.done { background: #27ae60; }

        /* ── BREADCRUMB ── */
        .breadcrumb { max-width: 1100px; margin: 16px auto 0; padding: 0 20px; font-size: 12px; color: #999; }
        .breadcrumb a { color: #C0392B; text-decoration: none; }

        /* ── LAYOUT ── */
        .checkout-wrapper {
            max-width: 1100px;
            margin: 20px auto 60px;
            padding: 0 20px;
            display: flex;
            gap: 28px;
            align-items: flex-start;
        }

        /* ── FORM SECTION ── */
        .form-section { flex: 1; }
        .form-card {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            margin-bottom: 20px;
        }
        .card-head {
            background: #1a1a1a;
            padding: 14px 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card-head i { color: #C0392B; font-size: 15px; }
        .card-head h3 { color: #fff; font-size: 14px; font-weight: 700; }
        .card-body { padding: 22px; }

        .delivery-toggle {
            display: flex;
            background: #f5f3ef;
            border-radius: 8px;
            padding: 4px;
            margin-bottom: 20px;
        }
        .del-btn {
            flex: 1; padding: 10px; border: none;
            background: transparent; border-radius: 6px;
            font-size: 13px; font-weight: 600; color: #777;
            cursor: pointer; font-family: 'Poppins', sans-serif;
            transition: all 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 6px;
        }
        .del-btn.active { background: #C0392B; color: #fff; }

        .field-row { display: flex; gap: 16px; }
        .field-row .field { flex: 1; }
        .field { margin-bottom: 16px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: #555; margin-bottom: 6px; }
        .field label span { color: #C0392B; }
        .field input, .field textarea {
            width: 100%; border: 1.5px solid #e5e5e5; border-radius: 8px;
            padding: 11px 14px; font-size: 14px; font-family: 'Poppins', sans-serif;
            color: #1a1a1a; background: #fafafa; outline: none;
            transition: border 0.2s, background 0.2s;
        }
        .field input:focus, .field textarea:focus { border-color: #C0392B; background: #fff; }
        .field textarea { resize: vertical; min-height: 80px; }
        .field input.error, .field textarea.error { border-color: #E74C3C; }

        /* Payment */
        .payment-options { display: flex; gap: 14px; }
        .pay-option {
            flex: 1; border: 2px solid #e5e5e5; border-radius: 10px;
            padding: 16px 14px; cursor: pointer; transition: all 0.2s;
            display: flex; flex-direction: column; align-items: center;
            gap: 8px; text-align: center; position: relative;
        }
        .pay-option:hover { border-color: #C0392B; }
        .pay-option.selected { border-color: #C0392B; background: #fff8f7; }
        .pay-option input[type="radio"] { position: absolute; opacity: 0; width: 0; height: 0; }
        .pay-icon { font-size: 28px; line-height: 1; }
        .pay-label { font-size: 13px; font-weight: 700; color: #1a1a1a; }
        .pay-desc  { font-size: 11px; color: #999; line-height: 1.4; }
        .pay-check {
            position: absolute; top: 8px; right: 8px;
            width: 18px; height: 18px; background: #C0392B;
            border-radius: 50%; display: none;
            align-items: center; justify-content: center;
            color: #fff; font-size: 10px;
        }
        .pay-option.selected .pay-check { display: flex; }
        #mm-field { display: none; margin-top: 14px; }

        /* Errors */
        .error-box {
            background: #fff5f5; border: 1.5px solid #E74C3C;
            border-radius: 8px; padding: 14px 18px; margin-bottom: 18px;
        }
        .error-box p { font-size: 13px; color: #C0392B; font-weight: 600; margin-bottom: 6px; }
        .error-box ul { padding-left: 18px; }
        .error-box li { font-size: 12px; color: #C0392B; margin-bottom: 3px; }

        /* ── ORDER SUMMARY (right panel) ── */
        .order-summary { width: 320px; flex-shrink: 0; position: sticky; top: 90px; }
        .summary-card {
            background: #fff; border-radius: 12px;
            overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }
        .summary-header {
            background: #1a1a1a; padding: 16px 20px;
            display: flex; align-items: center; gap: 10px;
        }
        .summary-header i { color: #C0392B; }
        .summary-header h3 { color: #fff; font-size: 15px; font-weight: 700; }
        .summary-body { padding: 18px 20px; }

        .s-item {
            display: flex; align-items: center; gap: 10px;
            padding: 8px 0; border-bottom: 1px solid #f5f3ef;
        }
        .s-item:last-child { border-bottom: none; }
        .s-item-img {
            width: 42px; height: 42px; border-radius: 6px;
            object-fit: cover; background: #f0ece4; flex-shrink: 0;
        }
        .s-item-placeholder {
            width: 42px; height: 42px; border-radius: 6px;
            background: #f0ece4; display: flex; align-items: center;
            justify-content: center; font-size: 20px; flex-shrink: 0;
        }
        .s-item-info { flex: 1; }
        .s-item-name { font-size: 12px; font-weight: 700; color: #1a1a1a; }
        .s-item-qty  { font-size: 11px; color: #999; }
        .s-item-price { font-size: 13px; font-weight: 700; color: #C0392B; white-space: nowrap; }

        .summary-divider { border: none; border-top: 1.5px dashed #e5e5e5; margin: 12px 0; }
        .summary-row {
            display: flex; justify-content: space-between;
            font-size: 13px; color: #555; margin-bottom: 10px;
        }
        .summary-row span:last-child { font-weight: 600; color: #1a1a1a; }
        .summary-total-row { display: flex; justify-content: space-between; align-items: center; margin-top: 4px; }
        .summary-total-label { font-size: 15px; font-weight: 700; }
        .summary-total-price { font-size: 22px; font-weight: 800; color: #C0392B; }

        .btn-place-order {
            width: 100%; background: #C0392B; color: #fff; border: none;
            padding: 15px; border-radius: 8px; font-size: 14px; font-weight: 700;
            letter-spacing: 0.06em; text-transform: uppercase; cursor: pointer;
            font-family: 'Poppins', sans-serif;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            margin-top: 18px; transition: background 0.2s, transform 0.1s;
        }
        .btn-place-order:hover { background: #A93226; }
        .btn-place-order:active { transform: scale(0.98); }
        .btn-place-order:disabled { background: #ccc; cursor: not-allowed; }

        .back-link { display: block; text-align: center; margin-top: 14px; font-size: 12px; color: #999; text-decoration: none; }
        .back-link:hover { color: #C0392B; }
        .secure-note { display: flex; align-items: center; justify-content: center; gap: 6px; margin-top: 12px; font-size: 11px; color: #bbb; }
        .secure-note i { color: #27ae60; }

        /* ── SUCCESS SCREEN ── */
        .success-wrapper {
            max-width: 600px;
            margin: 50px auto 60px;
            padding: 0 20px;
            text-align: center;
        }
        .tick-circle {
            width: 90px; height: 90px; background: #27ae60;
            border-radius: 50%; display: flex; align-items: center;
            justify-content: center; margin: 0 auto 24px;
            animation: pop 0.45s ease;
        }
        .tick-circle i { color: #fff; font-size: 40px; }
        @keyframes pop {
            0%   { transform: scale(0.4); opacity: 0; }
            80%  { transform: scale(1.12); }
            100% { transform: scale(1);   opacity: 1; }
        }
        .success-wrapper h1 { font-size: 28px; font-weight: 800; margin-bottom: 8px; }
        .success-wrapper h1 span { color: #27ae60; }
        .success-sub { font-size: 14px; color: #777; margin-bottom: 30px; line-height: 1.7; }

        .confirm-card {
            background: #fff; border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            overflow: hidden; text-align: left; margin-bottom: 24px;
        }
        .confirm-card-head {
            background: #1a1a1a; padding: 14px 20px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .confirm-card-head span { color: #fff; font-size: 14px; font-weight: 700; }
        .confirm-card-head .order-num { color: #C0392B; font-size: 16px; font-weight: 800; }
        .confirm-card-body { padding: 20px; }
        .mini-item {
            display: flex; justify-content: space-between;
            font-size: 12px; color: #666; padding: 5px 0;
            border-bottom: 1px solid #f5f3ef;
        }
        .mini-item:last-child { border-bottom: none; }
        .mini-item span:last-child { font-weight: 600; color: #333; }
        .confirm-total {
            display: flex; justify-content: space-between;
            font-size: 16px; font-weight: 800; color: #C0392B;
            margin-top: 12px; padding-top: 12px;
            border-top: 2px dashed #eee;
        }

        .btn-success-primary {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            width: 100%; background: #C0392B; color: #fff; padding: 14px;
            border-radius: 8px; font-size: 14px; font-weight: 700;
            text-decoration: none; margin-bottom: 12px; transition: background 0.2s;
        }
        .btn-success-primary:hover { background: #A93226; }
        .btn-success-secondary {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            width: 100%; background: #fff; color: #555;
            border: 1.5px solid #ddd; padding: 13px; border-radius: 8px;
            font-size: 14px; font-weight: 600; text-decoration: none; transition: all 0.2s;
        }
        .btn-success-secondary:hover { border-color: #C0392B; color: #C0392B; }
        .call-note { font-size: 12px; color: #aaa; margin-top: 18px; }
        .call-note i { color: #c8860a; }

        /* ── FOOTER ── */
        .bottom-bar { background: #222; text-align: center; padding: 12px; color: #bbb; font-size: 12px; }

        @media (max-width: 768px) {
            .checkout-wrapper { flex-direction: column; }
            .order-summary { width: 100%; position: static; }
            nav ul { display: none; }
            nav { padding: 12px 20px; }
            .steps { padding: 14px 20px; }
            .step-line { width: 30px; }
            .field-row { flex-direction: column; gap: 0; }
            .payment-options { flex-direction: column; }
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav>
    <div class="logo">
        <img src="media/logo2.png" alt="Mugiez Hotel" width="100" height="100">
    </div>
    <ul>
        <li><a href="index.html">Home</a></li>
        <li><a href="menu.php">Menu</a></li>
        <li><a href="cart.php">Mycart</a></li>
        <li><a href="index.php">Log Out</a></li>
    </ul>
    <a href="<?php echo $success ? 'menu.php' : 'cart.php'; ?>" class="cart-btn">
        <?php echo $success ? '🍽️ Order more' : '🛒 Cart'; ?>
    </a>
</nav>

<!-- PAGE HEADER -->
<div class="page-header">
    <?php if ($success): ?>
        <h1>Order <span>Confirmed!</span></h1>
        <p>Your food is on its way</p>
    <?php else: ?>
        <h1>Check<span>out</span></h1>
        <p>Almost there — fill in your details below</p>
    <?php endif; ?>
</div>

<!-- STEPS -->
<div class="steps">
    <div class="step done">
        <div class="step-num"><i class="fa fa-check" style="font-size:10px;"></i></div>
        Cart
    </div>
    <div class="step-line done"></div>
    <div class="step <?php echo $success ? 'done' : 'active'; ?>">
        <div class="step-num">
            <?php echo $success ? '<i class="fa fa-check" style="font-size:10px;"></i>' : '2'; ?>
        </div>
        Checkout
    </div>
    <div class="step-line <?php echo $success ? 'done' : ''; ?>"></div>
    <div class="step <?php echo $success ? 'active' : ''; ?>">
        <div class="step-num">3</div>
        Confirmed
    </div>
</div>

<?php if ($success): ?>
<!-- ═══════════════════════════════════════════════════════════════
     SUCCESS SCREEN
═══════════════════════════════════════════════════════════════ -->
<div class="success-wrapper">

    <div class="tick-circle">
        <i class="fa fa-check"></i>
    </div>

    <h1>Thank you, <span><?php echo htmlspecialchars($saved_name); ?>!</span></h1>
    <p class="success-sub">
        Your order has been received and our team will start preparing it shortly.<br/>
        We'll call you to confirm delivery details.
    </p>

    <div class="confirm-card">
        <div class="confirm-card-head">
            <span>Order summary</span>
            <span class="order-num">#<?php echo str_pad($order_id, 5, '0', STR_PAD_LEFT); ?></span>
        </div>
        <div class="confirm-card-body">
            <?php foreach($saved_cart as $item): ?>
            <div class="mini-item">
                <span><?php echo htmlspecialchars($item['name']); ?> × <?php echo $item['qty']; ?></span>
                <span>UGX <?php echo number_format($item['price'] * $item['qty']); ?></span>
            </div>
            <?php endforeach; ?>
            <div class="confirm-total">
                <span>Total</span>
                <span>UGX <?php echo number_format($saved_total); ?></span>
            </div>
        </div>
    </div>

    <a href="menu.php" class="btn-success-primary">
        <i class="fa fa-utensils"></i> Order more food
    </a>
    <a href="index.html" class="btn-success-secondary">
        <i class="fa fa-house"></i> Back to home
    </a>

    <p class="call-note">
        <i class="fa fa-phone"></i>
        Questions? Call us on <strong>+256 706 464 182</strong>
    </p>

</div>

<?php else: ?>
<!-- ═══════════════════════════════════════════════════════════════
     CHECKOUT FORM
═══════════════════════════════════════════════════════════════ -->

<!-- BREADCRUMB -->
<div class="breadcrumb">
    <a href="index.html">Home</a> &rsaquo;
    <a href="menu.php">Menu</a> &rsaquo;
    <a href="cart.php">Cart</a> &rsaquo;
    Checkout
</div>

<form method="POST" action="checkout.php" id="checkout-form">
<div class="checkout-wrapper">

    <!-- LEFT: Form -->
    <div class="form-section">

        <?php if (!empty($errors)): ?>
        <div class="error-box">
            <p><i class="fa fa-circle-exclamation"></i> Please fix the following:</p>
            <ul>
                <?php foreach($errors as $e): ?>
                    <li><?php echo htmlspecialchars($e); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Delivery / Pickup -->
        <div class="form-card">
            <div class="card-head">
                <i class="fa fa-motorcycle"></i>
                <h3>Delivery or Pickup?</h3>
            </div>
            <div class="card-body">
                <div class="delivery-toggle">
                    <button type="button" class="del-btn active" id="btn-delivery" onclick="setOrderType('delivery')">
                        <i class="fa fa-motorcycle"></i> Deliver to me
                    </button>
                    <button type="button" class="del-btn" id="btn-pickup" onclick="setOrderType('pickup')">
                        <i class="fa fa-store"></i> I'll pick up
                    </button>
                </div>
                <input type="hidden" name="order_type" id="order_type"
                       value="<?php echo val($post, 'order_type', 'delivery'); ?>"/>
            </div>
        </div>

        <!-- Contact details -->
        <div class="form-card">
            <div class="card-head">
                <i class="fa fa-user"></i>
                <h3>Your details</h3>
            </div>
            <div class="card-body">
                <div class="field-row">
                    <div class="field">
                        <label>Full name <span>*</span></label>
                        <input type="text" name="name" placeholder="e.g. John Ssebagala"
                               value="<?php echo val($post, 'name'); ?>"/>
                    </div>
                    <div class="field">
                        <label>Phone number <span>*</span></label>
                        <input type="tel" name="phone" placeholder="e.g. 0701 234 567"
                               value="<?php echo val($post, 'phone'); ?>"/>
                    </div>
                </div>

                <div id="address-field" class="field">
                    <label>Delivery address <span>*</span></label>
                    <textarea name="address" rows="2"
                              placeholder="e.g. Plot 14, Kampala Road, near Total petrol station"
                    ><?php echo val($post, 'address'); ?></textarea>
                </div>

                <div class="field" style="margin-bottom:0;">
                    <label>Special instructions <span style="color:#bbb;font-weight:400;">(optional)</span></label>
                    <textarea name="notes" rows="2"
                              placeholder="e.g. No onions, extra spicy, call when you arrive…"
                    ><?php echo val($post, 'notes'); ?></textarea>
                </div>
            </div>
        </div>

        <!-- Payment -->
        <div class="form-card">
            <div class="card-head">
                <i class="fa fa-credit-card"></i>
                <h3>Payment method</h3>
            </div>
            <div class="card-body">
                <div class="payment-options">
                    <label class="pay-option <?php echo val($post,'payment')==='cash'?'selected':''; ?>"
                           id="opt-cash" onclick="selectPayment('cash')">
                        <input type="radio" name="payment" value="cash"
                               <?php echo val($post,'payment')==='cash'?'checked':''; ?>/>
                        <span class="pay-check"><i class="fa fa-check"></i></span>
                        <span class="pay-icon">💵</span>
                        <span class="pay-label">Cash on delivery</span>
                        <span class="pay-desc">Pay when your order arrives</span>
                    </label>
                    <label class="pay-option <?php echo val($post,'payment')==='mtn'?'selected':''; ?>"
                           id="opt-mtn" onclick="selectPayment('mtn')">
                        <input type="radio" name="payment" value="mtn"
                               <?php echo val($post,'payment')==='mtn'?'checked':''; ?>/>
                        <span class="pay-check"><i class="fa fa-check"></i></span>
                        <span class="pay-icon">📱</span>
                        <span class="pay-label">MTN MoMo</span>
                        <span class="pay-desc">Pay via MTN Mobile Money</span>
                    </label>
                    <label class="pay-option <?php echo val($post,'payment')==='airtel'?'selected':''; ?>"
                           id="opt-airtel" onclick="selectPayment('airtel')">
                        <input type="radio" name="payment" value="airtel"
                               <?php echo val($post,'payment')==='airtel'?'checked':''; ?>/>
                        <span class="pay-check"><i class="fa fa-check"></i></span>
                        <span class="pay-icon">📲</span>
                        <span class="pay-label">Airtel Money</span>
                        <span class="pay-desc">Pay via Airtel Money</span>
                    </label>
                </div>

                <div id="mm-field" class="field">
                    <label>Mobile money number <span>*</span></label>
                    <input type="tel" name="mm_number" id="mm_number"
                           placeholder="e.g. 0771 234 567"
                           value="<?php echo val($post, 'mm_number'); ?>"/>
                    <p style="font-size:11px;color:#999;margin-top:5px;">
                        <i class="fa fa-info-circle" style="color:#c8860a;"></i>
                        You'll receive a payment prompt on this number after placing your order.
                    </p>
                </div>
            </div>
        </div>

    </div>

    <!-- RIGHT: Order summary -->
    <div class="order-summary">
        <div class="summary-card">
            <div class="summary-header">
                <i class="fa fa-receipt"></i>
                <h3>Your order</h3>
            </div>
            <div class="summary-body">

                <?php foreach($cart as $item): ?>
                <div class="s-item">
                    <?php if (!empty($item['image']) && file_exists("media/" . $item['image'])): ?>
                        <img class="s-item-img"
                             src="media/<?php echo htmlspecialchars($item['image']); ?>"
                             alt="<?php echo htmlspecialchars($item['name']); ?>"/>
                    <?php else: ?>
                        <div class="s-item-placeholder">🍽️</div>
                    <?php endif; ?>
                    <div class="s-item-info">
                        <div class="s-item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                        <div class="s-item-qty">× <?php echo $item['qty']; ?></div>
                    </div>
                    <div class="s-item-price">
                        UGX <?php echo number_format($item['price'] * $item['qty']); ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <hr class="summary-divider"/>

                <div class="summary-row">
                    <span>Subtotal (<?php echo $itemCount; ?> items)</span>
                    <span>UGX <?php echo number_format($subtotal); ?></span>
                </div>
                <div class="summary-row" id="delivery-fee-row">
                    <span>Delivery fee</span>
                    <span id="delivery-fee-val">UGX <?php echo number_format($deliveryFee); ?></span>
                </div>

                <hr class="summary-divider"/>

                <div class="summary-total-row">
                    <span class="summary-total-label">Total</span>
                    <span class="summary-total-price" id="grand-total">
                        UGX <?php echo number_format($subtotal + $deliveryFee); ?>
                    </span>
                </div>

                <button type="submit" class="btn-place-order" id="place-btn">
                    <i class="fa fa-check-circle"></i> PLACE ORDER
                </button>

                <a href="cart.php" class="back-link">
                    <i class="fa fa-arrow-left"></i> Back to cart
                </a>

                <div class="secure-note">
                    <i class="fa fa-lock"></i> Your details are safe with us
                </div>

            </div>
        </div>
    </div>

</div>
</form>
<?php endif; ?>

<div class="bottom-bar">Thank you for choosing Mugiez Hotel And Apartments.</div>

<script>
    const SUBTOTAL     = <?php echo $subtotal; ?>;
    const DELIVERY_FEE = <?php echo $deliveryFee; ?>;
    let   orderType    = '<?php echo val($post, 'order_type', 'delivery'); ?>';
    let   payMethod    = '<?php echo val($post, 'payment', ''); ?>';

    window.addEventListener('DOMContentLoaded', () => {
        setOrderType(orderType);
        if (payMethod === 'mtn' || payMethod === 'airtel') {
            document.getElementById('mm-field').style.display = 'block';
        }
    });

    function setOrderType(type) {
        orderType = type;
        document.getElementById('order_type').value = type;
        document.getElementById('btn-delivery').classList.toggle('active', type === 'delivery');
        document.getElementById('btn-pickup').classList.toggle('active',  type === 'pickup');
        const addrField = document.getElementById('address-field');
        const feeRow    = document.getElementById('delivery-fee-row');
        const feeVal    = document.getElementById('delivery-fee-val');
        const total     = document.getElementById('grand-total');
        if (type === 'delivery') {
            addrField.style.display = 'block';
            feeRow.style.display    = 'flex';
            feeVal.textContent      = 'UGX ' + DELIVERY_FEE.toLocaleString();
            total.textContent       = 'UGX ' + (SUBTOTAL + DELIVERY_FEE).toLocaleString();
        } else {
            addrField.style.display = 'none';
            feeRow.style.display    = 'none';
            total.textContent       = 'UGX ' + SUBTOTAL.toLocaleString();
        }
    }

    function selectPayment(method) {
        payMethod = method;
        document.querySelectorAll('.pay-option').forEach(el => el.classList.remove('selected'));
        document.querySelector('input[name="payment"][value="' + method + '"]').checked = true;
        document.getElementById('opt-' + method).classList.add('selected');
        const mmField = document.getElementById('mm-field');
        mmField.style.display = (method === 'mtn' || method === 'airtel') ? 'block' : 'none';
        if (method === 'cash') document.getElementById('mm_number').value = '';
    }

    document.getElementById('checkout-form')?.addEventListener('submit', function() {
        const btn = document.getElementById('place-btn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Placing order…';
    });
</script>
</body>
</html>