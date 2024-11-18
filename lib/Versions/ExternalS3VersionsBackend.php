<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesVersionsS3\Versions;

use OC\Files\ObjectStore\S3ConnectionTrait;
use OC\Files\Storage\Wrapper\Jail;
use OCA\Files_External\Lib\Storage\AmazonS3;
use OCA\Files_Versions\Versions\IVersion;
use OCP\Files\FileInfo;
use OCP\Files\Storage\IStorage;

class ExternalS3VersionsBackend extends AbstractS3VersionBackend {
	public function useBackendForStorage(IStorage $storage): bool {
		return true;
	}

	/**
	 * @param FileInfo $file
	 * @return S3ConnectionTrait|null
	 */
	protected function getS3(FileInfo $file) {
		$storage = $file->getStorage();
		if ($storage->instanceOfStorage(AmazonS3::class)) {
			/** @var AmazonS3 $storage */
			return $storage;
		} else {
			return null;
		}
	}

	protected function getUrn(FileInfo $file): string {
		$storage = $file->getStorage();
		$path = $file->getInternalPath();
		while ($storage->instanceOfStorage(Jail::class)) {
			/** @var Jail $storage */
			$path = $storage->getUnjailedPath($path);
			$storage = $storage->getUnjailedStorage();
		}
		return $path;
	}

	protected function postRollback(FileInfo $file, IVersion $version) {
		$file->getStorage()->getUpdater()->update($file->getInternalPath());
	}
}
