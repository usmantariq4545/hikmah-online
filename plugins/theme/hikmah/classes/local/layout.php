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

namespace theme_hikmah\local;

/**
 * Shared template context for the full-bleed Hikmah layouts (home, catalog, course detail, FAQ).
 *
 * @package    theme_hikmah
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class layout {
    /**
     * Highlights the menu item whose address has the given path, and no other. Leaves the menu
     * unchanged when no item has that path.
     *
     * @param array $nodes exported menu items (arrays or objects)
     * @param string $path path of the item to highlight, such as /theme/hikmah/catalog.php
     * @return array
     */
    public static function mark_active(array $nodes, string $path): array {
        $found = false;
        foreach ($nodes as $node) {
            $url = is_array($node) ? ($node['url'] ?? '') : ($node->url ?? '');
            if (parse_url((string) $url, PHP_URL_PATH) === $path) {
                $found = true;
            }
        }
        if (!$found) {
            return $nodes;
        }
        foreach ($nodes as $i => $node) {
            $url = is_array($node) ? ($node['url'] ?? '') : ($node->url ?? '');
            $active = parse_url((string) $url, PHP_URL_PATH) === $path;
            if (is_array($node)) {
                $nodes[$i]['isactive'] = $active;
            } else {
                $nodes[$i]->isactive = $active;
            }
        }
        return $nodes;
    }

    /**
     * Context shared by every Hikmah layout: navigation, user menu and body attributes.
     *
     * @param \moodle_page $page
     * @param \core\output\core_renderer $output
     * @param string[] $extraclasses extra body classes
     * @return array
     */
    public static function base_context(\moodle_page $page, $output, array $extraclasses = []): array {
        global $SITE;

        $primary = new \core\navigation\output\primary($page);
        $primarymenu = $primary->export_for_template($page->get_renderer('core'));

        // A course detail page belongs under "Courses" (the catalog), but Moodle only highlights a
        // menu item whose address matches the page exactly.
        if ($page->url->get_path(false) === '/theme/hikmah/course.php') {
            $primarymenu['moremenu']['nodearray'] = self::mark_active(
                $primarymenu['moremenu']['nodearray'] ?? [],
                '/theme/hikmah/catalog.php'
            );
            $primarymenu['mobileprimarynav'] = self::mark_active(
                $primarymenu['mobileprimarynav'] ?? [],
                '/theme/hikmah/catalog.php'
            );
        }

        return [
            'sitename' => format_string(
                $SITE->shortname,
                true,
                ['context' => \context_course::instance(SITEID), 'escape' => false]
            ),
            'output' => $output,
            'bodyattributes' => $output->body_attributes(array_merge(['hk-layout'], $extraclasses)),
            'primarymoremenu' => $primarymenu['moremenu'],
            'mobileprimarynav' => $primarymenu['mobileprimarynav'],
            'usermenu' => $primarymenu['user'],
            'langmenu' => $primarymenu['lang'],
        ];
    }
}
