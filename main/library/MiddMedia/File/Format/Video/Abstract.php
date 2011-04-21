<?php
/**
 * @package middmedia
 * 
 * @copyright Copyright &copy; 2010, Middlebury College
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 */

/**
 * Support for video info.
 * 
 * @package middmedia
 * 
 * @copyright Copyright &copy; 2010, Middlebury College
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 */
abstract class MiddMedia_File_Format_Video_Abstract
	extends MiddMedia_File_Format_Abstract
	implements MiddMedia_File_Format_Video_InfoInterface
{
	
	private $videoCodec;
	private $colorspace;
	private $width;
	private $height;
	private $frameRate;
	private $audioCodec;
	private $audioSampleRate;
	private $audioChannels;
	private $containerFormat;
	private $populated = false;
	
	
	
	/**
	 * Populate our video info.
	 * 
	 * @return void
	 */
	private function populateInfo () {
		if ($this->populated)
			return;
		
		if (!defined('FFMPEG_PATH'))
			throw new ConfigurationErrorException('FFMPEG_PATH is not defined');
		
		$command = FFMPEG_PATH.' -i '.escapeshellarg($this->getPath()).' 2>&1';
		$lastLine = exec($command, $output, $return_var);
		$output = implode("\n", $output);
		
		if (!preg_match('/Stream #[^:]+: Video: ([^,]+), (?:([^,]+), )?([0-9]+)x([0-9]+)[^,]*, ([0-9\.]+) (?:tbr|kb\/s),/', $output, $matches))
			throw new OperationFailedException("Could not determine video properties from: <pre>\n$output\n</pre>\n");
		$this->videoCodec = $matches[1];
		$this->colorspace = $matches[2];
		$this->width = intval($matches[3]);
		$this->height = intval($matches[4]);
		$this->framerate = floatval($matches[5]);
		
		if (preg_match('/Stream #[^:]+: Audio: ([^,]+), ([0-9]+) Hz, ([0-9]+) channels/', $output, $matches)) {
			$this->audioCodec = $matches[1];
			$this->audioSampleRate = intval($matches[2]);
			$this->audioChannels = intval($matches[3]);
		} else {
			$this->audioCodec = null;
			$this->audioSampleRate = null;
			$this->audioChannels = null;
		}
		
		if (preg_match('/Input #[^,]+, ([^ ]+), .+/', $output, $matches)) {
			$this->containerFormat = $matches[1];
		} else {
			$this->containerFormat = null;
		}
		
		$this->populated = true;
	}
	
	/*********************************************************
	 * MiddMedia_File_Image_InfoInterface
	 *********************************************************/
	
	/**
	 * Answer the width of the image in pixels.
	 * 
	 * @return int
	 */
	public function getWidth () {
		$this->populateInfo();
		return $this->width;
	}
	
	/**
	 * Answer the height of the image in pixels.
	 * 
	 * @return int
	 */
	public function getHeight () {
		$this->populateInfo();
		return $this->height;
	}
	
	/*********************************************************
	 * MiddMedia_File_Format_Video_InfoInterface
	 *********************************************************/
		
	/**
	 * Answer the video codec used.
	 * 
	 * @return string
	 */
	public function getVideoCodec () {
		$this->populateInfo();
		return $this->videoCodec;
	}
	
	/**
	 * Answer the frame rate of the video.
	 * 
	 * @return float
	 */
	public function getVideoFrameRate () {
		$this->populateInfo();
		return $this->frameRate;
	}
	
	/**
	 * Answer the container format
	 * 
	 * @return string
	 */
	public function getContainerFormat () {
		$this->populateInfo();
		return $this->containerFormat;
	}
	
	/*********************************************************
	 * MiddMedia_File_Format_Image_InfoInterface
	 *********************************************************/
	
	/**
	 * Answer the audio codec used.
	 * 
	 * @return string
	 */
	public function getAudioCodec () {
		$this->populateInfo();
		return $this->audioCodec;
	}
	
	/**
	 * Answer the sample rate of the audio.
	 * 
	 * @return int
	 */
	public function getAudioSampleRate () {
		$this->populateInfo();
		return $this->audioSampleRate;
	}
	
	/**
	 * Answer the number of channels in the audio.
	 * 
	 * @return int
	 */
	public function getAudioChannels () {
		$this->populateInfo();
		return $this->audioChannels;
	}
	
	/*********************************************************
	 * Shared helper methods
	 *********************************************************/
	/**
	 * Answer the target size based on the input size and maximums.
	 * 
	 * @param int $width
	 * @param int $height
	 * @return string  Width 'x' height. E.g. 720x480
	 */
	protected function getTargetDimensions ($width, $height) {
		// Determine the output size base on our maximums.
		if ($width > MIDDMEDIA_CONVERT_MAX_WIDTH) {
			$ratio = MIDDMEDIA_CONVERT_MAX_WIDTH / $width;
			$width = MIDDMEDIA_CONVERT_MAX_WIDTH;
			$height = round($ratio * $height);
		}
		if ($height > MIDDMEDIA_CONVERT_MAX_HEIGHT) {
			$ratio = MIDDMEDIA_CONVERT_MAX_HEIGHT / $height;
			$width = round($ratio * $width);
			$height = MIDDMEDIA_CONVERT_MAX_HEIGHT;
		}
		// Round to the nearest multiple of 2 as this is required for frame sizes.
		$width = round($width/2) * 2;
		$height = round($height/2) * 2;
		
		return $width.'x'.$height;
	}
	
	/**
	 * Answer the target audio sample rate based on the input sample rate.
	 * 
	 * @param int $sampleRate
	 * @return int
	 */
	protected function getTargetSampleRate ($sampleRate) {
		// Some audio sample rates die, so force to the closest of 44100, 22050, 11025
		if (in_array($sampleRate, array(44100, 22050, 11025)))
			return $sampleRate;
		else if ($sampleRate < 16538)
			return 11025;
		else if ($sampleRate < 33075)
			return 22050;
		else
			return 44100;
	}
}
