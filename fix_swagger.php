<?php
$files = ['app/Http/Controllers/Api/AuthController.php', 'app/Http/Controllers/Api/CampaignController.php'];
foreach ($files as $f) {
    $c = file_get_contents($f);
    $c = preg_replace('/#\[OA\\\\[^\]]+\]\s+/', '', $c);
    file_put_contents($f, $c);
}
echo "Done\n";
