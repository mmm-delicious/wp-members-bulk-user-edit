# WP-Members Bulk User Edit

A WordPress plugin that adds a CSV-based bulk user lookup and delete tool to the Users admin menu.

## Features

- Upload a CSV file containing user emails or IDs
- Matches CSV rows to WordPress users
- Shows matched users in a table with Edit and Delete links
- CSV can use an `email` column, an `id` column, or both

## Requirements

- WordPress 5.0+
- PHP 7.4+
- WP-Members plugin

## Installation

1. Upload the `wp-members-bulk-user-edit` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu in WordPress
3. Go to Users > Bulk Edit/Delete Users

## CSV Format

Your CSV must include at least one of the following columns (case-insensitive):

| Column | Description |
|--------|-------------|
| `email` | User's email address |
| `id` | WordPress user ID |

Example:
```
email,name
john@example.com,John
jane@example.com,Jane
```

## Changelog

### 1.1
- Added ABSPATH security guard
- Added CSRF nonce to upload form
- Escaped all output in results table (XSS prevention)
- Standardized plugin header format

### 1.0
- Initial release
