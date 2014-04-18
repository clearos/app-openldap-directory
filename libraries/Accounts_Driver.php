<?php

/**
 * OpenLDAP accounts driver class.
 *
 * @category   apps
 * @package    openldap-directory
 * @subpackage libraries
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2006-2011 ClearFoundation
 * @license    http://www.gnu.org/copyleft/lgpl.html GNU Lesser General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/openldap_directory/
 */

///////////////////////////////////////////////////////////////////////////////
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU Lesser General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Lesser General Public License for more details.
//
// You should have received a copy of the GNU Lesser General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.
//
///////////////////////////////////////////////////////////////////////////////

///////////////////////////////////////////////////////////////////////////////
// N A M E S P A C E
///////////////////////////////////////////////////////////////////////////////

namespace clearos\apps\openldap_directory;

///////////////////////////////////////////////////////////////////////////////
// B O O T S T R A P
///////////////////////////////////////////////////////////////////////////////

$bootstrap = getenv('CLEAROS_BOOTSTRAP') ? getenv('CLEAROS_BOOTSTRAP') : '/usr/clearos/framework/shared';
require_once $bootstrap . '/bootstrap.php';

///////////////////////////////////////////////////////////////////////////////
// T R A N S L A T I O N S
///////////////////////////////////////////////////////////////////////////////

clearos_load_language('accounts');

///////////////////////////////////////////////////////////////////////////////
// D E P E N D E N C I E S
///////////////////////////////////////////////////////////////////////////////

// Classes
//--------

use \clearos\apps\accounts\Accounts_Engine as Accounts_Engine;
use \clearos\apps\base\Folder as Folder;
use \clearos\apps\base\Lock as Lock;
use \clearos\apps\ldap\Nslcd as Nslcd;
use \clearos\apps\openldap\LDAP_Driver as LDAP_Driver;
use \clearos\apps\openldap_directory\OpenLDAP as OpenLDAP;
use \clearos\apps\openldap_directory\Utilities as Utilities;
use \clearos\apps\samba\OpenLDAP_Driver as OpenLDAP_Driver;

clearos_load_library('accounts/Accounts_Engine');
clearos_load_library('base/Folder');
clearos_load_library('base/Lock');
clearos_load_library('ldap/Nslcd');
clearos_load_library('openldap/LDAP_Driver');
clearos_load_library('openldap_directory/OpenLDAP');
clearos_load_library('openldap_directory/Utilities');
clearos_load_library('samba/OpenLDAP_Driver');

// Exceptions
//-----------

use \clearos\apps\base\Engine_Exception as Engine_Exception;

clearos_load_library('base/Engine_Exception');

///////////////////////////////////////////////////////////////////////////////
// C L A S S
///////////////////////////////////////////////////////////////////////////////

/**
 * OpenLDAP accounts driver class.
 *
 * @category   apps
 * @package    openldap-directory
 * @subpackage libraries
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2006-2011 ClearFoundation
 * @license    http://www.gnu.org/copyleft/lgpl.html GNU Lesser General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/openldap_directory/
 */

class Accounts_Driver extends Accounts_Engine
{
    ///////////////////////////////////////////////////////////////////////////////
    // C O N S T A N T S
    ///////////////////////////////////////////////////////////////////////////////

    const DRIVER_NAME = 'openldap_directory';
    const COMMAND_AUTHCONFIG = '/usr/sbin/authconfig';
    const PATH_EXTENSIONS = '/var/clearos/openldap_directory/extensions';
    const FILE_READY_FOR_EXTENSIONS = '/var/clearos/openldap_directory/ready_for_extensions';

    // Status codes for username/group/alias uniqueness
    const STATUS_ALIAS_EXISTS = 'alias';
    const STATUS_GROUP_EXISTS = 'group';
    const STATUS_USERNAME_EXISTS = 'user';
    const STATUS_UNIQUE = 'unique';

    ///////////////////////////////////////////////////////////////////////////////
    // V A R I A B L E S
    ///////////////////////////////////////////////////////////////////////////////

    protected $ldaph = NULL;
    protected $config = NULL;
    protected $modes = NULL;
    protected $extensions = array();
    protected $reserved_ids = array('root', 'manager', 'administrator');

    ///////////////////////////////////////////////////////////////////////////////
    // M E T H O D S
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * OpenLDAP_Accounts constructor.
     */

