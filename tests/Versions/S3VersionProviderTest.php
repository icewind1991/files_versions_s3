<?php
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesVersionsS3\Tests\Versions;

use OCA\Files_Versions\Versions\INameableVersion;
use OCA\Files_Versions\Versions\IVersion;
use OCA\Files_Versions\Versions\IVersionBackend;
use OCA\FilesVersionsS3\Command\ConfigManager;
use OCA\FilesVersionsS3\Command\S3Config;
use OCA\FilesVersionsS3\Tests\TestCase;
use OCA\FilesVersionsS3\Versions\S3VersionProvider;
use OCP\Files\FileInfo;
use OCP\IUser;

/**
 * @group DB
 */
class S3VersionProviderTest extends TestCase {
	/** @var S3Config */
	private $config;
	/** @var S3VersionProvider */
	private $versionProvider;
	/** @var IUser */
	private $user;
	/** @var IVersionBackend */
	private $backend;

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
		$this->versionProvider = new S3VersionProvider();
		$this->user = $this->createMock(IUser::class);
		$this->backend = $this->createMock(IVersionBackend::class);

		if (!$this->config->versioningEnabled()) {
			$this->config->enableVersioning();
		}
	}

	public function testListVersions() {
		$sourceFile = $this->createMock(FileInfo::class);
		$sourceFile->method('getName')->willReturn("foo");
		$sourceFile->method('getMimeType')->willReturn("mime");
		$sourceFile->method('getId')->willReturn("1");
		$this->config->getS3()->upload($this->config->getBucket(), 'foo', 'bar');

		// delay to make sure we have distinct timestamps for sorting
		sleep(1);

		$this->assertEmpty($this->versionProvider->getVersions(
			$this->config,
			'foo',
			$this->user,
			$sourceFile,
			$this->backend
		));

		$this->config->getS3()->upload($this->config->getBucket(), 'foo', 'foo');

		$versions = $this->versionProvider->getVersions($this->config, 'foo', $this->user, $sourceFile, $this->backend);
		$this->assertCount(1, $versions);
		$version1 = $versions[0];

		$this->config->getS3()->upload($this->config->getBucket(), 'foo', 'asd');
		$versions = $this->versionProvider->getVersions($this->config, 'foo', $this->user, $sourceFile, $this->backend);
		$this->assertCount(2, $versions);

		// sorted newest first
		$this->assertLessThan($versions[0]->getTimestamp(), $versions[1]->getTimestamp());
		$this->assertEquals($version1->getRevisionId(), $versions[1]->getRevisionId());
	}

	public function testReadVersion() {
		$sourceFile = $this->createMock(FileInfo::class);
		$sourceFile->method('getName')->willReturn("foo");
		$sourceFile->method('getMimeType')->willReturn("mime");
		$sourceFile->method('getId')->willReturn("1");
		$this->config->getS3()->upload($this->config->getBucket(), 'bar', 'bar');
		sleep(1);
		$this->config->getS3()->upload($this->config->getBucket(), 'bar', 'foo');
		$this->config->getS3()->upload($this->config->getBucket(), 'bar', 'asd');
		$versions = $this->versionProvider->getVersions($this->config, 'bar', $this->user, $sourceFile, $this->backend);
		$this->assertCount(2, $versions);


		$this->assertEquals(
			'bar',
			stream_get_contents($this->versionProvider->read($this->config, "bar", $versions[1]->getRevisionId()))
		);
		$this->assertEquals(
			'foo',
			stream_get_contents($this->versionProvider->read($this->config, "bar", $versions[0]->getRevisionId()))
		);
	}

	public function testRollback() {
		if (getenv('SKIP_ROLLBACK')) {
			$this->markTestSkipped();
		}
		$sourceFile = $this->createMock(FileInfo::class);
		$sourceFile->method('getName')->willReturn("foo");
		$sourceFile->method('getMimeType')->willReturn("mime");
		$sourceFile->method('getId')->willReturn("1");
		$this->config->getS3()->upload($this->config->getBucket(), 'rollback', 'bar');
		$this->config->getS3()->upload($this->config->getBucket(), 'rollback', 'foo');
		$this->config->getS3()->upload($this->config->getBucket(), 'rollback', 'asd');
		$versions = $this->versionProvider->getVersions(
			$this->config,
			'rollback',
			$this->user,
			$sourceFile,
			$this->backend
		);
		$this->assertCount(2, $versions);

		$this->versionProvider->rollback($this->config, "rollback", $versions[0]->getRevisionId());

		$versions = $this->versionProvider->getVersions(
			$this->config,
			'rollback',
			$this->user,
			$sourceFile,
			$this->backend
		);
		$this->assertCount(3, $versions);


		$this->assertEquals('foo', (string)$this->config->getS3()->getObject([
			'Bucket' => $this->config->getBucket(),
			'Key' => "rollback",
		])['Body']);
	}

	public function testLabeling() {
		if (getenv('SKIP_LABEL')) {
			$this->markTestSkipped();
		}
		$sourceFile = $this->createMock(FileInfo::class);
		$sourceFile->method('getName')->willReturn("foo");
		$sourceFile->method('getMimeType')->willReturn("mime");
		$sourceFile->method('getId')->willReturn("1");
		$this->config->getS3()->upload($this->config->getBucket(), 'labeling', 'bar');
		$this->config->getS3()->upload($this->config->getBucket(), 'labeling', 'foo');
		$this->config->getS3()->upload($this->config->getBucket(), 'labeling', 'asd');
		/** @var (INameableVersion|IVersion)[] $versions */
		$versions = $this->versionProvider->getVersions(
			$this->config,
			'labeling',
			$this->user,
			$sourceFile,
			$this->backend
		);

		$this->assertEquals("", $versions[1]->getLabel());

		$this->versionProvider->setVersionMetadata($this->config, 'labeling', $versions[1]->getRevisionId(), 'label', 'label');

		/** @var (INameableVersion|IVersion)[] $versions */
		$versions = $this->versionProvider->getVersions(
			$this->config,
			'labeling',
			$this->user,
			$sourceFile,
			$this->backend
		);

		$this->assertEquals("label", $versions[1]->getLabel());

		$this->versionProvider->setVersionMetadata($this->config, 'labeling', $versions[1]->getRevisionId(), 'label', '');

		/** @var (INameableVersion|IVersion)[] $versions */
		$versions = $this->versionProvider->getVersions(
			$this->config,
			'labeling',
			$this->user,
			$sourceFile,
			$this->backend
		);

		$this->assertEquals("", $versions[1]->getLabel());
	}

	public function testDeleteVersion() {
		$sourceFile = $this->createMock(FileInfo::class);
		$sourceFile->method('getName')->willReturn("foo");
		$sourceFile->method('getMimeType')->willReturn("mime");
		$sourceFile->method('getId')->willReturn("1");
		$this->config->getS3()->upload($this->config->getBucket(), 'delete', 'bar');
		$this->config->getS3()->upload($this->config->getBucket(), 'delete', 'foo');
		$this->config->getS3()->upload($this->config->getBucket(), 'delete', 'asd');
		$versions = $this->versionProvider->getVersions(
			$this->config,
			'delete',
			$this->user,
			$sourceFile,
			$this->backend
		);

		$this->assertCount(2, $versions);

		$this->versionProvider->deleteVersion($this->config, 'delete', $versions[1]->getRevisionId());

		$versions = $this->versionProvider->getVersions(
			$this->config,
			'delete',
			$this->user,
			$sourceFile,
			$this->backend
		);

		$this->assertCount(1, $versions);
	}
}
