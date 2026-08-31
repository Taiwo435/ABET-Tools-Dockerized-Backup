# how to print() like normal


```php
        file_put_contents('/tmp/debug.log', print_r([
            'request_uri' => $request->getRequestUri(),
            'user' => $security->getUser() ? "something" : "nothing",
            'session' => $request->hasSession()
                ? $request->getSession()->getId()
                : null,
            'cookies' => $request->cookies->all(),
        ], true), FILE_APPEND);
```

if on local, do this to view it

docker exec -it php_apache /bin/less /tmp/log


# how to debug server errror 500

view public_html/abet.asucapstonetools.com/error_log

# important

after you make changes, make sure to run this ON THE SERVER

```bash
cd abet_docker/src/abet_private
composer console cache:clear
```

it will make sure that the server is actually processing your next request.
Run this every time you make a change