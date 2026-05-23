<?php
// =============================================
// UniSMS Configuration
// SourcePoint - CamNorte Event Aggregator
// =============================================

// INSERT YOUR UNISMS API SECRET KEY HERE
// Get your API key from your profile dashboard at https://unismsapi.com/
define('UNISMS_API_KEY', 'sk_430ab460-669b-4367-a5c4-b1d6a396e74b'); 

// UniSMS REST API standard Endpoint
define('UNISMS_ENDPOINT', 'https://unismsapi.com/api/sms');

// Sender Name / ID (as registered in your UniSMS dashboard)
// Leave blank to use a default verified sender and prevent telecom blocking
define('UNISMS_SENDER_ID', 'SourcePoint');
?>
