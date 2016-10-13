#!/usr/bin/php -c /etc/php_pureftpd.ini
<?php
	/* ftpauth.php
	 *
	 * Authenticate users for pure-ftpd using pure-authd and local Active Directory
	 *
	 * (c)2016 Miles Lott <milos@groupwhere.org>
	 *
	 * This program is free software: you can redistribute it and/or modify
	 * it under the terms of the GNU General Public License as published by
	 * the Free Software Foundation, either version 3 of the License, or
	 * (at your option) any later version.
	 *
	 * This program is distributed in the hope that it will be useful,
	 * but WITHOUT ANY WARRANTY; without even the implied warranty of
	 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	 * GNU General Public License for more details.
	 *
	 * You should have received a copy of the GNU General Public License
	 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
	 */

	// Vars passed from authd
	$user = @$_ENV['AUTHD_ACCOUNT'];
	$pass = @$_ENV['AUTHD_PASSWORD'];

	$debug = False;
	$defaultuid = 10000;
	$defaultgid = 100;
	$defaultdir = '/nomansland';

	// START LDAP CONNECTION SETUP
	$domain = 'domain.tld';
	$base_dn = 'DC=domain,DC=tld';

	$ldaphosts = array(
		"ldap://dc1.$domain",
//		"ldaps://dc2.$domain",
//		"ldap://dc3.$domain",
//		"ldap://dc4.$domain"
	);

	/* Connect loop */
	$conn = False;
	while(!$conn)
	{
		foreach($ldaphosts as $ldaphost)
		{
			$ldap = @ldap_connect($ldaphost,389);
			if(@is_resource($ldap))
			{
				$conn = True;
			}
		}
	}
	if($debug)
	{
		ldap_set_option(NULL, LDAP_OPT_DEBUG_LEVEL, 7);
	}
	ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
	ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
	ldap_set_option($ldap, LDAP_OPT_NETWORK_TIMEOUT, 1000); /* Connection time out */
	ldap_set_option($ldap, LDAP_OPT_TIMELIMIT,60); /* For search, etc. */
	// END LDAP CONNECTION SETUP

	if($debug)
	{
		$fp = fopen('/tmp/test.log','a+');
		//fwrite($fp,implode($_ENV));
	}

	// AUTH USER
	if($user && $pass)
	{
		if($debug)
		{
			fwrite($fp, "USER: $user, PASS: $pass\n");
		}

		if(@ldap_bind($ldap,"$user@$domain",$pass))
		{
			// Get the user's uid, gid, and home dir
			$search_filter = '(!(userAccountControl:1.2.840.113556.1.4.803:=2))';
			$sr = ldap_search($ldap,$base_dn,"(&(samaccountname=$user)$search_filter)",array('dn','uidNumber','unixHomeDirectory','gidNumber'));
			$entry = ldap_get_entries($ldap, $sr);
			//if($debug)
			//{
			//	fwrite($fp, print_r($entry, True));
			//}
			if(@$entry['count'] > 0)
			{
				$uid = @$entry[0]['uidnumber'][0] ? $entry[0]['uidnumber'][0] : $defaultuid;
				$gid = @$entry[0]['gidnumber'][0] ? $entry[0]['gidnumber'][0] : 'oops';

				// Try to find a default gid if AD did not have one for us
				if($gid == 'oops')
				{
					$info = posix_getgrnam('ftp');
					// Use the ftp group or failover to defaultgid
					$gid = @$info['gid'] ? $info['gid'] : $defaultgid;
				}

				// Use the real dir from AD, or failover to a non-existent dir.
				// User will fail to complete login as a result.
				$dir = @$entry[0]['unixhomedirectory'][0] ? $entry[0]['unixhomedirectory'][0] : $defaultdir;

				if($debug)
				{
					fwrite($fp,"$uid;$gid;$dir\n"); 
				}

				// Finally, verify that the user's home dir is present
				if(@is_dir($dir))
				{
					// SUCCESS
					echo "auth_ok:1\n",
						"uid:$uid\n",
						"gid:$gid\n",
						"dir:$dir\n",
						"end\n";
					return;
				}
				else
				{
					// HARD FAILURE - BAD DIR
					echo "auth_ok:-1\n",
						"uid:10000\n",
						"gid:10000\n",
						"dir:$dir\n",
						"end\n";
				}
			}
		}
		else
		{
			if($debug)
			{
				fwrite($fp, "Unable to bind user\n");
			}
		}
		if($debug)
		{
			fclose($fp);
		}
		ldap_close($ldap);
		// SOFT FAILURE - try other auth such as mysql or ldap if enabled
		echo "auth_ok:0\n",
			"uid:10000\n",
			"gid:10000\n",
			"dir:$dir\n",
			"end\n";
	}
	else
	{
		ldap_close($ldap);
		if($debug)
		{
			fwrite($fp, "BAD LOGIN\n");
			fclose($fp);
		}
		// HARD FAILURE - NO USER OR PASS SENT
		echo "auth_ok:-1\n",
			"uid:10000\n",
			"gid:10000\n",
			"dir:$dir\n",
			"end\n";
	}
