<?php
$file = 'c:\\xampp\\htdocs\\integ\\pages\\contact.html';
$c = file_get_contents($file);

$old = "                const formData = {\r\n                    org_name: document.getElementById('orgName').value,\r\n                    contact_person: document.getElementById('contactPerson').value,\r\n                    contact_email: document.getElementById('contactEmail').value,\r\n                    contact_mobile: document.getElementById('contactMobile').value,\r\n                    event_title: document.getElementById('eventTitle').value,\r\n                    event_description: document.getElementById('eventDescription').value,\r\n                    event_category: document.getElementById('eventCategory').value,\r\n                    event_location: document.getElementById('eventLocation').value,\r\n                    event_date: document.getElementById('eventDate').value,\r\n                    event_organizer: document.getElementById('eventOrganizer').value,\r\n                    partner_organizations: document.getElementById('eventPartners').value\r\n                };\r\n\r\n                try {\r\n                    const response = await fetch('../api/submissions.php', {\r\n                        method: 'POST',\r\n                        headers: { 'Content-Type': 'application/json' },\r\n                        body: JSON.stringify(formData)\r\n                    });";

$new = "                // Use FormData to include the cover image file upload\r\n                const formData = new FormData();\r\n                formData.append('org_name', document.getElementById('orgName').value);\r\n                formData.append('contact_person', document.getElementById('contactPerson').value);\r\n                formData.append('contact_email', document.getElementById('contactEmail').value);\r\n                formData.append('contact_mobile', document.getElementById('contactMobile').value);\r\n                formData.append('event_title', document.getElementById('eventTitle').value);\r\n                formData.append('event_description', document.getElementById('eventDescription').value);\r\n                formData.append('event_category', document.getElementById('eventCategory').value);\r\n                formData.append('event_location', document.getElementById('eventLocation').value);\r\n                formData.append('event_date', document.getElementById('eventDate').value);\r\n                formData.append('event_organizer', document.getElementById('eventOrganizer').value);\r\n                formData.append('partner_organizations', document.getElementById('eventPartners').value);\r\n\r\n                // Include the cover image file if one was selected\r\n                const coverFileInput = document.getElementById('event-image');\r\n                if (coverFileInput && coverFileInput.files && coverFileInput.files.length > 0) {\r\n                    formData.append('cover_image', coverFileInput.files[0]);\r\n                }\r\n\r\n                try {\r\n                    const response = await fetch('../api/submissions.php', {\r\n                        method: 'POST',\r\n                        body: formData\r\n                        // Note: Do NOT set Content-Type header; let the browser set it with the boundary for FormData\r\n                    });";

$count = 0;
$c = str_replace($old, $new, $c, $count);
echo "Replaced: $count\n";

if ($count > 0) {
    file_put_contents($file, $c);
    echo "SUCCESS: File updated!\n";
} else {
    echo "FAILED: No replacement made.\n";
    // Debug: check if the text exists
    echo "Old exists: " . (strpos(file_get_contents($file), $old) !== false ? "yes" : "no") . "\n";
}
?>