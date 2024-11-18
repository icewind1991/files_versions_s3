<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesVersionsS3\Versions;

use Aws\S3\S3Client;
use OC\Files\ObjectStore\S3ConnectionTrait;
use OCA\Files_Versions\Versions\IVersion;
use OCA\Files_Versions\Versions\IVersionBackend;
use OCA\Files_Versions\Versions\Version;
use OCP\Files\FileInfo;
use OCP\IUser;

class S3VersionProvider {
	/**
	 * @param S3ConnectionTrait $objectStore
	 * @param string $urn
	 * @param IUser $user
	 * @param FileInfo $sourceFile
	 * @param IVersionBackend $backend
	 * @return IVersion[]
	 * @throws \Exception
	 */
	public function getVersions($objectStore, string $urn, IUser $user, FileInfo $sourceFile, IVersionBackend $backend) {
		$client = $objectStore->getConnection();
		$bucket = $objectStore->getBucket();
		$result = $client->listObjectVersions([
			'Bucket' => $bucket,
			'Prefix' => $urn,
		]);
		$s3versions = array_values($result['Versions'] ?? []);
		$s3versions = array_filter($s3versions, function (array $version) use ($urn) {
			return $version['Key'] === $urn;
		});
		$versions = array_map(function (array $version) use ($client, $bucket, $urn, $user, $sourceFile, $backend) {
			$versionId = $version['VersionId'];
			$lastModified = $version['LastModified'];

			$tagSet = $client->getObjectTagging([
				'Bucket' => $bucket,
				'Key' => $urn,
				'VersionId' => $versionId,
			])['TagSet'];
			$tags = [];

			foreach ($tagSet as $tag) {
				if (str_starts_with($tag['Key'], 'metadata:')) {
					$key = preg_replace('/^metadata:/', '', $tag['Key']);
					$value = base64_decode(str_replace('-', '=', $tag['Value']));
					$tags[$key] = $value;
				}
			}

			// Ensure compatibility with previous way of storing labels.
			if (!isset($tags['label'])) {
				foreach ($tagSet as $tag) {
					if ($tag['Key'] === 'Label') {
						$tags['label'] = base64_decode(str_replace('-', '=', $tag['Value']));
					}
				}
			}

			return new Version(
				$lastModified->getTimestamp(),
				$versionId,
				$sourceFile->getName(),
				(int)$version['Size'],
				$sourceFile->getMimetype(),
				$sourceFile->getId() . '/' . $lastModified,
				$sourceFile,
				$backend,
				$user,
				$tags,
			);
		}, $s3versions);
		usort($versions, function (IVersion $a, IVersion $b) {
			return $b->getTimestamp() - $a->getTimestamp();
		});
		return $versions;
	}

	/**
	 * @param S3ConnectionTrait $objectStore
	 * @param string $urn
	 * @param string $versionId
	 * @throws \OCP\Files\NotFoundException
	 */
	public function rollback($objectStore, string $urn, string $versionId) {
		$client = $objectStore->getConnection();
		$bucket = $objectStore->getBucket();

		$client->copyObject([
			'Bucket' => $bucket,
			'CopySource' => S3Client::encodeKey($bucket . '/' . $urn) . '?versionId=' . urlencode($versionId),
			'Key' => $urn,
		]);
	}

	/**
	 * @param S3ConnectionTrait $objectStore
	 * @param string $urn
	 * @param string $versionId
	 * @return bool|resource
	 * @throws \OCP\Files\NotFoundException
	 */
	public function read($objectStore, string $urn, string $versionId) {
		$client = $objectStore->getConnection();
		$command = $client->getCommand('GetObject', [
			'Bucket' => $objectStore->getBucket(),
			'Key' => $urn,
			'VersionId' => $versionId,
		]);
		$request = \Aws\serialize($command);
		$headers = [];
		foreach ($request->getHeaders() as $key => $values) {
			foreach ($values as $value) {
				$headers[] = "$key: $value";
			}
		}
		$opts = [
			'http' => [
				'header' => $headers,
			],
		];

		$context = stream_context_create($opts);
		return fopen((string)$request->getUri(), 'r', false, $context);
	}

	/**
	 * @param S3ConnectionTrait $objectStore
	 * @param string $urn
	 * @param string $versionId
	 * @param string $label
	 * @throws \OCP\Files\NotFoundException
	 */
	public function setVersionMetadata($objectStore, string $urn, string $versionId, string $key, string $value) {
		$client = $objectStore->getConnection();
		$bucket = $objectStore->getBucket();

		$tagSet = $client->getObjectTagging([
			'Bucket' => $bucket,
			'Key' => $urn,
			'VersionId' => $versionId,
		])['TagSet'];

		if ($value === '') {
			// Filter the key out if the value is empty
			$tagSet = array_filter($tagSet, function (array $tag) use ($key) {
				return $tag['Key'] !== "metadata:$key";
			});
		} else {
			$saved = false;
			foreach ($tagSet as &$tag) {
				if ($tag['Key'] === "metadata:$key") {
					$tag['Value'] = str_replace('=', '-', base64_encode($value));
					$saved = true;
					break;
				}
			}

			if (!$saved) {
				$tagSet[] = [
					'Key' => "metadata:$key",
					'Value' => str_replace('=', '-', base64_encode($value)),
				];
			}
		}

		$client->putObjectTagging([
			'Bucket' => $bucket,
			'Key' => $urn,
			'VersionId' => $versionId,
			'Tagging' => [
				'TagSet' => $tagSet,
			],
		]);
	}

	/**
	 * @param S3ConnectionTrait $objectStore
	 * @param string $urn
	 * @param string $versionId
	 * @throws \OCP\Files\NotFoundException
	 */
	public function deleteVersion($objectStore, string $urn, string $versionId) {
		$client = $objectStore->getConnection();
		$bucket = $objectStore->getBucket();

		$client->deleteObject([
			'Bucket' => $bucket,
			'Key' => $urn,
			'VersionId' => $versionId,
		]);
	}
}
