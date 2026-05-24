<?php

$dir = new RecursiveDirectoryIterator('c:\\xampp\\htdocs\\SpaceBorn\\SpaceBorn');
$ite = new RecursiveIteratorIterator($dir);
$files = new RegexIterator($ite, '/\.(php|html)$/', RegexIterator::MATCH);

foreach($files as $file) {
    if (strpos($file->getPathname(), 'vendor') !== false) continue;
    if ($file->getBasename() === 'fix_basis.php') continue;

    $content = file_get_contents($file->getPathname());
    $newContent = $content;
    
    // Replace 'basis.html' with 'basic.html'
    $newContent = str_replace('basis.html', 'basic.html', $newContent);
    // Replace 'BASIS' with 'BASIC'
    $newContent = str_replace('BASIS', 'BASIC', $newContent);
    // Replace 'basis_' with 'basic_' (like in $_ENV['PLAN_BASIS_MINUTES'])
    $newContent = str_replace('basis_', 'basic_', $newContent);
    $newContent = str_replace('PLAN_BASIS', 'PLAN_BASIC', $newContent);
    
    if ($content !== $newContent) {
        file_put_contents($file->getPathname(), $newContent);
        echo "Updated: " . $file->getPathname() . "\n";
    }
}

// Rename basis.html to basic.html
$oldPath = 'c:\\xampp\\htdocs\\SpaceBorn\\SpaceBorn\\simulator\\basis.html';
$newPath = 'c:\\xampp\\htdocs\\SpaceBorn\\SpaceBorn\\simulator\\basic.html';
if (file_exists($oldPath)) {
    rename($oldPath, $newPath);
    echo "Renamed $oldPath to $newPath\n";
}

echo "Done\n";
?>
