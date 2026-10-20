<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/bootstrap.php');
require_login();
require_capability('moodle/site:config', context_system::instance());
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/auth/soca/manage.php'));
$PAGE->set_title(get_string('maintenance', 'auth_soca'));
$PAGE->set_heading(get_string('maintenance', 'auth_soca'));
$flow = new \auth_soca\local\flow();
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    try {
        $operation = required_param('operation', PARAM_ALPHA);
        switch ($operation) {
            case 'documents': $flow->checkDocuments(); $message = 'Public documents passed. This is not a real login.'; break;
            case 'test': redirect($flow->begin(true)); break;
            case 'invalidate': $flow->invalidate(); $flow->audit('invalidate'); $message = 'Pending sign-ins invalidated.'; break;
            case 'link': $flow->link(required_param('subject', PARAM_RAW_TRIMMED), required_param('userid', PARAM_INT)); $message = 'Administrator-approved link created.'; break;
            case 'unlink':
                $userid = required_param('userid', PARAM_INT);
                $DB->delete_records('auth_soca_links', ['userid' => $userid]);
                $flow->audit('unlink', $userid); $message = 'Identity link removed.'; break;
        }
    } catch (Throwable $e) { $message = 'SOCA maintenance failed: ' . $e->getMessage(); }
}
echo $OUTPUT->header();
if ($message) { echo $OUTPUT->notification(s($message), 'notifyinfo'); }
try { $status = $flow->status(); } catch (Throwable $e) { $status = ['configuration_error' => $e->getMessage()]; }
echo html_writer::tag('pre', s(json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)));
echo html_writer::tag('p', 'Save a disabled candidate first. Operator tests preserve your session and never create, link or update users.');
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
foreach (['documents' => 'Check public documents', 'test' => 'Start operator test login', 'invalidate' => 'Invalidate pending sign-ins'] as $name => $label) {
    echo html_writer::tag('button', s($label), ['type' => 'submit', 'name' => 'operation', 'value' => $name, 'class' => 'btn btn-secondary m-1']);
}
echo html_writer::tag('h2', 'Approved account association');
echo html_writer::tag('p', 'Independently verify provider identity ownership. Email alone is never approval.');
echo html_writer::tag('label', 'Local account ID', ['for' => 'soca-userid']);
echo html_writer::empty_tag('input', ['type' => 'number', 'name' => 'userid', 'id' => 'soca-userid', 'min' => 1]);
echo html_writer::tag('label', 'Exact provider subject', ['for' => 'soca-subject']);
echo html_writer::empty_tag('input', ['name' => 'subject', 'id' => 'soca-subject', 'maxlength' => 255]);
foreach (['link' => 'Approve link', 'unlink' => 'Remove link'] as $name => $label) {
    echo html_writer::tag('button', $label, ['type' => 'submit', 'name' => 'operation', 'value' => $name, 'class' => 'btn btn-secondary m-1']);
}
echo html_writer::end_tag('form');
echo $OUTPUT->footer();
