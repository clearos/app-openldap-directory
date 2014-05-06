<?php

/**
 * OpenLDAP accounts class.
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

clearos_load_language('base');
clearos_load_language('network');
clearos_load_language('openldap_directory');
clearos_load_language('ldap');

///////////////////////////////////////////////////////////////////////////////
// D E P E N D E N C I E S
///////////////////////////////////////////////////////////////////////////////

// Classes
//--------

use \clearos\apps\accounts\Accounts_Configuration as Accounts_Configuration;
use \clearos\apps\accounts\Nscd as Nscd;
use \clearos\apps\base\Engine as Engine;
use \clearos\apps\base\File as File;
use \clearos\apps\base\Folder as Folder;
use \clearos\apps\base\Lock as Lock;
use \clearos\apps\base\Shell as Shell;
use \clearos\apps\ldap\Nslcd as Nslcd;
use \clearos\apps\mode\Mode_Engine as Mode_Engine;
use \clearos\apps\mode\Mode_Factory as Mode_Factory;
use \clearos\apps\network\Network_Utils as Network_Utils;
use \clearos\apps\openldap\LDAP_Driver as LDAP_Driver;
use \clearos\apps\openldap_directory\Accounts_Driver as Accounts_Driver;
use \clearos\apps\openldap_directory\User_Driver as User_Driver;
use \clearos\apps\openldap_directory\Utilities as Utilities;

clearos_load_library('accounts/Accounts_Configuration');
clearos_load_library('accounts/Nscd');
clearos_load_library('base/Engine');
clearos_load_library('base/File');
clearos_load_library('base/Folder');
clearos_load_library('base/Lock');
clearos_load_library('base/Shell');
clearos_load_library('ldap/Nslcd');
clearos_load_library('mode/Mode_Engine');
clearos_load_library('mode/Mode_Factory');
clearos_load_library('network/Network_Utils');
clearos_load_library('openldap/LDAP_Driver');
clearos_load_library('openldap_directory/Accounts_Driver');
clearos_load_library('openldap_directory/User_Driver');
clearos_load_library('openldap_directory/Utilities');

// Exceptions
//-----------

use \Exception as Exception;
use \clearos\apps\base\Engine_Exception as Engine_Exception;
use \clearos\apps\base\Validation_Exception as Validation_Exception;

clearos_load_library('base/Engine_Exception');
clearos_load_library('base/Validation_Exception');

///////////////////////////////////////////////////////////////////////////////
// C L A S S
///////////////////////////////////////////////////////////////////////////////

/**
 * OpenLDAP accounts class.
 *
 * @category   apps
 * @package    openldap-directory
 * @subpackage libraries
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2006-2011 ClearFoundation
 * @license    http://www.gnu.org/copyleft/lgpl.html GNU Lesser General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/openldap_directory/
 */

class OpenLDAP extends Engine
{
    ///////////////////////////////////////////////////////////////////////////////
    // C O N S T A N T S
    ///////////////////////////////////////////////////////////////////////////////

    // General
    const DEFAULT_DOMAIN = 'system.lan';
    const CONSTANT_BASE_DB_NUM = 3;

    // Access types
    const ACCESS_DISABLED = 'disabled';
    const ACCESS_ANONYMOUS = 'anonymous';
    const ACCESS_PASSWORD_ACCESS = 'password';

    // Paths
    const COMMAND_AUTHCONFIG = '/usr/sbin/authconfig';
    const COMMAND_SLAPCAT = '/usr/sbin/slapcat';
    const COMMAND_SLAPADD = '/usr/sbin/slapadd';
    const COMMAND_INITIALIZE = '/usr/sbin/app-openldap-directory-initialize';
    const FILE_CONFIG = '/etc/openldap/slapd.conf';
    const FILE_READY_FOR_EXTENSIONS = '/var/clearos/openldap_directory/ready_for_extensions';
    const FILE_LDIF_TEMPLATE = 'deploy/accounts.ldif.template';
    const FILE_LDIF_IMPORT = '/var/clearos/openldap_directory/accounts.ldif';
    const PATH_LDAP_BACKUP = '/var/clearos/openldap_directory/backup/';
    const PATH_LDAP = '/var/lib/ldap';

    // Containers
    const SUFFIX_COMPUTERS = 'ou=Computers,ou=Accounts';
    const SUFFIX_GROUPS = 'ou=Groups,ou=Accounts';
    const SUFFIX_SERVERS = 'ou=Servers';
    const SUFFIX_USERS = 'ou=Users,ou=Accounts';
    const SUFFIX_PASSWORD_POLICIES = 'ou=PasswordPolicies,ou=Accounts';
    const SUFFIX_ACCOUNTS_DN = 'cn=accounts,ou=Internal';
 
