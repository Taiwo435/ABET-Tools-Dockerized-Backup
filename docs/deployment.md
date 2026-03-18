# Steps for Deployment

## How to deploy

1) Add any ssh key that has access to the server. The `abet` key mentioned earlier works.
2) Run `deploy.bash` and wait for the messate `[INFO] Deployment complete`

The following are optional steps that should be run if you edited init.sql

3) go to cPanel -> phpMyAdmin
4) Select the tables that you edited and DROP them (yes)
5) Get terminal access to the server and run [scripts/server/execute_mysql_init.sh](../scripts/server/execute_mysql_init.sh)

> [!NOTE]  
> Please contact Danny (me) if you encounter errors at any step at this process!

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

I use ACT to simulate deployment.
The only trigger I have at the moment is workflow-dispatch because
I don't want this running on EVERY pull (yet).

Run this in the repository root!

> [!WARNING]
> This command pull an image that is 18GB of size!!! 😱

```bash
# assumes you have an ssh key at REPO_ROOT/.ssh/id_ed_25516
./scripts/deployment/generate_secrets.py
act -P ubuntu-latest=catthehacker/ubuntu:full-latest --secret-file .secrets
```

script that doesn't bankrupt your system of storage:

```bash
# assumes you have an ssh key at REPO_ROOT/.ssh/id_ed_25516
./scripts/deployment/generate_secrets.py
act -P ubuntu-latest=-self-hosted --secret-file .secrets
```

preconditon:

- The app works, tests pass
- .htaccess does not currently have SetEnv setup.
- sensitive files are built, copied, and up to date
  - may be achievable with gh secrets

postcondition:

- target prototype will be deployed on the server.
