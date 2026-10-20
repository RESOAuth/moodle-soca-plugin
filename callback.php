<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/bootstrap.php');
try {
    $flow = new \auth_soca\local\flow();
    if (!is_enabled_auth('soca')) { $flow->invalidate(); throw new RuntimeException('Authentication plugin disabled.'); }
    $claims = $flow->finish($_GET);
    if ($claims === null) { redirect(new moodle_url('/auth/soca/manage.php')); }
    $user = $flow->account($claims);
    complete_user_login($user);
    redirect(new moodle_url('/my/'));
} catch (Throwable $e) { throw new moodle_exception('signinfailed', 'auth_soca'); }
