# Scripts

These are scripts that I use to copy directly from the server to my local machine.
They may be of use to you.

Scripts in the `development/` directory are for development, and they are super rudimentary. Take a look at them before you execute them.

Scripts in the `deployment/` directory are for deploying the server. Everything there is held up to a higher standard, as you are literally deploying stuff to the server (to prod). 

Scripts in the `server/` directory are only meant to be ran on the server. Running them to your local machine will have undefined behavior. 

Scripts directly in this directory are ones that I think are very useful and are ones that can be used for multiple stages of the process.

## copy_from_server.bash

Copies server folders `abet_private` and `abet.asucapstonetools.com` to the local machine.

They are put into untracked `server_clone` directores in the appropriate location (ex. `src/abet_private/server_clone`).

## ssh.bash

A shortcut to ssh directly into the server. Gives command line access.

## Others

Most scripts are documented with their name and warning messages. 