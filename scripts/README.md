# Scripts

These are scripts that I use to copy directly from the server to my local machine.
They may be of use to you.

Scripts in the `development/` directory are for development, and they are super rudimentary. Take a look at them before you execute them.

Scripts in the `deployment/` directory are for deploying the server. Everything there is held up to a higher standard, as you are literally deploying stuff to the server (to prod).

Scripts in the `server/` directory are only meant to be ran on the server. Running them to your local machine will have unintended behavior.

Scripts directly in this directory are ones that I think are very useful and are ones that can be used for multiple stages of the process.

## copy_from_server.bash

Copies server folders `abet_private` and `abet.asucapstonetools.com` to the local machine. Editable to copy any file/folder you want from the server. (`copy_from_server_config.bash`) is an example of me copying this file.

They are put into untracked `server_clone` directores in the appropriate location (ex. `src/abet_private/server_clone`).

## ssh.bash

A shortcut to ssh directly into the server. Gives command line access.

## upload_scripts.bash

A very useful script for the deployment process. Want to edit bash scripts on your local machine and then send it to the server?
This does exactly that.

## Others

Most scripts are documented with their name and warning messages.
