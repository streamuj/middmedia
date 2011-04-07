<?php
/**
 * @package middmedia
 * 
 * @copyright Copyright &copy; 2010, Middlebury College
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 */ 

require_once(HARMONI.'/utilities/Filing/FileSystemFile.class.php');
require_once(dirname(__FILE__).'/Media.interface.php');
require_once(dirname(__FILE__).'/Format/Video/Source.class.php');
require_once(dirname(__FILE__).'/Format/Video/Mp4.class.php');
require_once(dirname(__FILE__).'/Format/Image/FullFrame.class.php');
require_once(dirname(__FILE__).'/Format/Image/Thumbnail.class.php');
require_once(dirname(__FILE__).'/Format/Image/Splash.class.php');

if (version_compare(PHP_VERSION, '5.2.0', '<'))
	throw new Exception('MiddMedia Requires PHP >= 5.2.0');

/**
 * A Media file is a link to the default version of an audio or video file as well
 * as a collection for accessing all versions of the the media.
 * 
 * @package middmedia
 * 
 * @copyright Copyright &copy; 2010, Middlebury College
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 */
class MiddMedia_File_Media
	extends Harmoni_Filing_FileSystemFile
	implements MiddMedia_File_MediaInterface
{
		
	/**
	 * Answer true if the file name is valid, false otherwise
	 * 
	 * @param string $name
	 * @return boolean
	 * @access public
	 * @since 11/19/08
	 * @static
	 */
	public static function nameValid ($name) {
		return (preg_match('/^[a-z0-9_+=,.?#@%^!~\'&\[\]{}()<>\s-]+$/i', $name) && strlen($name) < 260);
	}
	
	/**
	 * Answer an array of allowed extensions
	 * 
	 * @return array
	 * @access public
	 * @since 9/24/09
	 * @static
	 */
	public static function getAllowedVideoTypes () {
		$types = explode(",", MIDDMEDIA_ALLOWED_FILE_TYPES);
		array_walk($types, 'trim');
		array_walk($types, 'strtolower');
		return $types;
	}
	
	/**
	 * Answer video information
	 * 
	 * @param string $filePath
	 * @return array
	 * @access public
	 * @since 9/24/09
	 * @static
	 */
	public static function getVideoInfo ($filePath) {
		if (!file_exists($filePath))
			throw new OperationFailedException("File doesn't exist.");
		
		if (!defined('FFMPEG_PATH'))
			throw new ConfigurationErrorException('FFMPEG_PATH is not defined');
		
		$command = FFMPEG_PATH.' -i '.escapeshellarg($filePath).' 2>&1';
		$lastLine = exec($command, $output, $return_var);
		$output = implode("\n", $output);
		
		if (!preg_match('/Stream #[^:]+: Video: ([^,]+), (?:([^,]+), )?([0-9]+)x([0-9]+)[^,]*, ([0-9\.]+) (?:tbr|kb\/s),/', $output, $matches))
			throw new OperationFailedException("Could not determine video properties from: <pre>\n$output\n</pre>\n");
		$info['codec'] = $matches[1];
		$info['colorspace'] = $matches[2];
		$info['width'] = intval($matches[3]);
		$info['height'] = intval($matches[4]);
		$info['framerate'] = floatval($matches[5]);
		
		if (preg_match('/Stream #[^:]+: Audio: ([^,]+), ([0-9]+) Hz, ([0-9]+) channels/', $output, $matches)) {
			$info['audio_codec'] = $matches[1];
			$info['audio_samplerate'] = intval($matches[2]);
			$info['audio_channels'] = intval($matches[3]);
		}
		return $info;
	}
	
	/**
	 * Create a new empty file in this directory. Similar to touch().
	 * 
	 * This method throws the following exceptions:
	 *		InvalidArgumentException 	- If incorrect parameters are supplied
	 *		OperationFailedException 	- If the file already exists.
	 *		PermissionDeniedException 	- If the user is unauthorized to manage media here.
	 * 
	 * @param string $name
	 * @return object MiddMedia_File_MediaInterface The new file
	 */
	public static function create (MiddMedia_Directory $directory, $name) {
		if (!self::nameValid($name))
			throw new InvalidArgumentException("Invalid file name '$name'.");
		
		$pathInfo = pathinfo($name);
		
		$extension = strtolower($pathInfo['extension']);
		$noExtension =  $pathInfo['filename'];
		
		if ($extension == 'mp3')
			$basename = $noExtension.'.mp3';
		else
			$basename = $noExtension.'.mp4';
		
		if ($directory->fileExists($basename))
			throw new OperationFailedException("File already exists.");
		
		// Create a placeholder file and set metadata
		touch($directory->getFsPath().'/'.$basename);
		$media = new MiddMedia_File_Media($directory, $basename);
		$media->setCreator($directory->getManager()->getAgent());
		
		return $media;
	}
	
	/**
	 * Get an existing file in a directory.
	 * 
	 * This method throws the following exceptions:
	 *		InvalidArgumentException 	- If incorrect parameters are supplied
	 *		OperationFailedException 	- If the file doesn't exist.
	 *		PermissionDeniedException 	- If the user is unauthorized to manage media here.
	 * 
	 * @param MiddMedia_Directory $directory
	 * @param string $name
	 * @return object MiddMedia_File_MediaInterface The new file
	 */
	public static function get (MiddMedia_Directory $directory, $name) {
		return new MiddMedia_File_Media($directory, $name);
	}
	
	/**
	 * Constructor.
	 * 
	 * @param object MiddMedia_Directory $directory
	 * @param string $basename
	 * @return void
	 */
	public function __construct (MiddMedia_Directory $directory, $basename) {
		$this->directory = $directory;
		if (!self::nameValid($basename))
			throw new InvalidArgumentException('Invalid file name \''.$basename.'\'');
		
		parent::__construct($directory->getFSPath().'/'.$basename);
	}
	
	/**
	 * Move an uploaded file into our file and hand any conversion if needed.
	 * 
	 * @param string $tempName
	 * @return void
	 * @access public
	 * @since 9/24/09
	 */
	public function moveInUploadedFile ($tempName) {
		// MP3 audio only has a single version, so just store it.
		if ($this->getExtension() == 'mp3') {
			$mp3Format = $media->setPrimaryFormat(MiddMedia_File_Format_Audio_Mp3::create($this));
			$mp3Format->moveInUploadedFile($tempName);
			return;
		}
		
		// Store the temporary file in a source format, then queue for processing.
		$sourceFormat = MiddMedia_File_Format_Video_Source::create($this);
		$sourceFormat->moveInUploadedFile($tempName);
		
		// Create our placeholder formats
		$format = MiddMedia_File_Format_Video_Mp4::create($this);
		$format->putContents(file_get_contents(MYDIR.'/images/ConvertingVideo.mp4'));
		$this->setPrimaryFormat($format);
		
		$format = MiddMedia_File_Format_Image_Thumbnail::create($this);
		$format->putContents(file_get_contents(MYDIR.'/images/ConvertingVideo.jpg'));
		
		$format = MiddMedia_File_Format_Image_FullFrame::create($this);
		$format->putContents(file_get_contents(MYDIR.'/images/ConvertingVideo.jpg'));
		
		$format = MiddMedia_File_Format_Image_Splash::create($this);
		$format->putContents(file_get_contents(MYDIR.'/images/ConvertingVideo.jpg'));
		
		$this->queueForProcessing();
		
		$this->logAction('upload');
	}
	
	/**
	 * Add a new format for this media.
	 * 
	 * @param MiddMedia_File $formatFile
	 * @return MiddMedia_File The new format file
	 */
	protected function setPrimaryFormat (MiddMedia_File_FormatInterface $formatFile) {
		unlink($this->getPath());
		symlink($formatFile->getPath(), $this->getPath());
	}
	
	/**
	 * Queue a file for conversion to mp4.
	 * 
	 * @param string $tempName
	 * @return void
	 */
	protected function queueForProcessing () {		
		// Add an entry to our encoding queue.
		$query = new InsertQuery;
		$query->setTable('middmedia_queue');
		$query->addValue('directory', $this->directory->getBaseName());
		$query->addValue('file', $this->getBaseName());
		
		$dbMgr = Services::getService("DatabaseManager");
		try {
			$dbMgr->query($query, HARMONI_DB_INDEX);
		} catch (DuplicateKeyDatabaseException $e) {
			// If the file was re-uploaded, update the the timestamp.
			$query = new UpdateQuery;
			$query->setTable('middmedia_queue');
			$query->addRawValue('upload_time', 'NOW()');
			$query->addWhereEqual('directory', $this->directory->getBaseName());
			$query->addWhereEqual('file', $this->getBaseName());
			$dbMgr->query($query, HARMONI_DB_INDEX);
		}
	}
	
	/**
	 * Remove this file from the processing queue
	 * 
	 * @since 9/25/09
	 */
	protected function removeFromQueue () {
		$dbMgr = Services::getService("DatabaseManager");
		
		// Remove from the queue
		$query = new DeleteQuery;
		$query->setTable('middmedia_queue');
		$query->addWhereEqual('directory', $this->directory->getBaseName());
		$query->addWhereEqual('file', $this->getBaseName());
		$dbMgr->query($query, HARMONI_DB_INDEX);
	}
	
	/**
	 * Process any uploaded versions of this file.
	 * This method does no locking. Clients must handle locking to prevent multiple
	 * processing threads from clobbering each other's results
	 * 
	 * Exceptions:
	 *		OperationFailedException - Processing has failed.
	 *		ConfigurationErrorException - FFMPEG_PATH is not defined.
	 * 
	 * @return void
	 */
	protected function process () {
		$source = $this->getFormat('source');
		
		
		// Convert our video formats from the source format
		$mp4 = $this->getFormat('mp4');
		$mp4->process($source);
		
		// $this->getFormat('webm')->process($source);
		
		
		// Generate our image formats from the mp4
		$fullFrame = $this->getFormat('full_frame');
		$fullFrame->process($mp4);
		
		$this->getFormat('thumb')->process($fullFrame);
		$this->getFormat('splash')->process($fullFrame);
		
		
		// Clean up
		$source->delete();
		$this->removeFromQueue();
		$this->logAction('processed');
	}
	
	/**
	 * Delete the file.
	 * 
	 * @return null
	 * @access public
	 * @since 5/6/08
	 */
	public function delete () {
		parent::delete();
		
		$query = new DeleteQuery;
		$query->setTable('middmedia_metadata');
		$query->addWhereEqual('directory', $this->directory->getBaseName());
		$query->addWhereEqual('file', $this->getBaseName());
		
		$dbMgr = Services::getService("DatabaseManager");
		$dbMgr->query($query, HARMONI_DB_INDEX);
		
		foreach ($this->getFormats() as $format) {
			$format->delete();
		}
		
		$this->logAction('delete');
	}
	
	/**
	 * Answer the Agent that created this file.
	 *
	 * This method throws the following exceptions:
	 *		OperationFailedException 	- If no creator is listed or can be returned.
	 *		UnimplementedException 		- If this method is not available yet.
	 * 
	 * @return object Agent
	 * @access public
	 * @since 10/24/08
	 */
	public function getCreator () {
		if (!isset($this->creator)) {
			$query = new SelectQuery;
			$query->addTable('middmedia_metadata');
			$query->addColumn('creator');
			$query->addWhereEqual('directory', $this->directory->getBaseName());
			$query->addWhereEqual('file', $this->getBaseName());
			
			$dbMgr = Services::getService("DatabaseManager");
			$result = $dbMgr->query($query, HARMONI_DB_INDEX);
			
			if (!$result->getNumberOfRows())
				throw new OperationFailedException("No creator listed.");
			
			$agentMgr = Services::getService('Agent');
			$this->creator = $agentMgr->getAgent(new HarmoniId($result->field('creator')));
			$result->free();
		}
		return $this->creator;
	}
	
	/**
	 * Answer the username of the creator
	 * 
	 * @return string
	 * @access public
	 * @since 1/14/09
	 */
	public function getCreatorUsername () {
		$creator = $this->getCreator();
		$propertiesCollections = $creator->getProperties();
		while($propertiesCollections->hasNext()) {
			$properties = $propertiesCollections->next();
			$username = $properties->getProperty('username');
			if (!is_null($username))
				return $username;
		}
		throw new OperationFailedException ("No creator username available.");
	}
	
	/**
	 * Set the creator of the file.
	 * 
	 * @param object Agent $creator
	 * @return void
	 * @access public
	 * @since 11/21/08
	 */
	public function setCreator (Agent $creator) {
		$query = new InsertQuery;
		$query->setTable('middmedia_metadata');
		$query->addValue('directory', $this->directory->getBaseName());
		$query->addValue('file', $this->getBaseName());
		$query->addValue('creator', $creator->getId()->getIdString());
		
		$dbMgr = Services::getService("DatabaseManager");
		$dbMgr->query($query, HARMONI_DB_INDEX);
	}
	
	/**
	 * Answer our directory.
	 * 
	 * @return MiddMedia_Directory
	 */
	public function getDirectory () {
		return $this->directory;
	}
	
	/**
	 * Answer a format of this media file
	 * 
	 * @param string $format
	 * @return MiddMedia_File_FormatInterface
	 */
	public function getFormat ($format) {
		switch ($format) {
			case 'source':
				return MiddMedia_File_Format_Video_Source::get($this);
			case 'mp4':
				return MiddMedia_File_Format_Video_Mp4::get($this);
			case 'webm':
				return MiddMedia_File_Format_Video_WebM::get($this);
			case 'thumb':
				return MiddMedia_File_Format_Image_Thumbnail::get($this);
			case 'splash':
				return MiddMedia_File_Format_Image_Splash::get($this);
			case 'full_frame':
				return MiddMedia_File_Format_Image_FullFrame::get($this);
			default:
				throw new InvalidArgumentException("Unsupported format '$format'.");			
		}
	}
	
	/**
	 * Log actions about this file
	 * 
	 * @param string $category
	 * @return void
	 * @access private
	 * @since 2/2/09
	 */
	private function logAction ($category) {
		switch ($category) {
			case 'upload':
				$category = 'Upload';
				$description = "File uploaded: ".$this->directory->getBaseName()."/".$this->getBaseName();
				$type = 'Event_Notice';
				break;
			case 'delete':
				$category = 'Delete';
				$description = "File deleted: ".$this->directory->getBaseName()."/".$this->getBaseName();
				$type = 'Event_Notice';
				break;
			case 'processed':
				$category = 'Video Processed';
				$description = "Video converted to mp4: ".$this->directory->getBaseName()."/".$this->getBaseName();
				$type = 'Event_Notice';
				break;
			case 'changed':
				$category = 'Contents Changed';
				$description = "File contents changed: ".$this->directory->getBaseName()."/".$this->getBaseName();
				$type = 'Event_Notice';
				break;
			default:
				throw new InvalidArgumentException("Unknown category: $category");
		}
		
		if (Services::serviceRunning("Logging")) {
			$loggingManager = Services::getService("Logging");
			$log = $loggingManager->getLogForWriting("MiddMedia");
			$formatType = new Type("logging", "edu.middlebury", "AgentsAndNodes",
							"A format in which the acting Agent[s] and the target nodes affected are specified.");
			$priorityType = new Type("logging", "edu.middlebury", $type,
							"Normal events.");
			
			$item = new AgentNodeEntryItem($category, $description);
			$item->addAgentId($this->directory->getManager()->getAgent()->getId());
			
			
			$idManager = Services::getService("Id");
			
			$item->addNodeId($idManager->getId('middmedia:'.$this->directory->getBaseName().'/'));
			$item->addNodeId($idManager->getId('middmedia:'.$this->directory->getBaseName().'/'.$this->getBaseName()));
			
			$log->appendLogWithTypes($item,	$formatType, $priorityType);
		}
	}
	
}

?>