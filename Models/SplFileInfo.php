<?php
namespace FreePBX\modules\Backup\Models;

/**
 * Extends \SplFileInfo to support backup paths
 */
#[\AllowDynamicProperties]
class SplFileInfo extends \SplFileInfo {
	private $type;
	private $pathTo;
	private $base;

	/**
	 * @param string $file
	 * @param string $type
	 * @param string $pathTo
	 * @param string $base
	 */
	public function __construct($file, $type, $pathTo, $base) {
		parent::__construct($file);
		$this->type = $type;
		$this->pathTo = $pathTo;
		$this->base = $base;
	}

	/**
	 * Returns the file type
	 *
	 * @return string
	 */
	public function getType(): string|false {
		return $this->type;
	}

	/**
	 * Returns the path to extract to
	 *
	 * @return string
	 */
	public function getPathTo() {
		return $this->pathTo;
	}

	/**
	 * Returns the base path
	 *
	 * @return string
	 */
	public function getBase() {
		return $this->base;
	}

	/**
	 * Returns the contents of the file.
	 *
	 * @return string the contents of the file
	 *
	 * @author https://github.com/symfony/finder/blob/3.4/SplFileInfo.php
	 *
	 * @throws \RuntimeException
	 */
	public function getContents() {
		set_error_handler(function ($type, $msg) use (&$error) { $error = $msg; });
		$content = file_get_contents($this->getPathname());
		restore_error_handler();
		if (false === $content) {
			throw new \RuntimeException($error);
		}
		return $content;
	}
}