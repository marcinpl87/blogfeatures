blogfeatures
============

All features for blogs. Plugins, templates, code snippets, etc.

Deploy
======

`python3 deploy.py` uploads every file in `plugins/` to an FTP server.
Paths inside `plugins/` are kept, so `plugins/sitemap.php` lands as `sitemap.php` in `FTP_DIR`.

Create a `.env` file in this directory. It is listed in `.gitignore`.
These lines are required for deploy to work:

    FTP_HOST=ftp.example.com
    FTP_USER=your-username
    FTP_PASS=your-password
    FTP_DIR=/public_html/wp-content/plugins
    FTP_TLS=0

- `FTP_HOST` is the FTP server hostname.
- `FTP_USER` is the FTP username.
- `FTP_PASS` is the FTP password.
- `FTP_DIR` is the remote directory that receives the files. It must already exist.
- `FTP_TLS` is `0` for plain FTP. Set it to `1` when the server supports FTPS.
