<?php

/////////////////////////////////////////////////////////////////////////////
// General information
/////////////////////////////////////////////////////////////////////////////

$app['basename'] = 'openldap_directory';
$app['version'] = '1.5.5';
$app['release'] = '1';
$app['vendor'] = 'ClearFoundation';
$app['packager'] = 'ClearFoundation';
$app['license'] = 'GPLv3';
$app['license_core'] = 'LGPLv3';
$app['description'] = lang('openldap_directory_app_description');

/////////////////////////////////////////////////////////////////////////////
// App name and categories
/////////////////////////////////////////////////////////////////////////////

$app['name'] = lang('openldap_directory_app_name');
$app['category'] = lang('base_category_server');
$app['subcategory'] = lang('base_subcategory_directory');

/////////////////////////////////////////////////////////////////////////////
// Controllers
/////////////////////////////////////////////////////////////////////////////

$app['controllers']['openldap_directory']['title'] = lang('openldap_directory_app_name');

/////////////////////////////////////////////////////////////////////////////
// Packaging
/////////////////////////////////////////////////////////////////////////////

// FIXME: beta only - remove for final
$app['obsoletes'] = array(
    'app-directory-server',
);

$app['core_obsoletes'] = array(
    'app-directory-server-core',
);

$app['requires'] = array(
    'app-users',
    'app-groups => 1:1.2.3',
);

$app['core_provides'] = array(
    'system-accounts',
    'system-accounts-driver',
    'system-groups-driver',
    'system-users-driver',
);

$app['core_requires'] = array(
    'app-accounts-core >= 1:1.5.4',
    'app-groups-core',
    'app-ldap-core >= 1:1.4.5',
    'app-network-core',
    'app-openldap-core >= 1:1.4.8',
    'app-samba-extension-core',
    'app-users-core',
    'authconfig',
    'csplugin-filewatch',
    'nss-pam-ldapd',
    'nscd',
    'openldap',
    'openldap-clients',
    'openldap-servers',
    'pam_ldap',
    'webconfig-php-ldap'
);

$app['core_file_manifest'] = array(
    'filewatch-openldap-directory-mode.conf'=> array('target' => '/etc/clearsync.d/filewatch-openldap-directory-mode.conf'),
    'openldap_directory.php' => array( 'target' => '/var/clearos/accounts/drivers/openldap_directory.php' ),
    'nslcd.conf' => array( 'target' => '/var/clearos/ldap/synchronize/nslcd.conf' ),
    'pam_ldap.conf' => array( 'target' => '/var/clearos/ldap/synchronize/pam_ldap.conf' ),
    'app-openldap-directory-initialize' => array(
        'target' => '/usr/sbin/app-openldap-directory-initialize',
        'mode' => '0755',
    ),
);

$app['core_directory_manifest'] = array(
    '/var/clearos/openldap_directory' => array(),
    '/var/clearos/openldap_directory/backup' => array(),
    '/var/clearos/openldap_directory/extensions' => array(),
    '/var/clearos/openldap_directory/lock' => array(
        'mode' => '0775',
        'owner' => 'root',
        'group' => 'webconfig',
    ),
);
