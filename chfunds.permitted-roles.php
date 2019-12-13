<?php
/**
 * This constant DRUPAL_ROLE_PERMISSIONS holds an array of drupal roles and permissions that are to be programmatically assigned to each role in serialised format
 * If you unserialize the constant it will give following array in ['drupal-role-name' => [permission1, permission2 ...]] format,
 * where 'drupal-role-name' is case insensitive but permissions aren't
 *
 * unserialize(DRUPAL_ROLE_PERMISSIONS) = [
 *   'administrator' => [
 *     'CH admin miscellaneous',
 *     'assign CH Fund',
 *    ],
 *  ];
 *
 * which means 'adminstrator' role is given 'CH admin miscellaneous' and 'assign CH Fund' permission.
 * You can change it to assigning whichever permissions you want to whichever roles you want, assuming
 * permissions and roles exist. Like if you want to give 'CH admin miscellaneous' permission to 'adminstrator'
 * role and 'assign CH Fund' to 'staff adminstrator' role then the array would be:
 * [
 *   'adminstrator' => [
 *     'CH admin miscellaneous',
 *    ],
 *   'staff adminstrator' => [
 *     'assign CH Fund',
 *    ],
 *  ];
 * Use https://www.functions-online.com/serialize.html to serialize the array
 */

define('DRUPAL_ROLE_PERMISSIONS', 'a:1:{s:13:"administrator";a:2:{i:0;s:22:"CH admin miscellaneous";i:1;s:14:"assign CH Fund";}}');
