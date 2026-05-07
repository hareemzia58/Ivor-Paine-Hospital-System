<?php
/* ---------------- DB CONNECTION ---------------- */
$serverName = "localhost\\SQLEXPRESS";  // SQL Server instance
$options = [
    "Database" => "IvorPaineHospital",  // your hospital DB name
    "Uid"      => "",                   // SQL Server username (leave empty if using Windows Auth)
    "PWD"      => "",                   // SQL Server password (leave empty if using Windows Auth)
    "TrustServerCertificate" => true
];

function db() {
    global $serverName, $options;
    $conn = sqlsrv_connect($serverName, $options);
    if (!$conn) {
        die("Database connection failed: " . print_r(sqlsrv_errors(), true));
    }
    return $conn;
}
?>
