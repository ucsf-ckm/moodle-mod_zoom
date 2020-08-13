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
 * Load zoom meeting recording and add a record of the view.
 *
 * @package    mod_zoom
 * @copyright  2020 UC Regents
 * @author     Kubilay Agi <kubilay.agi@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/moodlelib.php');
require_once(__DIR__ . '/locallib.php');

$id = required_param('id', PARAM_INT);
$meetinguuid = required_param('meetinguuid', PARAM_TEXT);
$recordingstart = required_param('recordingstart', PARAM_INT);

// Find the video recording and audio only recording pair that matches the criteria.
$recordings = $DB->get_records('zoom_meeting_recordings', array('meetinguuid' => $meetinguuid, 'recordingstart' => $recordingstart));
if (empty($recordings)) {
    print_error('Recordings could not be found');
}

$context = context_module::instance($id);
$PAGE->set_context($context);
require_capability('mod/zoom:addinstance', $context);

$now = time();

// Toggle the showrecording value.
foreach ($recordings as $rec) {
    $rec->showrecording = intval($rec->showrecording) === 0 ? 1 : 0;
    $rec->timemodified = $now;
    $DB->update_record('zoom_meeting_recordings', $rec);
}

$urlparams = array('id' => $id);
$url = new moodle_url('/mod/zoom/recordings.php', $urlparams);

redirect($url);
