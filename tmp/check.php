<?php
$c = file_get_contents('c:\\xampp\\htdocs\\integ\\pages\\contact.html');
echo "FormData found: " . (strpos($c, 'FormData') !== false ? "YES" : "NO") . "\n";
echo "cover_image found: " . (strpos($c, "cover_image") !== false ? "YES" : "NO") . "\n";
echo "JSON.stringify found: " . (strpos($c, "JSON.stringify") !== false ? "YES (but should only be in contactForm)" : "NO") . "\n";
echo "File size: " . strlen($c) . " bytes\n";
?>