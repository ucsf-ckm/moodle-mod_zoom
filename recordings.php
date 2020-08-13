<?php
// This file is part of the Zoom plugin for Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Adding, updating, and deleting zoom meeting recordings.
 *
 * @package    mod_zoom
 * @copyright  2020 UC Regents
 * @author     Kubilay Agi <kubilay.agi@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/locallib.php');

$id = required_param('id', PARAM_INT);

list($course, $cm, $zoom) = zoom_get_instance_setup();

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
// Set up the page.
$params = array('id' => $cm->id);
$url = new moodle_url('/mod/zoom/recordings.php', $params);
$PAGE->set_url($url);

$strname = $zoom->name;
$PAGE->set_title("$course->shortname: $strname");
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();
echo $OUTPUT->heading($strname);

$iszoommanager = has_capability('mod/zoom:addinstance', $context);

// Set up html table.
$table = new html_table();
$table->attributes['class'] = 'generaltable mod_view';
if ($iszoommanager) {
    $table->align = array('left', 'left', 'left', 'left');
    $table->head = array(
        get_string('recordingdate', 'mod_zoom'),
        get_string('recordinglink', 'mod_zoom'),
        get_string('recordingpasscode', 'mod_zoom'),
        get_string('recordingshowtoggle', 'mod_zoom')
    );
} else {
    $table->align = array('left', 'left', 'left');
    $table->head = array(get_string('recordingdate', 'mod_zoom'), get_string('recordinglink', 'mod_zoom'), get_string('recordingpasscode', 'mod_zoom'));
}

$service = new mod_zoom_webservice();

$now = time();
if ($zoom->recurring || $now > (intval($zoom->start_time) + intval($zoom->duration))) {
    // Find all entries for this meeting in the database
    $recordings = $DB->get_records('zoom_meeting_recordings', array('zoomid' => $zoom->id), 'timecreated ASC');
    foreach ($recordings as $key => $recording) {
        unset($recordings[$key]);
        $recordings[$recording->zoomrecordingid] = $recording;
    }
    $recordinghtml = null;

    if ($iszoommanager) {
        // Since the recordings don't show by default, there's no point in retrieving them when the students view the page.
        $zoomrecordingpairlist = $service->get_recording_url_list($zoom->meeting_id);
        foreach ($zoomrecordingpairlist as $recordingstarttime => $zoomrecordingpair) {
            // The video recording and audio only recordings are grouped together by their recording start timestamp.
            // So far, the timestamp and the meeting uuid seem to be the best way to group the video recording and audio only recording together.
            foreach ($zoomrecordingpair as $zoomrecordinginfo) {
                if (array_key_exists($zoomrecordinginfo->recordingid, $recordings)) {
                    continue;
                }
                $rec = new stdClass();
                $rec->zoomid = $zoom->id;
                $rec->meetinguuid = $zoomrecordinginfo->meetinguuid;
                $rec->zoomrecordingid = $zoomrecordinginfo->recordingid;
                $rec->name = $zoom->name . ' (' . $zoomrecordinginfo->recordingtype . ')';
                $rec->externalurl = $zoomrecordinginfo->url;
                $rec->passcode = $zoomrecordinginfo->passcode;
                $rec->recordingtype = $zoomrecordinginfo->recordingtype;
                $rec->recordingstart = $recordingstarttime;
                $rec->timecreated = $now;
                $rec->timemodified = $now;
                $rec->id = $DB->insert_record('zoom_meeting_recordings', $rec);
                $recordings[] = $rec;
            }
        }
    }

    // Populate the page with the links in table format
    $grouping = 1;
    foreach ($recordings as $recording) {
        if ($iszoommanager || intval($recording->showrecording) === 1) {
            // If the current user is a zoom admin, then show the table entries for all recordings.
            // Otherwise, just show the ones that are allowed to be visible to students.
            $recordingurl = new moodle_url('/mod/zoom/loadrecording.php', array('id' => $cm->id, 'recordingid' => $recording->id));
            $recordinglink = html_writer::link($recordingurl, $recording->name);
            $recordinglinkhtml = html_writer::span($recordinglink, 'recording-link', array('style' => 'margin-right:1rem'));
            $recordinghtml .= html_writer::div($recordinglinkhtml, 'recording', array('style' => 'margin-bottom:.5rem'));
            $recordingpasscode = $recording->passcode;
            $recordingshowhtml = null;
            if ($iszoommanager) {
                // If the user is a zoom admin, show the button to toggle whether students can see the recording or not.
                $recordingshowurl = new moodle_url('/mod/zoom/showrecording.php', array(
                    'id' => $cm->id,
                    'meetinguuid' => $recording->meetinguuid,
                    'recordingstart' => $recording->recordingstart)
                );
                $recordingshowtext = intval($recording->showrecording) === 0 ? get_string('recordingshow', 'mod_zoom') : get_string('recordinghide', 'mod_zoom');
                $recordingshowbutton = html_writer::div($recordingshowtext, 'btn btn-primary');
                $recordingshowbuttonhtml = html_writer::link($recordingshowurl, $recordingshowbutton);
                $recordingshowhtml = html_writer::div($recordingshowbuttonhtml);
            }
            // TODO: there's definitely a better, safer way to do this. It is here as a placeholder.
            // Group the video recording and audio only recording together.
            if ($grouping % 2 == 0) {
                if ($iszoommanager) {
                    $table->data[] = array(date('F j, Y, g:i:s a \P\T', $recording->recordingstart), $recordinghtml, $recordingpasscode, $recordingshowhtml);
                } else {
                    $table->data[] = array(date('F j, Y, g:i:s a \P\T', $recording->recordingstart), $recordinghtml, $recordingpasscode);
                }
                $recordinghtml = null;
            }
            $grouping++;
        }
    }
}

echo html_writer::table($table);

echo $OUTPUT->footer();