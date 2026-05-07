<?php
// include the database connection
include("db_connect.php");
$conn = db();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Ivor Paine Hospital System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <h1>Welcome to Ivor Paine Hospital System</h1>
    <p>This is a placeholder homepage.</p>
    <hr>

    <h2>Database Connection Test</h2>
    <?php
    $sql = "SELECT TOP 5 p_id, fname, lname, dob, admission_date 
            FROM Patient 
            ORDER BY admission_date DESC";
    $stmt = sqlsrv_query($conn, $sql);

    if ($stmt) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Name</th><th>DOB</th><th>Admission Date</th></tr>";
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            echo "<tr>";
            echo "<td>".$row['p_id']."</td>";
            echo "<td>".$row['fname']." ".$row['lname']."</td>";
            echo "<td>".$row['dob']->format('Y-m-d')."</td>";
            echo "<td>".$row['admission_date']->format('Y-m-d')."</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No patients found or query failed.";
    }
    ?>
</body>
</html>
