# Steps for Deployment

1) Prepare sensitive environment files to deploy
   1) /docker/.env.prod
   2) /src/public/.htaccess
2) Ensure tests pass
3) Copy sensitive environment files from the server (I need to make a script for this)
4) push the code to the server
5) docker compose down
6) docker compose up -f <prod_compose>

## CD Steps

preconditon:

- The app works, tests pass
- .htaccess does not currently have SetEnv setup.
- sensitive files are built, copied, and up to date
  - may be achievable with gh secrets

postcondition:

- target prototype will be deployed on the server.
