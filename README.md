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

WordPress MCP
=============

An agent edits pages and posts through the WordPress REST API. The MCP server runs on the computer that runs the agent, not on the PHP server. Install it once:

    npm install -g @mangerik/wordpress-mcp

`.cursor/mcp.json` starts the `wordpress-mcp` command and reads secrets from `.env`. Add these lines next to the FTP settings:

    WP_URL=https://example.com
    WP_USERNAME=your-wp-username
    WP_APP_PASSWORD=xxxx xxxx xxxx xxxx xxxx xxxx

- `WP_URL` is the site address, including `https://`, with no path after the host.
- `WP_USERNAME` is the WordPress login the agent should act as.
- `WP_APP_PASSWORD` is an application password for that user. It is not the password used to sign in to wp-admin.

To create the application password, open wp-admin and go to Users, then Profile. In Application Passwords, type a name such as `Cursor` and click Add New Application Password. Copy the value WordPress shows. It is shown only once. The site must be served over HTTPS, or the Application Passwords section does not appear. An Editor account is enough when the agent only changes content.
