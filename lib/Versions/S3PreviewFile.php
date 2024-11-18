<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesVersionsS3\Versions;

use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\Files\ForbiddenException;
use OCP\Preview\IVersionedPreviewFile;

class S3PreviewFile implements File, IVersionedPreviewFile {
	private FileInfo $sourceFile;
	private $contentProvider;
	private string $revisionId;

	public function __construct(FileInfo $sourceFile, callable $contentProvider, string $revisionId) {
		$this->sourceFile = $sourceFile;
		$this->contentProvider = $contentProvider;
		$this->revisionId = $revisionId;
	}

	public function getContent() {
		return stream_get_contents(($this->contentProvider)());
	}

	public function putContent($data) {
		throw new ForbiddenException('Preview files are read only', false);
	}

	public function fopen($mode) {
		if ($mode === 'r' || $mode === 'rb') {
			return ($this->contentProvider)();
		} else {
			throw new ForbiddenException('Preview files are read only', false);
		}
	}

	public function hash($type, $raw = false) {
		return '';
	}

	public function getChecksum() {
		return '';
	}

	public function getMtime() {
		return $this->sourceFile->getMtime();
	}

	public function getMimetype() {
		return $this->sourceFile->getMimeType();
	}

	public function getMimePart() {
		return $this->sourceFile->getMimePart();
	}

	public function isEncrypted() {
		return $this->sourceFile->isEncrypted();
	}

	public function getType() {
		return $this->sourceFile->getType();
	}

	public function isCreatable() {
		return $this->sourceFile->isCreatable();
	}

	public function isShared() {
		return $this->sourceFile->isShared();
	}

	public function isMounted() {
		return $this->sourceFile->isMounted();
	}

	public function getMountPoint() {
		return $this->sourceFile->getMountPoint();
	}

	public function getOwner() {
		return $this->sourceFile->getOwner();
	}

	public function getExtension(): string {
		return $this->sourceFile->getExtension();
	}

	public function getPreviewVersion(): string {
		return $this->revisionId;
	}

	public function move($targetPath) {
		throw new ForbiddenException('Preview files are read only', false);
	}

	public function delete() {
		throw new ForbiddenException('Preview files are read only', false);
	}

	public function copy($targetPath) {
		throw new ForbiddenException('Preview files are read only', false);
	}

	public function touch($mtime = null) {
		throw new ForbiddenException('Preview files are read only', false);
	}

	public function getStorage() {
		return $this->sourceFile->getStorage();
	}

	public function getPath() {
		return $this->sourceFile->getPath();
	}

	public function getInternalPath() {
		return $this->sourceFile->getInternalPath();
	}

	public function getId() {
		return (int)$this->sourceFile->getId();
	}

	public function stat() {
		return [
			'mtime' => $this->getMtime(),
			'size' => $this->getSize()
		];
	}

	public function getSize($includeMounts = true) {
		return $this->sourceFile->getSize();
	}

	public function getEtag() {
		return $this->revisionId;
	}

	public function getPermissions() {
		return $this->sourceFile->getPermissions();
	}

	public function isReadable() {
		return $this->sourceFile->isReadable();
	}

	public function isUpdateable() {
		return $this->sourceFile->isUpdateable();
	}

	public function isDeletable() {
		return $this->sourceFile->isDeletable();
	}

	public function isShareable() {
		return $this->sourceFile->isShareable();
	}

	public function getParent() {
		if ($this->sourceFile instanceof File) {
			return $this->sourceFile->getParent();
		} else {
			throw new \Exception("Invalid file");
		}
	}

	public function getName() {
		return $this->sourceFile->getName();
	}

	public function lock($type) {
		// noop
	}

	public function changeLock($targetType) {
		// noop
	}

	public function unlock($type) {
		// noop
	}

	public function getCreationTime(): int {
		return 0;
	}

	public function getUploadTime(): int {
		return 0;
	}

	public function getParentId(): int {
		return $this->getParent()->getId();
	}

	public function getMetadata(): array {
		return [];
	}
}
