<?php

/**
 * Directory server settings view.
 *
 * @category   ClearOS
 * @package    OpenLDAP_Directory
 * @subpackage Views
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2011 ClearFoundation
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

$this->lang->load('base');
$this->lang->load('accounts');
$this->lang->load('openldap_directory');
$this->lang->load('ldap');

///////////////////////////////////////////////////////////////////////////////
// Form handler
///////////////////////////////////////////////////////////////////////////////

if ($form_type === 'init') {
    $initialize_blurb = TRUE;
    $read_only = FALSE;
    $buttons = array(
        form_submit_custom('initialize', lang('base_initialize')),
    );
} else if ($form_type === 'edit') {
    $initialize_blurb = FALSE;
    $read_only = FALSE;
    $buttons = array(
        form_submit_update('update'),
        anchor_cancel('/app/openldap_directory')
    );
} else {
    $initialize_blurb = FALSE;
    $read_only = TRUE;
    $buttons = array(
        anchor_edit('/app/openldap_directory/settings/edit')
    );
}

///////////////////////////////////////////////////////////////////////////////
// Status box
///////////////////////////////////////////////////////////////////////////////

echo "<input type='hidden' id='validated_action' value='$validated_action'>";

if ($driver === Accounts_Engine::DRIVER_OTHER) {
    echo infobox_warning(lang('base_warning'), lang('accounts_different_directory_is_already_configured'));
    return;
}

echo "<div id='infoboxes' style='display:none;'>";
echo infobox_highlight(
    lang('ldap_directory_status'),
    "<div id='initializing_status'></div>",
    array('id' => 'initializing_box', 'hidden' => TRUE)
);
echo "</div>";

///////////////////////////////////////////////////////////////////////////////
// Main form
///////////////////////////////////////////////////////////////////////////////

echo "<div id='directory_configuration' style='display:none;'>";

if ($initialize_blurb)
    echo infobox_highlight(lang('base_help'), lang('openldap_directory_initialize_help'));

echo form_open('openldap_directory/settings/edit');
echo form_header(lang('base_settings'));

echo field_view(lang('ldap_mode'), $mode_text, 'mode_settings');

if (($mode === LDAP::MODE_MASTER) || ($mode === LDAP::MODE_STANDALONE))
    echo field_input('domain', $domain, lang('ldap_base_domain'), $read_only);

echo field_dropdown('policy', $policies, $policy, lang('ldap_publish_policy'), $read_only);

echo field_button_set($buttons);

echo form_footer();
echo form_close();

echo "</div>";

