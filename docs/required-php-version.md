## Minimum required PHP version

Many shared hosts may use an outdated version of PHP by default. **Static Site Exporter requires PHP 8.2 or greater.**

If you get an error message that looks like `unexpected T_STRING`, `unexpected '['` or `expecting T_CONSTANT_ENCAPSED_STRING`, you need to update your PHP version. In a shared hosting environment, you should be able to change the version of PHP used by simply toggling the setting in the host's control panel.

PHP 5.4 lost support from the PHP project itself in 2015. You'll need to be running at least PHP 5.5 which adds namespace support (the reason it's breaking), but I'd recommend at least 7.3 (or the latest your host supports) as it's the [oldest supported version](https://www.php.net/supported-versions.php).

### How to determine which version of PHP you're running

* Try [this plugin](https://wordpress.org/plugins/display-php-version/)
* Follow [WordPress's tutorial](https://codex.wordpress.org/Finding_Server_Info) or [this wikihow](https://www.wikihow.com/Check-PHP-Version)

### How to upgrade your version of PHP

If you are using a shared hosting environment, upgrading to a newer version of PHP should be a matter of changing a setting in your host's control panel. You'll have to follow your host specific documentation to determine how to access it or where the setting lives. Check out [this list of common hosts](https://kb.yoast.com/kb/how-to-update-your-php-version/) for more details.
