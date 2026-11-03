# TuneTastic — Music Marketplace Prototype

A full-stack academic web application for discovering, uploading, and organizing music beats. This clean portfolio edition is adapted from an earlier collaborative TuneTastic coursework project, with a refreshed interface and refactored server-side code.

**Stack:** PHP 8.1+, MySQL 8+, HTML, CSS, vanilla JavaScript. **No Node.js, package manager, or paid music service required.**

> **Portfolio demo — not a commercial music store.** The "purchase" workflow adds a track to a demo library; no payment processing or credit-card collection is implemented. This project does not contain copyrighted music, personal photographs, user data, or real accounts. Upload media only when you have the rights to share it.

## Features

- Account registration and sign-in with password hashing and PHP sessions
- Artist studio for publishing, updating, and deleting your own tracks
- Audio previews, optional cover images, descriptions, genre labels, and illustrative prices
- Public discovery page and individual beat detail pages
- Demo "add to library" purchase record (no real transactions)
- Prepared SQL queries, CSRF protection, owner authorization, basic upload limits and MIME validation
- Media stored outside the public web root and served through a controlled route
- Responsive purple/pink interface built without frontend dependencies

## Getting started

### Requirements

- PHP **8.1 or newer**, with extensions `pdo_mysql` and `fileinfo`
- MySQL or compatible MariaDB server
- A browser

### 1. Create a database

Import `database/schema.sql` using phpMyAdmin or the command line:

```bash
mysql -u root -p < database/schema.sql
```

This creates the `tunetastic` database and the three tables: `users`, `beats`, `purchases`.

For local development, create a restricted MySQL user with permissions for the `tunetastic` database, or configure your existing local development account. Never commit real credentials.

### 2. Configure database access

Copy `config.example.php` to `config.local.php` and edit your local host, port, database user, and password. This file is excluded by `.gitignore`.

Windows (PowerShell):

```powershell
Copy-Item config.example.php config.local.php
```

macOS / Linux:

```bash
cp config.example.php config.local.php
```

The original student project used a WAMP MySQL port of `3308`; most local installations use `3306`. Choose the port your MySQL installation actually uses.

### 3. Start the application

From this repository's root directory:

```bash
php -S 127.0.0.1:8000 -t public
```

Visit **http://127.0.0.1:8000**. Sign up, upload an original MP3/WAV/OGG file, and use a second test account to save it in the demo library.

The app starts with **no sample tracks** by design: the original bundled music and personal images were intentionally removed.

### 4. Adjust PHP upload limits (if needed)

`php.ini` must permit your desired file size: for example `upload_max_filesize = 16M` and `post_max_size = 20M`. App limits are **12 MB per audio file** and **3 MB per cover**. Restart PHP after changing `php.ini`.

## Directory structure

```text
TuneTastic-Music-Platform/
├── app/
│   ├── bootstrap.php      # Configuration, DB connection, security helpers
│   └── actions.php        # Authentication and beat actions
├── public/
│   ├── index.php          # Routing and page templates
│   └── assets/style.css   # Responsive interface
├── database/schema.sql   # MySQL tables
├── storage/              # Private local uploads (git-ignored)
├── tests/test_helpers.php
├── config.example.php
└── README.md
```

## Architecture

**Browser → PHP controller/actions → PDO prepared queries → MySQL**. Uploaded media is given a randomized filename and placed in `storage/` outside the public directory. `/index.php?media=<beat_id>` streams the stored content after resolving the database record.

Every beat is owned by its creator. Edit/delete routes check the session's user ID against the beat's `owner_id`. The demo purchase route associates a registered listener with a beat in the `purchases` table.

## Tests and verification

Run helper tests:

```bash
php tests/test_helpers.php
```

Run PHP syntax checks:

```bash
php -l app/bootstrap.php
php -l app/actions.php
php -l public/index.php
```

Manual integration checks (require your own MySQL installation): sign up → log out → log in → upload original audio → visit discovery → listen → edit → save to another account's library → delete as creator. **No end-to-end database test is provided in the ZIP.**

## Scope and limitations

- Educational prototype, **not production hardened** and not a real checkout or payment system
- No email verification, password reset, anti-spam, rate limiting, or dedicated download authorization
- Tracks are publicly previewable by design; ownership/rights enforcement is outside scope
- Built-in PHP server is for local development, not production hosting
- Font stylesheet references Google Fonts; browser default fonts are used if unavailable
- Requires a local MySQL server and PHP extensions; does not ship with a populated database

