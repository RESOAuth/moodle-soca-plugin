<?php
namespace auth_soca\local;
defined('MOODLE_INTERNAL') || die();
final class observer {
    public static function user_deleted(\core\event\user_deleted $event): void {
        global $DB;
        $DB->delete_records('auth_soca_links', ['userid' => $event->objectid]);
    }
}
