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
 * Business logic for tool_guidance graphs.
 *
 * @package    tool_guidance
 * @copyright  2026 Lily Asshauer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guidance;

/**
 * Central place for graph mutations that need rules beyond single-record validation.
 */
class api {
    /** @var string Plugin config name holding the site-wide chooser entry node id. */
    const CHOOSER_ENTRY_CONFIG = 'chooserentrynodeid';

    /**
     * The single node the "Help me choose" chooser starts from, if set.
     *
     * @return node|null
     */
    public static function get_chooser_entry_node(): ?node {
        $id = (int) get_config('tool_guidance', self::CHOOSER_ENTRY_CONFIG);
        if (!$id) {
            return null;
        }
        return node::get_record(['id' => $id]) ?: null;
    }

    /**
     * Make a node the site-wide chooser entry. The entry must be a root, so this
     * also flags it as one.
     *
     * @param int $nodeid
     * @return void
     */
    public static function set_chooser_entry(int $nodeid): void {
        $node = node::get_record(['id' => $nodeid]);
        if (!$node) {
            return;
        }
        if (!$node->get('isroot')) {
            $node->set('isroot', 1);
            $node->update();
        }
        set_config(self::CHOOSER_ENTRY_CONFIG, $nodeid, 'tool_guidance');
    }

    /**
     * Clear the chooser entry if it points at the given node.
     *
     * @param int $nodeid
     * @return void
     */
    public static function clear_chooser_entry_if(int $nodeid): void {
        if ((int) get_config('tool_guidance', self::CHOOSER_ENTRY_CONFIG) === $nodeid) {
            unset_config(self::CHOOSER_ENTRY_CONFIG, 'tool_guidance');
        }
    }

    /**
     * Ensure the site has one initial graph with a root node wired up as the
     * chooser entry. A no-op once any graph exists.
     *
     * @return void
     */
    public static function ensure_default_graph(): void {
        if (graph::count_records() > 0) {
            return;
        }
        $graph = new graph(0, (object) [
            'name' => get_string('defaultgraphname', 'tool_guidance'),
            'description' => '',
            'descriptionformat' => FORMAT_HTML,
            'enabled' => 1,
        ]);
        $graph->create();

        $node = new node(0, (object) [
            'graphid' => $graph->get('id'),
            'type' => node::TYPE_QUESTION,
            'title' => get_string('defaultrootquestion', 'tool_guidance'),
            'description' => '',
            'descriptionformat' => FORMAT_HTML,
            'isroot' => 1,
            'posx' => 60,
            'posy' => 60,
        ]);
        $node->create();

        self::set_chooser_entry((int) $node->get('id'));
    }

    /**
     * Would adding the edge parent -> child create a cycle in the graph?
     *
     * A cycle is created when the child can already reach the parent through
     * existing links (i.e. parent is a descendant of child).
     *
     * @param int $graphid
     * @param int $parentnodeid
     * @param int $childnodeid
     * @return bool
     */
    public static function would_create_cycle(int $graphid, int $parentnodeid, int $childnodeid): bool {
        if ($parentnodeid === $childnodeid) {
            return true;
        }

        // Build adjacency list (parent => [children]) from existing links.
        // Dangling answers (no child) contribute no edge.
        $adjacency = [];
        foreach (link::get_records(['graphid' => $graphid]) as $existing) {
            $child = (int) $existing->get('childnodeid');
            if ($child) {
                $adjacency[(int) $existing->get('parentnodeid')][] = $child;
            }
        }

        // Breadth-first search downward from the prospective child.
        $queue = [$childnodeid];
        $seen = [$childnodeid => true];
        while ($queue) {
            $current = array_shift($queue);
            if ($current === $parentnodeid) {
                return true;
            }
            foreach ($adjacency[$current] ?? [] as $next) {
                if (empty($seen[$next])) {
                    $seen[$next] = true;
                    $queue[] = $next;
                }
            }
        }
        return false;
    }

    /**
     * Create an answer link after enforcing the no-cycle rule.
     *
     * Per-field rules (parent is a question, same graph, no self-link) are
     * enforced by the link persistent on save.
     *
     * @param \stdClass $data Properties for the link persistent.
     * @return link
     * @throws \moodle_exception When the link would create a cycle.
     */
    public static function create_link(\stdClass $data): link {
        $child = (int) ($data->childnodeid ?? 0);
        if ($child && self::would_create_cycle((int) $data->graphid, (int) $data->parentnodeid, $child)) {
            throw new \moodle_exception('error:cycle', 'tool_guidance');
        }
        $data->childnodeid = $child ?: null;
        $link = new link(0, $data);
        $link->create();
        return $link;
    }

    /**
     * Point an existing answer at a child node, or clear it (dangling).
     *
     * @param link $link
     * @param int $childnodeid Child node id, or 0 to leave the answer dangling.
     * @return link
     * @throws \moodle_exception When the new child would create a cycle.
     */
    public static function set_answer_child(link $link, int $childnodeid): link {
        if ($childnodeid && self::would_create_cycle(
            (int) $link->get('graphid'),
            (int) $link->get('parentnodeid'),
            $childnodeid
        )) {
            throw new \moodle_exception('error:cycle', 'tool_guidance');
        }
        $link->set('childnodeid', $childnodeid ?: null);
        $link->update();
        return $link;
    }

    /**
     * Delete a node together with all links touching it.
     *
     * If the node is the site chooser entry, that pointer is cleared.
     *
     * @param node $node
     * @return void
     */
    public static function delete_node(node $node): void {
        global $DB;
        $nodeid = (int) $node->get('id');

        // Answers belonging to this question disappear with it.
        $DB->delete_records('tool_guidance_link', ['parentnodeid' => $nodeid]);
        // Answers that pointed at this node become dangling rather than vanish.
        $DB->set_field('tool_guidance_link', 'childnodeid', null, ['childnodeid' => $nodeid]);

        self::clear_chooser_entry_if($nodeid);

        $node->delete();
    }

    /**
     * Delete a whole graph and all of its nodes and links.
     *
     * @param graph $graph
     * @return void
     */
    public static function delete_graph(graph $graph): void {
        global $DB;
        $graphid = (int) $graph->get('id');
        $DB->delete_records('tool_guidance_link', ['graphid' => $graphid]);
        $DB->delete_records('tool_guidance_node', ['graphid' => $graphid]);
        $graph->delete();
    }

    /**
     * Outgoing answer links of a node, ordered for display.
     *
     * @param int $nodeid
     * @return link[]
     */
    public static function get_child_links(int $nodeid): array {
        return link::get_records(['parentnodeid' => $nodeid], 'sortorder, id');
    }
}
