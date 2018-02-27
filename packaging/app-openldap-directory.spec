
Name: app-openldap-directory
Epoch: 1
Version: 2.4.0
Release: 1%{dist}
Summary: Directory Server
License: GPLv3
Group: ClearOS/Apps
Source: %{name}-%{version}.tar.gz
Buildarch: noarch
Requires: %{name}-core = 1:%{version}-%{release}
Requires: app-base
Requires: app-users
Requires: app-groups => 1:1.5.10

%description
The Directory Server provides information on users, groups and systems.  You can provide read only access to your directory to integrate third party applications both locally and in the cloud.

%package core
Summary: Directory Server - Core
License: LGPLv3
Group: ClearOS/Libraries
Provides: system-accounts
Provides: system-accounts-driver
Provides: system-groups-driver
Provides: system-users-driver
Requires: app-base-core
Requires: app-base-core >= 1:1.5.40
Requires: app-accounts-core >= 1:1.5.40
Requires: app-events-core
Requires: app-groups-core
Requires: app-ldap-core >= 1:1.4.5
Requires: app-mode-core >= 1:1.5.40
Requires: app-network-core
Requires: app-openldap-core >= 1:1.4.8
Requires: app-samba-extension-core
Requires: app-users-core
Requires: authconfig
Requires: nss-pam-ldapd
Requires: nscd
Requires: openldap
Requires: openldap-clients
Requires: openldap-servers
Requires: pam_ldap
Requires: webconfig-php-ldap

%description core
The Directory Server provides information on users, groups and systems.  You can provide read only access to your directory to integrate third party applications both locally and in the cloud.

This package provides the core API and libraries.

%prep
%setup -q
%build

%install
mkdir -p -m 755 %{buildroot}/usr/clearos/apps/openldap_directory
cp -r * %{buildroot}/usr/clearos/apps/openldap_directory/

install -d -m 0755 %{buildroot}/var/clearos/openldap_directory
install -d -m 0755 %{buildroot}/var/clearos/openldap_directory/backup
install -d -m 0755 %{buildroot}/var/clearos/openldap_directory/extensions
install -d -m 0775 %{buildroot}/var/clearos/openldap_directory/lock
install -D -m 0755 packaging/app-openldap-directory-initialize %{buildroot}/usr/sbin/app-openldap-directory-initialize
install -D -m 0644 packaging/clearos_anonymous.conf %{buildroot}/var/clearos/ldap/synchronize/clearos_anonymous.conf
install -D -m 0644 packaging/clearos_password_protected.conf %{buildroot}/var/clearos/ldap/synchronize/clearos_password_protected.conf
install -D -m 0755 packaging/mode-event %{buildroot}/var/clearos/events/mode/openldap_directory
install -D -m 0644 packaging/nslcd.conf %{buildroot}/var/clearos/ldap/synchronize/nslcd.conf
install -D -m 0644 packaging/openldap_directory.php %{buildroot}/var/clearos/accounts/drivers/openldap_directory.php
install -D -m 0644 packaging/pam_ldap.conf %{buildroot}/var/clearos/ldap/synchronize/pam_ldap.conf

%post
logger -p local6.notice -t installer 'app-openldap-directory - installing'

%post core
logger -p local6.notice -t installer 'app-openldap-directory-core - installing'

if [ $1 -eq 1 ]; then
    [ -x /usr/clearos/apps/openldap_directory/deploy/install ] && /usr/clearos/apps/openldap_directory/deploy/install
fi

[ -x /usr/clearos/apps/openldap_directory/deploy/upgrade ] && /usr/clearos/apps/openldap_directory/deploy/upgrade

exit 0

%preun
if [ $1 -eq 0 ]; then
    logger -p local6.notice -t installer 'app-openldap-directory - uninstalling'
fi

%preun core
if [ $1 -eq 0 ]; then
    logger -p local6.notice -t installer 'app-openldap-directory-core - uninstalling'
    [ -x /usr/clearos/apps/openldap_directory/deploy/uninstall ] && /usr/clearos/apps/openldap_directory/deploy/uninstall
fi

exit 0

%files
%defattr(-,root,root)
/usr/clearos/apps/openldap_directory/controllers
/usr/clearos/apps/openldap_directory/htdocs
/usr/clearos/apps/openldap_directory/views

%files core
%defattr(-,root,root)
%exclude /usr/clearos/apps/openldap_directory/packaging
%exclude /usr/clearos/apps/openldap_directory/unify.json
%dir /usr/clearos/apps/openldap_directory
%dir /var/clearos/openldap_directory
%dir /var/clearos/openldap_directory/backup
%dir /var/clearos/openldap_directory/extensions
%dir %attr(0775,root,webconfig) /var/clearos/openldap_directory/lock
/usr/clearos/apps/openldap_directory/deploy
/usr/clearos/apps/openldap_directory/language
/usr/clearos/apps/openldap_directory/libraries
/usr/sbin/app-openldap-directory-initialize
/var/clearos/ldap/synchronize/clearos_anonymous.conf
/var/clearos/ldap/synchronize/clearos_password_protected.conf
/var/clearos/events/mode/openldap_directory
%config(noreplace) /var/clearos/ldap/synchronize/nslcd.conf
/var/clearos/accounts/drivers/openldap_directory.php
/var/clearos/ldap/synchronize/pam_ldap.conf
