<?php
/** Run only against a disposable installed Moodle site. */
declare(strict_types=1);
define('CLI_SCRIPT', true);
require (getenv('MOODLE_ROOT') ?: '/var/www/html') . '/config.php';
require $CFG->dirroot . '/auth/soca/bootstrap.php';
set_config('profile', json_encode(['origin' => 'https://app.example.com', 'issuer' => 'https://issuer.example.com',
    'allowed_domains' => ['example.com'], 'provisioning' => true]), 'auth_soca');
set_config('auth', 'manual,soca');
$flow = new \auth_soca\local\flow();
$count = 0;
function verify(bool $value, string $label): void { global $count; $count++; if (!$value) { throw new RuntimeException($label); } }
function denied(callable $callback, string $label): void { try { $callback(); } catch (Throwable $e) { verify(true, $label); return; } verify(false, $label); }
$claims = ['iss' => 'https://issuer.example.com', 'sub' => 'test-' . bin2hex(random_bytes(8)),
    'email' => bin2hex(random_bytes(8)) . '@example.com', 'email_verified' => true];
$user = $flow->account($claims);
verify($user->id > 1 && $user->auth === 'soca' && $user->password === AUTH_PASSWORD_NOT_CACHED, 'Native account provisioning without a local password');
verify($flow->account($claims)->id === $user->id, 'Immutable link resolves returning account');
$before = $DB->count_records('user');
denied(fn() => $flow->account(array_replace($claims, ['sub' => 'other-subject'])), 'Email never establishes account ownership');
verify($DB->count_records('user') === $before, 'Failed provisioning leaves no orphan');
$DB->set_field('user', 'suspended', 1, ['id' => $user->id]);
denied(fn() => $flow->account($claims), 'Suspended accounts rejected');
$DB->set_field('user', 'suspended', 0, ['id' => $user->id]);
set_config('profile', json_encode(['origin' => 'https://app.example.com', 'provisioning' => false]), 'auth_soca');
denied(fn() => $flow->account(array_replace($claims, ['sub' => 'unlinked', 'email' => 'new@example.com'])), 'Opt-in provisioning enforced');
$put = new ReflectionMethod($flow, 'putTransaction'); $take = new ReflectionMethod($flow, 'takeTransaction');
$state = bin2hex(random_bytes(32));
$put->invoke($flow, $state, ['started' => time(), 'verifier' => 'native-session-secret']);
$pending = $SESSION->soca_transactions; unset($SESSION->soca_transactions);
denied(fn() => $take->invoke($flow, $state), 'Copied callback lacks browser transaction');
$SESSION->soca_transactions = $pending;
verify($take->invoke($flow, $state)['verifier'] === 'native-session-secret', 'Native replay row consumed under Moodle lock');
denied(fn() => $take->invoke($flow, $state), 'Replayed callback rejected');
verify($DB->count_records('auth_soca_transactions') === 0, 'Replay rows cleaned');
session_id(bin2hex(random_bytes(16)));
complete_user_login($user);
verify($USER->id === $user->id, 'Native Moodle session login');
verify(!get_auth_plugin('soca')->user_login($user->username, 'any-password'), 'Plugin never accepts password authentication');
echo "Moodle native host: $count assertions passed. Browser-cookie rotation remains a separate acceptance test.\n";
