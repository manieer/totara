<?php

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

class dp_manager_role extends dp_base_role {
    function user_has_role($userid=null) {
        global $USER;
        // use current user if none given
        if (!isset($userid)) {
            $userid = $USER->id;
        }
        // are they the manager of this plan's owner?
        if(totara_is_manager($this->plan->userid, $userid)) {
            return 'manager';

        // Are they an administrative super-user?
        } elseif ( has_capability('moodle/site:doanything', get_context_instance(CONTEXT_SYSTEM), $userid )){
            return 'manager';
        } else {
            return false;
        }
    }
}