    public function __construct()
    {
        clearos_profile(__METHOD__, __LINE__);
    }

    /**
     * Returns capabililites.
     *
     * @return string capabilities
     */

    public function get_capability()
    {
        clearos_profile(__METHOD__, __LINE__);

        if ($this->get_mode() == self::MODE_SLAVE)
            return Accounts_Engine::CAPABILITY_READ_ONLY;
        else
            return Accounts_Engine::CAPABILITY_READ_WRITE;
    }

    /**
     * Returns state of driver.
     *
     * @return boolean state of driver
     * @throws Engine_Exception
     */

    public function get_driver_status()
    {
        clearos_profile(__METHOD__, __LINE__);

        return $this->_get_driver_status(self::DRIVER_NAME);
    }

    /**
     * Returns list of directory extensions.
     *
     * @return array extension list
     * @throws Engine_Exception
     */

    public function get_extensions()
    {
        clearos_profile(__METHOD__, __LINE__);

        if (! empty($this->extensions))
            return $this->extensions;

        $folder = new Folder(self::PATH_EXTENSIONS);

        $list = $folder->get_listing();

        foreach ($list as $extension_file) {
            if (preg_match('/\.php$/', $extension_file)) {
                $extension = array();
                include self::PATH_EXTENSIONS . '/' . $extension_file;
                $this->extensions[$extension['extension']] = $extension;
            }
        }

        return $this->extensions;
    }

    /**
     * Returns the mode of the accounts engine.
     *
     * The return values are:
     * - Accounts_Engine::MODE_STANDALONE
     * - Accounts_Engine::MODE_MASTER
     * - Accounts_Engine::MODE_SLAVE
     *
     * @return string mode of the directory
     * @throws Engine_Exception
     */

    public function get_mode()
    {
        clearos_profile(__METHOD__, __LINE__);

        $ldap = new LDAP_Driver();

        return $ldap->get_mode();
    }

    /**
     * Returns a list of available modes.
     *
     * @return array list of modes
     * @throws Engine_Exception
     */

    public function get_modes()
    {
        clearos_profile(__METHOD__, __LINE__);

        $ldap = new LDAP_Driver();

        return $ldap->get_modes();
    }

    /**
     * Returns the next available user ID.
     *
     * @return integer next available user ID
     * @throws Engine_Exception
     */

    public function get_next_uid_number()
    {
        clearos_profile(__METHOD__, __LINE__);

        if ($this->ldaph === NULL)
            $this->ldaph = Utilities::get_ldap_handle();

        $dn = 'cn=Master,' . OpenLDAP::get_servers_container();

        $attributes = $this->ldaph->read($dn);

        // TODO: should have some kind of semaphore to prevent duplicate IDs
        $next['uidNumber'] = $attributes['uidNumber'][0] + 1;

        $this->ldaph->modify($dn, $next);

        return $attributes['uidNumber'][0];
    }

    /**
     * Returns status of account system.
     *
     * - Accounts_Engine::STATUS_INITIALIZING
     * - Accounts_Engine::STATUS_UNINITIALIZED
     * - Accounts_Engine::STATUS_OFFLINE
     * - Accounts_Engine::STATUS_ONLINE
     *
     * @return string account system status
     * @throws Engine_Exception
     */

    public function get_system_status()
    {
        clearos_profile(__METHOD__, __LINE__);

        // Check initializing
        //-------------------

        $lock = new Lock('openldap_directory_init');

        if ($lock->is_locked())
            return Accounts_Engine::STATUS_INITIALIZING;

        // Check initialized
        //------------------

        if (! $this->is_initialized())
            return Accounts_Engine::STATUS_UNINITIALIZED;

        // Check online/offline
        //---------------------

        if ($this->ldaph === NULL)
            $this->ldaph = Utilities::get_ldap_handle();

        if (!$this->ldaph->is_online())
            return Accounts_Engine::STATUS_OFFLINE;

        // Send busy if Samba is doing its long initialization
        //----------------------------------------------------

        if (clearos_library_installed('samba/OpenLDAP_Driver')) {
            clearos_load_library('samba/OpenLDAP_Driver');
            $driver = new \clearos\apps\samba\OpenLDAP_Driver();
            $status = $driver->get_status();

            if (($status === \clearos\apps\samba\OpenLDAP_Driver::STATUS_SAMBA_INITIALIZING)
                || ($status === \clearos\apps\samba\OpenLDAP_Driver::STATUS_OPENLDAP_INITIALIZING))
                
                return Accounts_Engine::STATUS_BUSY;
        }

        return Accounts_Engine::STATUS_ONLINE;
    }

