# Steps for Deployment

## How to deploy

1) Add any ssh key that has access to the server. The `abet` key mentioned earlier works.
2) Copy sensitive files from the server. `scripts/deployment/get_prod_files.bash` does this for you.
3) Run `deploy.bash`

## What the deployment script does

1) Prepare sensitive environment files to deploy
   1) /docker/.env.prod
   2) /src/public/.htaccess
2) Shuts down server docker containers
3) Copies git-tracked files to the server
4) Copy sensitive environment files from the server
5) Moves files into their correct location
   1) Replaces public_html/abet.asucapstonetools.com
6) Spins up the docker containers

All errors should be caught by the trap statement and should be reported for helpful debugging.
1) Prepare sensitive environment files to deploy
   1) /docker/.env.prod
   2) /src/public/.htaccess
2) Shuts down server docker containers
3) Copies git-tracked files to the server
4) Copy sensitive environment files from the server
5) Moves files into their correct location
   1) Replaces public_html/abet.asucapstonetools.com
6) Spins up the docker containers

All errors should be caught by the trap statement and should be reported for helpful debugging.

## CD Steps

preconditon:

- The app works, tests pass
- .htaccess does not currently have SetEnv setup.
- sensitive files are built, copied, and up to date
  - may be achievable with gh secrets

postcondition:

- target prototype will be deployed on the server.
