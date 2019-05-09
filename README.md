# Gravity Forms Offload S3 Uploads

Adds option to file upload fields to offload files to Amazon S3.

## Installation

1. Install plugin
2. Create security credendtials/API keys in the AWS console.
3. Setup the plugin by going to Gravity Forms > Settings > Offload S3
4. Check the "Offload file(s) to Amazon S3" checkbox on any file upload fields on your form that you want transferred.

## FAQ

### How do I set up my AWS security credendtials?

1. Go to the AWS IAM Management console
2. Create a Group and attach an `AmazonS3FullAccess` policy to its permissions
3. Create a User and add it to your previously created Group
4. Go to the "Security Credentials" tab under your User and create access keys.
5. Add your access keys to this plugin's settings page

### I think the "S3 File Permissions" setting is breaking my uploads!

Make sure the option you set for S3 File Permissions is supported by your S3 bucket's permissions. For example, if you want public files, you need to make sure your bucket's public access settings are not blocked.

### What happens to the local copies of the file uploads?

If the transfer to S3 is successful, the plugin will delete the local copies of the files. It will also update the form entry so the file URLs are the new S3 URLs. If the transfer fails for any reason, the local files will remain.

### The new S3 file URLs in my form entries are saying "Access denied"

If your bucket and file permissions are anything but "Public", you will not be able to access the files via the public URL. To fix this on previously uploaded files, log into the AWS S3 console, find your file(s) and update their permissions.
