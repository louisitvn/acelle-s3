<?php

return array (
  'title' => 'Amazon S3 storage',
  'subtitle' => 'Keep customer media, campaign attachments and template thumbnails in an S3 bucket instead of on the application server disk.',

  'connect.heading' => 'Connect an AWS account',
  'connect.intro' => 'Enter an access key with permission to read and write your bucket. The key is verified before it is saved, and the secret is encrypted at rest.',

  'pitch.heading' => 'Empowered by Amazon S3',
  'pitch.lede' => 'Move every uploaded image, attachment and template thumbnail off the application server and into storage built to hold it.',
  'pitch.illust_alt' => 'Files flowing from a cloud into a layered storage bucket.',
  'pitch.durability.title' => 'Built not to lose anything',
  'pitch.durability.body' => 'S3 is designed for 99.999999999% durability, replicating every object across multiple facilities.',
  'pitch.unlimited.title' => 'Room to keep growing',
  'pitch.unlimited.body' => 'No disk to fill, no resize to plan. Storage grows with what your customers upload.',
  'pitch.delivery.title' => 'Fast, or free of your bandwidth',
  'pitch.delivery.body' => 'Serve files straight from the bucket or from a CDN in front of it, instead of through this server.',
  'pitch.ownership.title' => 'Your account, your data',
  'pitch.ownership.body' => 'Files live in a bucket you own and control. Switch back to local storage whenever you want.',

  'section.account' => 'Connected account',
  'section.bucket' => 'Bucket',
  'section.delivery' => 'How files are delivered',
  'section.status' => 'Status',

  'field.access_key' => 'Access key ID',
  'field.secret_key' => 'Secret access key',
  'field.secret_key.help' => 'Leave blank to keep the key that is already stored.',
  'field.region' => 'Region',
  'field.bucket' => 'Bucket',
  'field.public_access' => 'Serve files directly from S3',
  'field.public_access.help' => 'Only switch this on if the bucket is publicly readable. Files will be served from:',
  'field.public_base_url' => 'CDN base URL (optional)',
  'field.public_base_url.help' => 'If a CDN sits in front of the bucket, enter its address and files will be served from there instead. Leave blank to use the bucket address.',

  'delivery.intro' => 'By default the application reads each file from S3 and passes it on, which always works but carries the traffic. If the bucket is public, files can be served straight from S3 or a CDN instead.',

  'bucket.choose' => 'Choose a bucket…',
  'bucket.found' => '{0} No buckets found on this account.|{1} 1 bucket available.|[2,*] :count buckets available.',
  'bucket.listing_failed' => 'The buckets could not be listed, so type the name instead. This is normal when the key is restricted to a single bucket.',

  'action.connect' => 'Connect',
  'action.save' => 'Save and test connection',
  'action.disconnect' => 'Disconnect this account',
  'action.activate' => 'Make this the active storage',
  'action.deactivate' => 'Switch back to the local disk',
  'action.back' => 'Back',

  'saved' => 'Connection tested and settings saved.',
  'activated' => 'New files are now stored in S3.',
  'deactivated' => 'New files are stored on the local disk again.',
  'disconnected' => 'The account was disconnected and its settings removed.',
  'region_adopted' => 'The region was set to :region to match the bucket.',

  'probe.credentials_ok' => 'Credentials accepted.',
  'probe.ok' => 'Connected: a test object was written, read back and deleted.',

  'warning.proxying' => 'Files will be served through the application rather than directly from storage.',

  'error.bad_key_id' => 'That access key ID does not exist.',
  'error.bad_secret' => 'The secret access key does not match that key ID.',
  'error.access_denied' => 'These credentials are valid but are not allowed to do this. Check the policy attached to the key.',
  'error.no_such_bucket' => 'That bucket does not exist on this account.',
  'error.wrong_region_known' => 'The bucket ":bucket" is in :region, but its region could not be detected automatically. Allow the s3:GetBucketLocation action on this key and try again.',
  'error.wrong_region' => 'The region of the bucket ":bucket" could not be detected. Allow the s3:GetBucketLocation action on this key and try again.',
  'error.unreachable' => 'Could not reach the storage service: :reason',
  'error.readback_mismatch' => 'A test object was written but read back with different contents.',
  'error.vendor' => 'The storage service refused the request (:code): :message',
  'error.connect_first' => 'Connect an account before choosing a bucket.',
  'error.not_configured' => 'Choose a bucket and test the connection before making this the active storage.',
  'error.no_local' => 'The local disk engine is missing from the storage_engines table.',

  'status.active' => 'Active — new files are stored here.',
  'status.configured' => 'Configured but not active.',
  'status.not_configured' => 'Not configured yet.',
  'status.other_active' => 'Another storage engine is currently active: :driver',

  'notice.no_migration.title' => 'Switching does not move existing files',
  'notice.no_migration.body' => 'Files already stored elsewhere stay where they are and will stop loading until you copy them across yourself. Run "php artisan storage:verify" to see exactly which files would be affected before you switch.',
);
