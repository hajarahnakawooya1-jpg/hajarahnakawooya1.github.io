<?php
include("connect.php");

// ===================== ADD ITEM =====================
if(isset($_POST['add_item'])) {
    $name        = $_POST['name'];
    $category_id = $_POST['category_id'];
    $price       = $_POST['price'];

    $image = $_FILES['image']['name'];
    $tmp   = $_FILES['image']['tmp_name'];
    move_uploaded_file($tmp, "media/" . $image);

    $sql = "INSERT INTO menu (Name, category_id, price, image) 
            VALUES ('$name', '$category_id', '$price', '$image')";

    if(mysqli_query($conn, $sql)) {
        echo "<script>alert('Item added successfully!');</script>";
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}

// ===================== UPDATE ITEM =====================
if(isset($_POST['update_item'])) {
    $id          = $_POST['id'];
    $name        = $_POST['name'];
    $category_id = $_POST['category_id'];
    $price       = $_POST['price'];

    if($_FILES['image']['name'] != '') {
        $image = $_FILES['image']['name'];
        $tmp   = $_FILES['image']['tmp_name'];
        move_uploaded_file($tmp, "images/" . $image);
    } else {
        $image = $_POST['old_image'];
    }

    $sql = "UPDATE menu SET Name='$name', category_id='$category_id', 
            price='$price', image='$image' WHERE id='$id'";

    if(mysqli_query($conn, $sql)) {
        echo "<script>alert('Item updated successfully!');</script>";
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}

// ===================== DELETE ITEM =====================
if(isset($_GET['delete_id'])) {
    $id  = $_GET['delete_id'];
    $sql = "DELETE FROM menu WHERE id='$id'";

    if(mysqli_query($conn, $sql)) {
        echo "<script>alert('Item deleted!');</script>";
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}

// ===================== FETCH ITEM FOR EDITING =====================
$edit_mode = false;
$edit_row  = null;

if(isset($_GET['edit_id'])) {
    $edit_mode = true;
    $id        = $_GET['edit_id'];
    $sql       = "SELECT * FROM menu WHERE id='$id'";
    $result    = mysqli_query($conn, $sql);
    $edit_row  = mysqli_fetch_assoc($result);
}

// ===================== FETCH CATEGORIES =====================
$cat_sql    = "SELECT * FROM categories";
$cat_result = mysqli_query($conn, $cat_sql);
$categories = [];
while($cat = mysqli_fetch_assoc($cat_result)) {
    $categories[] = $cat;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: Arial, sans-serif; }
        body { background: #f4f4f4; padding: 20px; }
        h1, h2 { color: #333; margin-bottom: 15px; }

        .form-box {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            max-width: 500px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .form-box label { font-weight: bold; font-size: 14px; color: #555; }
        .form-box input,
        .form-box select {
            width: 100%;
            padding: 9px;
            margin: 6px 0 14px 0;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 14px;
        }
        .btn-add    { background: #28a745; color: white; padding: 9px 20px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; }
        .btn-update { background: #007bff; color: white; padding: 9px 20px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; }
        .btn-edit   { background: #fd7e14; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-delete { background: #dc3545; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-cancel { background: #6c757d; color: white; padding: 9px 20px; border: none; border-radius: 6px; cursor: pointer; margin-left: 10px; }

        table { width: 100%; border-collapse: collapse; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        th { background: #333; color: white; padding: 12px; text-align: center; }
        td { padding: 10px; border-bottom: 1px solid #eee; text-align: center; vertical-align: middle; }
        tr:hover { background: #f9f9f9; }
        td img { border-radius: 6px; object-fit: cover; }
    </style>
</head>
<body>

<h1>Admin Panel - Menu Management</h1>

<!-- ===================== FORM ===================== -->
<div class="form-box">
    <h2><?php echo $edit_mode ? 'Edit Item' : 'Add New Item'; ?></h2>

    <form method="POST" enctype="multipart/form-data">

        <?php if($edit_mode) { ?>
            <input type="hidden" name="id" value="<?php echo $edit_row['id']; ?>">
            <input type="hidden" name="old_image" value="<?php echo $edit_row['image']; ?>">
        <?php } ?>

        <label>Name:</label>
        <input type="text" name="name"
               value="<?php echo $edit_mode ? $edit_row['Name'] : ''; ?>" required>

        <label>Category:</label>
        <select name="category_id" required>
            <option value="">-- Select Category --</option>
            <?php foreach($categories as $cat) { ?>
                <option value="<?php echo $cat['id']; ?>"
                    <?php echo ($edit_mode && $edit_row['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                    <?php echo $cat['icon'] . ' ' . $cat['name']; ?>
                </option>
            <?php } ?>
        </select>

        <label>Price (UGX):</label>
        <input type="number" name="price"
               value="<?php echo $edit_mode ? $edit_row['price'] : ''; ?>" required>

        <label>Image <?php echo $edit_mode ? '(leave empty to keep current)' : ''; ?>:</label>
        <?php if($edit_mode && $edit_row['image']) { ?>
            <img src="images/<?php echo $edit_row['image']; ?>" width="70" height="70"><br><br>
        <?php } ?>
        <input type="file" name="image" accept="image/*"
               <?php echo $edit_mode ? '' : 'required'; ?>>

        <br>
        <?php if($edit_mode) { ?>
            <button type="submit" name="update_item" class="btn-update">Update Item</button>
            <a href="admin.php"><button type="button" class="btn-cancel">Cancel</button></a>
        <?php } else { ?>
            <button type="submit" name="add_item" class="btn-add">+ Add Item</button>
        <?php } ?>

    </form>
</div>

<!-- ===================== TABLE ===================== -->
<h2>All Menu Items</h2>
<table>
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Category</th>
        <th>Price</th>
        <th>Image</th>
        <th>Actions</th>
    </tr>

    <?php
    $sql    = "SELECT menu.*, categories.name AS cat_name, categories.icon 
               FROM menu 
               LEFT JOIN categories ON menu.category_id = categories.id";
    $result = mysqli_query($conn, $sql);

    while($row = mysqli_fetch_assoc($result)) { ?>
        <tr>
            <td><?php echo $row['id']; ?></td>
            <td><?php echo $row['Name']; ?></td>
            <td><?php echo $row['icon'] . ' ' . $row['cat_name']; ?></td>
            <td>UGX <?php echo number_format($row['price']); ?></td>
            <td><img src="images/<?php echo $row['image']; ?>" width="60" height="60"></td>
            <td>
                <a href="admin.php?edit_id=<?php echo $row['id']; ?>">
                    <button class="btn-edit">Edit</button>
                </a>
                &nbsp;
                <a href="admin.php?delete_id=<?php echo $row['id']; ?>"
                   onclick="return confirm('Delete this item?')">
                    <button class="btn-delete">Delete</button>
                </a>
            </td>
        </tr>
    <?php } ?>
</table>

</body>
</html>