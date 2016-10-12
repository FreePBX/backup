<?php
/**
 * Copyright Sangoma Technologies, Inc 2016
 */
namespace FreePBX\modules\Backup\components\interfaces;

interface Communicationsmodule	{
	/**
	 * Asks if this class handles the supplied protocol
	 * @param  string $protocol the communications protocol [ftp,ssh.sftp]
	 * @return boolean
	 */
	public function voter($protocol);

	/**
	 * Configuration array
	 * @param array $config array of configuration parameters. You should "connect" here.
	 */
	public function setConfig($config=array());

	/**
	 * Pull down a file from the resource
	 * @param	string $file the filename
	 * @param	string $path the path
	 * @return resource requested file
	 */
	public function pull($file, $path);

	/**
	 * Push a file to the resource
	 * @param	string $file the filename
	 * @param	string $path the path
	 * @return array	 {status,message}
	 */
	public function push($file, $path);

	/**
	 * Delete a file
	 * @param	string $file The file to delete
	 * @return array			 {status,message}
	 */
	public function delete($file);

	/**
	 * List files on resource path
	 * @param	string	$path			path to list files under
	 * @param	boolean $recursive should the listing be recursive
	 * @return array						 an array of files relative to path.
	 */
	public function listFiles($path, $recursive=false);

	/**
	 * List directories on resource path
	 * @param	string	$path			path to list files under
	 * @param	boolean $recursive should the listing be recursive
	 * @return array						 an array of directories relative to path.
	 */
	public function listDirectories($path, $recursive=false);

	/**
	 * Get information on a file
	 * @param	string $fullpath path to file
	 * @return array					 file information {size, lastmodified, created, permissions}
	 */
	public function fileInfo($fullpath);

	/**
	 * Create a directory and optionally any parent directories.
	 * @param	string $path			Directory to create
	 * @param	boolean $recursive should child directories be created
	 * @return array						 {status,message}
	 */
	public function createDirectory($path,$recursive=false);

	/**
	 * Remove a directory and optionally all of the contents if NOT empty
	 * @param	string	$path			directory to remove
	 * @param	boolean $recursive Remove directory contents if not empty
	 * @return array						 {status,message}
	 */
	public function removeDirectory($path,$recursive=false);
}
