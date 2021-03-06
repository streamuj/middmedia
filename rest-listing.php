<?php
/**
 * This is a soap endpoint for MiddMedia
 *
 * @package middmedia
 *
 * @copyright Copyright &copy; 2005, Middlebury College
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 *
 * @version $Id$
 */

/*********************************************************
 * Setup stuff.
 *********************************************************/

define("MYDIR",dirname(__FILE__));

if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on')
	$protocol = 'https';
else
	$protocol = 'http';

define("MYPATH", $protocol."://".$_SERVER['HTTP_HOST'].str_replace(
												"\\", "/",
												dirname($_SERVER['PHP_SELF'])));
define("MYURL", MYPATH."/index.php");

require_once(dirname(__FILE__)."/main/include/libraries.inc.php");
require_once(dirname(__FILE__)."/main/include/setup.inc.php");



/*********************************************************
 * Authentication
 *********************************************************/
if (!isset($_SERVER['PHP_AUTH_USER']) || !$_SERVER['PHP_AUTH_USER'] || (isset($_SESSION['LastLoginTokens'])
	&& 	md5($_SERVER['PHP_AUTH_USER'].$_SERVER['PHP_AUTH_PW'])
		!= $_SESSION['LastLoginTokens']))
{
	header("WWW-Authenticate: Basic realm=\"MiddMedia\"");
	header('HTTP/1.0 401 Unauthorized');
	print "Please Authenticate.";
	exit;
}
$_SESSION['LastLoginTokens'] = md5($_SERVER['PHP_AUTH_USER'].$_SERVER['PHP_AUTH_PW']);
$user = $_SERVER['PHP_AUTH_USER'];
$pass = $_SERVER['PHP_AUTH_PW'];

/*********************************************************
 * Below here is just example stuff. Change to be implementation.
 *********************************************************/
header('Content-Type: text/xml');
print "<response>";

try {
	// Create a new manager for a username/password combo (username/shared key not yet implemented)
	$manager = MiddMedia_Manager::forUsernamePassword($user, $pass);

	// Get the personal directory
	try {
		$dir = $manager->getPersonalDirectory();
		print "\n\t<directory
					name=\"".$dir->getBaseName()."\"
					rtmp_url=\"".$dir->getRtmpUrl()."\"
					bytes_used=\"".$dir->getBytesUsed()."\"
					bytes_available=\"".$dir->getBytesAvailable()."\"
					type=\"personal\">";

		foreach ($dir->getFiles() as $file) {
			$primaryFormat = $file->getPrimaryFormat();
			if ($primaryFormat->supportsHttp())
				$httpUrl = $primaryFormat->getHttpUrl();
			else
				$httpUrl = '';
			if ($primaryFormat->supportsRtmp())
				$rtmpUrl = $primaryFormat->getRtmpUrl();
			else
				$rtmpUrl = '';
			print "\n\t\t<file
						name=\"".$file->getBaseName()."\"
						http_url=\"".$httpUrl."\"
						rtmp_url=\"".$rtmpUrl."\"
						mime_type=\"".$primaryFormat->getMimeType()."\"
						size=\"".$primaryFormat->getSize()."\"
						modification_date=\"".$file->getModificationDate()->asString()."\"";
			try {
				print "\n\t\t\tcreator_name=\"".$file->getCreator()->getDisplayName()."\"";
			} catch (OperationFailedException $e) {
			} catch (UnimplementedException $e) {
			}

			// As an example, lets include the content of text-files.
			if ($file->getMimeType() == 'text/plain') {
				print "><![CDATA[";
				print $file->getContents();
				print "]]></file>";
			} else {
				print "/>";
			}
		}

		print "\n\t</directory>";
	} catch (PermissionDeniedException $e) {
	}

	// Get the shared directories
	foreach ($manager->getSharedDirectories() as $dir) {
		print "\n\t<directory
				name=\"".$dir->getBaseName()."\"
				rtmp_url=\"".$dir->getRtmpUrl()."\"
				bytes_used=\"".$dir->getBytesUsed()."\"
				bytes_available=\"".$dir->getBytesAvailable()."\"
				type=\"shared\">";

		foreach ($dir->getFiles() as $file) {
			$primaryFormat = $file->getPrimaryFormat();
			if ($primaryFormat->supportsHttp())
				$httpUrl = $primaryFormat->getHttpUrl();
			else
				$httpUrl = '';
			if ($primaryFormat->supportsRtmp())
				$rtmpUrl = $primaryFormat->getRtmpUrl();
			else
				$rtmpUrl = '';
			print "\n\t\t<file
					name=\"".$file->getBaseName()."\"
					http_url=\"".$httpUrl."\"
					rtmp_url=\"".$rtmpUrl."\"
					mime_type=\"".$primaryFormat->getMimeType()."\"
					size=\"".$primaryFormat->getSize()."\"
					modification_date=\"".$file->getModificationDate()->asString()."\"";

			try {
				print "\n\t\t\tcreator_name=\"".$file->getCreator()->getDisplayName()."\"";
			} catch (OperationFailedException $e) {
			} catch (UnimplementedException $e) {
			}

			// As an example, lets include the content of text-files.
			if ($file->getMimeType() == 'text/plain') {
				print "><![CDATA[";
				print $file->getContents();
				print "]]></file>";
			} else {
				print "/>";
			}
		}

		print "\n\t</directory>";
	}
} catch (Exception $e) {
	print "\n\t<error type='".get_class($e)."'><![CDATA[".$e->getMessage()."]]></error>";
}

print "\n</response>";
