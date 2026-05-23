<?php
$file = 'c:\\xampp\\htdocs\\integ\\pages\\contact.html';
$c = file_get_contents($file);

// Find 3rd formData (the const formData = { ... } one for submission form)
$p1 = strpos($c, 'formData');
$p2 = strpos($c, 'formData', $p1 + 10);
$p3 = strpos($c, 'formData', $p2 + 10);

echo "3rd formData at: $p3\n";
$ctx = substr($c, max(0, $p3 - 80), 900);
echo "=== CONTEXT ===\n";
echo $ctx;
echo "\n=== END ===\n";

// Also check if there's a "const formData = {" pattern near p3
$near = substr($c, max(0, $p3 - 20), 40);
echo "\nNear p3: " . bin2hex($near) . "\n";
echo "String: " . $near . "\n";
?>