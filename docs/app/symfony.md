# Symfony's Changes

We are using Symfony 7.4, as the README says. If you want the REAL Symfony docs that tell you how it works, I recommend [Reading the official docs](https://symfony.com/doc/7.4) OR [watching this tutorial](https://youtu.be/i_jgWZItCGI?si=OpJsttT3o6r6UFsH&t=207). Note that due to the nature of our project, we have some deviations from that original tutorial

## Webserver

First and foremost, I don't use the Symfony CLI at all to test the application. I welcome you to, but using `docker compse` is highly recommended, as it is a really close setup to what we have on the server. 

## Legacy Router

Most symfony docs assume you are using Routes with Controllers + Twig templates.
Our application is a bit different. We have legacy PHP code that we want to handle, so we made a mechanism to handle these legacy files: `LegacyBridge.php`. AKA the Legacy Router (whatever you want to call it).
For every URI (the path after the site name), it checks if there exists a Symfony route for it.
If it doesn't exist, it falls back to looking for existing PHP files in the `public/` directory with the same name.

<img alt="flowchart showing the flow of HTTP requests to the server" src="../readme/Symfony Flowchart.png">
