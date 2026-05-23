<?php
$file = 'c:\\xampp\\htdocs\\integ\\pages\\contact.html';
$c = file_get_contents($file);

$p1 = strpos($c, 'formData');
$p2 = strpos($c, 'formData', $p1 + 10);
$p3 = strpos($c, 'formData', $p2 + 10);

// Find the matching closing brace and more context
$end = strpos($c, 'JSON.stringify(formData)', $p3);
echo "JSON.stringify at: $end\n";
echo substr($c, $p3 - 16, $end + 100 - $p3 + 16) . "\n";
echo "=== DONE ===\n";
?>