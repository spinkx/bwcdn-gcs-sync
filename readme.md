### Stateless Media Plugin


### Available Constants
Setting a setting via constants will prevent ability to make changes in control panel.

* WP_STATELESS_MEDIA_MODE - Set to "disabled", "backup" or "cdn" to configure mode. 
* WP_STATELESS_MEDIA_SERVICE_ACCOUNT - Google email address of service account.
* WP_STATELESS_MEDIA_KEY_FILE_PATH - Absolute, or relative to web-root, path to P12 file.


### Values to add in wp-config file

```Paste the following Lines into your  wp-config file replacing values according to Constant names

NOTE: values given below are for example only they wont work as it is , get proper json key from GCS


/* viksedit Wp2GoogleCloudStorage Key */
// uploads and replaces in content other modes are backup / disabled
define('WP_STATELESS_MEDIA_MODE','cdn');
// http or https scheme to use
define('WP_STATELESS_MEDIA_SCHEME','http');
// media bucket and also our host
define('WP_STATELESS_MEDIA_BUCKET', 'cdn1.brandwiki.buzz' );
// useless for now, I've hardcoded it to multisite structure of sites/1/file.jpg
define('WP_STATELESS_MEDIA_ROOT_DIR', 'site_folder');
// Whether to delete files from local after uploading to GCS, in Sync or regular mode 1=On,0=Off
define('WP_STATELESS_MEDIA_DELETE_LOCAL_ON_UPLOAD', 1);

//viksedit
define('WP_STATELESS_JSON_KEY', '{
  "type": "service_account",
  "project_id": "brandwiki-1279432342",
  "private_key_id": "c50519b2b154d32ce3b16ce7de2",
  "private_key": "-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQC0qWREF+OLdkJl\nFRgP2/WD5nGH9mCT6bSoBveLQNSco9x5n0PbupMYQrC/7ezWO8PHmUq1D8K6gZhj\nSf5MtE9aLFeEmNVaiDA8ENj+zA6zY2RPffATfXkGFdBq824VxfqPd0PM4dK72fVg\nnYHlOHFm7ksIibgmuD2EykIM4yq33uPc8wXfSzi39G5iVTmuCcDfI3Yutag3V/r3\nqMADm0N5f9rRi8tUbrfzkti+FKLqzVi92fnSH2oOO0J3xfNmgBIm3aPXBTu0+WOY\nTW5qvAK6FLfSSVAcOkhTWxp4CcjATUgZrmA8+OhVGGJBhU8h59CS30cOeBhqQngy\nVBwXSJh3AgMBAAECggEAWVkdOYAHDUYjeBKCn/VM6zrhEzkKcpy2uBMaAkjB3eY8\nd/oIeXdAoFL7TzDAXQOZw/FQPVPaHptRXmmN1ymlxRcBAZcEjY2lLU+3wevxqU6S\noa3LOhhn7laDiSFzZFlRnfqCEaXtrvIQpQPA5jiP/TQE7+gMpzmfUzkkiXMgAWbm\n36mEG8sEwXHilZY/C12bPzBPsLxKZDPd5+JMtwom5fAfxYJn8KZghTL8sVJJY40N\nxxSgayJKVeg4cZDYwL2pTPBxSBEPkKiOdt9+pF5co2qZX90AFoVsrKzItAYu8/45\nt3mF1EawKG0n2IPBzj3Bp8nPZCpA+rChOksbePr0YQKBgQD+L6yJrUsQICjVvPfB\n8/mkYOTKQgA2at0pegb9VsABrr6MIZF9bKCDC\ntk0u+Uru/DoDIfsfkd6e8Cex6RYbnXSxNQQKp431SwLYK9oJ8gaKhPnqMnbVsp6R\nodY//oYqHP9nNJZH41coNX6DSwKBgQC182jD/xRHdLS4+FKIDDtXgMaYM8MZhfo3\nmiCaQcOsUt3Sk4bRzfnzf4K+pkdRYt9FrdeBzn/nXs/vvECsfVigDa6FxtcKLoaY\n88cBvqekjla/wsqzaTTDKuOxIKa/FnJwgEfYIx/o0RwxHTtkS/mOAhtzFRh7VfK8\nr5DjzOMYBQKBgEO++xJasIXkvF1zFumHmAKanH/XpWzbgIR8dH5y74vDQh/hFoDC\n8pzEFugatak0Vmf6+Og8DymLsUz3JfwfUGTzpmgZq3CITwD0BMyBn2LIh87mYWKV\nibU5QRmeW2y4C03ZRqsGlAE6X/fGuoKACrVVpfZ1chDUsDUKv4rdDszp9pV1lCE3ZiYLfi5fqoPjHaUak5u7vRVJrleCMJz6T9tbL5\neTVgMwdoHrQsYkfZK8jeDM+lQaXx5mczCBsuBBhHBf2nOKk1JpJknf96fmyLQxjW\nxIwWLNg+nfVwMt9y3UEfwnkHXVA7mDpyx9wdlU0CgYEAv+3DKIcp8nv18xPlUJQm\nNcr60Hmx5YDAB/djt4t7tf0DSk59vSY7V+vpFQzReUBXggioMO1uSgdD3OEEYzmS\nqlM+eiYmjlaRNPkQjzqe+CXu7av4CfKSxIzHCQmcfAr/SH2ci86wTmulNYaWVwoj\n0hNL+DKtDyKc3h6qIbaDhmk=\n-----END PRIVATE KEY-----\n",
  "client_email": "bki-info-1@branki-1279.iam.gserviceaccount.com",
  "client_id": "11394234231254253661960",
  "auth_uri": "https://accounts.google.com/o/oauth2/auth",
  "token_uri": "https://accounts.google.com/o/oauth2/token",
  "auth_provider_x509_cert_url": "https://www.googleapis.com/oauth2/v1/certs",
  "client_x509_cert_url": "https://www.googleapis.com/robot/v1/metadata/x509/brandwiki-info-1%40brandwiki-1279.iam.gserviceaccount.com"
}');

/*************** End GCS Uploader Config********************************************/
```