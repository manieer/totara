<?php

$handlers = array (
    'program_assigned' => array (
         'handlerfile'      => '/totara/program/lib.php',
         'handlerfunction'  => 'prog_eventhandler_program_assigned',
         'schedule'         => 'instant'
     ),
    'program_unassigned' => array (
         'handlerfile'      => '/totara/program/lib.php',
         'handlerfunction'  => 'prog_eventhandler_program_unassigned',
         'schedule'         => 'instant'
     ),
    'program_completed' => array (
         'handlerfile'      => '/totara/program/lib.php',
         'handlerfunction'  => 'prog_eventhandler_program_completed',
         'schedule'         => 'instant'
     ),
    'program_courseset_completed' => array (
         'handlerfile'      => '/totara/program/lib.php',
         'handlerfunction'  => 'prog_eventhandler_courseset_completed',
         'schedule'         => 'instant'
     ),
    'user_firstaccess' => array (
         'handlerfile'      => '/totara/program/lib.php',
         'handlerfunction'  => 'prog_assignments_firstlogin',
         'schedule'         => 'instant'
     )
);