<?php
namespace auth_soca\local;
defined('MOODLE_INTERNAL') || die();

final class profile_setting extends \admin_setting_configtextarea {
    public function validate($data) {
        try {
            $config = json_decode($data, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($config) || array_is_list($config) || isset($config['private_key_pem'])) { return 'Use a JSON object without private keys.'; }
            \RESOAuth\Soca\Policy::validate(array_replace(\RESOAuth\Soca\Bridge\Flow::defaults(), $config));
            if (!empty($config['enabled'])) {
                $previous = json_decode(get_config('auth_soca', 'profile') ?: '{}', true);
                $candidate = $config; unset($candidate['enabled'], $previous['enabled']);
                $status = (new flow())->status();
                if ($candidate !== $previous || !$status['documents_checked'] || !$status['test_login_passed']) {
                    return 'Save this candidate disabled, check its public documents and complete an operator test before enabling.';
                }
            }
            return true;
        } catch (\Throwable $e) { return 'Invalid SOCA configuration: ' . $e->getMessage(); }
    }
    public function write_setting($data) {
        if (getenv('SOCA_CONFIG_FILE')) { return 'SOCA is deployment managed.'; }
        $result = parent::write_setting($data);
        if ($result === '') { $flow = new flow(); $flow->invalidate(); $flow->audit('configuration'); }
        return $result;
    }
}
