<?php
session_start();
include("connect.php");

// ── Simple password protection ────────────────────────────────────
$ADMIN_PASSWORD = "mugiez2026"; 

if (isset($_POST['login'])) {
    if ($_POST['password'] === $ADMIN_PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
    } else {
        $login_error = "Wrong password. Try again.";
    }
}

if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logged_in']);
    header("Location: admin_orders.php");
    exit();
}

// ── Update order status ───────────────────────────────────────────
if (isset($_GET['status']) && isset($_GET['id']) && isset($_SESSION['admin_logged_in'])) {
    $oid    = intval($_GET['id']);
    $status = $_GET['status'];
    $allowed = ['pending','confirmed','preparing','ready','delivered','cancelled'];
    if (in_array($status, $allowed)) {
        $stmt = mysqli_prepare($conn, "UPDATE orders SET status = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $status, $oid);
        mysqli_stmt_execute($stmt);
    }
    header("Location: admin_orders.php");
    exit();
}

// ── Fetch orders ──────────────────────────────────────────────────
$filter  = $_GET['filter'] ?? 'all';
$search  = trim($_GET['search'] ?? '');
$where   = [];
$params  = [];
$types   = "";

if ($filter !== 'all') {
    $where[]  = "status = ?";
    $params[] = $filter;
    $types   .= "s";
}
if (!empty($search)) {
    $where[]  = "(customer_name LIKE ? OR phone LIKE ?)";
    $s = "%$search%";
    $params[] = $s;
    $params[] = $s;
    $types   .= "ss";
}

$sql = "SELECT * FROM orders";
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY created_at DESC";

$stmt = mysqli_prepare($conn, $sql);
if ($params) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$orders = [];
while ($row = mysqli_fetch_assoc($result)) $orders[] = $row;

// ── Counts per status ─────────────────────────────────────────────
$counts_result = mysqli_query($conn, "SELECT status, COUNT(*) as cnt FROM orders GROUP BY status");
$counts = ['all' => 0, 'pending' => 0, 'confirmed' => 0, 'preparing' => 0, 'ready' => 0, 'delivered' => 0, 'cancelled' => 0];
while ($r = mysqli_fetch_assoc($counts_result)) {
    $counts[$r['status']] = (int)$r['cnt'];
    $counts['all'] += (int)$r['cnt'];
}

// Status colors and labels
$status_info = [
    'pending'   => ['color' => '#e67e22', 'bg' => '#fef9f0', 'icon' => '🕐', 'label' => 'Pending'],
    'confirmed' => ['color' => '#2980b9', 'bg' => '#f0f7ff', 'icon' => '✅', 'label' => 'Confirmed'],
    'preparing' => ['color' => '#8e44ad', 'bg' => '#f9f0ff', 'icon' => '👨‍🍳', 'label' => 'Preparing'],
    'ready'     => ['color' => '#27ae60', 'bg' => '#f0fff5', 'icon' => '📦', 'label' => 'Ready'],
    'delivered' => ['color' => '#1a1a1a', 'bg' => '#f5f5f5', 'icon' => '🎉', 'label' => 'Delivered'],
    'cancelled' => ['color' => '#C0392B', 'bg' => '#fff5f5', 'icon' => '❌', 'label' => 'Cancelled'],
];

// Next status transitions
$next_status = [
    'pending'   => 'confirmed',
    'confirmed' => 'preparing',
    'preparing' => 'ready',
    'ready'     => 'delivered',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Admin – Orders | Mugiez Hotel</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #f0f2f5; color: #1a1a1a; }

        /* ── LOGIN PAGE ── */
        .login-wrap {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #1a1a1a;
        }
        .login-box {
            background: #fff;
            border-radius: 16px;
            padding: 40px;
            width: 360px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
        }
        .login-box .lock-icon {
            width: 70px; height: 70px;
            background: #C0392B;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
            font-size: 28px; color: #fff;
        }
        .login-box h2 { font-size: 22px; font-weight: 800; margin-bottom: 4px; }
        .login-box p  { font-size: 13px; color: #999; margin-bottom: 24px; }
        .login-box input[type="password"] {
            width: 100%; border: 1.5px solid #eee; border-radius: 8px;
            padding: 12px 16px; font-size: 14px; font-family: 'Poppins', sans-serif;
            outline: none; margin-bottom: 14px;
        }
        .login-box input[type="password"]:focus { border-color: #C0392B; }
        .login-box button {
            width: 100%; background: #C0392B; color: #fff; border: none;
            padding: 13px; border-radius: 8px; font-size: 14px; font-weight: 700;
            cursor: pointer; font-family: 'Poppins', sans-serif;
        }
        .login-box button:hover { background: #A93226; }
        .login-error {
            background: #fff5f5; border: 1px solid #E74C3C;
            border-radius: 6px; padding: 10px; font-size: 12px;
            color: #C0392B; margin-bottom: 14px;
        }

        /* ── TOP BAR ── */
        .topbar {
            background: #1a1a1a;
            padding: 14px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky; top: 0; z-index: 100;
        }
        .topbar-left { display: flex; align-items: center; gap: 14px; }
        .topbar-left img { height: 40px; border-radius: 6px; }
        .topbar-left h1 { color: #fff; font-size: 16px; font-weight: 800; }
        .topbar-left h1 span { color: #C0392B; }
        .topbar-right { display: flex; align-items: center; gap: 14px; }
        .live-badge {
            background: #27ae60; color: #fff;
            font-size: 11px; font-weight: 700;
            padding: 4px 10px; border-radius: 20px;
            display: flex; align-items: center; gap: 5px;
        }
        .live-dot {
            width: 7px; height: 7px;
            background: #fff; border-radius: 50%;
            animation: blink 1.2s infinite;
        }
        @keyframes blink { 0%,100%{opacity:1;} 50%{opacity:0.2;} }
        .logout-btn {
            color: #aaa; font-size: 12px; text-decoration: none;
            border: 1px solid #444; padding: 6px 14px; border-radius: 6px;
        }
        .logout-btn:hover { color: #fff; border-color: #fff; }

        /* ── STAT CARDS ── */
        .stats-row {
            display: flex; gap: 14px;
            padding: 24px 30px 0;
            flex-wrap: wrap;
        }
        .stat-card {
            background: #fff; border-radius: 10px;
            padding: 16px 20px; flex: 1; min-width: 120px;
            box-shadow: 0 1px 6px rgba(0,0,0,0.06);
            display: flex; flex-direction: column; gap: 4px;
            cursor: pointer; transition: box-shadow 0.2s;
            text-decoration: none;
        }
        .stat-card:hover { box-shadow: 0 4px 14px rgba(0,0,0,0.1); }
        .stat-card.active { border-bottom: 3px solid #C0392B; }
        .stat-num { font-size: 28px; font-weight: 800; color: #1a1a1a; }
        .stat-label { font-size: 11px; color: #999; font-weight: 600; text-transform: uppercase; }
        .stat-card.pending   .stat-num { color: #e67e22; }
        .stat-card.confirmed .stat-num { color: #2980b9; }
        .stat-card.preparing .stat-num { color: #8e44ad; }
        .stat-card.ready     .stat-num { color: #27ae60; }

        /* ── TOOLBAR ── */
        .toolbar {
            display: flex; gap: 12px; align-items: center;
            padding: 20px 30px 0;
            flex-wrap: wrap;
        }
        .search-box {
            display: flex; align-items: center; gap: 8px;
            background: #fff; border: 1.5px solid #eee;
            border-radius: 8px; padding: 9px 14px;
            flex: 1; min-width: 200px;
        }
        .search-box i { color: #bbb; }
        .search-box input {
            border: none; outline: none; font-size: 13px;
            font-family: 'Poppins', sans-serif; width: 100%;
        }
        .refresh-btn {
            background: #1a1a1a; color: #fff; border: none;
            padding: 10px 18px; border-radius: 8px; font-size: 12px;
            font-weight: 600; cursor: pointer; font-family: 'Poppins', sans-serif;
            display: flex; align-items: center; gap: 6px;
        }
        .refresh-btn:hover { background: #333; }

        /* ── ORDERS GRID ── */
        .orders-wrap { padding: 20px 30px 40px; }
        .section-title {
            font-size: 14px; font-weight: 700; color: #555;
            margin-bottom: 14px; display: flex; align-items: center; gap: 8px;
        }

        .orders-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 18px;
        }

        .order-card {
            background: #fff; border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            overflow: hidden;
            border-top: 4px solid #eee;
            transition: box-shadow 0.2s;
        }
        .order-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.1); }
        .order-card.pending   { border-top-color: #e67e22; }
        .order-card.confirmed { border-top-color: #2980b9; }
        .order-card.preparing { border-top-color: #8e44ad; }
        .order-card.ready     { border-top-color: #27ae60; }
        .order-card.delivered { border-top-color: #1a1a1a; }
        .order-card.cancelled { border-top-color: #C0392B; }

        .order-head {
            padding: 14px 16px 10px;
            display: flex; justify-content: space-between; align-items: flex-start;
        }
        .order-id { font-size: 13px; font-weight: 800; color: #1a1a1a; }
        .order-time { font-size: 11px; color: #bbb; margin-top: 2px; }
        .status-badge {
            font-size: 11px; font-weight: 700;
            padding: 4px 10px; border-radius: 20px;
            display: flex; align-items: center; gap: 4px;
        }

        .order-customer {
            padding: 0 16px 10px;
            border-bottom: 1px solid #f5f3ef;
        }
        .customer-name { font-size: 14px; font-weight: 700; margin-bottom: 4px; }
        .customer-meta { font-size: 12px; color: #666; display: flex; gap: 12px; flex-wrap: wrap; }
        .customer-meta span { display: flex; align-items: center; gap: 4px; }
        .customer-meta i { color: #C0392B; font-size: 11px; }

        .order-items {
            padding: 10px 16px;
            border-bottom: 1px solid #f5f3ef;
        }
        .order-item-row {
            display: flex; justify-content: space-between;
            font-size: 12px; color: #444; padding: 3px 0;
        }
        .order-item-row span:last-child { font-weight: 600; }

        .order-totals {
            padding: 10px 16px;
            border-bottom: 1px solid #f5f3ef;
            display: flex; justify-content: space-between;
            align-items: center;
        }
        .order-total-label { font-size: 12px; color: #999; }
        .order-total-price { font-size: 16px; font-weight: 800; color: #C0392B; }

        .order-payment {
            padding: 8px 16px;
            border-bottom: 1px solid #f5f3ef;
            display: flex; gap: 10px; flex-wrap: wrap;
        }
        .tag {
            font-size: 11px; font-weight: 600;
            padding: 3px 10px; border-radius: 20px;
            background: #f5f3ef; color: #555;
            display: flex; align-items: center; gap: 4px;
        }

        .order-actions { padding: 12px 16px; display: flex; gap: 8px; flex-wrap: wrap; }
        .btn-next {
            flex: 1; background: #C0392B; color: #fff; border: none;
            padding: 9px 12px; border-radius: 6px; font-size: 12px;
            font-weight: 700; cursor: pointer; font-family: 'Poppins', sans-serif;
            text-decoration: none; display: flex; align-items: center;
            justify-content: center; gap: 5px; transition: background 0.2s;
        }
        .btn-next:hover { background: #A93226; }
        .btn-cancel {
            background: transparent; color: #bbb;
            border: 1.5px solid #eee; padding: 9px 12px;
            border-radius: 6px; font-size: 12px; font-weight: 600;
            cursor: pointer; font-family: 'Poppins', sans-serif;
            text-decoration: none; display: flex; align-items: center;
            justify-content: center; gap: 5px; transition: all 0.2s;
        }
        .btn-cancel:hover { border-color: #C0392B; color: #C0392B; }
        .btn-call {
            background: #f5f3ef; color: #333;
            border: none; padding: 9px 12px;
            border-radius: 6px; font-size: 12px; font-weight: 600;
            cursor: pointer; font-family: 'Poppins', sans-serif;
            text-decoration: none; display: flex; align-items: center;
            justify-content: center; gap: 5px; transition: all 0.2s;
        }
        .btn-call:hover { background: #e5e0d8; }

        /* address box */
        .address-box {
            background: #f9f7f4; border-left: 3px solid #c8860a;
            padding: 7px 12px; margin: 0 16px 10px;
            border-radius: 0 6px 6px 0; font-size: 12px; color: #555;
        }
        .address-box i { color: #c8860a; margin-right: 4px; }

        /* empty state */
        .empty-state {
            text-align: center; padding: 60px 20px; color: #bbb;
            grid-column: 1 / -1;
        }
        .empty-state i { font-size: 48px; margin-bottom: 14px; display: block; }
        .empty-state p { font-size: 14px; }

        /* auto-refresh countdown */
        .refresh-note { font-size: 11px; color: #bbb; }

        @media (max-width: 600px) {
            .stats-row, .toolbar, .orders-wrap { padding-left: 16px; padding-right: 16px; }
            .topbar { padding: 12px 16px; }
        }
    </style>
</head>
<body>

<?php if (!isset($_SESSION['admin_logged_in'])): ?>
<!-- ═══════════ LOGIN ═══════════ -->
<div class="login-wrap">
    <div class="login-box">
        <div class="lock-icon"><i class="fa fa-lock"></i></div>
        <h2>Staff Login</h2>
        <p>Mugiez Hotel — Orders Dashboard</p>
        <?php if (!empty($login_error)): ?>
            <div class="login-error"><i class="fa fa-circle-exclamation"></i> <?php echo $login_error; ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="password" name="password" placeholder="Enter staff password" autofocus/>
            <button type="submit" name="login"><i class="fa fa-right-to-bracket"></i> Login</button>
        </form>
    </div>
</div>

<?php else: ?>
<!-- ═══════════ DASHBOARD ═══════════ -->

<!-- TOP BAR -->
<div class="topbar">
    <div class="topbar-left">
        <img src="media/logo.jpg" alt="Logo"/>
        <h1>Mugiez <span>Orders</span></h1>
    </div>
    <div class="topbar-right">
        <div class="live-badge"><div class="live-dot"></div> LIVE</div>
        <span class="refresh-note" id="countdown">Refreshing in 30s</span>
        <a href="?logout=1" class="logout-btn"><i class="fa fa-right-from-bracket"></i> Logout</a>
    </div>
</div>

<!-- STAT CARDS -->
<div class="stats-row">
    <?php
    $stat_items = [
        ['key'=>'all',       'label'=>'All Orders',  'icon'=>'fa-list'],
        ['key'=>'pending',   'label'=>'Pending',     'icon'=>'fa-clock'],
        ['key'=>'confirmed', 'label'=>'Confirmed',   'icon'=>'fa-check'],
        ['key'=>'preparing', 'label'=>'Preparing',   'icon'=>'fa-fire-burner'],
        ['key'=>'ready',     'label'=>'Ready',       'icon'=>'fa-box'],
        ['key'=>'delivered', 'label'=>'Delivered',   'icon'=>'fa-flag-checkered'],
    ];
    foreach ($stat_items as $s):
    ?>
    <a href="?filter=<?php echo $s['key']; ?>"
       class="stat-card <?php echo $s['key']; ?> <?php echo $filter===$s['key']?'active':''; ?>">
        <div class="stat-num"><?php echo $counts[$s['key']] ?? 0; ?></div>
        <div class="stat-label"><?php echo $s['label']; ?></div>
    </a>
    <?php endforeach; ?>
</div>

<!-- TOOLBAR -->
<div class="toolbar">
    <form method="GET" style="display:flex;gap:10px;flex:1;flex-wrap:wrap;">
        <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>"/>
        <div class="search-box">
            <i class="fa fa-search"></i>
            <input type="text" name="search"
                   placeholder="Search by name or phone…"
                   value="<?php echo htmlspecialchars($search); ?>"/>
        </div>
        <button type="submit" class="refresh-btn">
            <i class="fa fa-search"></i> Search
        </button>
    </form>
    <button class="refresh-btn" onclick="location.reload()">
        <i class="fa fa-rotate"></i> Refresh
    </button>
</div>

<!-- ORDERS -->
<div class="orders-wrap">
    <div class="section-title">
        <i class="fa fa-receipt" style="color:#C0392B;"></i>
        <?php echo count($orders); ?> order<?php echo count($orders)!==1?'s':''; ?>
        <?php if ($filter !== 'all'): ?>
            — <span style="color:#C0392B;"><?php echo ucfirst($filter); ?></span>
        <?php endif; ?>
    </div>

    <div class="orders-grid">
        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <i class="fa fa-inbox"></i>
                <p>No orders found.</p>
            </div>
        <?php endif; ?>

        <?php foreach($orders as $o):
            $info  = $status_info[$o['status']] ?? $status_info['pending'];
            $items = json_decode($o['items'], true) ?? [];
            $next  = $next_status[$o['status']] ?? null;
            $next_info = $next ? $status_info[$next] : null;
        ?>
        <div class="order-card <?php echo $o['status']; ?>">

            <!-- Head -->
            <div class="order-head">
                <div>
                    <div class="order-id">Order #<?php echo str_pad($o['id'],5,'0',STR_PAD_LEFT); ?></div>
                    <div class="order-time">
                        <i class="fa fa-clock" style="font-size:10px;"></i>
                        <?php echo date('d M Y, h:i A', strtotime($o['created_at'])); ?>
                    </div>
                </div>
                <div class="status-badge"
                     style="background:<?php echo $info['bg']; ?>;color:<?php echo $info['color']; ?>;">
                    <?php echo $info['icon'] . ' ' . $info['label']; ?>
                </div>
            </div>

            <!-- Customer -->
            <div class="order-customer">
                <div class="customer-name"><?php echo htmlspecialchars($o['customer_name']); ?></div>
                <div class="customer-meta">
                    <span><i class="fa fa-phone"></i><?php echo htmlspecialchars($o['phone']); ?></span>
                    <span><i class="fa fa-<?php echo $o['order_type']==='delivery'?'motorcycle':'store'; ?>"></i>
                        <?php echo ucfirst($o['order_type']); ?>
                    </span>
                </div>
            </div>

            <!-- Address -->
            <?php if (!empty($o['address'])): ?>
            <div class="address-box">
                <i class="fa fa-location-dot"></i>
                <?php echo htmlspecialchars($o['address']); ?>
            </div>
            <?php endif; ?>

            <!-- Items -->
            <div class="order-items">
                <?php foreach($items as $item): ?>
                <div class="order-item-row">
                    <span><?php echo htmlspecialchars($item['name']); ?> × <?php echo $item['qty']; ?></span>
                    <span>UGX <?php echo number_format($item['price'] * $item['qty']); ?></span>
                </div>
                <?php endforeach; ?>
                <?php if (!empty($o['notes'])): ?>
                <div style="margin-top:6px;font-size:11px;color:#c8860a;font-style:italic;">
                    <i class="fa fa-pen"></i> <?php echo htmlspecialchars($o['notes']); ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Total -->
            <div class="order-totals">
                <div>
                    <div class="order-total-label">Total</div>
                    <?php if ($o['delivery_fee'] > 0): ?>
                    <div style="font-size:10px;color:#bbb;">
                        Subtotal UGX <?php echo number_format($o['subtotal']); ?> + delivery UGX <?php echo number_format($o['delivery_fee']); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="order-total-price">UGX <?php echo number_format($o['total']); ?></div>
            </div>

            <!-- Payment tags -->
            <div class="order-payment">
                <?php
                $pay_icons = ['cash'=>'💵','mtn'=>'📱','airtel'=>'📲'];
                $pay_labels= ['cash'=>'Cash on delivery','mtn'=>'MTN MoMo','airtel'=>'Airtel Money'];
                ?>
                <span class="tag">
                    <?php echo ($pay_icons[$o['payment_method']] ?? '💳') . ' ' . ($pay_labels[$o['payment_method']] ?? $o['payment_method']); ?>
                </span>
            </div>

            <!-- Actions -->
            <div class="order-actions">
                <!-- Call button -->
                <a href="tel:<?php echo htmlspecialchars($o['phone']); ?>" class="btn-call">
                    <i class="fa fa-phone"></i> Call
                </a>

                <!-- WhatsApp quick message -->
                <?php
                $wa_msg = urlencode("Hi " . $o['customer_name'] . ", your order #" . str_pad($o['id'],5,'0',STR_PAD_LEFT) . " from Mugiez Hotel has been confirmed and is being prepared. Thank you!");
                $wa_num = preg_replace('/^0/', '256', preg_replace('/\s+/', '', $o['phone']));
                ?>
                <a href="https://wa.me/<?php echo $wa_num; ?>?text=<?php echo $wa_msg; ?>"
                   target="_blank" class="btn-call" style="background:#dcf8c6;">
                    <i class="fab fa-whatsapp" style="color:#25D366;"></i> WhatsApp
                </a>

                <!-- Advance status -->
                <?php if ($next): ?>
                <a href="?id=<?php echo $o['id']; ?>&status=<?php echo $next; ?>&filter=<?php echo $filter; ?>"
                   class="btn-next"
                   onclick="return confirm('Mark this order as <?php echo $next_info['label']; ?>?')">
                    <?php echo $next_info['icon']; ?> Mark <?php echo $next_info['label']; ?>
                </a>
                <?php endif; ?>

                <!-- Cancel (only if not delivered/cancelled) -->
                <?php if (!in_array($o['status'], ['delivered','cancelled'])): ?>
                <a href="?id=<?php echo $o['id']; ?>&status=cancelled&filter=<?php echo $filter; ?>"
                   class="btn-cancel"
                   onclick="return confirm('Cancel this order?')">
                    <i class="fa fa-xmark"></i> Cancel
                </a>
                <?php endif; ?>
            </div>

        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php endif; ?>

<script>
// Auto-refresh every 30 seconds so new orders appear automatically
let secs = 30;
const cd = document.getElementById('countdown');
if (cd) {
    setInterval(() => {
        secs--;
        if (secs <= 0) { location.reload(); }
        cd.textContent = 'Refreshing in ' + secs + 's';
    }, 1000);
}
</script>
</body>
</html>