# Amazon S3 Storage for Acelle Mail

[![Plugin version](https://img.shields.io/badge/version-1.0.0-blue)](https://github.com/louisitvn/acelle-s3/releases)
[![Acelle Mail](https://img.shields.io/badge/Acelle%20Mail-4.0.24%2B-2563eb)](https://acellesend.com)

This is an **Amazon S3 storage plugin for [Acelle Mail](https://acellesend.com) — a self-hosted email marketing platform** that runs on your own hosting server. It moves every uploaded image, campaign attachment and template thumbnail off that server's disk and into an **S3 bucket in your own AWS account**, where files can be served through the application, straight from the bucket, or from **CloudFront**.

It is **free**, and it is a drop-in: upload the ZIP, enable it, paste an access key, pick a bucket. No core files are patched.

## Why bother

A self-hosted campaign platform quietly accumulates files. Every template thumbnail, every image a campaign embeds, every attachment — and if you run Acelle as a **SaaS** with the Extended License, every file *your customers* upload lands on your server too. Disk is the one resource that never frees itself: it fills, backups get slower and fatter, and the day you want to move to a bigger box or add a second application server, all that media has to come with you.

Putting it in S3 changes the shape of the problem:

- **No disk to fill.** The server keeps the application; the bucket keeps the media.
- **It stays in your own account.** Your bucket, your AWS bill, your lifecycle and retention rules — nothing is held by a third-party vendor on your behalf.
- **Durability is not your problem any more.** S3 is designed for 99.999999999% durability; a single server disk is not.
- **Horizontal scaling stops being blocked by files.** Two application servers can serve the same media without rsync or a shared NFS mount.

And if you already send through **Amazon SES**, this is the same AWS account, the same IAM console, the same bill. In fact the AWS SDK is already installed in your Acelle server *because* of SES — this plugin talks to it directly rather than dragging in another storage abstraction.

## About Acelle Mail

[Acelle Mail](https://acellesend.com) is **self-hosted email marketing software** you install on your own hosting server: lists, segmentation, automation, templates, campaign tracking and reporting, with the full source code included and a **one-time licence** instead of a monthly platform fee.

Because you host it, you also choose how mail leaves: **[Amazon SES](https://acellesend.com/integrations)**, SendGrid, Mailgun, SparkPost, Postmark, Elastic Email or any plain SMTP relay — several at once if you like. The Extended License adds a **multi-tenant SaaS framework** for running it as your own email marketing service.

[features](https://acellesend.com/features) · [integrations](https://acellesend.com/integrations) · [pricing](https://acellesend.com/pricing) · [live demo](https://acellesend.com/demo) · [knowledge base](https://acellesend.com/kb)

## Where files are served from

Storing a file and serving it are two separate decisions. Once the media is in your bucket, you pick how the browser fetches it:

| Mode | What happens | When it is right |
|---|---|---|
| **Through the application** *(default)* | Your Acelle server reads the object from S3 and streams it to the browser. | Always works, and the bucket can stay **completely private**. Costs your server bandwidth on every view. |
| **Straight from the bucket** | The browser fetches the S3 URL directly. | Only correct if the bucket itself is publicly readable. |
| **From a CDN** *(recommended)* | The browser fetches your CloudFront address. | Fast and cheap at volume, and the bucket can **stay private** — give CloudFront access with an Origin Access Control and leave Block Public Access switched on. |

Pick a CDN mode without filling in the address and the plugin falls back to serving through the application rather than emitting a broken URL.

## Requirements

| | Minimum |
|---|---|
| **Acelle Mail** | 4.0.24 or newer |
| **AWS account** | Any account with an S3 bucket. If you already use Amazon SES for sending, use the same account. |
| **IAM access key** | An access key ID + secret allowed to read and write that bucket ([policy below](#a-minimal-iam-policy)). |
| **Outbound HTTPS** | Your server must reach the S3 API on port 443. |

No webhook, no cron, no extra service.

## Installation

Two ways. The first needs no files at all.

### Through Acelle, from the marketplace (recommended)

Your installation fetches the plugin itself — nothing to download, nothing to upload.

1. In your Acelle admin, open **Plugins**.
2. Click **Install plugin** and choose **Connect with marketplace**.
3. Approve the connection once, then install **Amazon S3 Storage** from the catalog.

[![Connecting an Acelle Mail installation to the plugin marketplace, with the Amazon S3 Storage plugin among those installed](https://acelle2026.s3.dualstack.us-east-1.amazonaws.com/plugin-install-from-marketplace.png)](https://acellesend.com/integrations)

<sub>**Plugins → Install plugin → Connect with marketplace.** Installed plugins show their version and package name — `v1.0.0 · acelle/s3` on the card at the bottom right.</sub>

When a new version is published it appears in the same place, so upgrading is the same three clicks.

### Manually

1. Download the ZIP — from the **Plugins** page of your account, or build it from source:

   ```bash
   git clone https://github.com/louisitvn/acelle-s3.git
   cd acelle-s3
   git checkout 1.0.0          # always build from a tag
   ./build.sh /tmp/out         # → /tmp/out/s3-1.0.0.zip
   ```

2. In your Acelle admin, open **Plugins → Install plugin → Upload plugin package** and drop the ZIP in.
3. Find **Amazon S3 Storage** in the list, open the **⋮** menu and choose **Enable**.

## Setting it up

1. Open **Storage Engine** and click **Connect an AWS account**.
2. Paste the **Access key ID** and **Secret access key**. They are verified against AWS *before* anything is written, so a typo fails here rather than silently later; the secret is encrypted at rest and never shown again.
3. **Choose the bucket.** Buckets on the account are listed for you. A key scoped to a single bucket cannot list — that is normal, and the form lets you type the name instead rather than claiming you have no buckets.
4. **Save and test bucket access.** The plugin writes a small test object and reads it back. A bucket that accepts writes but returns something different is caught here, not by a customer looking at a broken image.
5. Pick a delivery mode, then make S3 the **active** storage engine.

The bucket's region is detected automatically — that needs `s3:GetBucketLocation` on the key, and if it is missing the plugin says exactly that and names the bucket.

## Switching engines moves nothing

Several storage engines can be configured at once, and exactly one is active. **Making S3 active affects new uploads only.** Files already stored on the previous engine stay where they are and keep being served from there. Nothing is copied, and nothing is deleted.

Plan the switch as a starting line, not a migration. The plugin says so on screen too: a configured-but-inactive engine reports *"Configured, but not in use yet. Files are still being stored elsewhere."*, and the active one reports *"new uploads are going to your bucket."*

## A minimal IAM policy

Everything the plugin does, and nothing more. Replace `YOUR-BUCKET`:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "ListBucketsForThePicker",
      "Effect": "Allow",
      "Action": "s3:ListAllMyBuckets",
      "Resource": "*"
    },
    {
      "Sid": "BucketLevel",
      "Effect": "Allow",
      "Action": ["s3:GetBucketLocation", "s3:ListBucket"],
      "Resource": "arn:aws:s3:::YOUR-BUCKET"
    },
    {
      "Sid": "ObjectLevel",
      "Effect": "Allow",
      "Action": ["s3:PutObject", "s3:GetObject", "s3:DeleteObject"],
      "Resource": "arn:aws:s3:::YOUR-BUCKET/*"
    }
  ]
}
```

`ListAllMyBuckets` only powers the bucket dropdown — drop it and type the bucket name by hand instead. `ListBucket` is what makes a missing object report as a clean *not found* rather than *access denied*.

## Amazon S3 only, deliberately

This plugin targets **Amazon S3**, not "S3-compatible" storage in general. That is a design decision, not an oversight: supporting every vendor means branching on custom endpoints, path-style addressing and ACL dialects, and it makes a bucket's public URL undecidable — which is exactly what the CDN and direct-bucket delivery modes depend on. Acelle's storage engines are a registry, so another vendor is properly another plugin.

It also talks to the AWS SDK directly instead of going through Flysystem. The SDK is already there for SES, and Acelle's generic `s3` filesystem disk is configured not to throw — which would turn a failed write into a `false` and a failed lookup into an empty content type, silently. Failures here surface as failures.

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| *That access key ID does not exist* / *secret does not match* | The pair is mistyped, or the two halves come from different keys. |
| *Valid credentials, but not allowed to do this* | The IAM policy on the key does not cover the action — compare it with the policy above. |
| *The region of the bucket could not be detected* | `s3:GetBucketLocation` is missing from the key's policy. |
| *A test object was written but read back with different contents* | Something between the plugin and the bucket is rewriting objects — check for a bucket policy or replication rule that transforms them. |
| *Buckets could not be listed* | Normal for a key scoped to one bucket. Type the bucket name instead. |
| *Images 404 after switching to direct-bucket delivery* | The bucket is not publicly readable. Use CDN delivery with an Origin Access Control, or serve through the application. |

## Other free plugins for Acelle

Payment gateways (Stripe, PayPal, Braintree, Paddle, Razorpay, Paystack, and more), sending drivers (Amazon SES, Postmark, SMTP2GO, Brevo, MailerSend, Resend), verification engines and admin tools — browse them from the Plugins page of your account, or see [all integrations](https://acellesend.com/integrations).

## Support

- **Knowledge base** — <https://acellesend.com/kb>
- **Blog and guides** — <https://acellesend.com/blog>
- **Contact** — <https://acellesend.com/contact> or support@acellemail.com
- **Bugs in this plugin** — open an issue on this repository.

## License

This plugin is provided **free of charge** for use with Acelle Mail installations: use it, modify it for your own installation, deploy it on as many of your own servers as you like.

Acelle Mail itself is a **commercial product** — the source code comes with the licence you buy, but it is not open-source software and a licence is required to run it. See [pricing](https://acellesend.com/pricing).

*Amazon S3, Amazon SES, CloudFront and AWS are trademarks of Amazon.com, Inc. This plugin is an independent integration and is not affiliated with or endorsed by Amazon.*
