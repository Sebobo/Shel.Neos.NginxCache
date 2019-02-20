Shel NGINX Proxy Cache Documentation
====================================

Installation
------------

Add the dependency to your site package like this::

    composer require --no-update shel/neos-nginx-cache

And then run `composer update` in your projects root folder.

Package configuration
---------------------

You can override the following settings in your own `Settings.yaml` files::

    Shel:
      Neos:
        NginxCache:
          # Disables NGINX cache interaction if set to false (does not turn off NGINX proxy cache!)
          enabled: true
          # IP/URL or IPs/URLs (if array) NGINX is running on for requests (skip trailing slash)
          servers: '127.0.0.1'
          # The base uri for the generation of purge urls, f.e. 'https://neos.io'
          baseUri: ''
          purge:
            # Set to true if your NGINX installation has the purge module installed, if not only refresh is used
            installed: false
            # The subpath that trigger purges. See https://foshttpcache.readthedocs.io/en/stable/nginx-configuration.html
            location: ''
          # Cache header sending configuration
          cacheHeaders:
            # Default and maximum TTL in seconds
            defaultSharedMaximumAge: null
            # Disable sending headers (useful for staging environments)
            disabled: false


NGINX configuration
-------------------

Here is an example configuration for NGINX taken from https://foshttpcache.readthedocs.io/en/stable/nginx-configuration.html
and adjusted for Neos CMS and PHP-FPM.

Full version::

    worker_processes 4;

    events {
        worker_connections 768;
    }

    http {

        log_format fastcgi_cache '$time_local '
            '"$upstream_cache_status | X-Refresh: $http_x_refresh" '
            '"$request" ($status) '
            '"$http_user_agent" ';

        error_log /tmp/fos_nginx_error.log debug;
        access_log /tmp/fos_nginx_access.log fastcgi_cache;

        # Set cache size to 100MB and discard old entries after 60 minutes
        # Adapt cache path and name (keys_zone) to your needs
        fastcgi_cache_path /tmp/foshttpcache-nginx levels=1:2 keys_zone=MY_APP_CACHE:100m inactive=60m;

        # Add an HTTP header with the cache status. Required for FOSHttpCache tests.
        # Don't enable this on production if not needed
        add_header X-Cache $upstream_cache_status;

        server {
            listen 127.0.0.1:80;

            server_name localhost 127.0.0.1;

            proxy_set_header   Host             $host;
            proxy_set_header   X-Real-IP        $remote_addr;
            proxy_set_header   X-Forwarded-For  $proxy_add_x_forwarded_for;

            charset utf-8;
            client_max_body_size 50M;

            root   /Users/sebastianhelzle/Workspace/helzle.it/Web/;
            index index.html index.php;

            # Cache everything by default
            set $no_cache 0;

            # Don't cache POST requests
            if ($request_method = POST) {
                set $no_cache 1;
            }

            # Don't cache if the URL contains a query string
            # You can adjust this to allow caching of certain query strings
            if ($query_string != "") {
                set $no_cache 1;
            }

            # Don't cache the Neos backend
            if ($request_uri ~* "/(neos/)") {
                set $no_cache 1;
            }

            # Don't cache the user workspace
            if ($request_uri ~* "(@user-)") {
                set $no_cache 1;
            }

            # Disable .htaccess and other hidden files
            location ~ /\. {
                access_log      off;
                log_not_found   off;
                deny            all;
            }

            # No need to log access to robots and favicon
            location = /favicon.ico {
                log_not_found off;
                access_log off;
            }

            # Block access to the main resources folder
            location ~ "^/_Resources/" {
                access_log off;
                log_not_found off;
                expires max;
                break;
            }

            # Stop rewriting by existing files | is instead of -> location / { rewrite ".*" /index.php last; }
            location / {
                try_files $uri $uri/ /index.php?$args;
            }

            # Pass the PHP scripts to FastCGI server listening on 127.0.0.1:9000
            location ~ \.php$ {
                include        fastcgi_params;
                try_files      $uri =404;
                fastcgi_pass   127.0.0.1:9000;
                fastcgi_index  index.php;
                fastcgi_param  SCRIPT_FILENAME $document_root$fastcgi_script_name;
                fastcgi_param  PATH_INFO         $fastcgi_path_info;
                # Adjust the FLOW_CONTEXT to your environment
                fastcgi_param  FLOW_CONTEXT      Development;
                fastcgi_param  FLOW_REWRITEURLS  1;
                fastcgi_param  X-Forwarded-For   $proxy_add_x_forwarded_for;
                fastcgi_param  X-Forwarded-Port  $proxy_port;
                fastcgi_param  REMOTE_ADDR       $remote_addr;
                fastcgi_param  REMOTE_PORT       $remote_port;
                fastcgi_param  SERVER_ADDR       $server_addr;
                fastcgi_param  SERVER_NAME       $http_host;
                fastcgi_split_path_info ^(.+\.php)(.*)$;
                fastcgi_read_timeout         300;
                fastcgi_buffer_size          128k;
                fastcgi_buffers              256 16k;
                fastcgi_busy_buffers_size    256k;
                fastcgi_temp_file_write_size 256k;

                # Proxy cache, use the same name here as in the definition above
                fastcgi_cache MY_APP_CACHE;
                # Cache normal requests for 10 minutes if no header is set
                fastcgi_cache_valid 200 302 301 10m;
                # Cache page not found results for 1 minute
                fastcgi_cache_valid 404 1m;
                # Use cached version of a page if NGINX gets an error from php
                fastcgi_cache_use_stale error timeout http_500;
                # Let other requests wait if NGINX already receives a new result
                fastcgi_cache_lock on;
                # Cache identifier
                fastcgi_cache_key "$scheme$request_method$host$request_uri$is_args$args";
                # Triggers to bypass the cache
                fastcgi_cache_bypass $http_x_refresh $no_cache;
                # Allow NGINX to update a cache entry after a user get's a stale version
                fastcgi_cache_background_update on;
                # When this is set, a response from php is not cached
                fastcgi_no_cache $no_cache;
            }

            # This must be the same as the purge location supplied in the Settings.yaml (only with the NGINX purge module)
            # in the Nginx class constructor
            location ~ /purge(/.*) {
                allow 127.0.0.1;
                deny all;
                fastcgi_cache_purge MY_APP_CACHE $1$is_args$args;
            }
        }
    }

You can find information on all configs here http://nginx.org/en/docs/http/ngx_http_fastcgi_module.html

It's also possible to configure the cache when not using fastcgi. You then just have to rename the options.
Find the information here: http://nginx.org/en/docs/http/ngx_http_proxy_module.html

Testing the configuration
-------------------------

You can test the configuration by hand by checking out the response headers from your site in the browser.

Or you can write tests like described here https://foshttpcache.readthedocs.io/en/stable/testing-your-application.html

Debugging
---------

The package will write a logfile to `Data/Logs/NginxCache.log`. The content depends on your setups `LOG_LEVEL`.
Set it to `DEBUG` if you want all output.

Also check adapt your access log like in the configuration above to see cache information there.

If you get `cURL error 52: Empty reply from server` in your log, verify the `servers` and `baseUri` options in
your `Settings.yaml`. Match the protocol, port, etc... if you have issues.

Further adjustments for production environments
-----------------------------------------------

* Disable the `X-Cache` header output to not tell attackers, that they found a way to circumvent the cache.
* Configure a second server in your NGINX and package config that only accepts local requests to refresh and invalidate entries with the `X-Refresh` header.
* Checkout https://foshttpcache.readthedocs.io/en/stable/user-context.html if you need caching for different user groups.
