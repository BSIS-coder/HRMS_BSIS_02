<?php
// install_fonts.php
// Run this file in your browser to download and install the missing FPDF font files.

error_reporting(E_ALL);
ini_set('display_errors', 1);

$fontDir = __DIR__ . '/libs/font';

// 1. Create the font directory if it doesn't exist
if (!file_exists($fontDir)) {
    if (mkdir($fontDir, 0755, true)) {
        echo "✅ Created directory: " . htmlspecialchars($fontDir) . "<br>";
    } else {
        die("❌ Error: Could not create directory '$fontDir'. Please create it manually.");
    }
} else {
    echo "ℹ️ Directory already exists: " . htmlspecialchars($fontDir) . "<br>";
}

// 2. Download the font definition files from a reliable mirror (Setasign/FPDF)
$baseUrl = 'https://raw.githubusercontent.com/Setasign/FPDF/master/font/';
$fonts = [
    'helvetica.php',    // Arial (Normal)
    'helveticab.php',   // Arial (Bold)
    'helveticai.php',   // Arial (Italic)
    'helveticabi.php',  // Arial (Bold Italic)
    'courier.php',
    'times.php'
];

echo "<h3>Downloading Font Files...</h3>";

foreach ($fonts as $file) {
    $targetFile = $fontDir . '/' . $file;
    
    if (file_exists($targetFile)) {
        echo "⏭️ $file already exists.<br>";
        continue;
    }
    
    echo "⬇️ Downloading $file... ";
    
    // Use file_get_contents with context for SSL (fixes common local SSL errors)
    $arrContextOptions=array(
        "ssl"=>array(
            "verify_peer"=>false,
            "verify_peer_name"=>false,
        ),
    );  
    $content = @file_get_contents($baseUrl . $file, false, stream_context_create($arrContextOptions));
    
    if ($content) {
        if (file_put_contents($targetFile, $content)) {
            echo "✅ OK<br>";
        } else {
            echo "❌ Failed to write file.<br>";
        }
    } else {
        echo "❌ Failed to download.<br>";
    }
}

echo "<hr>";
echo "<strong>Status:</strong> Font setup attempt finished.<br>";
echo "You can now try to generate a payslip again.";
?>