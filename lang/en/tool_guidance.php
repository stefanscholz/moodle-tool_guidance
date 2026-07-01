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
 * Language strings for tool_guidance.
 *
 * @package    tool_guidance
 * @copyright  2026 Lily Asshauer, bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Guidance';

// Capabilities.
$string['guidance:manage'] = 'Manage guidance graphs';
$string['guidance:view'] = 'Use the guidance activity chooser';

// Admin pages.
$string['managegraphs'] = 'Manage guidance graphs';
$string['graphs'] = 'Guidance graphs';
$string['nographs'] = 'No guidance graphs yet.';
$string['addgraph'] = 'Add graph';
$string['editgraph'] = 'Edit graph';
$string['deletegraph'] = 'Delete graph';
$string['confirmdeletegraph'] = 'Delete the graph "{$a}" and all of its nodes and answers? This cannot be undone.';
$string['backtographs'] = 'Back to graphs';

// Graph fields.
$string['graphname'] = 'Name';
$string['graphidnumber'] = 'ID number';
$string['graphidnumber_help'] = 'An optional machine-readable key used to reference this graph from code or imports.';
$string['graphdescription'] = 'Description';
$string['graphenabled'] = 'Enabled';

// Node management.
$string['managenodesfor'] = 'Nodes: {$a}';
$string['nodes'] = 'Nodes';
$string['nonodes'] = 'This graph has no nodes yet.';
$string['addnode'] = 'Add node';
$string['editnode'] = 'Edit node';
$string['deletenode'] = 'Delete node';
$string['confirmdeletenode'] = 'Delete the node "{$a}" and every answer pointing to or from it?';
$string['rootnode'] = 'Entry node';
$string['setrootnode'] = 'Set as entry node';
$string['isrootnode'] = 'Entry node';
$string['noroot'] = 'No entry node set.';

// Node fields.
$string['nodetype'] = 'Type';
$string['nodetype:question'] = 'Question';
$string['nodetype:leaf'] = 'Leaf (recommendation)';
$string['nodetitle'] = 'Title / question';
$string['nodedescription'] = 'Description';
$string['nodetargettype'] = 'Target type';

// Canvas editor.
$string['addquestion'] = 'Add question';
$string['addleaf'] = 'Add leaf';
$string['answerlabeldefault'] = 'Answer';
$string['createnodehere'] = 'Create here';
$string['editorhint'] = 'Drag from a question\'s bottom dot to drop an answer: onto a node to link it, or onto empty space to leave it dangling. Drag an answer\'s dot to point it at a node (or empty space to create one). Drag the background to pan.';
$string['untitlednode'] = '(untitled)';
$string['confirmdeletenodejs'] = 'Delete this node and every answer touching it?';

// Answer links.
$string['answers'] = 'Answers';
$string['noanswers'] = 'No answers from this question yet.';
$string['addanswer'] = 'Add answer';
$string['editanswer'] = 'Edit answer';
$string['deleteanswer'] = 'Delete answer';
$string['confirmdeleteanswer'] = 'Delete the answer "{$a}"?';
$string['answerlabel'] = 'Answer';
$string['answerchild'] = 'Leads to node';
$string['sortorder'] = 'Order';

// Targets.
$string['target:activity'] = 'Create an activity';
$string['target:activity:action'] = 'Add this activity';
$string['target:activity:modname'] = 'Activity type';
$string['target:route'] = 'Internal page';
$string['target:route:action'] = 'Open page';
$string['target:route:path'] = 'Moodle path';
$string['target:route:path_help'] = 'A site-relative path beginning with a slash, e.g. /admin/settings.php?section=modsettingurl';
$string['target:url'] = 'External link';
$string['target:url:action'] = 'Open link';
$string['target:url:url'] = 'URL';
$string['target:url:newwindow'] = 'Open in a new window';

// Errors.
$string['error:leafneedstarget'] = 'A leaf node must have a target type.';
$string['error:unknowntargettype'] = 'Unknown target type: {$a}';
$string['error:questionhastarget'] = 'A question node cannot have a target.';
$string['error:invalidtargetconfig'] = 'The target configuration is not valid JSON.';
$string['error:nodenotfound'] = 'Referenced node does not exist.';
$string['error:parentnotquestion'] = 'Answers can only start from a question node.';
$string['error:crossgraphlink'] = 'Both nodes of an answer must belong to the same graph.';
$string['error:selflink'] = 'A node cannot link to itself.';
$string['error:cycle'] = 'This answer would create a loop in the graph.';
$string['error:activitymodnamerequired'] = 'Choose an activity type.';
$string['error:activitymodnameunknown'] = 'Unknown activity type: {$a}';
$string['error:routepathrequired'] = 'Enter a Moodle path.';
$string['error:routepathrelative'] = 'The path must be site-relative and start with a slash.';
$string['error:urlrequired'] = 'Enter a URL.';
$string['error:urlinvalid'] = 'Enter a valid http(s) URL.';

// Privacy.
$string['privacy:metadata:usermodified'] = 'The user who last created or modified this record.';
$string['privacy:metadata:timecreated'] = 'The time the record was created.';
$string['privacy:metadata:timemodified'] = 'The time the record was last modified.';
$string['privacy:metadata:tool_guidance_graph'] = 'Guidance graph definitions, including which admin last edited each graph.';
$string['privacy:metadata:tool_guidance_node'] = 'Guidance node definitions, including which admin last edited each node.';
$string['privacy:metadata:tool_guidance_link'] = 'Guidance answer links, including which admin last edited each link.';

// Activity chooser ("plus" menu) entry point.
$string['choosebutton'] = 'Help me choose…';

// Chooser shell.
$string['choosertitle'] = 'Help me choose';
$string['chooserintro'] = 'Answer a few questions and we will suggest an activity, set up for your goal.';
$string['startover'] = 'Start over';
$string['chooserunavailable'] = 'No guidance tree has been set up for this course yet.';
$string['suggestedpresets'] = 'Suggested activities';
$string['usepreset'] = 'Use this template';
$string['showconfig'] = 'Show configuration';
$string['usepresetnote'] = 'Creating an activity from a template will be available once the content library is connected.';
