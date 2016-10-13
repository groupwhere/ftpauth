# ftpauth
AD Auth module for pure-ftpd in PHP

First, note the shebang line (first line) for php which includes a specific php.ini file.  This is optional, but whichever php.ini file is used, it should enable the following:

	variables_order = "EGPCS"

In any event, that setting should contain 'E' so that environment variables are registered.  This is required for use with pure-authd.

The script assumes that your user accounts have the following unix attributes, which should be possible without schema modification, but ymmv:

	uidNumber
	gidNumber
	unixHomeDirectory

As designed, it will attempt to find those values after binding as the user.  If it cannot find those values, defaults are also set in the script (edit this near the top as desired).

The user must be enabled.  This is determined by the search_filter variable, which contains a common value for userAccountControl which should include any case of an enabled user account.  You can leave this out if you want to auth everyone for some reason.

Default values:

	uidNumber = 10000
	gidNumber = 100
	unixHomeDirectory = /nomansland

The default directory should either be unavailable or it could be a default common directory if you wish.  If you want users to be able to upload and delete, be sure to set directory permissions to match the defaults for uidNumber and gidNumber.

Edit the script to adjust for your AD domain and base_dn to search:

	$domain = 'domain.tld';
	$base_dn = 'DC=domain,DC=tld'; // OR OU=FTP Users,DC=domain,DC=tld, etc...

Also, edit a list of available domain controllers:

	$ldaphosts = array(
		"ldaps://dc1.$domain"
	);

The script is designed to loop through an array of hosts to obtain a connection.  Feel free to edit that functionality as needed.

Then run pure-authd:

	/usr/sbin/pure-authd -r /path/to/ftpauth.php -s /var/run/ftpd.sock

The socket file /var/run/ftpd.sock should match whatever is set in /etc/pure-ftpd/pure-ftpd.conf:

	# Path to pure-authd socket (see README.Authentication-Modules)
	ExtAuth                       /var/run/ftpd.sock
