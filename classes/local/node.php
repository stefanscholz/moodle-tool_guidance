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
 * Immutable value object for a single decision-tree node.
 *
 * A node is either a question (with answers pointing to other nodes) or a
 * result (with a list of suggested presets).
 *
 * @package    tool_guidance
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class node {

    /** @var string Question node type. */
    const TYPE_QUESTION = 'question';

    /** @var string Result node type. */
    const TYPE_RESULT = 'result';

    /** @var string Node id. */
    private string $id;

    /** @var string Node type, one of the TYPE_* constants. */
    private string $type;

    /** @var string Language key for the node's prompt/heading. */
    private string $textkey;

    /** @var array List of ['labelkey' => string, 'target' => string] answers. */
    private array $answers;

    /** @var preset[] List of presets for a result node. */
    private array $presets;

    /**
     * Constructor.
     *
     * @param string $id Node id.
     * @param string $type Node type.
     * @param string $textkey Language key for the prompt/heading.
     * @param array $answers Answers (question nodes only).
     * @param preset[] $presets Presets (result nodes only).
     */
    private function __construct(string $id, string $type, string $textkey, array $answers, array $presets) {
        $this->id = $id;
        $this->type = $type;
        $this->textkey = $textkey;
        $this->answers = $answers;
        $this->presets = $presets;
    }

    /**
     * Create a question node.
     *
     * @param string $id Node id.
     * @param string $textkey Language key for the question prompt.
     * @param array $answers List of ['labelkey' => string, 'target' => string].
     * @return self
     */
    public static function question(string $id, string $textkey, array $answers): self {
        return new self($id, self::TYPE_QUESTION, $textkey, $answers, []);
    }

    /**
     * Create a result node.
     *
     * @param string $id Node id.
     * @param string $textkey Language key for the result heading.
     * @param preset[] $presets List of presets.
     * @return self
     */
    public static function result(string $id, string $textkey, array $presets): self {
        return new self($id, self::TYPE_RESULT, $textkey, [], $presets);
    }

    /**
     * @return string Node id.
     */
    public function get_id(): string {
        return $this->id;
    }

    /**
     * @return bool Whether this is a question node.
     */
    public function is_question(): bool {
        return $this->type === self::TYPE_QUESTION;
    }

    /**
     * @return bool Whether this is a result node.
     */
    public function is_result(): bool {
        return $this->type === self::TYPE_RESULT;
    }

    /**
     * @return string Language key for the prompt/heading.
     */
    public function get_textkey(): string {
        return $this->textkey;
    }

    /**
     * @return array List of ['labelkey' => string, 'target' => string].
     */
    public function get_answers(): array {
        return $this->answers;
    }

    /**
     * @return preset[] List of presets.
     */
    public function get_presets(): array {
        return $this->presets;
    }
}
