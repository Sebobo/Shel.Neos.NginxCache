# Shel.Neos.NginxCache

[![Latest Stable Version](https://poser.pugx.org/shel/neos-nginx-cache/v/stable)](https://packagist.org/packages/shel/neos-nginx-cache)
[![Total Downloads](https://poser.pugx.org/shel/neos-nginx-cache/downloads)](https://packagist.org/packages/shel/neos-nginx-cache)
[![License](https://poser.pugx.org/shel/neos-nginx-cache/license)](https://packagist.org/packages/shel/neos-nginx-cache)

## Introduction

This package provides an improved integration for Neos CMS and the [NGINX proxy cache](https://nginx.org/en/docs/http/ngx_http_proxy_module.html).
The NGINX proxy cache caches the output of your website for a certain time and will deliver
the cached version to your visitors with much greater performance.
This system can also help with high traffic or smaller attacks on your site. 

But of course NGINX cannot know when your content is updated.
Therefore it's helpful to send refresh and invalidation notifications from your CMS when editing.
This allows you to increase cache times and be sure that your visitors see current content with great performance.

Read [An Introduction to Cache Invalidation](https://foshttpcache.readthedocs.io/en/stable/invalidation-introduction.html#an-introduction-to-cache-invalidation)
to understand the main issue of caching and it's advantages and drawbacks.  

### Features

* ✓ Send refresh requests to NGINX when a page is published
* ✓ Send invalidation requests to NGINX when a page is not available anymore (needs the optional NGINX purge module)
* ✓ Send headers with each page to tell NGINX about the cache timeouts of a page
* ✓ Backend module to check configuration and flush certain pages
* ✓ Disable caching via node properties
* ✓ Respect caching timeouts defined in fusion

Not possible yet

* Invalidate other pages than the published one

## Requirements

* Neos CMS 4.x 
* NGINX as webserver       
* NGINX purge module (optional)   

## Installation

Add the dependency to your site package like this

    composer require --no-update shel/neos-nginx-cache
    
And then run `composer update` in your projects root folder.

If you don't have the NGINX purge module, the package will only send refresh requests instead of using invalidations.
You can find more information on how to setup the purge module [here](https://foshttpcache.readthedocs.io/en/stable/nginx-configuration.html#purge).              

## Documentation

For more information on the setup and configuration, see [the documentation](Documentation/Index.rst)

### Other helpful resources

* [Official NGINX proxy documentation](http://nginx.org/en/docs/http/ngx_http_proxy_module.html)                 
* [NGINX configuration for the library used in this package](https://foshttpcache.readthedocs.io/en/stable/nginx-configuration.html)                 
* [NGINX caching example by digitalocean](https://www.digitalocean.com/community/tutorials/how-to-setup-fastcgi-caching-with-nginx-on-your-vps)

## Comparison to Varnish as proxy cache                                     

This package was inspired by [MOC.Varnish](https://github.com/mocdk/MOC.Varnish).

Varnish works similarly, but allows you to create a much finer configuration when and what is flushed from it's cache.
But the setup & configuration needs more knowledge as you need the Varnish service running additionally
and you need to configure your webserver to communicate with it.

Therefore this package was created to help people who have smaller sites, which don't need the bigger setup
or don't have access to Varnish on their servers.

For larger sites it's recommended to use Varnish when a lot of editing is happening.


| Client  | Purge | Refresh | Ban | Tagging |
| ------- | ----- | ------- | --- | ------- |
| Varnish | ✓     | ✓       | ✓   | ✓       |
| NGINX   | ✓     | ✓       |     |         | 

## Contributions

Contributions are very welcome! 

Please create detailed issues and PRs.
