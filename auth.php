<?php

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/authlib.php');

class auth_plugin_soca extends auth_plugin_base {
    public function __construct() { $this->authtype = 'soca'; $this->config = get_config('auth_soca'); }
    public function user_login($username, $password) { return false; }
    public function is_internal() { return false; }
    public function can_change_password() { return false; }
    public function prevent_local_passwords() { return true; }
    public function loginpage_idp_list($wantsurl) {
        if (getenv('SOCA_EMERGENCY_DISABLE') && !in_array(strtolower(getenv('SOCA_EMERGENCY_DISABLE')), ['0', 'false'], true)) { return []; }
        return [['url' => new moodle_url('/auth/soca/login.php'), 'name' => get_string('pluginname', 'auth_soca'),
            'iconurl' => new moodle_url('/pix/i/user.svg')]];
    }
}
