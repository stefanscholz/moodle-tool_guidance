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

namespace tool_guidance\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Static source of the decision tree.
 *
 * This is the single swap-point for the chooser. A future implementation will
 * load nodes and presets from the backend/content library; callers depend only
 * on the {@see node} and {@see preset} value objects returned here.
 *
 * @package    tool_guidance
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tree_provider {

    /** @var string Id of the first node in the tree. */
    const START = 'q_goal';

    /**
     * Return the starting node.
     *
     * @return node
     */
    public static function get_start(): node {
        return self::get_node(self::START);
    }

    /**
     * Return a node by id, or null if it does not exist.
     *
     * @param string $id Node id.
     * @return node|null
     */
    public static function get_node(string $id): ?node {
        $nodes = self::nodes();
        return $nodes[$id] ?? null;
    }

    /**
     * Build the static set of nodes keyed by id.
     *
     * @return node[]
     */
    private static function nodes(): array {
        return [
            'q_goal' => node::question('q_goal', 'q_goal', [
                ['labelkey' => 'a_goal_assess', 'explainkey' => 'a_goal_assess_help', 'target' => 'q_assess'],
                ['labelkey' => 'a_goal_discuss', 'explainkey' => 'a_goal_discuss_help', 'target' => 'r_forum'],
                ['labelkey' => 'a_goal_collect', 'explainkey' => 'a_goal_collect_help', 'target' => 'r_assign'],
            ]),

            'q_assess' => node::question('q_assess', 'q_assess', [
                ['labelkey' => 'a_assess_auto', 'explainkey' => 'a_assess_auto_help', 'target' => 'r_quiz'],
                ['labelkey' => 'a_assess_open', 'explainkey' => 'a_assess_open_help', 'target' => 'r_assign'],
            ]),

            'r_quiz' => node::result('r_quiz', 'r_quiz_heading', [
                preset::make('quiz', 'p_quiz_title', 'p_quiz_desc', [
                    ['name' => 'cfg_questions', 'value' => 'cfgv_quiz_questions'],
                    ['name' => 'cfg_attempts', 'value' => 'cfgv_quiz_attempts'],
                    ['name' => 'cfg_grademethod', 'value' => 'cfgv_quiz_grademethod'],
                ]),
                preset::make('quiz', 'p_quiz2_title', 'p_quiz2_desc', [
                    ['name' => 'cfg_questions', 'value' => 'cfgv_quiz2_questions'],
                    ['name' => 'cfg_attempts', 'value' => 'cfgv_quiz2_attempts'],
                    ['name' => 'cfg_grademethod', 'value' => 'cfgv_quiz2_grademethod'],
                ]),
                preset::make('quiz', 'p_quiz3_title', 'p_quiz3_desc', [
                    ['name' => 'cfg_questions', 'value' => 'cfgv_quiz3_questions'],
                    ['name' => 'cfg_attempts', 'value' => 'cfgv_quiz3_attempts'],
                    ['name' => 'cfg_grademethod', 'value' => 'cfgv_quiz3_grademethod'],
                ]),
            ]),

            'r_assign' => node::result('r_assign', 'r_assign_heading', [
                preset::make('assign', 'p_assign_title', 'p_assign_desc', [
                    ['name' => 'cfg_submissiontypes', 'value' => 'cfgv_assign_submission'],
                    ['name' => 'cfg_duedate', 'value' => 'cfgv_assign_due'],
                    ['name' => 'cfg_grade', 'value' => 'cfgv_assign_grade'],
                ]),
                preset::make('assign', 'p_assign2_title', 'p_assign2_desc', [
                    ['name' => 'cfg_submissiontypes', 'value' => 'cfgv_assign2_submission'],
                    ['name' => 'cfg_duedate', 'value' => 'cfgv_assign2_due'],
                    ['name' => 'cfg_grade', 'value' => 'cfgv_assign2_grade'],
                ]),
            ]),

            'r_forum' => node::result('r_forum', 'r_forum_heading', [
                preset::make('forum', 'p_forum_title', 'p_forum_desc', [
                    ['name' => 'cfg_forumtype', 'value' => 'cfgv_forum_type'],
                    ['name' => 'cfg_subscription', 'value' => 'cfgv_forum_sub'],
                    ['name' => 'cfg_grading', 'value' => 'cfgv_forum_grading'],
                ]),
                preset::make('forum', 'p_forum2_title', 'p_forum2_desc', [
                    ['name' => 'cfg_forumtype', 'value' => 'cfgv_forum2_type'],
                    ['name' => 'cfg_subscription', 'value' => 'cfgv_forum2_sub'],
                    ['name' => 'cfg_grading', 'value' => 'cfgv_forum2_grading'],
                ]),
                preset::make('forum', 'p_forum3_title', 'p_forum3_desc', [
                    ['name' => 'cfg_forumtype', 'value' => 'cfgv_forum3_type'],
                    ['name' => 'cfg_subscription', 'value' => 'cfgv_forum3_sub'],
                    ['name' => 'cfg_grading', 'value' => 'cfgv_forum3_grading'],
                ]),
            ]),
        ];
    }
}
