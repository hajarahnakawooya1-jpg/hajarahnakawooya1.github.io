<?php
include("connect.php");

// Fetch all categories
$cat_sql    = "SELECT * FROM categories";
$cat_result = mysqli_query($conn, $cat_sql);
$categories = [];
while($cat = mysqli_fetch_assoc($cat_result)) {
    $categories[] = $cat;
}

// Fetch all menu items with category info
$sql    = "SELECT menu.*, categories.name AS cat_name, categories.icon 
           FROM menu 
           LEFT JOIN categories ON menu.category_id = categories.id";
$result = mysqli_query($conn, $sql);

// Group items by category
$menu = [];
while($row = mysqli_fetch_assoc($result)) {
    $menu[$row['cat_name']][] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Menu</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: Arial, sans-serif; }

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
        nav ul li a:hover, nav ul li a.active { color: #E74C3C; }
        nav ul li a.active { border-bottom: 2px solid #E74C3C; padding-bottom: 4px; }

        .cart-btn {
            border: 2px solid ;
            color: white;
            padding: 10px 30px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 15px;
        }

        .cart-btn:hover { background:maroon ; }

        .hero {
            background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)),
                        url('images/hero.jpg') center/cover no-repeat;
            height: 300px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 0 40px;
            color: white;
        }
        .hero span { color: #E74C3C; font-size: 14px; letter-spacing: 3px; text-transform: uppercase; margin-bottom: 10px; }
        .hero h1 { font-size: 50px; margin-bottom: 10px; }
        .hero p { font-size: 16px; color: #ccc; max-width: 400px; }

        .main {
            background: #f9f5f0;
            border-radius: 30px 30px 0 0;
            margin-top: -20px;
            display: flex;
            gap: 30px;
            padding: 40px;
            min-height: 100vh;
        }

        /* Toast notification */
.toast {
    position: fixed;
    bottom: 30px; right: 30px;
    background: #1a1a1a;
    color: #fff;
    padding: 12px 20px;
    border-radius: 8px;
    font-size: 14px;
    border-left: 4px solid #E74C3C;
    z-index: 9999;
    opacity: 0;
    transform: translateY(20px);
    transition: all 0.3s ease;
    pointer-events: none;
}
.toast.show {
    opacity: 1;
    transform: translateY(0);
}

/* Button feedback */
.add-btn.added {
    background: #27ae60;
}

        .sidebar { min-width: 210px; }
        .category-btn {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            padding: 14px 20px;
            margin-bottom: 10px;
            background: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            color: #333;
        }
        .category-btn:hover, .category-btn.active {
            background: #a14238;
            color: white;
            font-weight: bold;
        }
        .count {
            background: #f0e8da;
            color: #c8860a;
            border-radius: 50%;
            padding: 2px 8px;
            font-size: 13px;
        }
        .category-btn.active .count {
            background: rgba(255,255,255,0.3);
            color: white;
        }

        .menu-content { flex: 1; }
        .menu-content h2 {
            font-size: 26px;
            margin-bottom: 20px;
            color: #333;
            border-bottom: 3px solid #7e2016;
            display: inline-block;
            padding-bottom: 5px;
        }
        .category-section { display: none; }
        .category-section.active { display: block; }

        .items-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        .card {
            background: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            padding: 15px;
            gap: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .card img {
            width: 90px;
            height: 90px;
            object-fit: cover;
            border-radius: 10px;
            flex-shrink: 0;
        }
        .card-details { flex: 1; }
        .card-details h3 { font-size: 16px; color: #222; margin-bottom: 8px; }
        .card-bottom { display: flex; justify-content: space-between; align-items: center; }
        .price { font-size: 16px; font-weight: bold; color: #333; }
        .add-btn {
            background: #912b20;
            color: white;
            border: none;
            padding: 7px 14px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
        }
        .add-btn:hover { background: #e9a29a; }

        .footer-note { text-align: center; padding: 20px; color: #999; font-size: 13px; }
    </style>
</head>
<body>
<link rel="stylesheet" href="design.css">
<!-- NAVBAR -->
<nav>
    <div class="logo">
        <img src="media/logo2.png" alt="Logo" width="100px" height="100px">
    </div>
    <div class="nav-links">
    <ul>
        <li><a href="home.html">Home</a></li>
        <li><a href="menu.php" class="active">Menu</a></li>
        <li><a href="index.html">Log out</a></li>
      </div>  
    </ul>

    <a href="cart.php" class="cart-btn">
    🛒 Cart <span id="cart-count" style="
        background:#C0392B; color:#fff; border-radius:50%;
        padding:1px 7px; font-size:12px; margin-left:4px;
        display:none;">0</span>
</a>
</nav>

<!-- HERO -->
<div class="hero">
    <span>— Check Out —</span>
    <h1>Our Menu</h1>
    <p>A delightful selection of meals and drinks made just for you.</p>
</div>

<!-- MAIN -->
<div class="main">

    <!-- SIDEBAR -->
    <div class="sidebar">
        <?php $first = true; foreach($categories as $cat) { 
            $count = isset($menu[$cat['name']]) ? count($menu[$cat['name']]) : 0;
        ?>
            <button class="category-btn <?php echo $first ? 'active' : ''; ?>"
                    onclick="showCategory('<?php echo $cat['name']; ?>')">
                <?php echo $cat['icon'] . ' ' . $cat['name']; ?>
                <span class="count"><?php echo $count; ?></span>
            </button>
        <?php $first = false; } ?>
    </div>

    <!-- MENU ITEMS -->
    <div class="menu-content">
        <?php $first = true; foreach($categories as $cat) { ?>
            <div class="category-section <?php echo $first ? 'active' : ''; ?>"
                 id="cat-<?php echo $cat['name']; ?>">

                <h2><?php echo $cat['icon'] . ' ' . $cat['name']; ?></h2>

                <div class="items-grid">
                    <?php if(isset($menu[$cat['name']])) {
                        foreach($menu[$cat['name']] as $item) { ?>
                            <div class="card">
                                <img src="media/<?php echo $item['image']; ?>"
                                     alt="<?php echo $item['Name']; ?>">
                                <div class="card-details">
                                    <h3><?php echo $item['Name']; ?></h3>
                                    <div class="card-bottom">
                                        <span class="price">UGX <?php echo number_format($item['price']); ?></span>
                                        <button class="add-btn" onclick="addToCart(<?php echo $item['id']; ?>, this)">
    🛒 Add to Cart
</button>
                                    </div>
                                </div>
                            </div>
                    <?php } } else { ?>
                        <p style="color:#999;">No items in this category yet.</p>
                    <?php } ?>
                </div>
            </div>
        <?php $first = false; } ?>
    </div>

</div>

<div class="footer-note">All prices are in Uganda Shillings (UGX)</div>

<script>
    function showCategory(name) {
        document.querySelectorAll('.category-section').forEach(s => s.classList.remove('active'));
        document.querySelectorAll('.category-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('cat-' + name).classList.add('active');
        event.currentTarget.classList.add('active');
    }
</script>
<!-- Toast element -->
<div class="toast" id="toast"></div>

<script>
    function showCategory(name) {
        document.querySelectorAll('.category-section').forEach(s => s.classList.remove('active'));
        document.querySelectorAll('.category-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('cat-' + name).classList.add('active');
        event.currentTarget.classList.add('active');
    }

    function addToCart(foodId, btn) {
        btn.disabled = true;
        btn.textContent = 'Adding…';

        fetch('add_to_cart.php?food_id=' + foodId)
            .then(r => r.json())
            .then(data => {
                if(data.success) {
                    // Update badge
                    const badge = document.getElementById('cart-count');
                    badge.textContent = data.count;
                    badge.style.display = 'inline';

                    // Button feedback
                    btn.textContent = '✓ Added!';
                    btn.classList.add('added');

                    // Toast
                    showToast(data.name + ' added to cart!');

                    // Reset button after 2s
                    setTimeout(() => {
                        btn.textContent = '🛒 Add to Cart';
                        btn.classList.remove('added');
                        btn.disabled = false;
                    }, 2000);
                }
            })
            .catch(() => {
                btn.textContent = '🛒 Add to Cart';
                btn.disabled = false;
            });
    }

    function showToast(msg) {
        const toast = document.getElementById('toast');
        toast.textContent = msg;
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 3000);
    }
</script>
</body>
</html>