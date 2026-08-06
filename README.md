# Square Customer Analyser

## Installation

1. Download [Composer](https://getcomposer.org/doc/00-intro.md) or update `composer self-update`.
2. Run `php composer.phar create-project --prefer-dist cakephp/app [app_name]`.
3. Ensure composer is up to date with `composer install`
4. Setup your database with `bin/cake migrations migrate`
5. Seed the database with `bin/cake seeds run`
6. Fill in the values at the top of the `.env.example` file.

## Usage

The default credentials are `wendy@example.com` and `admin123`.
Logon and go to the dashboard and connect with Square.

## Connecting with Square in Sandbox

1. Go to <https://app.squareup.com/launchpad>
2. Click View Dashboard for our account.
3. Go to <https://developer.squareup.com/apps>
4. Click Sandbox test accounts.
5. Click Square Dashboard for the `Auth` user.