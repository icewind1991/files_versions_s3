<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesVersionsS3\Versions;

use OC\Files\ObjectStore\ObjectStoreStorage;
use OC\Files\ObjectStore\S3;
use OC\Files\ObjectStore\S3ConnectionTrait;
use OCA\Files_Versions\Versions\IVersion;
use OCP\Files\FileInfo;
use OCP\Files\Storage\IStorage;

class PrimaryS3VersionsBackend extends AbstractS3VersionBackend {
	public function useBackendForStorage(IStorage $storage): bool {
		if ($storage->instanceOfStorage(ObjectStoreStorage::class)) {
			/** @var ObjectStoreStorage $storage */
			$objectStore = $storage->getObjectStore();
			return $objectStore instanceof S3;
		}
		return false;
	}

	/**
	 * @param FileInfo $file
	 * @return S3ConnectionTrait|null
	 */
	protected function getS3(FileInfo $file) {
		$storage = $file->getStorage();
		if ($storage->instanceOfStorage(ObjectStoreStorage::class)) {
			/** @var ObjectStoreStorage $storage */
			$objectStore = $storage->getObjectStore();
			if ($objectStore instanceof S3) {
				return $objectStore;
			}
		}

		return null;
	}

	protected function getUrn(FileInfo $file): string {
		/** @var ObjectStoreStorage $storage */
		$storage = $file->getStorage();
		return $storage->getURN($file->getId());
	}

	protected function postRollback(FileInfo $file, IVersion $version) {
		$cache = $file->getStorage()->getCache();
		$cache->update($file->getId(), [
			'mtime' => time(),
			'etag' => $file->getStorage()->getETag($file->getInternalPath()),
			'size' => $version->getSize(),
		]);
	}
}
