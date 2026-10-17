<?php
// Run: composer install && php -S 127.0.0.1:8000 -t public
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use SeoUltra\OpenConsent\Config;
use SeoUltra\OpenConsent\ConsentCookie;
use SeoUltra\OpenConsent\Frontend;
use SeoUltra\OpenConsent\Paths;

// Configuration lives in your app (or a JSON file); invalid values fall back to safe defaults.
$config = Config::fromArray([
    'policyVersion' => '1',
    'links' => ['privacyPolicy' => '/privacy/'],
    'google' => ['enabled' => true, 'tags' => ['G-XXXXXXXXXX']],
]);
foreach ($config->warnings() as $w) {
    error_log('OpenConsent config: ' . $w);
}

// Server side: decide what to RENDER. Never use this for authorization.
$consent = ConsentCookie::read($config);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>OpenConsent + Composer</title>
  <?= Frontend::head($config, '/open-consent.js', Paths::browserBundleIntegrity()) ?>
  <?= Frontend::blockedScript('analytics', "console.log('analytics allowed');") ?>
</head>
<body>
  <h1>OpenConsent + Composer</h1>
  <p>Server sees: <code><?= htmlspecialchars(json_encode($consent->categories()) ?: '', ENT_QUOTES) ?></code>
     (reason: <?= htmlspecialchars($consent->reason(), ENT_QUOTES) ?>)</p>
  <?php if ($consent->allows('marketing')): ?>
    <?= Frontend::blockedIframe('marketing', 'https://www.youtube-nocookie.com/embed/aqz-KE-bpKQ', ['width' => '560', 'height' => '315', 'title' => 'Video']) ?>
  <?php else: ?>
    <p>Marketing not allowed — the video is not even rendered.</p>
  <?php endif; ?>
  <p><?= Frontend::preferencesButton('Cookie settings') ?></p>
</body>
</html>
