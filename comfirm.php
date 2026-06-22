<?php
session_start();

// Must have a completed order in session
if (empty($_SESSION['last_order_id'])) {
    header( "comfirm.php");
    exit();
}

$order_id    = $_SESSION['last_order_id'];
$order_name  = $_SESSION['last_order_name'];
$order_total = $_SESSION['last_order_total'];
$order_items = json_decode($_SESSION['last_order_items'], true);

// Clear session data so refreshing doesn't re-show stale info
unset($_SESSION['last_order_id'], $_SESSION['last_order_name'],
      $_SESSION['last_order_total'], $_SESSION['last_order_items']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Order Confirmed – Mugiez Hotel</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #f5f3ef; }

        nav {
            background: #1a1a1a;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 40px;
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
        }

        .confirm-wrapper {
            max-width: 600px;
            margin: 50px auto;
            padding: 0 20px;
            text-align: center;
        }

        /* Animated tick circle */
        .tick-circle {
            width: 90px; height: 90px;
            background: #27ae60;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            animation: pop 0.4s ease;
        }
        .tick-circle i { color: #fff; font-size: 40px; }
        @keyframes pop {
            0%   { transform: scale(0.5); opacity: 0; }
            80%  { transform: scale(1.1); }
            100% { transform: scale(1);   opacity: 1; }
        }

        h1 { font-size: 28px; font-weight: 800; color: #1a1a1a; margin-bottom: 8px; }
        h1 span { color: #27ae60; }
        .sub { font-size: 14px; color: #777; margin-bottom: 30px; line-height: 1.6; }

        .order-card {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            text-align: left;
            margin-bottom: 24px;
        }
        .order-card-head {
            background: #1a1a1a;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .order-card-head span { color: #fff; font-size: 14px; font-weight: 700; }
        .order-card-head .order-num { color: #C0392B; font-size: 16px; font-weight: 800; }
        .order-card-body { padding: 20px; }

        .detail-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            color: #555;
            padding: 7px 0;
            border-bottom: 1px solid #f5f3ef;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-row span:last-child { font-weight: 600; color: #1a1a1a; }

        .items-mini { margin: 12px 0; }
        .mini-item {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #666;
            padding: 5px 0;
        }
        .mini-item span:last-child { font-weight: 600; color: #333; }

        .total-row {
            display: flex;
            justify-content: space-between;
            font-size: 16px;
            font-weight: 800;
            color: #C0392B;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 2px dashed #eee;
        }

        /* Action buttons */
        .actions { display: flex; gap: 12px; flex-direction: column; }
        .btn-primary {
            background: #C0392B;
            color: #fff;
            padding: 14px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background 0.2s;
        }
        .btn-primary:hover { background: #A93226; }
        .btn-secondary {
            background: #fff;
            color: #555;
            border: 1.5px solid #ddd;
            padding: 13px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .btn-secondary:hover { border-color: #C0392B; color: #C0392B; }

        .note {
            font-size: 12px;
            color: #aaa;
            margin-top: 20px;
        }
        .note i { color: #c8860a; }

        .bottom-bar {
            background: #222;
            text-align: center;
            padding: 12px;
            color: #bbb;
            font-size: 12px;
            margin-top: 40px;
        }
    </style>
</head>
<body>

<nav>
    <div class="logo">
        <img src="media/logo.jpg" alt="Mugiez Hotel" width="100" height="100">
    </div>
    <ul>
        <li><a href="index.html">Home</a></li>
        <li><a href="menu.php">Menu</a></li>
        <li><a href="about.php">About Us</a></li>
        <li><a href="contact.php">Contact Us</a></li>
    </ul>
    <a href="menu.php" class="cart-btn">🛒 Order more</a>
</nav>

<div class="confirm-wrapper">

    <div class="tick-circle">
        <i class="fa fa-check"></i>
    </div>

    <h1>Order <span>confirmed!</span></h1>
    <p class="sub">
        Thank you, <strong><?php echo htmlspecialchars($order_name); ?></strong>!<br/>
        We've received your order and our team will prepare it shortly.<br/>
        We'll call you to confirm delivery.
    </p>

    <!-- Order summary card -->
    <div class="order-card">
        <div class="order-card-head">
            <span>Order details</span>
            <span class="order-num">#<?php echo str_pad($order_id, 5, '0', STR_PAD_LEFT); ?></span>
        </div>
        <div class="order-card-body">

            <!-- Items -->
            <div class="items-mini">
                <?php foreach($order_items as $item): ?>
                <div class="mini-item">
                    <span><?php echo htmlspecialchars($item['name']); ?> × <?php echo $item['qty']; ?></span>
                    <span>UGX <?php echo number_format($item['price'] * $item['qty']); ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="total-row">
                <span>Total paid</span>
                <span>UGX <?php echo number_format($order_total); ?></span>
            </div>

        </div>
    </div>

    <!-- CTA buttons -->
    <div class="actions">
        <a href="menu.php" class="btn-primary">
            <i class="fa fa-utensils"></i> Order more food
        </a>
        <a href="index.html" class="btn-secondary">
            <i class="fa fa-house"></i> Back to home
        </a>
    </div>

    <p class="note">
        <i class="fa fa-phone"></i>
        Questions? Call us on <strong>+256 706 464 182</strong>
    </p>

</div>

<div class="bottom-bar">Thank you for choosing Mugiez Hotel And Apartments.</div>

</body>
</html>