# Make twitter.com Work Again!

This is a proxy server application I threw together based on the [Rehike source code](//github.com/Rehike/Rehike).

It proxies twitter.com requests to act as if they were made on x.com. This allows you to continue accessing twitter.com instead of being redirected to x.com.

Note that I currently don't have the redirection experiment, so this isn't properly tested.

# Installation

[Please see the Rehike installation instructions for more information.](//github.com/Rehike/Rehike/wiki/Installation)

## Setting up Apache

You need to setup vhosts. Here is my configuration:

```xml
<VirtualHost *:80>
    DocumentRoot "D:/xampp/htdocs_twitterredirect"
    ServerName 127.0.0.8
    <Directory "D:/xampp/htdocs_twitterredirect">
		AllowOverride All
        Require all granted 
    </Directory>
</VirtualHost>

<VirtualHost *:443>
    DocumentRoot "D:/xampp/htdocs_twitterredirect"
    ServerName 127.0.0.8
    ServerName twitter.com
    SSLEngine on
    SSLCertificateFile "D:/xampp/htdocs_twitterredirect/cert/server.crt"
    SSLCertificateKeyFile "D:/xampp/htdocs_twitterredirect/cert/server.key"
    <Directory "D:/xampp/htdocs_twitterredirect">
		AllowOverride All
        Require all granted 
    </Directory>
</VirtualHost>
```

## Setting up your browser

Install [cert/ca-crt.pem](/cert/ca-crt.pem) into your browser as a certificate authority.

## Setting up your OS

Add `127.0.0.8 twitter.com` to the hosts file (adjust if you change the localhost IP).