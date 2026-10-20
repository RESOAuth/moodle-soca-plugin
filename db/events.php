<?php
defined('MOODLE_INTERNAL') || die();
$observers = [['eventname' => '\core\event\user_deleted', 'callback' => '\auth_soca\local\observer::user_deleted']];
