# Pydio Auth Plugin for Gmail

[GitHub-Repository](https://github.com/surekap/pydio-auth-gmail) |
[Issue-Tracker](https://github.com/surekap/pydio-auth-gmail/issues) |

This plugin is for use with Pydio. It enables users to authenticate into Pydio with their Google (Gmail) account. See http://pyd.io/ for more information.

## How to contribute

- Report issues or feature requests here on the [issue tracker](https://github.com/surekap/pydio-auth-gmail/issues)
- Fork and send us your Pull Requests

## Installation

### Requirements

- Pydio: >= 5
- PHP Curl (http://www.php.net/manual/en/book.curl.php)

### Set up Pydio
@todo
1. Log in to your Pydio instance as 'admin'
2. Go to `Settings > Core Configurations > Authentication`
3. Switch the `Main Instance` to __"Gmail Authentication Storage"__ 
4. Set `Users File` to `AJXP_DATA_PATH/plugins/auth.serial/users.ser`, and auto-create user to __"No"__
