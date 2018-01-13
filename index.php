<?php
error_reporting( E_ALL );
require("PoGoDB.php");
$database = new PoGoDB();

$page = isset( $_GET["p"] ) ? $_GET["p"] : "livescores";
