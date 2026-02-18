<?php
header('Content-Type: text/plain');

function test_url($url) {
    echo "Testing URL: $url\n";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    
    $html = curl_exec($ch);
    $info = curl_getinfo($ch);
    $error = curl_error($ch);
    curl_close($ch);

    echo "HTTP Code: " . $info['http_code'] . "\n";
    if ($error) {
        echo "cURL Error: " . $error . "\n";
    }
    echo "Content Length: " . strlen($html) . "\n";
    echo "Snippet: " . substr(strip_tags($html), 0, 300) . "\n";
    echo "----------------------------------------\n\n";
}

echo "Diagnosticando conexão de saída...\n\n";

test_url("https://www.google.com");
test_url("https://imginn.com/macariobrazil/");
test_url("https://instanavigation.com/user-profile/macariobrazil");
