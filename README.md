# Composer Wrapper
[![Build Status](https://travis-ci.org/kamazee/composer-wrapper.svg?branch=master)](https://travis-ci.org/kamazee/composer-wrapper)

## What for?

Nearly every README for a non-ancient PHP project begins with `composer install`. Some of them explain how to install composer itself, some of them don't and rely on developer's experience, web search engine of developer's choice, or implicitly on a globally installed composer.
Well, there's nothing wrong with that, really, but I think we can do better.

Here is a couple of assumptions:
* composer is meant to be up to date;
* composer is born to be installed locally (to be upgraded easily at will, for example).

So, if there was a script that checked if composer was there, installed it if it wasn't, upgraded if outdated, and finally proxied parameters as is to it as if we referred to a composer itself, all the issues outined above would have been solved...

## How To

1. Copy `composer` file from this repository into a directory within your project where "binaries" (well, usually just CLI entry points) reside
2. Add execution permissions to `composer` file: `chmod +x composer`
3. Add `composer` file to version control
4. Add `composer.phar` in that directory into version control ignore file

Done, your colleagues should worry never again about installing composer and keeping it up to date, and neither should ops team care how it appears on a build server!

**Just use the `composer` script instead of actual composer, and it will make sure you have an actual composer installed and up to date!**

`./composer install`, `./composer update`, `./composer dump-autoload`, and other commands you used to should just work as if you had installed composer yourself.

## Configuration

All the configuration is done through [*environment variables*](https://www.digitalocean.com/community/tutorials/how-to-read-and-set-environmental-and-shell-variables-on-a-linux-vps) in order to not interact with composer's CLI parameters. The following environment variables are supported:

* `COMPOSER_UPDATE_FREQ`. Time between checks for updates (defaults to 7 days). This is a [relative time specifier](http://php.net/manual/en/datetime.formats.relative.php) that is fed to [`DateTime::modify`](http://php.net/manual/en/datetime.modify.php). It's chosen because it can be perfectly readable by someone who knows no PHP and doesn't want to (e.g. ops people), and it's recommended to keep it that way, e.g. "5 days", "2 weeks", "1 month".
* `COMPOSER_DIR`. Directory where composer.phar will be searched for and copied to. Defaults to the directory where the script is located (`__DIR__`); note: *not* the current working directory! Sometimes it's useful to keep real composer.phar a couple of levels higher up the directory where wrapper is placed, for example, on a CI server: it would help avoid downloading composer afresh for every build.  
* `COMPOSER_FORCE_MAJOR_VERSION`. When set to either 1 or 2, forces composer to self-update to the most recent version in the specified major branch (1.x or 2.x resprctively).

## FAQ

### It's just in version control, how do I update it?

Well, you likely don't. It's intended to be as stupid as possible, and you probably have no reason to update it as long as it works. When it breaks, you just commit an updated version.

### Can I just copy it? Do I have to preserve copyright notice or a licence?

Sure, go ahead. It's [CC0](https://wiki.creativecommons.org/wiki/CC0)-licensed which basically means you don't owe the author anything, even a mention.

## Implicit assumptions

The wrapper is aimed to work in any environment that is supported by composer itself (hence, support all the PHP versions that composer supports). If this assumption doesn't work for you, feel free to open an issue or pull request.

Also, when opening a pull request, keep in mind the huge variety of PHP versions that composer supports.
