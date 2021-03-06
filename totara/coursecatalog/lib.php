<?php

/**
 * Get the number of visible items in or below the selected categories
 *
 * This function counts the number of items within a set of categories, only including
 * items that are visible to the user.
 *
 * By default returns the course count, but will work for programs, certifications too.
 *
 * We need to jump through some hoops to do this efficiently:
 *
 * - To avoid having to do it recursively it relies on the context
 *   path to find courses within a category
 *
 * - To avoid having to check capabilities for every item it only
 *   checks hidden courses, and only if user isn't a siteadmin
 *
 * @param integer|array $categoryids ID or IDs of the category/categories to fetch
 * @param boolean $viewtype  - type of item to count: course,program,certification
 *
 * @return integer|array Associative array, where keys are the sub-category IDs and value is the count.
 * If $categoryids is a single integer, just returns the count as an integer
 */
function totara_get_category_item_count($categoryids, $viewtype = 'course') {
    global $CFG, $USER, $DB;
    require_once($CFG->dirroot . '/totara/cohort/lib.php');

    list($insql, $params) = $DB->get_in_or_equal(is_array($categoryids) ? $categoryids : array($categoryids));

    if (!$categories = $DB->get_records_select('course_categories', "id $insql", $params)) {
        return array();
    }

    // What items are we counting, courses, programs, or certifications?
    switch ($viewtype) {
        case 'course':
            $itemcap = 'moodle/course:viewhiddencourses';
            $itemtable = "{course}";
            $itemcontext = CONTEXT_COURSE;
            $itemalias = 'c';
            $extrawhere = '';
            break;
        case 'program':
            $itemcap = 'totara/program:viewhiddenprograms';
            $itemtable = "{prog}";
            $itemcontext = CONTEXT_PROGRAM;
            $itemalias = 'p';
            $extrawhere = " AND certifid IS NULL";
            break;
        case 'certification':
            $itemcap = 'totara/certification:viewhiddencertifications';
            $itemtable = "{prog}";
            $itemcontext = CONTEXT_PROGRAM;
            $itemalias = 'p';
            $extrawhere = " AND certifid IS NOT NULL";
            break;
        default:
            print_error('invalid viewtype');
    }

    list($insql, $inparams) = $DB->get_in_or_equal(array_keys($categories), SQL_PARAMS_NAMED);
    $sql = "SELECT instanceid, path
              FROM {context}
             WHERE contextlevel = :contextlvl
               AND instanceid {$insql}
             ORDER BY depth DESC";
    $params = array('contextlvl' => CONTEXT_COURSECAT);
    $params = array_merge($params, $inparams);

    $contextpaths = $DB->get_records_sql_menu($sql, $params);

    // Builds a WHERE snippet that matches any items inside the sub-category.
    // This won't match the category itself (because of the trailing slash),
    // But that's okay as we're only interested in the items inside.
    $contextwhere = array(); $contextparams = array();
    foreach ($contextpaths as $path) {
        $paramalias = 'cxt_' . rand(1, 10000);
        $contextwhere[] = $DB->sql_like('cx.path', ":{$paramalias}");
        $contextparams[$paramalias] = $path . '/%';
    }

    // Add audience visibility setting.
    $visibilitysql = '';
    $visibilityparams = array();
    $canmanagevisibility = has_capability('totara/coursecatalog:manageaudiencevisibility', context_system::instance());
    if (!empty($CFG->audiencevisibility) && !$canmanagevisibility) {
        list($visibilitysql, $visibilityparams) = totara_cohort_get_visible_learning_sql($itemalias, 'id', $itemcontext);
    }

    $sql = "SELECT {$itemalias}.id as itemid, {$itemalias}.visible, {$itemalias}.audiencevisible, cx.path
              FROM {context} cx
              JOIN {$itemtable} {$itemalias}
                ON {$itemalias}.id = cx.instanceid AND contextlevel = :itemcontext
                {$visibilitysql}
             WHERE (" . implode(' OR ', $contextwhere) . ")" . $extrawhere;
    $params = array('itemcontext' => $itemcontext);
    $params = array_merge($params, $contextparams);
    $params = array_merge($params, $visibilityparams);

    // Get all items inside all the categories.
    if (!$items = $DB->get_records_sql($sql, $params)) {
        // Sub-categories are all empty.
        if (is_array($categoryids)) {
            return array();
        } else {
            return 0;
        }
    }

    $results = array();
    foreach ($items as $item) {
        if (empty($CFG->audiencevisibility)) {
            // Check individual permission.
            // Get contextobj - use a switch in case this gets even more complicated in future with a third type.
            switch ($itemcontext) {
                case CONTEXT_COURSE:
                    $contextobj = context_course::instance($item->itemid);
                    break;
                case CONTEXT_PROGRAM:
                    $contextobj = context_program::instance($item->itemid);
                    break;
            }
            if (!$item->visible && !has_capability($itemcap, $contextobj)) {
                continue;
            }
        }

        // We need to check if programs are available to students.
        if ($viewtype == 'program'  && !is_siteadmin($USER->id)) {
            $program = new program($item->itemid);
            if (!$program->is_accessible()) {
                continue;
            }
        }

        // Now we need to figure out which sub-category each item is a member of.
        foreach ($contextpaths as $categoryid => $contextpath) {
            // It's a member if the beginning of the contextpath's match.
            if (substr($item->path, 0, strlen($contextpath.'/')) ==
                $contextpath.'/') {
                if (array_key_exists($categoryid, $results)) {
                    $results[$categoryid]++;
                } else {
                    $results[$categoryid] = 1;
                }
                break;
            }
        }
    }

    if (empty($results)) {
        return 0;
    } else if (is_array($categoryids)) {
        return $results;
    } else {
        return current($results);
    }

}

