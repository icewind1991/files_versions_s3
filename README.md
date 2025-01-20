<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# files_versions_s3

Use S3 object versioning for file versioning

## Warning

This app does not include any mechanism for expiring old s3 versions,
you should setup your own version expiry (also called "Lifecycle management" in S3)
to prevent versions from taking up an ever increasing amount of space.

## Nextcloud builtin versioning

Note that the default versioning app for Nextcloud will still work with S3 object storage without enabling this app.
The default Nextcloud versioning will create new objects for every versioning instead of multiple versions for the same object.

Enabling this app should improve performance when creating new versions of large files and the integration with S3 native
lifecycle management might be a desired feature, but comes with the downsides as described above.

## Limitations with renaming/moving files when using external storage

Due to limitations in how versions are stored in S3, when using versioning on an S3 external storage,
old versions will be lost when a file is moved or renamed.
This issue does not occur when using S3 as primary storage.


## Usage

- install the app
- check if bucket versioning is enabled for your storage using `occ files_versions_s3:status`
- enable bucket versioning if not yet enabled using `occ files_versions_s3:enable <id>`. Where `<id>` is the integer of the storage item from `occ files_versions_s3:status` that you want to enable versioning on.
