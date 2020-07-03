<?php

$servername = "localhost";
$username = "root";
$password = "";
$customer_ordered_product = 'DC_ENGINE';

//Get global connection object for MYSQL DB.
$conn = mysqli_connect($servername, $username, $password);
if (! $conn) {
  die("Connection failed: " . mysqli_error($conn));
}



//Function to get the number of manufactured units from stock_mouvement table
function getNumberOfUnits($conn, $product_id) {
	$sql_number_of_units = "SELECT value FROM dolibarr.llx_stock_mouvement where fk_product = $product_id";
	$result_number_of_units = mysqli_query($conn, $sql_number_of_units);
	$unitsArray = [];
    while($row = mysqli_fetch_array($result_number_of_units, MYSQLI_ASSOC)) {
		$units = $row['value'];
        array_push($unitsArray, $units);
    }
	$length_unitsArray = sizeof($unitsArray);
	echo "Num of manufactured_units " . $unitsArray[$length_unitsArray-1] . "<br>";
	return $unitsArray[$length_unitsArray-1];
}


//Function to get stock of a given product given ref
//product name ref is unique, so its safe to get row details using ref
function getStockGivenRef($conn, $product) {
    $sql_get_stock = "SELECT stock FROM dolibarr.llx_product WHERE ref = '$product'";
    $result_get_stock = mysqli_query($conn, $sql_get_stock);

    if($result_get_stock) {
        while($row = mysqli_fetch_object($result_get_stock)) {
            $stock = $row->stock;                             // get the stock of input product
        }
        return $stock;
    }
    if( !$result_get_stock ) {
        die('Could not fetch stock for product '. $product . ' : ' . mysqli_error($conn));
    }
}

//Function to get stock of a given product rowid
//product name ref is unique, so its safe to get row details using ref
function getStockGivenID($conn, $product_id) {
    $sql_get_stock = "SELECT stock FROM dolibarr.llx_product WHERE rowid = $product_id";
    $result_get_stock = mysqli_query($conn, $sql_get_stock);

    if($result_get_stock) {
        while($row = mysqli_fetch_object($result_get_stock)) {
            $stock = $row->stock;                             // get the stock of input product
        }
        return $stock;
    }
    if( !$result_get_stock ) {
        die('Could not fetch stock for product '. $product . ' : ' . mysqli_error($conn));
    }
}


//Function to get row ID of a given product
//product name ref is unique, so its safe to get row details using ref
function getRowId($conn, $product) {
    $sql_get_rowid = "SELECT rowid FROM dolibarr.llx_product WHERE ref = '$product'";
    $result_get_rowid = mysqli_query($conn, $sql_get_rowid);

    if($result_get_rowid){
        while ($row = mysqli_fetch_object($result_get_rowid)) {
            $rowid =  $row->rowid;                             // get the rowid of product
        }
        return $rowid;
    }
    if(! $result_get_rowid ) {
        die('Could not fetch row ID for product '. $product . ' : ' . mysqli_error($conn));
    }
}

//Function to get children components multiplied with reqd_stock of parent component.
function get_children($conn, $fk_product_father, $reqd_stock) {
    $sql_get_children_and_qty = "SELECT fk_product_children, qty FROM dolibarr.llx_product_factory WHERE fk_product_father = $fk_product_father";
    $result_get_children_and_qty = mysqli_query($conn, $sql_get_children_and_qty);
    if($result_get_children_and_qty) {
        $resultArray = [];
    	  while($row = mysqli_fetch_array($result_get_children_and_qty, MYSQLI_ASSOC)) {

    		    $stock_needed_of_child = $row['qty'] * $reqd_stock;
    		    echo "get_children:: " . "stock needed of child product:" . $stock_needed_of_child . "<br>";
    		    $child_id = $row['fk_product_children'];
    		    echo "get_children:: " . "child ID: ". $child_id . "<br>";
    		    $currentArray = array($child_id, $stock_needed_of_child);
    		    array_push($resultArray, $currentArray);
    	  }
        return $resultArray;
    }
    if(! $result_get_children_and_qty ) {
        die('Could not fetch children and qty for product '. $fk_product_father . ' : ' . mysqli_error($conn));
    }
}