    const OU_PASSWORD_POLICIES = 'PasswordPolicies';
    const CN_MASTER = 'cn=Master';
    const DRIVER_NAME = 'openldap_directory';

    ///////////////////////////////////////////////////////////////////////////////
    // V A R I A B L E S
    ///////////////////////////////////////////////////////////////////////////////

    protected $ldaph = NULL;

    ///////////////////////////////////////////////////////////////////////////////
    // M E T H O D S
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * OpenLDAP_Driver constructor.
     */

    public function __construct()
    {
        clearos_profile(__METHOD__, __LINE__);
    }

    /**
     * Returns state of accounts access.
     *
     * @return boolean state of accounts access
     */

    public function get_accounts_access()
    {
        clearos_profile(__METHOD__, __LINE__);

        $file = new File(self::FILE_CONFIG);
        $lines = $file->get_contents_as_array();

        foreach ($lines as $line) {
            if (preg_match('/^include\s+\/etc\/openldap\/clearos_anonymous.conf\s*$/', $line))
                return self::ACCESS_ANONYMOUS;

            if (preg_match('/^include\s+\/etc\/openldap\/clearos_password_protected.conf\s*$/', $line))
                return self::ACCESS_PASSWORD_ACCESS;
        }

        return self::ACCESS_DISABLED;
    }

    /**
     * Returns accounts access types.
     *
     * @return array types of accounts access
     */

    public function get_accounts_access_types()
    {
        clearos_profile(__METHOD__, __LINE__);

        $types = array(
            self::ACCESS_DISABLED => lang('base_disabled'),
            self::ACCESS_ANONYMOUS => lang('openldap_directory_anonymous'),
            self::ACCESS_PASSWORD_ACCESS => lang('openldap_directory_password_access'),
        );

        return $types;
    }

    /**
     * Returns the accounts DN.
     *
     * @return string accounts DN
     * @throws Engine_Exception
     */

    public function get_accounts_dn()
    {
        clearos_profile(__METHOD__, __LINE__);

        return self::SUFFIX_ACCOUNTS_DN . ',' . $this->get_base_dn();
    }

    /** 
     * Returns the base DN.
     *
     * @return string base DN
     * @throws Engine_Exception
     */

    public static function get_base_dn()
    {
        clearos_profile(__METHOD__, __LINE__);

        $ldap = new LDAP_Driver();

        return $ldap->get_base_dn();
    }

    /** 
     * Returns base DN in Internet domain format.
     *
     * @return string default domain
     * @throws Engine_Exception
     */

    public static function get_base_internet_domain()
    {
        clearos_profile(__METHOD__, __LINE__);

        $ldap = new LDAP_Driver();

        return $ldap->get_base_internet_domain();
    }

    /** 
     * Returns the container for computers.
     *
     * @return string container for computers.
     * @throws Engine_Exception
     */

    public static function get_computers_container()
    {
        clearos_profile(__METHOD__, __LINE__);

        return self::SUFFIX_COMPUTERS . ',' . self::get_base_dn();
    }

    /** 
     * Returns the ontainer for groups.
     *
     * @return string container for groups
     * @throws Engine_Exception
     */

    public static function get_groups_container()
    {
        clearos_profile(__METHOD__, __LINE__);

        return self::SUFFIX_GROUPS . ',' . self::get_base_dn();
    }

    /**
     * Returns the DN of the master server 
     *
     * @return string DN of the master server
     * @throws Engine_Exception
     */

    public static function get_master_dn()
    {
        clearos_profile(__METHOD__, __LINE__);

        return self::CN_MASTER . ',' . self::SUFFIX_SERVERS . ',' . self::get_base_dn();
    }

    /** 
     * Returns the container for password policies.
     *
     * @return string container for password policies
     * @throws Engine_Exception
     */

    public static function get_password_policies_container()
    {
        clearos_profile(__METHOD__, __LINE__);

        return self::SUFFIX_PASSWORD_POLICIES . ',' . self::get_base_dn();
    }

    /** 
     * Returns the container for servers.
     *
     * @return string container for servers.
     * @throws Engine_Exception
     */

    public static function get_servers_container()
    {
        clearos_profile(__METHOD__, __LINE__);

        return self::SUFFIX_SERVERS . ',' . self::get_base_dn();
    }

    /** 
     * Returns the container for users.
     *
     * @return string container for users.
     * @throws Engine_Exception
     */

