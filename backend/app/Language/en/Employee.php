<?php

declare(strict_types=1);

return [
    'EMP_MANAGER_SELF'              => 'An employee cannot manage itself.',
    'EMP_MANAGER_INACTIVE'          => 'An inactive employee cannot be selected as a manager.',
    'EMP_MANAGER_CYCLE'             => 'The manager relationship would create an invalid cycle.',
    'EMP_MANAGER_REFERENCE_INVALID' => 'The manager reference is invalid.',
    'EMP_MANAGER_MUTATION_FAILED'   => 'The manager relationship could not be updated. Please try again.',
    'EMP_PERSISTENCE_FAILED'        => 'The Employee could not be saved.',
    'EMP_CONNECTION_INVALID'        => 'The database connection cannot write Employees.',
    'EMP_UPDATE_EMPTY'              => 'The Employee update cannot be empty.',
    'EMP_FIELD_UNSUPPORTED'         => 'The Employee field is not supported.',
    'EMP_FIELD_REQUIRED'            => 'A required Employee field is missing.',
    'EMP_ASSIGNMENT_PERSISTENCE_FAILED' => 'The Employee Assignment could not be saved.',
    'EMP_ASSIGNMENT_CONNECTION_INVALID' => 'The database connection cannot write Employee Assignments.',
    'EMP_ASSIGNMENT_UPDATE_EMPTY'       => 'The Employee Assignment update cannot be empty.',
    'EMP_ASSIGNMENT_FIELD_UNSUPPORTED'  => 'The Employee Assignment field is not supported.',
    'EMP_ASSIGNMENT_FIELD_REQUIRED'     => 'A required Employee Assignment field is missing.',
];
