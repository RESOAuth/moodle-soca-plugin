<?php

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/bootstrap.php');
require_once(__DIR__ . '/classes/local/profile_setting.php');
if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading('auth_soca/intro', get_string('pluginname', 'auth_soca'),
        get_string('auth_socadescription', 'auth_soca')));
    if (getenv('SOCA_CONFIG_FILE')) {
        $settings->add(new admin_setting_heading('auth_soca/locked', get_string('deploymentlocked', 'auth_soca'),
            html_writer::tag('pre', s(json_encode(\RESOAuth\Soca\Bridge\Flow::deployment(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)))));
    } else {
        $settings->add(new \auth_soca\local\profile_setting('auth_soca/profile', get_string('profilejson', 'auth_soca'),
            get_string('profilehelp', 'auth_soca'), json_encode(\RESOAuth\Soca\Bridge\Flow::defaults(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PARAM_RAW, 80, 20));
    }
    $settings->add(new admin_setting_heading('auth_soca/manage', get_string('maintenance', 'auth_soca'),
        html_writer::link(new moodle_url('/auth/soca/manage.php'), get_string('maintenance', 'auth_soca'))));
}
