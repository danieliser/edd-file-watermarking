<?php
/**
 * ZipArchive test doubles.
 *
 * @package EDDFileWatermarking
 */

/**
 * Commit ZIP data but report a failed close operation.
 */
class CloseFailingZipArchive extends ZipArchive {

	/**
	 * Close the archive, then simulate a failed commit result.
	 *
	 * @return bool
	 */
	public function close(): bool {
		parent::close();
		return false;
	}
}
