<?php
$message = "You do not have permission to view this page.";
?>
<!DOCTYPE html>
<html>
<head>
    <title>Access Denied</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<h1>Access Denied</h1>
<p><?php echo $message; ?></p>
<p><a href="index.php">Back to Login</a></p>

</body>
</html>