<?php

declare(strict_types=1);

namespace auth_soca\local;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../../bootstrap.php');

final class flow extends \RESOAuth\Soca\Bridge\Flow {
    protected function settings(): array {
        $raw = get_config('auth_soca', 'profile');
        return $raw ? json_decode($raw, true, 32, JSON_THROW_ON_ERROR) : [];
    }
    protected function paths(): array {
        global $CFG;
        $base = parse_url($CFG->wwwroot, PHP_URL_PATH) ?: '';
        return ['redirect_uri' => $base . '/auth/soca/callback.php', 'metadata_uri' => $base . '/auth/soca/metadata.php',
            'jwks_uri' => $base . '/auth/soca/jwks.php'];
    }
    protected function siteName(): string { global $SITE; return $SITE->fullname ?: 'Moodle'; }
    public function operator(): int {
        global $USER;
        return !empty($USER->id) && has_capability('moodle/site:config', \context_system::instance()) ? (int) $USER->id : 0;
    }
    protected function putTransaction(string $state, array $transaction): void {
        global $SESSION, $DB;
        $pending = (array) ($SESSION->soca_transactions ?? []);
        $pending = array_filter($pending, fn($t) => ($t['started'] ?? 0) >= time() - 300);
        if (count($pending) >= 5) { throw new \RuntimeException('Too many pending sign-ins. Wait five minutes.'); }
        $DB->delete_records_select('auth_soca_transactions', 'expires < ?', [time()]);
        $hash = hash('sha256', $state);
        $DB->insert_record('auth_soca_transactions', (object) ['statehash' => $hash, 'expires' => time() + 300]);
        $pending[$hash] = $transaction;
        $SESSION->soca_transactions = $pending;
    }
    protected function takeTransaction(string $state): array {
        global $SESSION, $DB;
        $pending = (array) ($SESSION->soca_transactions ?? []);
        $hash = hash('sha256', $state);
        $transaction = $pending[$hash] ?? null;
        if (!$transaction) { throw new \RuntimeException('This browser has no matching sign-in attempt.'); }
        unset($pending[$hash]);
        $SESSION->soca_transactions = $pending;
        // A database lock serialises the delete/check across workers for this state.
        $factory = \core\lock\lock_config::get_lock_factory('auth_soca');
        $lock = $factory->get_lock($hash, 5);
        if (!$lock) { throw new \RuntimeException('Sign-in replay lock unavailable.'); }
        try {
            $row = $DB->get_record('auth_soca_transactions', ['statehash' => $hash]);
            if (!$row || $row->expires < time()) { throw new \RuntimeException('Sign-in expired or already used.'); }
            $DB->delete_records('auth_soca_transactions', ['id' => $row->id]);
        } finally { $lock->release(); }
        return $transaction;
    }
    protected function evidence(string $kind): ?string { return get_config('auth_soca', 'evidence_' . $kind) ?: null; }
    protected function saveEvidence(string $kind, string $revision): void { set_config('evidence_' . $kind, $revision, 'auth_soca'); }
    public function invalidate(): void {
        global $DB, $SESSION;
        $DB->delete_records('auth_soca_transactions');
        unset($SESSION->soca_transactions);
    }
    public function account(array $claims): \stdClass {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/user/lib.php');
        $identity = self::identity($claims);
        $lock = \core\lock\lock_config::get_lock_factory('auth_soca')->get_lock('identity_' . $identity, 5);
        if (!$lock) { throw new \RuntimeException('Account creation lock unavailable.'); }
        try {
            $tx = $DB->start_delegated_transaction();
            $link = $DB->get_record('auth_soca_links', ['identityhash' => $identity]);
            if ($link) { $user = $DB->get_record('user', ['id' => $link->userid]); }
            else {
                if (!$this->configuration()['provisioning']) { throw new \RuntimeException('Administrator-approved account linking is required.'); }
                $profile = self::profile($claims);
                if ($DB->record_exists('user', ['email' => $profile['email'], 'deleted' => 0])) { throw new \RuntimeException('Provisioning requires administrator review.'); }
                $user = (object) ['username' => $profile['username'], 'email' => $profile['email'],
                    'firstname' => $profile['given_name'], 'lastname' => $profile['family_name'],
                    'auth' => 'soca', 'password' => AUTH_PASSWORD_NOT_CACHED, 'confirmed' => 1,
                    'mnethostid' => $CFG->mnet_localhost_id, 'lang' => $CFG->lang];
                $user->id = user_create_user($user, false, true);
                $DB->insert_record('auth_soca_links', (object) ['identityhash' => $identity, 'userid' => $user->id, 'timecreated' => time()]);
                $user = $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
            }
            if (!$user || $user->deleted || $user->suspended || !$user->confirmed || is_siteadmin($user) || isguestuser($user) ||
                has_capability('moodle/site:config', \context_system::instance(), $user)) {
                throw new \RuntimeException('This local account is not eligible for SOCA.');
            }
            $tx->allow_commit();
            return $user;
        } catch (\Throwable $e) {
            if (isset($tx)) { $tx->rollback($e); }
            throw $e;
        } finally { $lock->release(); }
    }
    public function link(string $subject, int $userid): void {
        global $DB;
        if (!$this->operator()) { throw new \RuntimeException('Administrator approval required.'); }
        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        if ($user->deleted || $user->suspended || is_siteadmin($user) || isguestuser($user) ||
                has_capability('moodle/site:config', \context_system::instance(), $user)) { throw new \RuntimeException('Account is ineligible.'); }
        $DB->insert_record('auth_soca_links', (object) ['identityhash' => self::identity(['iss' => $this->configuration()['issuer'], 'sub' => $subject]),
            'userid' => $userid, 'timecreated' => time()]);
        $this->audit('link', $userid);
    }
    public function audit(string $action, int $userid = 0): void {
        global $DB;
        $DB->insert_record('auth_soca_audit', (object) ['actor' => $this->operator(), 'action' => $action,
            'userid' => $userid, 'timecreated' => time()]);
    }
}
