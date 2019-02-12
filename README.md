# Webhook proxy

_A proxy forward for webhooks_

**What does this plugin do?**

...tbd 

## 1. Usage

Download plugin .zip from `/dist` folder and install with wordpress

## 2. Development


1. clone repo
2. do a `npm install` for dependencies
3. run `grunt dist` to compile distributable plugin dist
4. do a `composer install` (provided you have composer installed globally)

you should tag new versions of plugin by running:

```bash
$ sh ./tag.sh $version $message
```

where `$version` is the version string (use `git describe --tags --long` for current version) and
`$message` is the optional tag message string. The version is automatically updated in plugin bootstrap
php file.