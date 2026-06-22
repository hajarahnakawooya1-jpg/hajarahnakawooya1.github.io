<?php
//connecting to the database in MYSQL 
$server="localhost";
$user="root";
$password="";
$database="mugiez";

$conn=mysqli_connect($server,$user,$password,$database);

if ($conn){
   //echo"Connected to the database server";
}
?>