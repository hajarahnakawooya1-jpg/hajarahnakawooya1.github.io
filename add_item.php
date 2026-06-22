<?php
include 'connect.php';
//Check if the form is submitted
if(isset($_POST['submit'])){

//Get data from form inputs.
$Name = $_POST['Name'];
$price = $_POST['price'];
   
//inserting a new job in the SQL Database called job 
    $sql = "INSERT INTO menu (Name, price)
            VALUES ('$Name', '$price')";
//excecuting the query
    $conn->query($sql);
//show a success massage that the jo is added
    echo "<script>alert('Item Added!!'); window.location.href='menu.php';</script>";
}
?>


<!DOCTYPE html>
<html>
<head><title>Add_to_menu</title></head>
<body>

<h2>Add New Item</h2>
<!--link to external java script-->
<script src="java.js"></script>
<!--Form for adding a new job by the admin-->
<form method="POST">
    <label>Item:</label><br>
    <input type="text" name="title" required><br><br>
    
    <label>price:</label><br>
    <input type="text" name="salary"><br><br>

    <button type="submit" name="submit">Add Item</button>
</form>

</body>
</html>