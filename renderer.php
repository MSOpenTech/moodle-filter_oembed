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
 * Renderer for oembed filter.
 * @author    gthomas2
 * @copyright Copyright (c) 2016 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class filter_oembed_renderer extends plugin_renderer_base {

    /**
     * Pre loader HTML.
     *
     * @param string $embedhtml
     * @param array $json
     * @return string
     */
    public function preload($embedhtml, array $json) {
        $data = (object) $json;
        $data->embedhtml = $embedhtml; // Has some extra processing to what is available in $json['html'].
        return $this->render_from_template('filter_oembed/preload', $data);
    }
}
