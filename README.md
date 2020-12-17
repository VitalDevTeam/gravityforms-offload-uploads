# Gravity Forms Offload File Uploads

Adds option to Gravity Forms file upload fields to offload uploaded files to Amazon S3 or FTP storage. Unlike other plugins that offload the entire media library, you can enable offload on a per-field basis.

## Installation

1. Install plugin
2. Create security credendtials/API keys in the AWS console and/or generate FTP login credentials.
3. Setup the plugin by going to Gravity Forms > Settings > Offload File Uploads
4. Check the appropriate checkboxes on any file upload field on your forms that you want transferred.

## FAQ

### How do I set up my AWS security credendtials?

1. Go to the AWS IAM Management console
2. Create a Group and attach an `AmazonS3FullAccess` policy to its permissions. You may also set your own custom policy as well, if you have one that works.
3. Create a User and add it to your previously created Group
4. Go to the "Security Credentials" tab under your User and create access keys.
5. Add your access keys to this plugin's settings page

### I think the "S3 File Permissions" setting is breaking my uploads!

Make sure the option you set for S3 File Permissions is supported by your S3 bucket's permissions. For example, if you want public files, you need to make sure your bucket's public access settings are not blocked.

### What happens to the local copies of the file uploads?

If the transfer to Amazon S3/FTP is successful, the plugin will delete the local copies of the files. If using Amazon S3, the form entry will be updated so that the file URLs are the new S3 URLs. If using FTP, the form entry will be updated so that the file URLs are the file path on the FTP server. If the transfer fails for any reason, the local files will not be deleted.

### The new Amazon S3 file URLs in my form entries are saying "Access denied"

If your bucket and file permissions are anything but "Public", you will not be able to access the files via the public URL. To fix this on previously uploaded files, log into the AWS S3 console, find your file(s) and update their permissions.

### My Implicit FTPS connection is not working.

Implicit FTPS has been [officially deprecated](https://tools.ietf.org/html/draft-murray-auth-ftp-ssl-07#appendix-A) and is not supported.

## Future Enhancements/Fixes

1. Deal with uploads of the same name IF using specific S3 bucket path. Will currently overwrite.
2. Need a progress indicator or something to wait for the file transfer
3. Add option to reject form submission if upload fails. Currently keeps local file and then completes entry.
