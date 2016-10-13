# ftpauth
AD Auth module for pure-ftpd in PHP

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
