<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesVersionsS3\Command;

use Exception;
use OC\Files\ObjectStore\ObjectStoreStorage;
use OC\Files\ObjectStore\S3;
use OCA\Files_External\Lib\Backend\AmazonS3;
use OCA\Files_External\Lib\StorageConfig;
use OCA\Files_External\Service\GlobalStoragesService;
use OCP\Files\IRootFolder;
use OCP\IServerContainer;

class ConfigManager {
	/** @var GlobalStoragesService|null */
	private $globalService;
	private $rootFolder;

	public function __construct(IServerContainer $server, IRootFolder $rootFolder) {
		$this->rootFolder = $rootFolder;

		if (class_exists(GlobalStoragesService::class)) {
			$this->globalService = $server->query(GlobalStoragesService::class);
		} else {
			$this->globalService = null;
		}
	}

	/**
	 * @return (S3Config|BrokenConfig)[]
	 */
	public function getS3Configs() {
		if ($this->globalService) {
			$externalStorageConfigs = $this->globalService->getAllStorages();
			$s3StorageConfigs = array_filter($externalStorageConfigs, function (StorageConfig $storage) {
				return $storage->getBackend() instanceof AmazonS3;
			});
			$storages = array_map(function (StorageConfig $config) {
				$storageClass = $config->getBackend()->getStorageClass();
				try {
					/** @var \OCA\Files_External\Lib\Storage\AmazonS3 $storage */
					$storage = new $storageClass($config->getBackendOptions());
					return new S3Config((string)$config->getId(), $storage->getConnection(), $storage->getBucket(), $config->getMountPoint());
				} catch (Exception $e) {
					return new BrokenConfig((string)$config->getId(), $storage->getBucket(), $config->getMountPoint(), $e);
				}
			}, $s3StorageConfigs);
		} else {
			$storages = [];
		}

		$primaryStorage = $this->rootFolder->get('')->getStorage();

		if ($primaryStorage->instanceOfStorage(ObjectStoreStorage::class) and $primaryStorage->getObjectStore() instanceof S3) {
			/** @var S3 $s3 */
			$s3 = $primaryStorage->getObjectStore();
			$storages[] = new S3Config('primary', $s3->getConnection(), $s3->getBucket(), "Primary Storage");
		}

		return $storages;
	}
}
