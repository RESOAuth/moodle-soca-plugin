<?php
defined('MOODLE_INTERNAL') || die();
function xmldb_auth_soca_uninstall() {
    // Moodle drops plugin tables and configuration. Preserve local user records.
    return true;
}
