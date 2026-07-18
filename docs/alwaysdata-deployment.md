# Alwaysdata deployment

Live site: https://keith.alwaysdata.net/

The app is deployed to `/home/keith/www` on `ssh-keith.alwaysdata.net`.
The live-only file `/home/keith/www/includes/config.local.php` contains the production DB and SMTP settings and must not be committed.

## GitHub Actions secret

Create this repository secret in GitHub:

`ALWAYSDATA_SSH_PRIVATE_KEY`

Set its value to the full contents of the local ignored private key file:

`.alwaysdata_deploy_key_v2`

Include the `-----BEGIN OPENSSH PRIVATE KEY-----` and `-----END OPENSSH PRIVATE KEY-----` lines.

## How deploys run

The workflow `.github/workflows/deploy-alwaysdata.yml` deploys automatically on pushes to `main`.
It can also be run manually from GitHub Actions using `Deploy to Alwaysdata`.

The workflow deploys code only. It preserves live uploads and `includes/config.local.php`.
Database schema/data changes should be reviewed and imported separately so the production DB stays consistent.