    public static function get_users_container()
    {
        clearos_profile(__METHOD__, __LINE__);

        return self::SUFFIX_USERS . ',' . self::get_base_dn();
    }

    /**
     * Initializes the OpenLDAP accounts system.
     *
     * @param string  $domain base domain
     * @param boolean $force  forces initialization if TRUE
     *
     * @return void
     * @throws Engine_Exception, Validation_Exception
     */

    public function initialize($domain = self::DEFAULT_DOMAIN, $force = FALSE)
    {
        clearos_profile(__METHOD__, __LINE__);

        // Bail if initialized
        //--------------------

        $driver = new Accounts_Driver();

        if ($driver->is_initialized()) {
            if ($force) {
                $driver->set_initialized(FALSE);
                $driver->set_ready(FALSE);
            } else {
                return;
            }
        }

        // Lock state file
        //----------------

        $lock = new Lock('openldap_directory_init');

        if ($lock->get_lock() !== TRUE) {
            clearos_log('openldap_directory', 'local initialization is already running');
            return;
        }

        // Go through initalization process
        //---------------------------------

        try {
            // Set driver so status information knows where to look
            //-----------------------------------------------------

            $this->_set_driver();
            $ldap = new LDAP_Driver();

            // Initialize LDAP with appropriate mode
            //--------------------------------------

            $sysmode = Mode_Factory::create();
            $mode = $sysmode->get_mode();

            if ($mode != Mode_Engine::MODE_SLAVE) {
                if ($ldap->is_initialized() && !$force) {
                    clearos_log('openldap_directory', 'setting base domain name');
                    $ldap->set_base_internet_domain($domain);
                } else if ($mode === Mode_Engine::MODE_MASTER) {
                    clearos_log('openldap_directory', 'initializing master mode');
                    $ldap->initialize_master($domain, NULL, $force);
                } else if ($mode === Mode_Engine::MODE_STANDALONE) {
                    clearos_log('openldap_directory', 'initializing standalone mode');
                    $ldap->initialize_standalone($domain, NULL, $force);
                }

                clearos_log('openldap_directory', 'adding account objects');
                $this->_add_account_objects($domain);
            }

            // Post LDAP tasks
            //----------------

            clearos_log('openldap_directory', 'initializing authconfig');
            $this->_initialize_authconfig();

            clearos_log('openldap_directory', 'cleaning up shadow system');
            $this->_remove_overlaps();

            clearos_log('openldap_directory', 'initializing caching');
            $this->_initialize_caching();
        } catch (Exception $e) {
            $lock->unlock();
            throw new Engine_Exception(clearos_exception_message($e));
        }

        try {
            if ($mode !== Mode_Engine::MODE_SLAVE) {
                clearos_log('openldap_directory', 'initializing plugin groups');
                $driver->initialize_plugin_groups(FALSE);
            }
        } catch (Exception $e) {
            // Not fatal
        }

        // Tell accounts system we're done
        //--------------------------------

        // The API is designed as a 2-step process:
        // 1) initialize OpenLDAP (this method)
        // 2) initialize Samba extensions
        // 
        // The Samba extensions take time to initialize and it is best to leave
        // LDAP alone during this process.  In practice, these two steps are run 
        // one right after the other on a ClearOS OpenLDAP implementation.  
        // However, it is certainly possible to either never run the second step 
        // (i.e. no Samba) or run the Samba extensions at a later time.
        // 
        // This little sleep gives the Samba initialization process a chance to
        // get the ball rolling before the system gives the "okay, I'm initialized"
        // go ahead.

        clearos_log('openldap_directory', 'accounts ready for extensions');

        $driver->set_ready(TRUE);

        sleep(8);

        clearos_log('openldap_directory', 'accounts initialized');
        $driver->set_initialized(TRUE);
        $driver->synchronize();

        // Cleanup file / file lock
        //-------------------------

        $lock->unlock();
    }

    /**
     * Initializes the OpenLDAP accounts system.
     *
     * @param string  $domain base domain
     * @param boolean $force  forces initialization if TRUE
     *
     * @return void
     * @throws Engine_Exception, Validation_Exception
     */

    public function run_initialize($domain = self::DEFAULT_DOMAIN, $force = FALSE)
    {
        clearos_profile(__METHOD__, __LINE__);

        Validation_Exception::is_valid($this->validate_domain($domain));

        $options['background'] = TRUE;

        $force = ($force) ? '-f' : '';

        $shell = new Shell();
        $shell->execute(self::COMMAND_INITIALIZE, "-d '$domain' $force", TRUE, $options);
    }