/**
 * Sorts a pair of objects based on the itemcount property (high to low)
 *
 * @param object $a The first object
 * @param object $b The second object
 * @return integer Returns 1/0/-1 depending on the relative values of the objects itemcount property
 */
function totara_course_cmp_by_count($a, $b) {
    if ($a->itemcount < $b->itemcount) {
        return +1;
    } else if ($a->itemcount > $b->itemcount) {
        return -1;
    } else {
        return 0;
    }
}

/**
 * Returns true or false depending on whether or not this course is visible to a user.
 *
 * @param int $courseid
 * @param int $userid
 * @return bool
 */
function totara_course_is_viewable($courseid, $userid = null) {
    global $USER, $CFG, $DB;
    require_once($CFG->dirroot . '/totara/cohort/lib.php');

    if ($userid == null) {
        $userid = $USER->id;
    }

    $coursecontext = context_course::instance($courseid);

    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
    if (empty($CFG->audiencevisibility)) {
        // This check is moved from require_login().
        if (!$course->visible && !has_capability('moodle/course:viewhiddencourses', $coursecontext, $userid)) {
            return false;
        }
    } else {
        if ($course->audiencevisible == COHORT_VISIBLE_ALL) {
            // Course needs to be visible to all.
            return true;
        } else if (has_capability('totara/coursecatalog:manageaudiencevisibility', context_system::instance())) {
            // Or user has 'manageaudiencevisibility' capability.
            return true;
        } else if (is_enrolled($coursecontext, $userid)) {
            // Or user needs to be enrolled.
            return true;
        } else {
            // Or user is in visible audiences.
            $sql = "SELECT cv.instanceid
                            FROM {cohort_visibility} cv
                            JOIN {cohort_members} cm ON cv.cohortid = cm.cohortid
                            JOIN {course} c ON cv.instanceid = c.id AND c.audiencevisible > 0
                            WHERE cv.instancetype = :instancetype
                                  AND cm.userid = :userid
                                  AND c.id = :cid";
            $params = array('instancetype' => COHORT_ASSN_ITEMTYPE_COURSE,
                            'userid' => $userid,
                            'cid' => $courseid);

            if ($DB->record_exists_sql($sql, $params)) {
                return true;
            }
            return false;
        }
    }

    return true;
}
