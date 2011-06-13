<?php
/**
 * Allows for arbitrarily adding resources or activities to extra (non-standard) course sections with instance
 * configuration for the block title.
 *
 * NOTE: Code modified from Moodle site_main_menu block.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    base
 * @subpackage blocks-side_bar
 * @see        blocks-site_main_menu
 * @author     Justin Filip <jfilip@remote-learner.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2011 Justin Filip
 *
 */

class block_side_bar extends block_list {

    function init() {
        global $CFG;

        $this->title   = get_string('blockname', 'block_side_bar');
        $this->version = 2011061200;

        // Make sure the global section start value is set.
        if (!isset($CFG->block_side_bar_section_start)) {
            set_config('block_side_bar_section_start', 1000);
        }
    }

    function get_content() {
        global $USER, $CFG;

        if ($this->content !== NULL) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->items  = array();
        $this->content->icons  = array();
        $this->content->footer = '';

        if (empty($this->instance)) {
            return $this->content;
        }

        if (!isset($this->config->title)) {
            $this->config->title = '';
        }

        $course = get_record('course', 'id', $this->instance->pageid);

        if ($course->id == SITEID) {
            $context = get_context_instance(CONTEXT_SYSTEM);
        } else {
            $context = get_context_instance(CONTEXT_COURSE, $course->id);
        }

        $isteacher = (has_capability('moodle/legacy:teacher', $context, $USER->id, false) ||
                      has_capability('moodle/legacy:editingteacher', $context, $USER->id, false) ||
                      has_capability('moodle/legacy:admin', $context, $USER->id, false));
        $isediting = isediting($course->id);
        $ismoving  = ismoving($course->id);

        $section_start = $CFG->block_side_bar_section_start;

        // Create a new section for this block (if necessary).
        if (empty($this->config->section)) {
            $sql = "SELECT MAX(section) as sectionid
                    FROM {$CFG->prefix}course_sections
                    WHERE course = {$this->instance->pageid}";

            $rec = get_record_sql($sql);

            $sectionnum = $rec->sectionid;

            if ($sectionnum < $section_start) {
                $sectionnum = $section_start;
            } else {
                $sectionnum++;
            }

            $section = new stdClass;
            $section->course   = $course->id;
            $section->section  = $sectionnum;
            $section->summary  = '';
            $section->sequence = '';
            $section->visible  = 1;
            $section->id = insert_record('course_sections', $section);

            if (empty($section->id)) {
                if ($course->id == SITEID) {
                    $link = $CFG->wwwroot.'/';
                } else {
                    $link = $CFG->wwwroot.'/course/view.php?id='.$course->id;
                }

                print_error('error_couldnotaddsection', 'block_side_bar', $link);
            }

            // Store the section number and ID of the DB record for that section.
            $this->config->section    = $section->section;
            $this->config->section_id = $section->id;
            parent::instance_config_commit();

        } else {
            if (empty($this->config->section_id)) {
                $section = get_record('course_sections', 'course', $course->id, 'section', $this->config->section);

                $this->config->section_id = $section->id;
                parent::instance_config_commit();
            } else {
                $section = get_record('course_sections', 'id', $this->config->section_id);
            }

            // Double check that the section number hasn't been modified by something else.
            // Fixes problem found by Charlotte Owen when moving 'center column' course sections.
            if ($section->section != $this->config->section) {
                $section->section = $this->config->section;

                update_record('course_sections', $section);
            }
        }

        if (!empty($section) || $isediting) {
            get_all_mods($course->id, $mods, $modnames, $modnamesplural, $modnamesused);
        }

        $groupbuttons     = $course->groupmode;
        $groupbuttonslink = !$course->groupmodeforce;

        if ($ismoving) {
            $strmovehere          = get_string('movehere');
            $strmovefull          = strip_tags(get_string('movefull', '', "'$USER->activitycopyname'"));
            $strcancel            = get_string('cancel');
        }

        $modinfo     = unserialize($course->modinfo);
        $editbuttons = '';

        if ($ismoving) {
            $this->content->icons[] = '&nbsp;<img align="bottom" src="'.$CFG->pixpath.'/t/move.gif" height="11" ' .
                                      'width="11" alt="" />';
            $this->content->items[] = $USER->activitycopyname.'&nbsp;(<a href="'.$CFG->wwwroot.'/course/mod.php' .
                                      '?cancelcopy=true&amp;sesskey='.$USER->sesskey.'">'.$strcancel.'</a>)';
        }

        if (!empty($section) && !empty($section->sequence)) {
            $sectionmods = explode(',', $section->sequence);

            foreach ($sectionmods as $modnumber) {
                if (empty($mods[$modnumber])) {
                    continue;
                }

                $mod = $mods[$modnumber];

                if ($isediting && !$ismoving) {
                    if ($groupbuttons) {
                        if (! $mod->groupmodelink = $groupbuttonslink) {
                            $mod->groupmode = $course->groupmode;
                        }
                    } else {
                        $mod->groupmode = false;
                    }

                    $editbuttons = '<br />'.make_editing_buttons($mod, true, true);
                } else {
                    $editbuttons = '';
                }

                if ($mod->visible || $isteacher) {
                    if ($ismoving) {
                        if ($mod->id == $USER->activitycopy) {
                            continue;
                        }

                        $this->content->items[] = '<a title="'.$strmovefull.'" href="'.$CFG->wwwroot.
                                                  '/course/mod.php?moveto='.$mod->id.'&amp;sesskey='.$USER->sesskey.
                                                  '">'.'<img height="16" width="80" src="'.$CFG->pixpath.
                                                  '/movehere.gif" alt="'.$strmovehere.'" border="0" /></a>';
                        $this->content->icons[] = '';
                   }
                    $instancename = urldecode($modinfo[$modnumber]->name);
                    $instancename = format_string($instancename, true, $this->instance->pageid);
                    $linkcss = $mod->visible ? '' : ' class="dimmed" ';

                    if (!empty($modinfo[$modnumber]->extra)) {
                        $extra = urldecode($modinfo[$modnumber]->extra);
                    } else {
                        $extra = '';
                    }

                    if (!empty($modinfo[$modnumber]->icon)) {
                        $icon = $CFG->pixpath.'/'.urldecode($modinfo[$modnumber]->icon);
                    } else {
                        $icon = $CFG->modpixpath.'/'.$mod->modname.'/icon.gif';
                    }

                    if ($mod->modname == 'label') {
                        $this->content->items[] = format_text($extra, FORMAT_HTML).$editbuttons;
                        $this->content->icons[] = '';
                    } else {
                        $this->content->items[] = '<a title="'.$mod->modfullname.'" '.$linkcss.' '.$extra.
                                                  ' href="'.$CFG->wwwroot.'/mod/'.$mod->modname.'/view.php?id='.
                                                  $mod->id.'">'.$instancename.'</a>'.$editbuttons;
                        $this->content->icons[] = '<img src="'.$icon.'" height="16" width="16" alt="'.
                                                  $mod->modfullname.'" />';
                    }
                }
            }
        }

        if ($ismoving) {
            $this->content->items[] = '<a title="'.$strmovefull.'" href="'.$CFG->wwwroot.'/course/mod.php?' .
                                      'movetosection='.$section->id.'&amp;sesskey='.$USER->sesskey.'">'.
                                      '<img height="16" width="80" src="'.$CFG->pixpath.'/movehere.gif" alt="'.
                                      $strmovehere.'" border="0" /></a>';
            $this->content->icons[] = '';
        }

        if ($isediting && $modnames) {
            $this->content->footer = print_section_add_menus($course, $this->config->section, $modnames, true, true);
        } else {
            $this->content->footer = '';
        }

        return $this->content;
    }

