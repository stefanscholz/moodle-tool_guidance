<?php
// This file is part of Moodle - http://moodle.org/
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
 * List of guidance activity presets.
 *
 * @package    guidanceaddon_preset
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace guidanceaddon_preset\table;

defined('MOODLE_INTERNAL') || die();

use guidanceaddon_preset\local\preset_manager;
use html_writer;
use moodle_url;

require_once($GLOBALS['CFG']->libdir . '/tablelib.php');

/**
 * Presets list with visibility toggles and reordering.
 */
class preset_list extends \table_sql {

    /** @var int Helper counter for showing up/down arrows. */
    protected $updowncount = 1;

    /** @var int Total number of presets. */
    public $count = 0;

    /** @var moodle_url Base management URL. */
    protected $manageurl;

    /**
     * Constructor.
     *
     * @param string $uniqueid Unique table id.
     */
    public function __construct($uniqueid) {
        parent::__construct($uniqueid);

        $this->manageurl = new moodle_url('/admin/tool/guidance/addon/preset/manage.php');

        $columns = ['title', 'shortname', 'modname', 'backupfile', 'status', 'sortorder', 'action'];
        $headers = [
            get_string('title', 'guidanceaddon_preset'),
            get_string('shortname', 'guidanceaddon_preset'),
            get_string('modname', 'guidanceaddon_preset'),
            get_string('backupfile', 'guidanceaddon_preset'),
            get_string('status', 'guidanceaddon_preset'),
            get_string('presetorder', 'guidanceaddon_preset'),
            get_string('action'),
        ];

        $this->define_columns($columns);
        $this->define_headers($headers);
        $this->no_sorting('action');
        $this->no_sorting('backupfile');

        $this->sort_default_column = 'sortorder';
        $this->sort_default_order = SORT_ASC;

        $this->baseurl = $this->manageurl;
    }

    /**
     * Record the total count so the last row hides its "down" arrow.
     *
     * @param int $pagesize
     * @param bool $useinitialsbar
     * @return void
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        global $DB;
        parent::query_db($pagesize, $useinitialsbar);
        $this->count = $DB->count_records_sql($this->countsql, $this->countparams);
    }

    /**
     * Short name column.
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_shortname($row): string {
        return html_writer::span(s($row->shortname), 'guidancepreset-shortname');
    }

    /**
     * Activity type column.
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_modname($row): string {
        return $row->modname ? s($row->modname) : '-';
    }

    /**
     * Backup file download column.
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_backupfile($row): string {
        $file = preset_manager::get_backup_file((int) $row->id);
        if (!$file) {
            return '-';
        }
        $url = moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename(),
            true
        );
        return html_writer::link($url, $file->get_filename());
    }

    /**
     * Status toggle column.
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_status($row): string {
        if ($row->status) {
            $url = new moodle_url($this->manageurl, ['action' => 'disable', 'id' => $row->id, 'sesskey' => sesskey()]);
            return html_writer::link($url, html_writer::span(
                get_string('enabledbadge', 'guidanceaddon_preset'),
                'badge badge-success'
            ));
        }
        $url = new moodle_url($this->manageurl, ['action' => 'enable', 'id' => $row->id, 'sesskey' => sesskey()]);
        return html_writer::link($url, html_writer::span(
            get_string('disabledbadge', 'guidanceaddon_preset'),
            'badge badge-danger'
        ));
    }

    /**
     * Reorder column with up/down arrows.
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_sortorder($row): string {
        global $OUTPUT;
        $updown = html_writer::start_span('guidancepreset-order');

        if ($this->updowncount > 1) {
            $url = new moodle_url($this->manageurl, ['action' => 'up', 'id' => $row->id, 'sesskey' => sesskey()]);
            $updown .= html_writer::link($url, $OUTPUT->pix_icon('t/up', get_string('moveup')));
        } else {
            $updown .= $OUTPUT->spacer();
        }

        if ($this->updowncount < $this->count) {
            $url = new moodle_url($this->manageurl, ['action' => 'down', 'id' => $row->id, 'sesskey' => sesskey()]);
            $updown .= html_writer::link($url, $OUTPUT->pix_icon('t/down', get_string('movedown')));
        } else {
            $updown .= $OUTPUT->spacer();
        }
        $updown .= html_writer::end_span();

        $this->updowncount++;
        return $updown;
    }

    /**
     * Edit / delete actions column.
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_action($row): string {
        global $OUTPUT;

        $editurl = new moodle_url($this->manageurl, ['action' => 'edit', 'id' => $row->id]);
        $html = $OUTPUT->action_icon($editurl, new \pix_icon('t/edit', get_string('editpreset', 'guidanceaddon_preset')));

        $deleteurl = new moodle_url($this->manageurl, ['action' => 'delete', 'id' => $row->id, 'sesskey' => sesskey()]);
        $confirm = new \confirm_action(get_string('confirmdeletepreset', 'guidanceaddon_preset'));
        $html .= $OUTPUT->action_icon(
            $deleteurl,
            new \pix_icon('t/delete', get_string('deletepreset', 'guidanceaddon_preset')),
            $confirm
        );

        return $html;
    }
}