    /**
     * Sets type of accounts access.
     *
     * @param string $type type
     *
     * @return void
     */

    public function set_accounts_access($type, $password = NULL)
    {
        clearos_profile(__METHOD__, __LINE__);

        Validation_Exception::is_valid($this->validate_accounts_access($type));

        $current_type = $this->get_accounts_access();

        if (!empty($password)) {
            Validation_Exception::is_valid($this->validate_password($password));
            $this->_initialize_accounts_dn($password);
        }

        // Bail if nothing needs to be done
        if ($current_type == $type)
            return;

        // There are two instances of "access to *" references in slapd.conf,
        // but we want the one near the end of the file.  Used line_count.

        $file = new File(self::FILE_CONFIG);
        $lines = $file->get_contents_as_array();
        $number_of_lines = count($lines);
        $line_count = 0;
        $check_newline = FALSE;

        $new_lines = array();

        foreach ($lines as $line) {
            $line_count++;

            if (preg_match('/^access\s+to\s+\*\s*$/', $line) && (($number_of_lines - $line_count) < 8)) {
                if ($type === self::ACCESS_ANONYMOUS) {
                    $new_lines[] = 'include /etc/openldap/clearos_anonymous.conf';
                    $new_lines[] = '';
                } else if ($type === self::ACCESS_PASSWORD_ACCESS) {
                    $new_lines[] = 'include /etc/openldap/clearos_password_protected.conf';
                    $new_lines[] = '';
                }
            } else if (preg_match('/^include\s+\/etc\/openldap\/clearos_.*conf\s*$/', $line)) {
                $check_newline = TRUE;
                continue;
            } else if ($check_newline) {
                $check_newlines = FALSE;
                if (preg_match('/^\s*$/', $line))
                    continue;
            }

            $new_lines[] = $line;
        }

        $file->dump_contents_from_array($new_lines);
    }

    /**
     * Changes base domain used in directory
     *
     * @param string $domain domain
     *
     * @return void
     */

    public function set_base_internet_domain($domain)
    {
        clearos_profile(__METHOD__, __LINE__);

        Validation_Exception::is_valid($this->validate_domain($domain));

        // Use the underlying LDAP driver to do most of the work
        //------------------------------------------------------

        $ldap = new LDAP_Driver();
        $ldap->set_base_internet_domain($domain);

        // Restart nscd
        //-------------

        try {
            $nscd = new Nscd();

            if ($nscd->get_running_state())
                $nscd->restart();
            else
                $nscd->set_running_state(TRUE);
        } catch (Exception $e) {
            // Not fatal
        }
    }

    ///////////////////////////////////////////////////////////////////////////////
    // V A L I D A T I O N
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * Validates accounts access type.
     *
     * @param string $type account access type
     *
     * @return string error message if type is invalid
     */

    public function validate_accounts_access($type)
    {
        clearos_profile(__METHOD__, __LINE__);

        if (!array_key_exists($type, $this->get_accounts_access_types()))
            return lang('openldap_directory_accounts_access_invalid');
    }

    /**
     * Validates domain.
     *
     * @param string $domain domain
     *
     * @return string error message if domain is invalid
     */

    public function validate_domain($domain)
    {
        clearos_profile(__METHOD__, __LINE__);

        if (! Network_Utils::is_valid_domain($domain))
            return lang('network_domain_invalid');
    }

    /**
     * Validates ID.
     *
     * @param string $id ID
     *
     * @return string error message if ID is invalid
     */

    public function validate_id($id)
    {
        clearos_profile(__METHOD__, __LINE__);

        // TODO
    }

    /**
     * Validates account password.
     *
     * @param string $password password
     *
     * @return string error message if password is invalid
     */

    public function validate_password($password)
    {
        clearos_profile(__METHOD__, __LINE__);

        // TODO
    }

    ///////////////////////////////////////////////////////////////////////////////
    // P R I V A T E  M E T H O D S
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * Adds account objects to LDAP.
     *
     * @param string $domain base domain
     *
     * @return void
     * @throws EngineException, ValidationException
     */