    function instance_delete() {
        global $CFG;

        if (empty($this->instance) || !isset($this->config->section)) {
            return true;
        }

        // Cleanup the section created by this block and any course modules.
        $section = get_record('course_sections', 'section', $this->config->section, 'course', $this->instance->pageid);

        if (empty($section)) {
            return true;
        }

        if ($rs = get_recordset('course_modules', 'section', $section->id)) {
            $mods = array();

            while ($module = $rs->fetch_next_record) {
                $modid = $module->module;

                if (!isset($mods[$modid])) {
                    $mods[$modid] = get_field('modules', 'name', 'id', $modid);
                }

                $mod_lib = $CFG->dirroot.'/mod/'.$mods[$modid].'/lib.php';

                if (file_exists($mod_lib)) {
                    require_once($mod_lib);

                    $delete_func = $mods[$modid].'_delete_instance';

                    if (function_exists($delete_func)) {
                        $delete_func($module->instance);
                    }
                }
            }

            rs_close($rs);
        }

        return delete_records('course_sections', 'id', $section->id);
    }

    function specialization() {
        if (!empty($this->config->title)) {
            $this->title = $this->config->title;
        }
    }

    function has_config() {
        return true;
    }

    function config_save($data) {
        if (!empty($data->block_side_bar_section_start)) {
            set_config('block_side_bar_section_start', intval($data->block_side_bar_section_start));
        }
    }

    function instance_allow_multiple() {
        return true;
    }

    function applicable_formats() {
        return array(
            'site-index'  => true,
            'course-view' => true
        );
    }

    function after_restore($restore) {
        // Get the correct course_sections record ID for the new course
        $section = get_record('course_sections', 'course', $this->instance->pageid, 'section', $this->config->section);

        if (!empty($section->id)) {
            $this->config->section_id = $section->id;
            parent::instance_config_commit();
        }

        return true;
    }

}

?>
