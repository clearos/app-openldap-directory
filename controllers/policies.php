<?php

/**
 * Directory server policies controller.
 *
 * @category   apps
 * @package    openldap-directory
 * @subpackage controllers
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

///////////////////////////////////////////////////////////////////////////////
// D E P E N D E N C I E S
///////////////////////////////////////////////////////////////////////////////

use \clearos\apps\openldap_directory\OpenLDAP as OpenLDAP;
use \Exception as Exception;

///////////////////////////////////////////////////////////////////////////////
// C L A S S
///////////////////////////////////////////////////////////////////////////////

/**
 * Directory_server policies controller.
 *
 * We are actually initializing two layers here:
 * - The base LDAP layer using the (basically, an empty LDAP directory)
 * - The base accounts layer (all things related to user accounts)
 *
 * @category   apps
 * @package    openldap-directory
 * @subpackage controllers
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2014 ClearFoundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/openldap_directory/
 */

class Policies extends ClearOS_Controller
{
    /**
     * Directory server settings default  controller
     *
     * @return view
     */

    function index()
    {
        $this->_item('view');
    }

    /**
     * Edit view
     *
     * @return view
     */

    function edit()
    {
        $this->_item('edit');
    }

    /**
     * View view
     *
     * @param string $action action
     *
     * @return view
     */

    function view($action)
    {
        $this->_item('view');
    }

    /**
     * Common form handler
     *
     * @param string $form_type form type
     *
     * @return view
     */

    function _item($form_type)
    {
        // Load dependencies
        //------------------

        $this->lang->load('openldap_directory');
        $this->load->library('openldap/LDAP_Driver');
        $this->load->library('openldap_directory/Accounts_Driver');
        $this->load->library('openldap_directory/OpenLDAP');

        // Bail if driver not set
        //-----------------------

        try {
            $data['initialized'] = $this->accounts_driver->is_initialized();
        } catch (Exception $e) {
            $this->page->view_exception($e);
        }

        if (! $data['initialized'])
            return;

        // Set validation rules
        //---------------------
         
        $this->form_validation->set_policy('policy', 'openldap/LDAP_Driver', 'validate_security_policy', TRUE);
        $this->form_validation->set_policy('access_type', 'openldap_directory/OpenLDAP', 'validate_accounts_access', TRUE);

        if ($this->input->post('access_type') === OpenLDAP::ACCESS_PASSWORD_ACCESS)
            $this->form_validation->set_policy('access_password', 'openldap_directory/OpenLDAP', 'validate_password', TRUE);

        $form_ok = $this->form_validation->run();

        // Handle form submit
        //-------------------

        if ($this->input->post('update') && $form_ok) {
            try {
                $this->ldap_driver->set_security_policy($this->input->post('policy'));
                $this->openldap->set_accounts_access(
                    $this->input->post('access_type'),
                    $this->input->post('access_password')
                );

                $this->ldap_driver->reset();
                redirect('/openldap_directory/policies');
            } catch (Exception $e) {
                $this->page->view_exception($e);
                return;
            }
        }

        // Load view data
        //---------------

        try {
            $data['form_type'] = $form_type;

            $data['policies'] = $this->ldap_driver->get_security_policies();
            $data['policy'] = $this->ldap_driver->get_security_policy();
            $data['initialized'] = $this->accounts_driver->is_initialized();
            $data['access'] = $this->openldap->get_accounts_access();
            $data['access_types'] = $this->openldap->get_accounts_access_types();
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }

        // Load views
        //-----------

        if (!$data['initialized'])
            return;

        $this->page->view_form('openldap_directory/policies', $data, lang('openldap_directory_policies'));
    }
}
