<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../bootstrap.php');
\core\session\manager::set_user(get_admin());
$options = getopt('', ['status', 'check', 'invalidate', 'link:', 'subject:', 'unlink:']);
$flow = new \auth_soca\local\flow();
try {
    if (isset($options['check'])) { $flow->checkDocuments(); }
    if (isset($options['invalidate'])) { $flow->invalidate(); $flow->audit('invalidate'); }
    if (isset($options['link'])) { $flow->link($options['subject'] ?? '', (int) $options['link']); }
    if (isset($options['unlink'])) { $DB->delete_records('auth_soca_links', ['userid' => (int) $options['unlink']]); $flow->audit('unlink', (int) $options['unlink']); }
    echo json_encode($flow->status(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $e) { fwrite(STDERR, $e->getMessage() . PHP_EOL); exit(1); }
