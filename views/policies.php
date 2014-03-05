<?php

/**
 * Directory server policies view.
 *
 * @category   apps
 * @package    openldap-directory
 * @subpackage views
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2014 ClearFoundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/openldap_directory/
 */

///////////////////////////////////////////////////////////////////////////////
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.  
//  
///////////////////////////////////////////////////////////////////////////////

use \clearos\apps\openldap\LDAP_Driver as LDAP;
use \clearos\apps\accounts\Accounts_Engine as Accounts_Engine;

///////////////////////////////////////////////////////////////////////////////
// Load dependencies
///////////////////////////////////////////////////////////////////////////////

$this->lang->load('openldap_directory');
$this->lang->load('ldap');

///////////////////////////////////////////////////////////////////////////////
// Form handler
///////////////////////////////////////////////////////////////////////////////

if ($form_type === 'edit') {
    $read_only = FALSE;
    $buttons = array(
        form_submit_update('update'),
        anchor_cancel('/app/openldap_directory')
    );
} else {
    $read_only = TRUE;
    $buttons = array(
        anchor_edit('/app/openldap_directory/policies/edit')
    );
}

///////////////////////////////////////////////////////////////////////////////
// Main form
///////////////////////////////////////////////////////////////////////////////

echo form_open('openldap_directory/policies/edit');
echo form_header(lang('openldap_directory_policies'));

echo field_dropdown('policy', $policies, $policy, lang('ldap_publish_policy'), $read_only);
echo field_dropdown('access_type', $access_types, $access, lang('openldap_directory_accounts_access'), $read_only);

if (!$read_only)
    echo field_input('access_password', $access_password, lang('base_password'), $read_only);

echo field_button_set($buttons);

echo form_footer();
echo form_close();