    /**
     * Check for reserved usernames, groups and aliases in the directory.
     *
     * @param string $id username, group or alias
     *
     * @return boolean TRUE if ID is reserved
     */

    public function is_reserved_id($id)
    {
        clearos_profile(__METHOD__, __LINE__);

        if (in_array($id, $this->reserved_ids))
            return TRUE;
        else
            return FALSE;
    }

    /**
     * Check for reserved usernames, groups and aliases in the directory.
     *
     * @param string $id username, group or alias
     *
     * @return string warning message if ID is reserved
     */

    public function is_reserved_id_message($id)
    {
        clearos_profile(__METHOD__, __LINE__);

        if ($this->is_reserved_id($id))
            return lang('accounts_reserved_for_system_use');
        else
            return '';
    }

    /**
     * Check for overlapping usernames, groups and aliases in the directory.
     *
     * @param string $id                     username, group or alias
     * @param string $ignore_aliases_for_uid ignore aliases for given uid
     *
     * @return string warning type if ID is not unique
     */

    public function is_unique_id($id, $ignore_aliases_for_uid = NULL)
    {
        clearos_profile(__METHOD__, __LINE__);

        if ($this->ldaph === NULL)
            $this->ldaph = Utilities::get_ldap_handle();

        // Check for duplicate user
        //-------------------------

        $result = $this->ldaph->search(
            "(&(objectclass=inetOrgPerson)(uid=$id))",
            OpenLDAP::get_users_container(),
            array('dn')
        );

        $entry = $this->ldaph->get_first_entry($result);

        if ($entry)
            return self::STATUS_USERNAME_EXISTS;

        // Check for duplicate alias
        //--------------------------

        $filter = is_null($ignore_aliases_for_uid) ? '' : "(!(uid=$ignore_aliases_for_uid))";

        $result = $this->ldaph->search(
            "(&(objectclass=inetOrgPerson)(clearMailAliases=$id)$filter)",
            OpenLDAP::get_users_container(),
            array('dn')
        );

        $entry = $this->ldaph->get_first_entry($result);

        if ($entry)
            return self::STATUS_ALIAS_EXISTS;

        // Check for duplicate group
        //--------------------------
    
        // The "displayName" is used in Samba group mapping.  In other words,
        // the "displayName" is what is used by Windows networking (not the cn).

        $result = $this->ldaph->search(
            "(&(objectclass=posixGroup)(|(cn=$id)(displayName=$id)))",
            OpenLDAP::get_groups_container(),
            array('dn')
        );

        $entry = $this->ldaph->get_first_entry($result);

        if ($entry)
            return self::STATUS_GROUP_EXISTS;

        // TODO: Flexshares?  How do we deal with this in master/replica mode?

        return self::STATUS_UNIQUE;
    }

    /**
     * Check for overlapping usernames, groups and aliases in the directory.
     *
     * @param string $id                     username, group or alias
     * @param string $ignore_aliases_for_uid ignore aliases for given uid
     *
     * @return string warning message if ID is not unique
     */

    public function is_unique_id_message($id, $ignore_aliases_for_uid = NULL)
    {
        clearos_profile(__METHOD__, __LINE__);

        $status = $this->is_unique_id($id, $ignore_aliases_for_uid);

        if ($status === self::STATUS_USERNAME_EXISTS)
            return lang('accounts_username_with_this_name_exists');
        else if ($status === self::STATUS_ALIAS_EXISTS)
            return lang('accounts_alias_with_this_name_exists');
        else if ($status === self::STATUS_GROUP_EXISTS)
            return lang('accounts_group_with_this_name_exists');
        else
            return '';
    }

    /**
     * Restarts the relevant daemons in a sane order.
     *
     * @return void
     */

    public function synchronize()
    {
        clearos_profile(__METHOD__, __LINE__);

        $ldap = new LDAP_Driver();
        $ldap->synchronize();

        try {
            $nslcd = new Nslcd();

            if ($nslcd->get_running_state())
                $nslcd->reset();
            else
                $nslcd->set_running_state(TRUE);
        } catch (Engine_Exception $e) {
            // Not fatal.
        }
    }
}