    protected function _add_account_objects($domain)
    {
        clearos_profile(__METHOD__, __LINE__);

        // Replace @@@base_dn@@@ in the template file
        //-------------------------------------------
        $base_dn = preg_replace('/\./', ',dc=', $domain);
        $base_dn = "dc=$base_dn";

        $ldif_template = clearos_app_base('openldap_directory') . '/' . self::FILE_LDIF_TEMPLATE;

        $file = new File($ldif_template);

        $contents = $file->get_contents();
        $contents = preg_replace("/\@\@\@base_dn\@\@\@/", $base_dn, $contents);

        $file = new File(self::FILE_LDIF_IMPORT, TRUE);
        if ($file->exists())
            $file->delete();

        $file->create('root', 'root', '0644');
        $file->add_lines("$contents\n");

        // Import the LDIF file
        //---------------------

        $ldap = new LDAP_Driver();
        $is_running = $ldap->get_running_state();

        if ($is_running) 
            $ldap->set_running_state(FALSE);

        $shell = new Shell();
        $shell->execute(self::COMMAND_SLAPADD, '-n3 -l ' . self::FILE_LDIF_IMPORT, TRUE);
        // $file->delete();

        // Fix file permissions
        //---------------------

        $folder = new Folder(self::PATH_LDAP);
        $folder->chown('ldap', 'ldap', TRUE);

        if ($is_running)
            $ldap->set_running_state(TRUE);
    }

    /**
     * Initializes authconfig.
     *
     * This method will update the nsswitch.conf and pam configuration.
     *
     * @return void
     */

    protected function _initialize_authconfig()
    {
        clearos_profile(__METHOD__, __LINE__);

        $shell = new Shell();
        $shell->execute(
            self::COMMAND_AUTHCONFIG, 
            '--enableshadow --passalgo=sha512 ' .
            '--enablecache --enablelocauthorize --enablemkhomedir ' .
            '--disablewinbind --disablewinbindauth ' .
            '--enableldap --enableldapauth --disablefingerprint --update', 
            TRUE
        );

        // TODO: the authconfig command seems to break the bind_dn in places (?)
        // Use the synchronize routine as a workaround.

        $ldap = new LDAP_Driver();
        $ldap->synchronize();
    }

    /**
     * Initializes authentication caching.
     *
     * @return void
     * @throws Engine_Exception
     */

    protected function _initialize_caching()
    {
        clearos_profile(__METHOD__, __LINE__);

        try {
            $nslcd = new Nslcd();
            $nslcd->set_boot_state(TRUE);

            if ($nslcd->get_running_state())
                $nslcd->reset(TRUE);
            else
                $nslcd->set_running_state(TRUE);

            $nscd = new Nscd();
            $nscd->set_boot_state(TRUE);

            if ($nscd->get_running_state())
                $nscd->reset(TRUE);
            else
                $nscd->set_running_state(TRUE);

        } catch (Engine_Exception $e) {
            // Not fatal
        }
    }

    /**
     * Initializes accounts access object.
     *
     * @return void
     * @throws Engine_Exception
     */

    protected function _initialize_accounts_dn($password)
    {
        clearos_profile(__METHOD__, __LINE__);

        // Create accouts access account in LDAP
        //--------------------------------------

        $ldap_object = array();
        $ldap_object['objectClass'] = array(
            'top',
            'inetOrgPerson',
        );

        $ldap_object['userPassword'] = '{sha}' . base64_encode(pack('H*', sha1($password)));
        $ldap_object['uid'] = 'accounts';
        $ldap_object['cn'] = 'accounts';
        $ldap_object['sn'] = 'Accounts Access User';

        $ldaph = Utilities::get_ldap_handle();
        $dn = $this->get_accounts_dn();

        for ($inx = 1; $inx < 3; $inx++) {
            try {
                if (! $ldaph->exists($dn))
                    $ldaph->add($dn, $ldap_object);
                else
                    $ldaph->modify($dn, $ldap_object);

                break;
            } catch (Exception $e) {
                sleep(1);
            }
        }
    }


    /**
     * Removes overlapping groups and users found in Posix.
     *
     * Some default users/groups found in the Posix system overlap with LDAP
     * entries.  For example, the group "users" is often listed in /etc/group.
     * Since a Windows Network considers the "Users" group in a special way,
     * it is best to not have it floating around.
     *
     * @return void
     * @throws Engine_Exception
     */

    protected function _remove_overlaps()
    {
        clearos_profile(__METHOD__, __LINE__);

        // TODO: move to a separate class in the base app, cleanup
        $file = new File('/etc/group');
        $file->replace_lines('/^users:/', '');

        $file = new File('/etc/default/useradd');
        $file->replace_lines('/^GROUP=/', "GROUP=" . User_Driver::DEFAULT_USER_GROUP_ID . "\n");
    }

    /**
     * Sets driver.
     *
     * @return void
     */

    protected function _set_driver()
    {
        clearos_profile(__METHOD__, __LINE__);

        $accounts = new Accounts_Configuration();
        $accounts->set_driver(self::DRIVER_NAME);
    }
}
