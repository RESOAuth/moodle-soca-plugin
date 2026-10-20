<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/bootstrap.php');
try {
    if (!is_enabled_auth('soca')) { throw new RuntimeException('Authentication plugin disabled.'); }
    redirect((new \auth_soca\local\flow())->begin());
} catch (Throwable $e) { throw new moodle_exception('signinfailed', 'auth_soca'); }
