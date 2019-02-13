# Webhook proxy

_A proxy forwarder for generic post webhooks_

**What does this package do?**

Suppose a webhook sender (like. Gitlab, Slack, Minio, ...) can not send directly to your webhook target or want to
forward the webhook event to multiple targets you may find this package interesting.

The proxy will forward the post request to any configured endpoint. Since it uses PHP CURL you can customize
the curl request with your own curl options  


## 1. Usage

Install package with composer or download `dist.zip` and use it.


## 2. Configure

In order to configure the proxy you need to create a `config.json` file in the package root directory.
The following options exist:

- `debug=<true>|<false>`: enable debug mode
- `log=<true>|<false>`: enable logging (the proxy will try to log into `./logs/*` or use `error_log()`)
- `referer=<array>`: array or allowed remote addr referer IP´s
- `token=<string>`: the token must be added in the webhook request
- `endpoints=<array>`: array or endpoints to forward to
- `curl=<array>`: array of additional curl options

The only mandatory option is: `endpoints`


## 3. Development

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