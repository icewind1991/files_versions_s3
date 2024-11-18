<?php
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesVersionsS3\Tests\Command;

use OCA\FilesVersionsS3\Command\ConfigManager;
use OCA\FilesVersionsS3\Command\S3Config;
use OCA\FilesVersionsS3\Tests\TestCase;

/**
 * @group DB
 */
class S3ConfigTest extends TestCase {
	/** @var S3Config */
	private $config;

	public function setUp(): void {
		parent::setUp();

		/** @var ConfigManager $configManager */
		$configManager = \OC::$server->query(ConfigManager::class);
		$configs = $configManager->getS3Configs();
		$configs = array_filter($configs, function ($config) {
			return $config instanceof S3Config;
		});

		if (!$configs) {
			$this->markTestSkipped("No S3 configured");
			return;
		}
		$this->config = current($configs);
	}

	public function testEnable() {
		if ($this->config->versioningEnabled()) {
			$this->markTestSkipped("S3 versioning already enabled");
			return;
		}

		$this->config->enableVersioning();
		usleep(100 * 1000);
		$this->assertTrue($this->config->versioningEnabled());
	}
}