#Update stock for given product_id with new_stock
function updateStock($conn, $product_id, $new_stock) {
    $sql_update_stock = "UPDATE dolibarr.llx_product SET stock = $new_stock WHERE rowid = $product_id";
    $result_update_stock = mysqli_query($conn, $sql_update_stock);
    if( !$result_update_stock ) {
      die('Could not update stock for product '. $product_id . ' : ' . mysqli_error($conn));
    }
    $updated_stock = getStockGivenID($conn, $product_id);
    return $updated_stock;
}


//Recursive function to update stock for child components of a given parent product
//reqd_stock=customer order
function recursiveStockUpdate($conn, $rowid_of_parent, $reqd_stock) {
    $resultArray = get_children($conn, $rowid_of_parent, $reqd_stock);
    #Example resultArray: Rows are children of input parent. Column 0 is rowid of child product, column 1 is qty*(reqd stock of immediate parent)
    #Array ( [0] => Array ( [0] => 1 [1] => 40 ) [1] => Array ( [0] => 2 [1] => 40 ) [2] => Array ( [0] => 3 [1] => 40 ) )
    echo "Required stock for children of " . $rowid_of_parent;
    print_r($resultArray);
    echo "<br>";

    if(sizeof($resultArray) == 0) {
       echo "No children for parent " . $rowid_of_parent . "<br>";
       return;
    }

    for( $i = 0; $i < sizeof($resultArray); $i++ ) {
        echo "Calling recursiveStockUpdate() for product" . $resultArray[$i][0] . " with required stock " .$resultArray[$i][1]. "<br>";
        recursiveStockUpdate($conn, $resultArray[$i][0], $resultArray[$i][1]);

        echo "Updating stock for product " . $resultArray[$i][0] . "<br>";

        $product_id = (int)$resultArray[$i][0];
        $mysql_get_current_stock =  "SELECT stock FROM dolibarr.llx_product WHERE rowid = $product_id ";
        $result_get_current_stock = mysqli_query($conn, $mysql_get_current_stock);

        if($result_get_current_stock) {
            while ($row = mysqli_fetch_object($result_get_current_stock)) {
                 $current_stock = $row->stock;
                 $difference = $current_stock - $resultArray[$i][1];
                 if ( $difference < 0) {
                    die('Current stock '. $current_stock . ' Required stock ' . $resultArray[$i][1] . 'Not enough stocks available for ' .$product_id);
                 }
            }
            $updated_stock = updateStock($conn, $product_id, $difference);
            echo "Updated stock for product " . $product_id . "is " . $updated_stock ."<br>";
        }
        if(! $result_get_current_stock ) {
            die('Could not fetch current stock for product '. $product_id . ' : ' . mysqli_error($conn));
        }
    }
}

#Get current stock of $customer_ordered_product
$customer_ordered_product_current_stock = getStockGivenRef($conn, $customer_ordered_product);
echo "Current stock of product ". $customer_ordered_product . ":" . $customer_ordered_product_current_stock . "<br>";

#Get rowid of $customer_ordered_product
$customer_ordered_product_rowid = getRowId($conn, $customer_ordered_product);
echo "RowID of product ". $customer_ordered_product . ":" . $customer_ordered_product_rowid . "<br>";

$manufactured_units = getNumberOfUnits($conn, $customer_ordered_product_rowid);

$difference_ordered_product = $customer_ordered_product_current_stock - $manufactured_units;
if ( $difference_ordered_product < 0) {
   die('Current stock '. $customer_ordered_product_current_stock . ' Required stock ' . $manufactured_units . 'Not enough stocks available');
}

#Update stock for $customer_ordered_product_rowid along with all children products
recursiveStockUpdate($conn, $customer_ordered_product_rowid, $manufactured_units);

$updated_stock = updateStock($conn, $customer_ordered_product_rowid, $difference_ordered_product);
echo "Updated stock for product " . $customer_ordered_product_rowid . "is " . $updated_stock ."<br>";

?>