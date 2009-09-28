<?php
/**
 * @since 7/24/09
 * @package middmedia
 * 
 * @copyright Copyright &copy; 2009, Middlebury College
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 */ 

require_once(dirname(__FILE__).'/UnauthenticatedFile.class.php');

/**
 * The unauthenticated directory allows direct access to files if the name is known
 * but does not allow for browsing as no authentication has been checked.
 * 
 * @since 7/24/09
 * @package middmedia
 * 
 * @copyright Copyright &copy; 2009, Middlebury College
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 */
class MiddMedia_UnauthenticatedDirectory
	extends MiddMedia_Directory
{
		
	/**
	 * Answer the directory if it exists. Throw an UnknownIdException if it doesn't.
	 * 
	 * @param object MiddMediaManagerMiddMediaManager $manager
	 * @param string $name
	 * @return object MiddMedia_Directory
	 * @access public
	 * @since 11/13/08
	 * @static
	 */
	public static function getIfExists (MiddMediaManager $manager, $name) {
		$dir = new MiddMedia_UnauthenticatedDirectory($manager, $name);
		
		if (!file_exists($dir->getFSPath())) {
			throw new UnknownIdException("Directory does not exist");
		}
		
		return $dir;
	}

	/**
	 * Answer the directory, creating if needed.
	 * 
	 * @param object MiddMediaManagerMiddMediaManager $manager
	 * @param string $name
	 * @return ovject MiddMedia_Directory
	 * @access public
	 * @since 11/13/08
	 * @static
	 */
	public static function getAlways (MiddMediaManager $manager, $name) {
		try {
			return self::getIfExists($manager, $name);
		} catch (UnknownIdException $e) {
			throw new PermissionDeniedException("The UnauthenticatedDirectory cannot create directories.");
		}
	}
	
	/**
	 * Answer an array of the files in this directory.
	 * 
	 * @return array of MiddMediaFile objects
	 * @access public
	 * @since 10/24/08
	 */
	public function getFiles () {
		throw new PermissionDeniedException("The UnauthenticatedDirectory cannot browse files.");
	}
	
	/**
	 * Answer a single file by name
	 * 
	 * @param string $name
	 * @return object MiddMedia_File
	 * @access public
	 * @since 11/13/08
	 */
	public function getFile ($name) {
		if (!$this->fileExists($name))
			throw new UnknownIdException("File '$name' does not exist.");
		return new MiddMedia_UnauthenticatedFile($this, $name);
	}
	
	/**
	 * Set the quota of this directory in bytes.
	 * 
	 * @param int $quota
	 * @return void
	 * @access public
	 * @since 12/10/08
	 */
	public function setCustomQuota ($quota) {
		throw new PermissionDeniedException("The UnauthenticatedDirectory cannot set quotas.");
	}
	
	/**
	 * Remove the custom quota
	 * 
	 * @return void
	 * @access public
	 * @since 12/10/08
	 */
	public function removeCustomQuota () {
		throw new PermissionDeniedException("The UnauthenticatedDirectory cannot set quotas.");
	}
	
	/**
	 * Add a file to this directory
	 * 
	 * This method throws the following exceptions:
	 *		InvalidArgumentException 	- If incorrect parameters are supplied
	 *		OperationFailedException 	- If the file already exists.
	 *		PermissionDeniedException 	- If the user is unauthorized to manage media here.
	 * 
	 * @param object Harmoni_Filing_FileInterface $file
	 * @return object MiddMediaFile The new file
	 * @access public
	 * @since 10/24/08
	 */
	public function addFile (Harmoni_Filing_FileInterface $file) {
		throw new PermissionDeniedException("The UnauthenticatedDirectory cannot create files.");
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
	 * @return object MiddMediaFile The new file
	 * @access public
	 * @since 11/21/08
	 */
	public function createFile ($name) {
		throw new PermissionDeniedException("The UnauthenticatedDirectory cannot create files.");
	}
	
	/**
	 * Answer true if the file is writable
	 * 
	 * @return boolean
	 * @access public
	 * @since 11/19/08
	 */
	public function isWritable () {
		return false;
	}
}

?>