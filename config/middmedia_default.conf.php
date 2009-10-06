<?php

define('FFMPEG_PATH', '/usr/local/bin/ffmpeg');
define('IMAGE_MAGICK_COMPOSITE_PATH', '/usr/local/bin/composite');
define('IMAGE_MAGICK_CONVERT_PATH', '/usr/local/bin/convert');
define('MIDDMEDIA_DEFAULT_FRAME_PATH', MYDIR.'/images/Black.jpg');
define('MIDDMEDIA_SPLASH_OVERLAY', MYDIR.'/images/splash-overlay.png');

define('MIDDMEDIA_VIDEO_EMBED_CODE', '<embed src="http://middmedia.middlebury.edu/flowplayer/FlowPlayerLight.swf?config=%7Bembedded%3Atrue%2CstreamingServerURL%3A%27rtmp%3A%2F%2Fmiddmedia.middlebury.edu%2Fvod%27%2CautoPlay%3Afalse%2Cloop%3Afalse%2CinitialScale%3A%27fit%27%2CvideoFile%3A%27###ID###%27%2CsplashImageFile%3A%27###SPLASH_URL###%27%7D" width="400" height="300" scale="fit" bgcolor="#111111" type="application/x-shockwave-flash" allowFullScreen="true" allowNetworking="all" pluginspage="http://www.macromedia.com/go/getflashplayer"></embed>');

define('MIDDMEDIA_AUDIO_EMBED_CODE', '<script type="text/javascript" src="http://middmedia.middlebury.edu/AudioPlayer/audio-player.js"></script><object width="290" height="24" id="###HTML_ID###" data="http://middmedia.middlebury.edu/AudioPlayer/player.swf" type="application/x-shockwave-flash"><param value="http://middmedia.middlebury.edu/AudioPlayer/player.swf" name="movie" /><param value="high" name="quality" /><param value="false" name="menu" /><param value="transparent" name="wmode" /><param value="soundFile=###HTTP_URL###" name="FlashVars" /></object>');

define('MIDDMEDIA_FS_BASE_DIR', '/home/afranco/public_html/middmedia_data');
define('MIDDMEDIA_HTTP_BASE_URL', 'http://chisel.middlebury.edu/~afranco/middmedia_data');
define('MIDDMEDIA_RTMP_BASE_URL', 'rtmp://chisel.middlebury.edu/fms');


/*********************************************************
 * If a single valued property can be used for the directory name, specify it here.
 *********************************************************/
// define('MIDDMEDIA_GROUP_DIRNAME_PROPERTY', 'mail nickname');

/*********************************************************
 * Alternatively, specify a callback function which takes a group.
 *********************************************************/
define('MIDDMEDIA_GROUP_DIRNAME_CALLBACK', 'getGroupDirname');
function getGroupDirname(Group $group) {
	$propertiesIterator = $group->getProperties();
	while ($propertiesIterator->hasNext()) {
		$properties = $propertiesIterator->next();
		if ($properties->getProperty('EMail')) {
			return substr($properties->getProperty('EMail'), 0, strpos($properties->getProperty('EMail'), '@'));
		}
	}
	throw new OperationFailedException('Could not find an EMail property for group '.$group->getId()->getIdString());	
}


define('MIDDMEDIA_ALLOWED_FILE_TYPES', 'mp3, mp4, flv, avi, asf, dv, m4v, mj2, mjp, mjpg, mkv, mov, mpeg, mpg, ogv, qt, rv, swf, wm, wmv');
define('MIDDMEDIA_CONVERT_MAX_HEIGHT', 480);
define('MIDDMEDIA_CONVERT_MAX_WIDTH', 720);
define('MIDDMEDIA_TMP_DIR', '/tmp');

MiddMediaManager::addPersonalDirectoryGroup('CN=All Faculty,OU=General,OU=Groups,DC=middlebury,DC=edu');
MiddMediaManager::addPersonalDirectoryGroup('CN=All Staff,OU=General,OU=Groups,DC=middlebury,DC=edu');

// MiddMediaManager::addTrustedServiceKey('blogs', '1234567890abcdefghijkl');	// Key for a remote service
// MiddMediaManager::addTrustedServiceKey('wiki', 'abcdefghijklmnop');	// Key for a second remote service