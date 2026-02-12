<?php
// This file is used to render the employee table for AJAX responses
if (!function_exists("render_employee_table")) {
    include_once("employee-management.php");
}
echo render_employee_table($conn);
?>
