<?php

// file containing restriction functions called by get_restrictions
// should return an element or array of elements to be allowed or null
// if no elements should be allowed
//
// these will be matched to the 'field' defined in sources/*/restrictions.php
//
// these functions are assigned by the 'name' defined in sources/*/restrictions.php

// match current user
function learningreport_restriction_own_records() {
    global $USER;
    return $USER->id;
}

// match users who have current user as their manager
function learningreport_restriction_staff_records() {
    global $USER;
    $userid = $USER->id;
    // work out ID of managerid custom field
    $managerfieldid = get_field('user_info_field','id','shortname','managerid');
    // return users with this user as manager
    $staff = get_records_select('user_info_data',"fieldid={$managerfieldid} and data='{$userid}'",'','userid');
    // return null if none found
    if(!$staff) {
        return null;
    }

    // loop to generate output in correct form (array of userids)
    $ret = array();
    foreach($staff as $member) {
        $ret[] = $member->userid;
    }
    return $ret;
}

// match users who are in an organisation at or below the current
// user's organisation
function learningreport_restriction_local_records() {
    global $CFG,$USER;
    $userid = $USER->id;
    // work out ID of organisationid custom field
    $orgfieldid = get_field('user_info_field','id','shortname','organisationid');
    // get the user's organisationid
    $orgid = get_field('user_info_data','data','fieldid',$orgfieldid,'userid',$userid);
    // no results if they don't have one
    if(empty($orgid)) {
        return null;
    }

    // get list of organisations to find users for
    $hierarchy = new hierarchy();
    $hierarchy->prefix = 'organisation';
    $children = $hierarchy->get_item_descendants($orgid);
    $olist = array();
    foreach($children as $child) {
        $olist[] = "'{$child->id}'";
    }

    // return users who are in an organisation in that list
    $users = get_records_select('user_info_data',"fieldid={$orgfieldid} AND data IN (".implode(',',$olist).")",'','userid');
    $ulist = array();
    foreach ($users as $user) {
        $ulist[] = $user->userid;
    }
    return $ulist;
}

// match records which were completed at or below the current user's organisation
function learningreport_restriction_local_completed_records() {
    global $CFG,$USER;
    $userid = $USER->id;
    // work out ID of organisationid custom field
    $orgfieldid = get_field('user_info_field','id','shortname','organisationid');
    // get the user's organisationid
    $orgid = get_field('user_info_data','data','fieldid',$orgfieldid,'userid',$userid);
    // no results if they don't have one
    if(empty($orgid)) {
        return null;
    }

    // get list of organisations to find users for
    $hierarchy = new hierarchy();
    $hierarchy->prefix = 'organisation';
    $children = $hierarchy->get_item_descendants($orgid);
    $olist = array();
    foreach($children as $child) {
        $olist[] = "'{$child->id}'";
    }
    return $olist;

}
