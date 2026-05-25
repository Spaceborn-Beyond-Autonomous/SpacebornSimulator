<?php
$files = [
    'c:/xampp/htdocs/SpaceBorn/SpaceBorn/simulator/pro.php',
    'c:/xampp/htdocs/SpaceBorn/SpaceBorn/simulator/max.php'
];

$replacement = "               if (res.ok) {\n                  cloudTelemetryUrl = data.publicUrl;\n                  window.cloudTelemetryUrls = window.cloudTelemetryUrls || [];\n                  window.cloudTelemetryUrls.push({ time: new Date().toLocaleTimeString(), url: data.publicUrl });\n                  if (typeof UI !== 'undefined' && UI.toast) UI.toast('✅ Telemetry saved to Cloudflare!');\n                  else alert('✅ Telemetry saved to Cloudflare!');\n               }";

foreach ($files as $file) {
    $content = file_get_contents($file);
    
    // Use regex to match ignoring \r
    $pattern = "/\s*if\s*\(res\.ok\)\s*\{\s*cloudTelemetryUrl\s*=\s*data\.publicUrl;\s*if\s*\(typeof UI !== 'undefined' && UI\.toast\)\s*UI\.toast\('✅ Telemetry saved to Cloudflare!'\);\s*else\s*alert\('✅ Telemetry saved to Cloudflare!'\);\s*\}/s";
    
    if (preg_match($pattern, $content, $matches)) {
        // We only want to replace the first occurrence (which is in triggerCloudSave ideally, but let's just replace all occurrences of this specific block since the other occurrence was already heavily modified or might not match this exact string anymore)
        $content = preg_replace($pattern, "\n" . $replacement, $content, 1);
        file_put_contents($file, $content);
        echo "Patched $file successfully.\n";
    } else {
        echo "Could not find regex match in $file.\n";
    }
}
