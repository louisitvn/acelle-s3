<?php

return array (
  'title' => 'S3 storage',
  'subtitle' => 'Keep customer files in S3-compatible object storage instead of the application server disk.',

  'section.credentials' => 'Connection',
  'section.status' => 'Status',

  'field.bucket' => 'Bucket',
  'field.bucket.help' => 'The bucket name exactly as it appears in your provider console.',
  'field.region' => 'Region',
  'field.region.help' => 'For providers that do not use regions, enter "auto".',
  'field.access_key' => 'Access key ID',
  'field.secret_key' => 'Secret access key',
  'field.secret_key.help' => 'Leave blank to keep the key that is already stored.',
  'field.endpoint' => 'Endpoint',
  'field.endpoint.help' => 'Leave blank for Amazon S3. Set it for any other S3-compatible provider.',
  'field.public_base_url' => 'Public base URL',
  'field.public_base_url.help' => 'The address files are publicly served from — a bucket public URL, a bound custom domain, or a CDN in front of the bucket. Leave blank and the application will serve every file itself, which always works but carries the traffic.',
  'field.path_style' => 'Use path-style addressing',
  'field.path_style.help' => 'Required by some self-hosted providers.',

  'action.save' => 'Save and test connection',
  'action.activate' => 'Make this the active storage',
  'action.deactivate' => 'Switch back to the local disk',

  'saved' => 'Connection tested and settings saved.',
  'activated' => 'New files are now stored in S3.',
  'deactivated' => 'New files are stored on the local disk again.',

  'probe.ok' => 'Connected: a test object was written, read back and deleted.',

  'warning.no_public_base_url' => 'No public base URL is set, so every file will be served through the application rather than directly from storage.',

  'error.not_configured' => 'Configure and test the connection before making this the active storage.',
  'error.no_local' => 'The local disk engine is missing from the storage_engines table.',

  'status.active' => 'Active — new files are stored here.',
  'status.configured' => 'Configured but not active.',
  'status.not_configured' => 'Not configured yet.',
  'status.other_active' => 'Another storage engine is currently active: :driver',

  'notice.no_migration.title' => 'Switching does not move existing files',
  'notice.no_migration.body' => 'Files already stored elsewhere stay where they are and will stop loading until you copy them across yourself. Run "php artisan storage:verify" to see exactly which files would be affected before you switch.',
);
